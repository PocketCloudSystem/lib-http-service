<?php

namespace r3pt1s\httpserver\util;

use pmmp\thread\ThreadSafe;

final class Address extends ThreadSafe {

    public function __construct(
        private readonly string $address,
        private readonly int $port
    ) {}

    public function getAddress(): string {
        return $this->address;
    }

    public function getPort(): int {
        return $this->port;
    }

    public function __toString(): string {
        return "/" . $this->address . ":" . $this->port;
    }

    public static function create(string $address, int $port): self {
        return new self($address, $port);
    }
}