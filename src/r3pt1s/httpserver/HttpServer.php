<?php

namespace r3pt1s\httpserver;

use Closure;
use r3pt1s\httpserver\io\Request;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\route\Path;
use r3pt1s\httpserver\route\RegularPath;
use r3pt1s\httpserver\socket\SocketClient;
use r3pt1s\httpserver\socket\SocketServer;
use r3pt1s\httpserver\util\ActionFailureReason;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\HttpConstants;
use r3pt1s\httpserver\util\RateLimiter;
use r3pt1s\httpserver\util\SingletonTrait;
use r3pt1s\httpserver\util\StatusCode;
use r3pt1s\httpserver\version\ApiVersion;

final class HttpServer {
    use SingletonTrait;

    /** @var array<ApiVersion> */
    private array $versions = [];
    /** @var array<array<Path>> */
    private array $paths = [];

    private SocketServer $server;
    private Closure $rateLimitResponse;

    public function __construct(
        private readonly Address $address,
        private readonly RateLimiter $rateLimiter,
        private readonly bool $enableVersioning,
        private readonly bool $enableResponseCaching,
        private readonly int $cachingTimeInSeconds = 60
    ) {
        self::setInstance($this);
        $this->server = new SocketServer($this->address);
        $this->rateLimitResponse = function (SocketClient $client, Request $request, int $endTimestamp): Response {
            return ResponseBuilder::create()
                ->code(StatusCode::TOO_MANY_REQUESTS)
                ->body(["message" => "You are being rate limited. Please try again in " . ($endTimestamp - time()) . " seconds.", "end_timestamp" => $endTimestamp])
                ->build();
        };
    }

    public function stop(): void {
        $this->server->close();
    }

    public function setRateLimitResponse(Closure $closure): void {
        $this->rateLimitResponse = $closure;
    }

    public function registerPath(Path $path): true|ActionFailureReason {
        $pathRoute = "/" . trim($path->getPath(), "/");
        if ($path->getApiVersion() !== null && !$this->enableVersioning) return ActionFailureReason::PATH_REGISTER_FAILED_VERSIONING_DISABLED;
        if (!in_array($path->getMethod(), HttpConstants::SUPPORTED_REQUEST_METHODS)) return ActionFailureReason::PATH_REGISTER_FAILED_UNSUPPORTED_REQUEST_METHOD;

        if ($path instanceof RegularPath) {
            $this->paths[$path->getMethod()][$path->getFullPath()] = $path;
        } else {
            if (($version = $this->getVersion($path->getApiVersion())) !== null) {
                if (!$version->isValidPath($path->getMethod(), $pathRoute)) {
                    $version->addPath($path->getMethod(), $pathRoute);
                }

                $this->paths[$path->getMethod()][$path->getFullPath()] = $path;
            } else return ActionFailureReason::PATH_REGISTER_FAILED_API_VERSION_NOT_EXISTENT;
        }

        return true;
    }

    public function registerVersion(ApiVersion $version): true|ActionFailureReason {
        if (!$this->enableVersioning) return ActionFailureReason::VERSION_REGISTER_FAILED_VERSIONING_DISABLED;
        if (isset($this->versions[$version->getVersion()])) return ActionFailureReason::VERSION_REGISTER_FAILED_VERSION_EXISTS;
        $this->versions[$version->getVersion()] = $version;
        return true;
    }

    public function getVersion(string $versionOrPath, string $method = "GET"): ?ApiVersion {
        if (isset($this->versions[$versionOrPath])) return $this->versions[$versionOrPath];
        if (count($a = array_filter($this->versions, fn(ApiVersion $version) => $version->isValidPath($method, $versionOrPath))) > 0) return current($a);
        return null;
    }

    public function getVersions(): array {
        return $this->versions;
    }

    public function getPath(string $method, string $path): ?Path {
        return $this->paths[$method][$path] ?? null;
    }

    public function getPaths(): array {
        return $this->paths;
    }

    public function getServer(): SocketServer {
        return $this->server;
    }

    public function getRateLimitResponse(SocketClient $client, Request $request, int $endTimestamp): Response {
        return ($this->rateLimitResponse)($client, $request, $endTimestamp);
    }

    public function getAddress(): Address {
        return $this->address;
    }

    public function getRateLimiter(): RateLimiter {
        return $this->rateLimiter;
    }

    public function isEnableVersioning(): bool {
        return $this->enableVersioning;
    }

    public function isEnableResponseCaching(): bool {
        return $this->enableResponseCaching;
    }

    public function getCachingTimeInSeconds(): int {
        return $this->cachingTimeInSeconds;
    }
}