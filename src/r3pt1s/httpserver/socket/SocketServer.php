<?php

namespace r3pt1s\httpserver\socket;

use r3pt1s\httpserver\HttpServer;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\io\ResponseCache;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\HttpConstants;
use r3pt1s\httpserver\util\StatusCode;
use r3pt1s\httpserver\util\Utils;
use Socket;

final class SocketServer {

    private ?Socket $socket = null;

    /** @var array<string, Socket> */
    private array $clients = [];

    /** @var array<string, array{buffer: string, contentLength: int, headersComplete: bool, bodyStartPos: int, address: Address}> */
    private array $clientBuffers = [];

    private int $totalConnections = 0;
    private int $totalRequests = 0;

    public function __construct(private readonly Address $address) {}

    public function create(): bool {
        if ($this->socket !== null) return false;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) return false;

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (!socket_bind($this->socket, $this->address->getAddress(), $this->address->getPort())) return false;
        return socket_listen($this->socket);
    }

    public function listen(): void {
        if ($this->socket === null) return;

        $lastCacheCleanup = time();
        $lastStatsLog = time();

        while ($this->socket !== null) {
            $read = [$this->socket];

            foreach ($this->clients as $clientSocket) {
                $read[] = $clientSocket;
            }

            $write = null;
            $except = null;

            $changed = @socket_select($read, $write, $except, 0, 50 * 1000);

            if ($changed === false) continue;

            if ($changed === 0) {
                $this->performMaintenance($lastCacheCleanup, $lastStatsLog);
                continue;
            }

            if (in_array($this->socket, $read)) {
                $this->acceptNewConnection();

                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }

            foreach ($read as $clientSocket) {
                $this->handleClientData($clientSocket);
            }

            $this->performMaintenance($lastCacheCleanup, $lastStatsLog);
        }
    }
    
    private function acceptNewConnection(): void {
        $clientSocket = @socket_accept($this->socket);

        if ($clientSocket === false) return;
        if (!$clientSocket instanceof Socket) return;

        socket_set_nonblock($clientSocket);

        if (!@socket_getpeername($clientSocket, $address, $port)) {
            @socket_close($clientSocket);
            return;
        }

        $clientId = "$address:$port";
        $this->clients[$clientId] = $clientSocket;
        $this->clientBuffers[$clientId] = [
            "buffer" => "",
            "contentLength" => 0,
            "headersComplete" => false,
            "bodyStartPos" => 0,
            "address" => new Address($address, $port)
        ];

        $this->totalConnections++;
    }
    
    private function handleClientData(Socket $clientSocket): void {
        if (!@socket_getpeername($clientSocket, $address, $port)) {
            @socket_close($clientSocket);
            return;
        }

        $clientId = "$address:$port";

        if (!isset($this->clients[$clientId])) return;
        if (!isset($this->clientBuffers[$clientId])) return;

        $buffer = &$this->clientBuffers[$clientId];

        $chunk = @socket_read($clientSocket, HttpConstants::CHUNK_SIZE);

        if ($chunk === false || $chunk === "") {
            $this->closeClient($clientId);
            return;
        }

        $buffer["buffer"] .= $chunk;

        if (strlen($buffer["buffer"]) > HttpConstants::MAX_REQUEST_SIZE) {
            $this->closeClient($clientId);
            return;
        }

        if (!$buffer["headersComplete"]) {
            if (($headerEndPos = strpos($buffer["buffer"], "\r\n\r\n")) !== false) {
                $buffer["headersComplete"] = true;
                $buffer["bodyStartPos"] = $headerEndPos + 4;

                $headerSection = substr($buffer["buffer"], 0, $headerEndPos);

                if (preg_match("/Content-Length:\s*(\d+)/i", $headerSection, $matches)) {
                    $buffer["contentLength"] = (int) $matches[1];

                    if ($buffer["contentLength"] > HttpConstants::MAX_REQUEST_SIZE) {
                        $this->closeClient($clientId);
                        return;
                    }
                }
            }
        }

        if ($buffer["headersComplete"]) {
            $currentBodyLength = strlen($buffer["buffer"]) - $buffer["bodyStartPos"];

            if ($currentBodyLength >= $buffer["contentLength"]) {
                $this->processCompleteRequest($clientId, $buffer["buffer"], $buffer["address"]);
            }
        }
    }
    
    private function processCompleteRequest(string $clientId, string $requestBuffer, Address $address): void {
        if (!isset($this->clients[$clientId])) return;

        $this->totalRequests++;
        
        $client = new SocketClient($address, $this->clients[$clientId]);
       
        ResponseCache::tick();

        $this->handleRequest($client, $requestBuffer);

        unset($this->clients[$clientId]);
        unset($this->clientBuffers[$clientId]);
    }

    private function closeClient(string $clientId): void {
        if (isset($this->clients[$clientId])) {
            @socket_close($this->clients[$clientId]);
            unset($this->clients[$clientId]);
        }

        if (isset($this->clientBuffers[$clientId])) {
            unset($this->clientBuffers[$clientId]);
        }
    }

    private function performMaintenance(int &$lastCacheCleanup, int &$lastStatsLog): void {
        $now = time();

        if (($now - $lastCacheCleanup) >= 5) {
            ResponseCache::tick();
            $lastCacheCleanup = $now;
        }

        if (($now - $lastStatsLog) >= 30) {
            $lastStatsLog = $now;
        }
    }

    public function handleRequest(SocketClient $client, string $buffer): void {
        $request = Utils::parseHttpRequest($client->getAddress(), $buffer);

        if ($request instanceof StatusCode) {
            $client->respond(ResponseBuilder::create()
                ->code($request)
                ->build()
            );
            return;
        }

        $path = $request->getPath();

        if ($path->getApiVersion() !== null) {
            $ver = HttpServer::getInstance()->getVersion($path->getApiVersion());
            if ($ver !== null && !$ver->getAuthentication()->authenticate($client, $request)) {
                $client->respond($path->handleFailedAuth($request));
                return;
            }
        }

        if ($path->getAuthentication()->authenticate($client, $request)) {
            if (HttpServer::getInstance()->getRateLimiter()->checkRequest($client->getAddress(), $endTimestamp)) {
                if ($path->isBadRequest($request, $badRequestResponse = ResponseBuilder::create()->code(StatusCode::BAD_REQUEST))) {
                    $client->respond($badRequestResponse->build());
                    return;
                }

                if ($path->willCauseError($request, $serverErrorResponse = ResponseBuilder::create()->code(StatusCode::INTERNAL_SERVER_ERROR))) {
                    $client->respond($serverErrorResponse->build());
                    return;
                }

                $response = ResponseCache::check($request);
                if ($response === null) {
                    $response = $path->handle($request);

                    if ($response->getStatusCode() == 200) {
                        ResponseCache::cache($request, $response);
                    }
                }

                $client->respond($response);
            } else {
                $client->respond(HttpServer::getInstance()->getRateLimitResponse($client, $request, $endTimestamp));
            }
        } else {
            $client->respond($path->handleFailedAuth($request));
        }
    }

    public function close(): void {
        if ($this->socket !== null) {
            foreach ($this->clients as $clientId => $clientSocket) $this->closeClient($clientId);
            socket_close($this->socket);
            $this->socket = null;
        }
    }
}