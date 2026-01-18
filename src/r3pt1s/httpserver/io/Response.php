<?php

namespace r3pt1s\httpserver\io;

use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use r3pt1s\httpserver\util\StatusCode;
use r3pt1s\httpserver\util\Utils;

final class Response extends ThreadSafe {

	public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly ?string $customMessage,
        private readonly ThreadSafeArray $headers
    ) {}

    public function buildResponseString(): string {
        $httpResponse = "HTTP/1.1 " . $this->statusCode . " " . StatusCode::toString($this->statusCode) . "\r\n";
        $httpResponse .=  implode("\r\n", Utils::encodeHeaders((array) $this->headers)) . "\r\n";
        $httpResponse .= "\r\n";
        $httpResponse .= $this->body;
        return $httpResponse;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function getCustomMessage(): ?string {
        return $this->customMessage;
    }

    public function getHeaders(): array {
        return (array) $this->headers;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}