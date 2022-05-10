<?php


require __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

$id = rand();
for ($i = 0; $i <=300000;$i++){
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}",
        "description" => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum semper ex in odio convallis, id dapibus ante ullamcorper. Nulla aliquet risus sapien, nec finibus mi pharetra ac. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non sollicitudin lacus, sit amet volutpat sapien. Aliquam erat volutpat. Pellentesque suscipit venenatis rutrum.",
        "email" => "fulano{$i}@teste.com"
    ]);
}