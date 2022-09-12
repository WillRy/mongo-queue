<?php

require_once __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = true;

/** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
$visibilityMinutes = 1;

/** @var int Delay em segundos para o processamento de cada item */
$delaySeconds = 3;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue_database",
    "queue_list",
    $autoDelete,
    $visibilityMinutes
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
