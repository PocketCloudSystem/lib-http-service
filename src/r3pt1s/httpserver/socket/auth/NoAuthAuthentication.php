<?php

namespace r3pt1s\httpserver\socket\auth;

use r3pt1s\httpserver\io\Request;
use r3pt1s\httpserver\socket\SocketClient;

final class NoAuthAuthentication implements Authentication {

    public function authenticate(SocketClient $client, Request $request): bool {
        return true;
    }
}