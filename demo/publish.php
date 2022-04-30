<?php


require __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

$id = rand();
for ($i = 0; $i <=300000;$i++){
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}"
    ]);
}