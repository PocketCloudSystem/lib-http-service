<?php

namespace r3pt1s\httpserver\socket\auth;

use r3pt1s\httpserver\io\Request;
use r3pt1s\httpserver\socket\SocketClient;

interface Authentication {

    /**
     * @param SocketClient $client
     * @param Request $request
     * @return bool return true if authenticated and false if the authentication process failed
     */
    public function authenticate(SocketClient $client, Request $request): bool;
}