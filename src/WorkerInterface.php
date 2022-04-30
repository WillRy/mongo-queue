<?php

namespace WillRy\MongoQueue;

use Exception;

interface WorkerInterface
{
    public function handle(array $data);

    public function error(array $data, Exception $error = null);
}