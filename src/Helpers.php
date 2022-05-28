<?php


namespace WillRy\MongoQueue;


use MongoDB\BSON\UTCDateTime;

trait Helpers
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
}