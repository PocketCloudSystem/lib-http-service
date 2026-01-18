<?php

namespace r3pt1s\httpserver\route;

use r3pt1s\httpserver\io\Request;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\auth\Authentication;
use r3pt1s\httpserver\util\StatusCode;

abstract class ApiPath implements Path {

    public function __construct(
        private readonly string $path,
        private readonly string $version,
        private readonly string $requestMethod,
        private readonly Authentication $authentication
    ) {}

    public function handleFailedAuth(Request $request): Response {
        return ResponseBuilder::create()
            ->code(StatusCode::FORBIDDEN)
            ->build();
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getFullPath(): string {
        return "/" . $this->getApiVersion() . "/" . trim($this->getPath(), "/");
    }

    public function getApiVersion(): string {
        return $this->version;
    }

    public function getMethod(): string {
        return $this->requestMethod;
    }

    public function getAuthentication(): Authentication {
        return $this->authentication;
    }
}