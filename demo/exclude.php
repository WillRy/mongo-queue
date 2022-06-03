<?php

require_once __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

$autoDelete = false;
$maxRetries = 3;
$visibiityMinutes = 1;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $maxRetries,
    $visibiityMinutes
);


/**
 * Delete old finished jobs by days
 */
$days = 1;
$mqueue->deleteOldJobs($days);


/**
 * Delete job by id
 */
$mqueue->deleteJobByCustomID(1);
