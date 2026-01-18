<?php

namespace r3pt1s\httpserver;

use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\RateLimiter;

final class HttpServerBuilder {

    public static function create(Address $address): self {
        return new self($address);
    }

    public function __construct(
        private Address $address,
        private ?RateLimiter $rateLimiter = null,
        private bool $enableVersioning = false,
        private bool $enableResponseCaching = false,
        private int $cachingTimeInSeconds = 60
    ) {}

    public function setAddress(Address $address): HttpServerBuilder {
        $this->address = $address;
        return $this;
    }

    public function setRateLimiter(RateLimiter $rateLimiter): HttpServerBuilder {
        $this->rateLimiter = $rateLimiter;
        return $this;
    }

    public function setEnableVersioning(bool $enableVersioning): HttpServerBuilder {
        $this->enableVersioning = $enableVersioning;
        return $this;
    }

    public function setEnableResponseCaching(bool $enableResponseCaching): HttpServerBuilder {
        $this->enableResponseCaching = $enableResponseCaching;
        return $this;
    }

    public function setCachingTimeInSeconds(int $cachingTimeInSeconds): HttpServerBuilder {
        $this->cachingTimeInSeconds = $cachingTimeInSeconds;
        return $this;
    }

    public function build(): HttpServer {
        return new HttpServer(
            $this->address,
            $this->rateLimiter ?? RateLimiter::configure(false, 0, 0, 0),
            $this->enableVersioning,
            $this->enableResponseCaching,
            $this->cachingTimeInSeconds
        );
    }
}