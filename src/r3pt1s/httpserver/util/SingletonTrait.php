<?php

namespace r3pt1s\httpserver\util;

trait SingletonTrait {

    private static ?object $instance = null;

    public static function setInstance(object $instance): void {
        self::$instance = $instance;
    }

    public static function getInstance(): static {
        return self::$instance ??= new static;
    }
}