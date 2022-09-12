<?php

class WorkerQueue implements \WillRy\MongoQueue\WorkerInterface
{

    public function handle(\WillRy\MongoQueue\Task $task)
    {
        $parImpar = rand() % 2 === 0;

        //if is too long, made a ping
        if($parImpar) $task->ping();

        //fake error = Exceptions são detectadas e tratadas como nackError automaticamente
        IF($parImpar) throw new Exception("Erro aleatório");


        $task->ack(); //marca como sucesso

        /** marca como erro */
        //$task->nackError();

        /** marca como cancelado */
//        $task->nackCanceled();


    }

    public function error($data = null, \Exception $error = null)
    {

    }
}
