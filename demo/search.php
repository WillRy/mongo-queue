<?php


require_once __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = true;

/** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
$maxRetries = 3;

/** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
$visibilityMinutes = 1;

/** @var int Delay em segundos para o processamento de cada item */
$delaySeconds = 3;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue_database",
    "queue_list",
    $autoDelete,
    $maxRetries,
    $visibilityMinutes
);

for ($i = 0; $i <= 10; $i++) {
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}"
    ]);
}

$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?? 1;

/** filtrar itens não processados que contenham o nome "fulano" no payload  */
$filter = [
    "startTime" => null,
    "payload.name" => [
        '$regex' => 'fulano', '$options' => 'i'
    ]
];
$mqueue->createIndex([
    "startTime" => 1,
    "payload.name" => 1
]);
$cursor = $mqueue->searchByPayload(
    $page,
    10,
    $filter
);
$total = $mqueue->countByPayload($filter);

$break = !empty($_SERVER["SERVER_NAME"]) ? "<br>" : PHP_EOL;

print("Total: {$total}" . $break);

foreach ($cursor as $document) {
    $data = $document->getArrayCopy();
    print("ID: {$data["id"]}" . $break);
}
