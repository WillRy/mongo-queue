<?php

use WillRy\MongoQueue\Connect;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/WorkerQueue.php";

Connect::config("mongo", "root", "root");


/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = true;

/** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
$maxRetries = 3;

/** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
$visibiityMinutes = 1;

/** @var int Delay em segundos para o processamento de cada item */
$delaySeconds = 3;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $maxRetries,
    $visibiityMinutes
);
$worker = new WorkerQueue();
$mqueue->consume($worker, $delaySeconds);



