<?php

namespace WillRy\MongoQueue;

use MongoDB\Client;

class Connect
{
    /**
     * @const array
     */
    private static $opt = [];

    /**
     * @var Client
     */

    private static $instance;

    /**
     * Connect constructor. Private singleton
     */
    private function __construct()
    {
    }

    public static function getInstance(): ?Client
    {
        if (empty(self::$instance)) {
            self::$instance = new Client(self::$opt["uri"]);
        }

        return self::$instance;
    }

    public static function config($host, $user, $pass, $port = 27017)
    {
        self::$opt = [
            'scheme' => "tcp",
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'uri' => "mongodb://{$user}:{$pass}@{$host}:$port/"
        ];
    }

    /**
     * Connect clone. Private singleton
     */
    private function __clone()
    {
    }
}