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