# Mongo Queue

Uma biblioteca para criar filas e background jobs usando mongodb.

A biblioteca permite:

- Criação de multiplas filas
- Uso de vários workers (workers não pegam itens duplicados)
- Ao usar mongo como fila, ao invés do REDIS, é possível realizar pesquisas indexadas no payload dos itens da fila

## Performance

Em testes realizados foi possível manter 600.000 itens na fila, com 9 workers processando com um delay de 2s no consumo,
com menos de 2.5% de CPU.

## Utilização

A pasta **demo** contém demonstrações de como **publicar**, **consumir** e **excluir** itens velhos

### Publicar item na fila

```php
<?php

require __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

$id = rand();
for ($i = 0; $i <=300;$i++){
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}"
    ]);
}
```

### Consumir item na fila

```php
<?php

class Worker extends \WillRy\MongoQueue\Queue implements \WillRy\MongoQueue\WorkerInterface
{

    public function handle(array $data)
    {

        $parImpar = rand() % 2 === 0;

        //se o background job for longo, sinaliza que ainda
        //está em processamento
        if($parImpar) $this->ping($data['payload']['id']);


        //fake error
        if($parImpar) throw new Exception("Erro aleatório");

        print("Sucesso:{$data['payload']['id']} | Tentativa: {$data['tries']}".PHP_EOL);
    }

    public function error(array $data, \Exception $error = null)
    {
        print("Retentativa:{$data['payload']['id']}".PHP_EOL);
    }
}
```

```php
<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/Worker.php";


$mqueue = new Worker("mongo", "root", "root");
$mqueue->initializeQueue("queue", "list");

$requeue = true;
$maxRetries = 3;
$autoDelete = true;

$mqueue->consume(2, $requeue, $maxRetries, $autoDelete);
```

### Excluir item específico da fila

Excluir item da fila com base no ID

```php
<?php

require_once __DIR__ . "/../vendor/autoload.php";

//new \WillRy\MongoQueue\Queue("host","user","pass","port")
$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->deleteJobByID(1);

```

### Excluir item antigo na fila

Excluir itens processados em "N" dias atrás

```php
<?php

require_once __DIR__ . "/../vendor/autoload.php";

//new \WillRy\MongoQueue\Queue("host","user","pass","port")
$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

$days = 1;

$mqueue->deleteOldJobs($days);
```

### Usar conexão do mongo

```php
<?php

require_once __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

/** @var MongoDB\Client|null  */
$connection = $mqueue->db;
```

### Criar indices para pesquisa na fila

Para pesquisar itens na fila, com base em algum campo do payload, é possível criar um indice e em seguida usar o método
de pesquisa.

**IMPORTANTE**

Deve criar os indices com base na ordem em que os campos são usados na pesquisa

```php
<?php

require_once __DIR__ . "/../vendor/autoload.php";

$mqueue = new \WillRy\MongoQueue\Queue("mongo", "root", "root");

$mqueue->initializeQueue("queue", "list");

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

$break = $_SERVER["SERVER_NAME"] ? "<br>" : PHP_EOL;

print("Total: {$total}" . $break);

foreach ($cursor as $document) {
    $data = $document->getArrayCopy();
    print("ID: {$data["id"]}" . $break);
}

```
