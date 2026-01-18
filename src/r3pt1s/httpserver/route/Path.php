<?php

namespace r3pt1s\httpserver\route;

use r3pt1s\httpserver\io\Request;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\auth\Authentication;

interface Path {

    public function handle(Request $request): Response;

    public function handleFailedAuth(Request $request): Response;

    public function isBadRequest(Request $request, ResponseBuilder $response): bool;

    public function willCauseError(Request $request, ResponseBuilder $response): bool;

    public function getApiVersion(): ?string;

    public function getPath(): string;

    public function getFullPath(): string;

    public function getMethod(): string;

    public function getAuthentication(): Authentication;
}