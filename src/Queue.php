<?php

namespace WillRy\MongoQueue;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use MongoDB\BSON\UTCDateTime;

class Queue
{
    use Helpers;

    /** @var Client|null */
    public $db;

    /** @var Collection */
    public $collection;

    /** @var string Nome do banco de dados */
    public $databaseName;

    /** @var string Nome da collection da fila */
    public $queueName;



    /** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
    public $maxRetries;

    /** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
    public $autoDelete;

    /** @var int Tempo em minutos que um item fica invisivel na fila, para não ser reprocessado */
    public $visibiity;

    public function __construct(
        string $database,
        string $queue,
        $autoDelete = false,
        $maxRetries = null,
        $visibiityMinutes = 30
    )
    {
        $this->db = Connect::getInstance();

        $this->databaseName = $database;

        $this->queueName = $queue;

        $this->collection = Queue::getCollection($database, $queue);

        $this->initializeQueue();


        $this->maxRetries = $maxRetries;

        $this->visibiity = $visibiityMinutes;

        $this->autoDelete = $autoDelete;

        /** Graceful shutdown */
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
        switch ($signal) {
            case SIGTERM:
                print "Caught SIGTERM" . PHP_EOL;
                exit;
            case SIGKILL:
                print "Caught SIGKILL" . PHP_EOL;;
                exit;
            case SIGINT:
                print "Caught SIGINT" . PHP_EOL;;
                exit;
        }
    }

    public static function getCollection(string $database, string $queue)
    {
        return Connect::getInstance()->{$database}->{$queue};
    }


    public function initializeQueue()
    {
        // index for deleteOldJobs
        $this->collection->createIndex([
            'endTime' => 1
        ]);

        // index for deleteJobByID
        $this->collection->createIndex([
            'id' => 1
        ]);

        // index for analyzeRequeue
        $this->collection->createIndex([
            'tries' => 1
        ]);

        $this->collection->createIndex([
            'start' => 1,
            'ack' => 1,
            'nack' => 1,
            'ping' => 1,
            'tries' => 1,
        ]);
    }

    public function dropDatabase(string $database)
    {
        return $this->db->dropDatabase($database);
    }

    public function createIndex(array $columns = [])
    {
        return $this->collection->createIndex($columns);
    }

    public function insert($id, $payload)
    {
        return $this->collection->insertOne([
            "id" => $id,
            "startTime" => null,
            "endTime" => null,
            "end" => 0,
            "start" => 0,
            'ack' => false,
            'nack' => false,
            "createdOn" => $this->generateMongoDate("now"),
            "ping" => null,
            "priority" => 1,
            "tries" => 1,
            "payload" => $payload
        ]);
    }


    public function consume(WorkerInterface $worker, $delaySeconds = 3)
    {
        while (true) {

            sleep($delaySeconds);

            /**
             * Buscar background jobs que não tenham sidos processados
             * ou que comecaram a ser processados e tiveram algum problema
             * que os levou a não serem processados dentro do tempo estimado:
             * $visibiity
             */
            /** @var BSONDocument $job */
            $job = $this->maxRetries ? $this->getTaskWithRequeue() : $this->getTaskWithoutRequeue();


            if (empty($job)) continue;

            $payload = $job->getArrayCopy();


            $task = (new Task(
                $this->collection,
                $this->autoDelete,
                $this->maxRetries
            ))->hydrate($payload);

            try {
                $worker->handle($task);
            } catch (\Exception $e) {
                $worker->error($task, $e);
            }

        }

    }

    public function getTaskWithRequeue()
    {
        return $this->collection->findOneAndUpdate(
            [
                '$or' => [
                    [
                        'start' => 0,
                        'ack' => false,
                        'nack' => false,
                        'tries' => [
                            '$lte' => $this->maxRetries
                        ],
                        'ping' => null,
                    ],
                    [
                        'start' => 0,
                        'ack' => false,
                        'nack' => false,
                        'tries' => [
                            '$lte' => $this->maxRetries
                        ],
                        'ping' => [
                            '$lte' => $this->generateMongoDate("-{$this->visibiity}minutes")
                        ],
                    ],
                ]
            ],
            [
                '$set' => [
                    'startTime' => $this->generateMongoDate("now"),
                    'start' => 1,
                    'ping' => $this->generateMongoDate("now")
                ]
            ],
            [
                'sort' => [
                    "tries" => 1
                ]
            ]
        );
    }

    public function getTaskWithoutRequeue()
    {
        return $this->collection->findOneAndUpdate(
            [
                '$or' => [
                    [
                        'start' => 0,
                        'ack' => false,
                        'nack' => false,
                    ],
                    [
                        'start' => 1,
                        'ack' => false,
                        'nack' => false,
                        'ping' => [
                            '$lte' => $this->generateMongoDate("-{$this->visibiity}minutes")
                        ],
                    ],
                ]
            ],
            [
                '$set' => [
                    'startTime' => $this->generateMongoDate("now"),
                    'start' => 1,
                ]
            ],
            [
                'sort' => [
                    "tries" => 1
                ]
            ]
        );
    }

    public function deleteOldJobs(int $days = 1)
    {
        return $this->collection->deleteMany([
            "endTime" => [
                '$lte' => $this->generateMongoDate("-{$days} days")
            ]
        ]);
    }

    public function deleteJobByCustomID($id)
    {
        return $this->collection->deleteOne([
            "id" => $id
        ]);
    }


    public function searchByPayload(int $page = 1, int $perPage = 10, array $conditions = ["startTime" => null])
    {
        $offset = ($page - 1) * $perPage;
        return $this->collection->find($conditions, [
            'limit' => $perPage,
            'skip' => $offset
        ]);
    }

    public function countByPayload(array $conditions = ["startTime" => null])
    {
        return $this->collection->countDocuments($conditions);
    }

}