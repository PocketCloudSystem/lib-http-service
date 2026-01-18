<?php

namespace r3pt1s\httpserver\socket;

use pmmp\thread\ThreadSafe;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\util\Address;
use Socket;

final class SocketClient extends ThreadSafe {

    private const int WRITE_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Address $address,
        private readonly Socket $socket
    ) {}

    public static function fromSocket(Socket $socket): ?SocketClient {
        if (!@socket_getpeername($socket, $address, $port)) return null;
        return new SocketClient(new Address($address, $port), $socket);
    }

    public function respond(Response $response): void {
        $httpResponse = $response->buildResponseString();
        $total = strlen($httpResponse);
        $written = 0;
        $startTime = time();

        while ($written < $total) {
            if (time() - $startTime > self::WRITE_TIMEOUT_SECONDS) {
                $this->close();
                return;
            }

            $result = @socket_write(
                $this->socket,
                substr($httpResponse, $written),
                $total - $written
            );

            if ($result === false) break;
            $written += $result;
        }

        $this->close();
    }

    public function read(int $len): false|string {
        return socket_read($this->socket, $len);
    }

    public function close(): void {
        if ($this->socket !== null) @socket_close($this->socket);
    }

    public function getAddress(): Address {
        return $this->address;
    }
}