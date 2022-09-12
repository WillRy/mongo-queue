<?php


namespace WillRy\MongoQueue;


use MongoDB\Client;
use MongoDB\Collection;

class Task
{

    use MongoHelpers;

    /** @var Client|null */
    public $db;

    /** @var
     * ID de identificaÃ§Ã£o da Task
     */
    public $id;

    /** @var
     * Dados do item da fila
     */
    public $data;

    /** @var
     * Objeto de manipulaÃ§Ã£o da collection
     */
    public $collection;


    /** @var int|null NÃºmero mÃ¡ximo de retentativa caso tenha recolocar fila configurado */
    public $maxRetries;

    /** @var bool Indica se Ã© para excluir item da fila ao finalizar todo o ciclo de processamento */
    public $autoDelete;

    public function __construct(Collection $collection, $autoDelete = true, $maxRetries = null)
    {
        $this->db = Connect::getInstance();

        $this->collection = $collection;

        $this->autoDelete = $autoDelete;

        $this->maxRetries = $maxRetries;
    }

    /**
     * Insere todos os itens no $data da classe
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (empty($this->data)) $this->data = new \stdClass();
        $this->data->$name = $value;
    }

    /**
     * Pega todos os itens no $data da classe
     * @param $name
     * @param $value
     */
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

        print_r("[SUCCESS]: {$this->id}" . PHP_EOL);

        if ($this->autoDelete) return $this->autoDelete();

        return $this->collection->updateOne(
            [
                '_id' => $this->id,
            ],
            [
                '$set' => [
                    'ack' => true,
                    'endTime' => $this->generateMongoDate("now"),
                    'status' => Queue::$status['success'],
                ]
            ]
        );
    }

    public function nackError(bool $resetTries = false, \Exception $error = null)
    {
        $msg = !empty($error) ? $error->getMessage() : 'Erro manual';

        print_r("[ERROR]: " . $msg . PHP_EOL);

        return $this->nack(Queue::$status['error'], $resetTries, $error);
    }


    public function nackCanceled()
    {
        print_r("[CANCELED]: " . $this->id . PHP_EOL);

        return $this->nack(Queue::$status['canceled'], false, null);
    }


    /**
     * Marca o item como processado com erro, sendo possÃ­vel resetar
     * as retentativas manualmente
     *
     * Se tem requeue: Exclui o item ao exceder o limite de tentativas
     * Se nÃ£o tem requeue: MantÃ©m o item na fila, mesmo com erro
     *
     * @param false $resetTries
     * @return mixed
     */
    public function nack(string $status, bool $resetTries = false, \Exception $error = null)
    {
        $requeue = $this->requeue_on_error && $status !== Queue::$status['canceled'];

        $tries = $this->tries >= 0 ? $this->tries + 1 : 0;

        $maxRetries = $this->maxRetries;

        $payload = [
            'endTime' => $this->generateMongoDate("now"),
            'ping' => null,
            'tries' => $tries,
            'last_error' => !empty($error) ? $error->getMessage() : null,
            'status' => $status
        ];

        if ($resetTries) {
            $payload['tries'] = 0;
            $tries = 0;
        }

        $inMaxTries = $tries === $maxRetries;

        //se tem autodelete e passou do numero de retries: Excluir item
        if ($this->autoDelete && $inMaxTries) {
            return $this->autoDelete();
        }

        if ($requeue && !$inMaxTries) {
            $payload['tries'] = $tries;
            $payload['status'] = Queue::$status['waiting'];
        }

        //se nÃ£o Ã© para excluir o item: Manter item
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
     * Marca que o item ainda estÃ¡ em processamento
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

    /**
     * Retorna todos os dados do item na fila
     * @return object|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Retorna o payload de um item na fila
     * @return object|null
     */
    public function getPayload()
    {
        return $this->payload;
    }

}
