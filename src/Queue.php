<?php
declare(ticks=1);


namespace WillRy\MongoQueue;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

class Queue
{
    use MongoHelpers;

    /** @var Client|null */
    public $db;

    /** @var Collection */
    public $collection;

    /** @var string Nome do banco de dados */
    public $databaseName;

    /** @var string Nome da collection da fila */
    public $queueName;

    /** @var int|null NÃºmero mÃ¡ximo de retentativa caso tenha recolocar fila configurado */
    public $maxRetries;

    /** @var bool Indica se Ã© para excluir item da fila ao finalizar o ciclo de processamento */
    public $autoDelete;

    /** @var int
     * Tempo em minutos em que se uma atividade nÃ£o for processada depois de for pegada, ela vai ser pega de novo
     * por que provavelmente travou e ficou pendurada em algum erro
     */
    public $visibility;

    public $qtdProcessadas = 0;

    public static $status = [
        'waiting' => 1,
        'processing' => 2,
        'canceled' => 3,
        'error' => 4,
        'success' => 5,
    ];

    public function __construct(
        string $database,
        string $queue,
               $autoDelete = false,
               $visibilityMinutes = 2
    )
    {
        $this->db = Connect::getInstance();

        $this->databaseName = $database;

        $this->queueName = $queue;

        $this->collection = Queue::getCollection($database, $queue);

        $this->initializeIndex();

        $this->autoDelete = $autoDelete;

        $this->visibility = $visibilityMinutes;

        /**
         * Graceful shutdown
         * Faz a execucao parar ao enviar um sinal do linux para matar o script
         */
        if (php_sapi_name() == "cli") {
            \pcntl_signal(SIGTERM, function ($signal) {
                $this->shutdown($signal);
            }, false);
            \pcntl_signal(SIGINT, function ($signal) {
                $this->shutdown($signal);
            }, false);
        }
    }

    /**
     * Garante o desligamento correto dos workers
     * @param $signal
     */
    public function shutdown($signal)
    {
        $data = date('Y-m-d H:i:s');
        switch ($signal) {
            case SIGTERM:
                print "Caught SIGTERM {$data}" . PHP_EOL;
                exit;
            case SIGKILL:
                print "Caught SIGKILL {$data}" . PHP_EOL;;
                exit;
            case SIGINT:
                print "Caught SIGINT {$data}" . PHP_EOL;;
                exit;
        }
    }

    public static function getCollection(string $database, string $queue)
    {
        return Connect::getInstance()->{$database}->{$queue};
    }


    /**
     * Cria indices para melhorar a performance da fila
     */
    public function initializeIndex()
    {
        // index for deleteOldJobs
        $this->collection->createIndex([
            'endTime' => 1
        ]);

        // index for deleteJobByID
        $this->collection->createIndex([
            'id' => 1
        ]);

        $this->collection->createIndex([
            'status' => 1,
            'ping' => 1,
            'tries' => 1,
        ]);

    }

    public function insert($id, $payload, $requeue_on_error = true, $maxRetries = 3)
    {
        return $this->collection->insertOne([
            "id" => $id,
            "startTime" => null,
            "endTime" => null,
            "createdOn" => $this->generateMongoDate("now"),
            "ping" => null,
            'requeue_on_error' => $requeue_on_error,
            'status' => self::$status['waiting'],
            'maxRetries' => $maxRetries,
            "tries" => 0,
            "payload" => $payload
        ]);
    }


    /**
     * MantÃ©m o consumo da fila em loop
     * @param WorkerInterface $worker
     * @param int $delaySeconds
     */
    public function consumeLoop(WorkerInterface $worker, $delaySeconds = 3)
    {
        while (true) {

            sleep($delaySeconds);

            /** @var BSONDocument $job */

            $this->consume($worker);
        }
    }

    /**
     * Buscar background jobs que nÃ£o tenham sidos processados
     * ou que comecaram a ser processados e tiveram algum problema
     * que os levou a nÃ£o serem processados dentro do tempo estimado:
     * $visibility
     */
    public function consume(WorkerInterface $worker)
    {

        // a cada 10 tarefas, procura uma que ficou esquecida

        if ($this->qtdProcessadas === 10) {
            $job = $this->getLostTasks();

            $found = !empty($job) ? 1 : 0;
            print_r("[SEARCHING LOST TASK]: {$found}" . PHP_EOL);
            $this->qtdProcessadas = 0;
        } else {
            $job = $this->getTaskWithRequeue();
        }

        $this->qtdProcessadas++;


        if (empty($job)) return;

        $payload = $job->getArrayCopy();


        $task = (new Task(
            $this->collection,
            $this->autoDelete,
            $this->maxRetries
        ))->hydrate($payload);

        print_r("[RECEIVED TASK]: {$task->id}" . PHP_EOL);

        try {
            $worker->handle($task);
        } catch (\Exception $e) {
            $task->nackError(false, $e);

            $worker->error($task->getData(), $e);
        }
    }

    /**
     * Retorna um item na fila, COM analise de retentativa
     * @return array|object|null
     */
    public function getTaskWithRequeue()
    {
        return $this->collection->findOneAndUpdate(
            [
                'status' => Queue::$status['waiting'],
                'ping' => null,
                '$where' => "this.tries <= this.maxRetries"
            ],
            [
                '$set' => [
                    'startTime' => $this->generateMongoDate("now"),
                    'ping' => $this->generateMongoDate("now"),
                    'status' => self::$status['processing'],
                ]
            ],
            [
                'sort' => [
                    "tries" => 1
                ]
            ]
        );
    }

    public function getLostTasks()
    {
        return $this->collection->findOneAndUpdate(
            [
                'status' => Queue::$status['processing'],
                'ping' => [
                    '$lte' => $this->generateMongoDate("-{$this->visibility}minutes")
                ],
                '$where' => "this.tries <= this.maxRetries"
            ],
            [
                '$set' => [
                    'startTime' => $this->generateMongoDate("now"),
                    'ping' => $this->generateMongoDate("now"),
                    'status' => self::$status['processing'],
                ]
            ],
            [
                'sort' => [
                    "tries" => 1
                ]
            ]
        );
    }

    /**
     * Deleta itens antigos na fila
     * @param int $days
     * @return \MongoDB\DeleteResult
     */
    public function deleteOldJobs(int $days = 1)
    {
        return $this->collection->deleteMany([
            "endTime" => [
                '$lte' => $this->generateMongoDate("-{$days} days")
            ]
        ]);
    }

    /**
     * Deleta um item na fila com base no ID
     * @param $id
     * @return \MongoDB\DeleteResult
     */
    public function deleteJobByCustomID($id)
    {
        return $this->collection->deleteOne([
            "id" => $id
        ]);
    }


    /**
     * Pesquisa itens na fila de forma paginada
     * @param int $page
     * @param int $perPage
     * @param array|null[] $conditions
     * @return \MongoDB\Driver\Cursor
     */
    public function searchByPayload(int $page = 1, int $perPage = 10, array $conditions = ["startTime" => null])
    {
        $offset = ($page - 1) * $perPage;
        return $this->collection->find($conditions, [
            'limit' => $perPage,
            'skip' => $offset
        ]);
    }

    /**
     * Conta itens na fila
     * @param array|null[] $conditions
     * @return int
     */
    public function countByPayload(array $conditions = ["startTime" => null])
    {
        return $this->collection->countDocuments($conditions);
    }
}
