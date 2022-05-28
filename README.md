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

use WillRy\MongoQueue\Connect;

Connect::config("mongo", "root", "root");

/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = false;

/** @var bool Indica se é para recolocar item na fila automaticamente em caso de erro */
$requeue = true;

/** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
$maxRetries = 3;

/** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
$visibiityMinutes = 1;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $requeue,
    $maxRetries,
    $visibiityMinutes
);

$id = rand();
for ($i = 0; $i <=3;$i++){
    $mqueue->insert($i, [
        'id' => $i,
        "name" => "Fulano {$i}",
        "description" => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum semper ex in odio convallis, id dapibus ante ullamcorper. Nulla aliquet risus sapien, nec finibus mi pharetra ac. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non sollicitudin lacus, sit amet volutpat sapien. Aliquam erat volutpat. Pellentesque suscipit venenatis rutrum.",
        "email" => "fulano{$i}@teste.com"
    ]);
}
```

### Consumir item na fila

```php
<?php

class WorkerQueue implements \WillRy\MongoQueue\WorkerInterface
{

    public function handle(\WillRy\MongoQueue\Task $task)
    {
        try {
            print("Início: {$task->id} | Tentativa: {$task->tries}".PHP_EOL);

            $parImpar = rand() % 2 === 0;

            //if is too long, made a ping
            if($parImpar) $task->ping();

            //fake error
            if($parImpar) throw new Exception("Erro aleatório");

            print("Sucesso: {$task->id} | Tentativa: {$task->tries}".PHP_EOL);

            $task->ack();
        } catch (\Exception $e) {
            $task->nack(false);
            throw $e;
        }
    }

    public function error(\WillRy\MongoQueue\Task $task, \Exception $error = null)
    {
        print("Retentativa:{$task->id} | Tentativa: {$task->tries}".PHP_EOL);
    }
}
```

```php
<?php

use WillRy\MongoQueue\Connect;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/WorkerQueue.php";

Connect::config("mongo", "root", "root");


/** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
$autoDelete = true;

/** @var bool Indica se é para recolocar item na fila automaticamente em caso de erro */
$requeue = true;

/** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
$maxRetries = null;

/** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
$visibiityMinutes = 1;

/** @var int Delay em segundos para o processamento de cada item */
$delaySeconds = 3;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $requeue,
    $maxRetries,
    $visibiityMinutes
);

$worker = new WorkerQueue();
$mqueue->consume($worker, $delaySeconds);
```

### Excluir item específico da fila

Excluir item da fila com base no ID

```php
<?php


require_once __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

$autoDelete = false;
$requeue = true;
$maxRetries = 3;
$visibiityMinutes = 1;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $requeue,
    $maxRetries,
    $visibiityMinutes
);

$mqueue->deleteJobByCustomID(1);
```

### Excluir item antigo na fila

Excluir itens processados em "N" dias atrás

```php
<?php


require_once __DIR__ . "/../vendor/autoload.php";

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

$autoDelete = false;
$requeue = true;
$maxRetries = 3;
$visibiityMinutes = 1;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $requeue,
    $maxRetries,
    $visibiityMinutes
);

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

use WillRy\MongoQueue\Connect;
Connect::config("mongo", "root", "root");

$autoDelete = false;
$requeue = true;
$maxRetries = 3;
$visibiityMinutes = 1;

$mqueue = new \WillRy\MongoQueue\Queue(
    "queue",
    "list",
    $autoDelete,
    $requeue,
    $maxRetries,
    $visibiityMinutes
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

```


## Configurar ambiente de produção

Em produção, o ideal é utilizar um supervisor para manter em execução
o processamento da fila com vários workers.

### Instalar o supervisor

```shell
# instalar o supervisor
sudo apt-get install supervisor
```

### Configurar arquivo do worker 

Configurar em **/etc/supervisor/conf.d/mongo-worker.conf**

```text
[program:mongo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/consume.php
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/worker.log
stopwaitsecs=3600
```

**command=** O caminho do script ou comando que vai fazer o consumo

**numprocs=** O número de workers

### Executar supervisor

```shell
sudo supervisorctl reread

sudo supervisorctl update

sudo supervisorctl start mongo-worker:*
```