<?php

require __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;

Connect::config("mongo", "root", "root");

/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = true;

/** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
$maxRetries = 3;

/** @var bool Se deve recolocar na fila em caso de erro */
$requeue_on_error = true;

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

$id = rand();
for ($i = 0; $i <=3000;$i++){
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}",
        "description" => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum semper ex in odio convallis, id dapibus ante ullamcorper. Nulla aliquet risus sapien, nec finibus mi pharetra ac. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non sollicitudin lacus, sit amet volutpat sapien. Aliquam erat volutpat. Pellentesque suscipit venenatis rutrum.",
        "email" => "fulano{$i}@teste.com"
    ], $requeue_on_error, $maxRetries);
}
