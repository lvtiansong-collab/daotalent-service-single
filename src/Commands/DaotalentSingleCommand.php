<?php

namespace Wiltechsteam\DaotalentServiceSingle\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class DaotalentSingleCommand extends Command
{
    protected $signature = 'daotalent:work';
    protected $description = 'Daotalent Queue Work';

    public function handle()
    {
        ini_set('memory_limit', '1024M');

        $queue = config('daotalent.rabbitmq_queue');

        $connection = new AMQPStreamConnection(
            config('daotalent.rabbitmq_host'),
            config('daotalent.rabbitmq_port'),
            config('daotalent.rabbitmq_login'),
            config('daotalent.rabbitmq_password'),
            config('daotalent.rabbitmq_vhost', '/'),
            false,         // insist
            'AMQPLAIN',    // login_method
            null,          // login_response
            'en_US',       // locale
            120,           // read_timeout
            120,           // write_timeout
            null,          // context
            false,         // keepalive
            60             // heartbeat
        );

        $channel = $connection->channel();
        $channel->queue_declare($queue, true, false, true, false);
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($queue, 'consumer-' . getmypid(), false, false, false, false, fn($e) => $this->process($e));

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function process($callback): void
    {
        $exchange = $callback->delivery_info['exchange'];
        $channel = $callback->delivery_info['channel'];
        $tag = $callback->delivery_info['delivery_tag'];
        $bodyData = json_decode($callback->body, true);

        try {
            $this->bindEvent($exchange, $bodyData);
            $channel->basic_ack($tag);
            $this->info(date('Y-m-d H:i:s') . ' ' . $exchange . ' - succeed');
        } catch (\Throwable $e) {
            Log::error('Daotalent Queue Error', [
                'exchange' => $exchange,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->error(date('Y-m-d H:i:s') . ' ' . $exchange . ' - error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());

            try {
                $this->publishError($channel, $bodyData, $e);
            } catch (\Throwable $publishException) {
                $this->error('Failed to publish to error queue: ' . $publishException->getMessage());
            }

            $channel->basic_ack($tag);
        }
    }

    private function publishError($channel, array $bodyData, \Throwable $error): void
    {
        $errorQueue = config('daotalent.rabbitmq_queue_error');

        if (empty($errorQueue)) {
            $this->error("DAOTALENT_RABBITMQ_QUEUE_ERROR is not configured, skipping error queue publish");
            return;
        }

        try {
            $channel->exchange_declare($errorQueue, 'direct', false, true, false);
            $channel->queue_declare($errorQueue, true, false, false, false);
            $channel->queue_bind($errorQueue, $errorQueue, $errorQueue);
        } catch (\Throwable $e) {
            $this->error("Failed to setup error queue: " . $e->getMessage());
            return;
        }

        $bodyData['error'] = [
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];

        $channel->basic_publish(
            new AMQPMessage(json_encode($bodyData), ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
            $errorQueue
        );
    }

    private function bindEvent(string $exchangeName, array $bodyData): void
    {
        if (strpos($exchangeName, ':') === false) {
            throw new \Exception('Exchange name is illegality.');
        }

        $eventAlias = explode(':', $exchangeName)[1];
        $eventClass = config('daotalent.events')[$eventAlias] ?? null;

        if (!$eventClass || !class_exists($eventClass)) {
            throw new \Exception("Event '$eventAlias' is not found.");
        }

        event(new $eventClass($bodyData));
    }
}
