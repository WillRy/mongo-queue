<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/Worker.php";


$mqueue = new Worker("mongo", "root", "root");
$mqueue->initializeQueue("queue", "list");

$requeue = true;
$maxRetries = 3;
$autoDelete = true;

$mqueue->consume(3, $requeue, $maxRetries, $autoDelete);