<?php


require_once __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

/**
 * Delete old finished jobs by days
 */
$days = 1;
$mqueue->deleteOldJobs($days);


/**
 * Delete job by id
 */
$mqueue->deleteJobByID(1);
