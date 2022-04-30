<?php

class Worker extends \WillRy\MongoQueue\Queue implements \WillRy\MongoQueue\WorkerInterface
{

    public function handle(array $data)
    {

        $parImpar = rand() % 2 === 0;

        //if is too long, made a ping
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