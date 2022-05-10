<?php

namespace WillRy\MongoQueue;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use MongoDB\BSON\UTCDateTime;

class Queue
{
    /** @var Client|null */
    public $db;

    /** @var Collection */
    public $collection;

    public function __construct($host, $user, $pass, $port = 27017)
    {
        Connect::config($host, $user, $pass, $port);
        $this->db = Connect::getInstance();

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

    public function initializeQueue(string $database, string $queue)
    {
        $this->collection = $this->db->{$database}->{$queue};

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
            'tries' => 1,
            'end' => 1,
            'ping' => 1
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
            "createdOn" => $this->generateMongoDate("now"),
            "ping" => $this->generateMongoDate("now"),
            "priority" => 1,
            "tries" => 1,
            "payload" => $payload
        ]);
    }

    public function generateMongoDate(string $dateString)
    {
        $time = (new \DateTime($dateString))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->getTimestamp() * 1000;
        return new UTCDateTime($time);
    }

    public function getMongoDate(UTCDateTime $date)
    {
        return $date->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    public function consume($delaySeconds = 3, $requeue = true, $maxRetries = 3, $autoDelete = false, $minutesRequeueUnprocessed = 30)
    {
        while (true) {

            sleep($delaySeconds);

            /**
             * Buscar background jobs que não tenham sidos processados
             * ou que comecaram a ser processados e tiveram algum problema
             * que os levou a não serem processados dentro do tempo estimado:
             * $minutesRequeueUnprocessed
             */
            /** @var BSONDocument $job */
            $job = $this->collection->findOneAndUpdate(
                [
                    '$or' => [
                        [
                            'start' => 0,
                            'tries' => [
                                '$lte' => $maxRetries
                            ]
                        ],
                        [
                            'start' => 1,
                            'tries' => [
                                '$lte' => $maxRetries
                            ],
                            'end' => 0,
                            'ping' => [
                                '$lte' => $this->generateMongoDate("-{$minutesRequeueUnprocessed}minutes")
                            ],
                        ]
                    ]
                ],
                [
                    '$set' => [
                        'startTime' => $this->generateMongoDate("now"),
                        'start' => 1
                    ]
                ],
                [
                    'sort' => [
                        "tries" => 1
                    ]
                ]
            );

            if (empty($job)) continue;

            $payload = $job->getArrayCopy();

            try {

                static::handle($payload);

                if($autoDelete) {
                    $this->deleteJobByID($payload['id']);
                } else {
                    $this->collection->updateOne(
                        [
                            "_id" => $job['_id']
                        ],
                        [
                            '$set' => [
                                'end' => 1,
                                'endTime' => $this->generateMongoDate("now")
                            ]
                        ]
                    );
                }

            } catch (\Exception $e) {
                if ($requeue && $payload["tries"] < $maxRetries) $this->analyzeRequeue($payload);
                static::error($payload, $e);
            }

        }

    }

    public function analyzeRequeue($payload)
    {
        return $this->collection->updateOne(
            [
                '_id' => $payload['_id'],
            ],
            [
                '$set' => [
                    'tries' => $payload["tries"] + 1,
                    'start' => 0
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

    public function deleteJobByID($id)
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

    /**
     * Marca um background job como ainda em processamento
     *
     * Deve ser usado para marcar como 'ainda em processamento', os background jobs longos, que extrapolam o tempo
     * definido no consume
     * @param $id
     */
    public function ping($id)
    {
        $this->collection->updateOne(
            [
                'id' => $id,
            ],
            [
                '$set' => [
                    'ping' => $this->generateMongoDate("now")
                ]
            ]
        );
    }
}