<?php

namespace WillRy\MongoQueue;

use Exception;

interface WorkerInterface
{
    public function handle(Task $data);

    public function error($data = null, \Exception $error = null);
}
