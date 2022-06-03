<?php


namespace WillRy\MongoQueue;


use MongoDB\Client;

class Task
{
    use Helpers;

    /** @var Client|null */
    public $db;

    /** @var
     * ID de identificação da Task
     */
    public $id;

    /** @var
     * Dados do item da fila
     */
    public $data;

    /** @var
     * Objeto de manipulação da collection
     */
    public $collection;


    /** @var int|null Número máximo de retentativa caso tenha recolocar fila configurado */
    public $maxRetries;

    /** @var bool Indica se é para excluir item da fila ao finalizar todo o ciclo de processamento */
    public $autoDelete;

    public function __construct($collection, $autoDelete = true, $maxRetries = null)
    {
        $this->db = Connect::getInstance();

        $this->collection = $collection;

        $this->autoDelete = $autoDelete;

        $this->maxRetries = $maxRetries;
    }

    public function __set($name, $value)
    {
        if (empty($this->data)) $this->data = new \stdClass();
        $this->data->$name = $value;
    }

    public function __get($name)
    {
        if (!empty($this->data->$name)) return $this->data->$name;
        return null;
    }


    /**
     * Popular dados da task na classe
     * @param array $payload
     * @return $this
     */
    public function hydrate(array $payload)
    {
        foreach ($payload as $key => $item) {
            $this->$key = $item;
        }

        $this->id = $payload['_id'];

        return $this;
    }

    /**
     * Excluir o item da fila
     * @return mixed
     */
    public function autoDelete()
    {
        return $this->collection->deleteOne([
            "_id" => $this->id
        ]);
    }

    /**
     * Marca o item como processado com sucesso
     * @return mixed
     */
    public function ack()
    {
        if ($this->autoDelete) return $this->autoDelete();

        return $this->collection->updateOne(
            [
                '_id' => $this->id,
            ],
            [
                '$set' => [
                    'ack' => true,
                    'end' => 1,
                    'endTime' => $this->generateMongoDate("now")
                ]
            ]
        );
    }

    /**
     * Marca o item como processado com erro, sendo possível resetar
     * as retentativas manualmente
     *
     * Se tem requeue: Exclui o item ao exceder o limite de tentativas
     * Se não tem requeue: Mantém o item na fila, mesmo com erro
     *
     * @param false $resetTries
     * @return mixed
     */
    public function nack($requeue = true, bool $resetTries = false)
    {
        $payload = [
            'nack' => true,
            'end' => 0,
            'endTime' => $this->generateMongoDate("now"),
        ];

        $passMaxTries = $this->tries >= $this->maxRetries;

        if ($resetTries) $payload['tries'] = 1;

        if ($resetTries || ($requeue && $this->maxRetries && !$passMaxTries)) {
            $payload['start'] = 0;
            $payload['end'] = 0;
            $payload['tries'] = $this->tries + 1;
            $payload['nack'] = false;
        }


        //se tem autodelete e não é para fazer requeue: Excluir item
        if ($this->autoDelete && !$requeue) return $this->autoDelete();

        //se tem autodelete e passou do numero de retries: Excluir item
        if ($this->autoDelete && $passMaxTries) return $this->autoDelete();

        //se não é para excluir o item: Manter item
        return $this->collection->updateOne(
            [
                '_id' => $this->id,
            ],
            [
                '$set' => $payload
            ]
        );

    }

    /**
     * Marca que o item ainda está em processamento
     * Para evitar que seja pego novamente pela fila
     */
    public function ping()
    {
        $this->collection->updateOne(
            [
                '_id' => $this->id,
            ],
            [
                '$set' => [
                    'ping' => $this->generateMongoDate("now")
                ]
            ]
        );
    }

}