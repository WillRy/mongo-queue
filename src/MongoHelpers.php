<?php

namespace WillRy\MongoQueue;

use MongoDB\BSON\UTCDateTime;


trait MongoHelpers
{

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

    public function dropDatabase(string $database)
    {
        return $this->db->dropDatabase($database);
    }

    public function createIndex(array $columns = [])
    {
        return $this->collection->createIndex($columns);
    }
}