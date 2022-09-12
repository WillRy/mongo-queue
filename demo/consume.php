<?php

use WillRy\MongoQueue\Connect;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/WorkerQueue.php";

Connect::config("mongo", "root", "root");


/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = false;

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
$worker = new WorkerQueue();


/** Consumir um item por vez, recomendado para crons */
//$mqueue->consume($worker);

/** Consumir através de um loop (recomendado para supervisor e systemd) */
$mqueue->consumeLoop($worker, $delaySeconds);
