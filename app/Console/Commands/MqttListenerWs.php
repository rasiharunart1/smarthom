<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Workerman\Worker;

class MqttListenerWs extends Command
{
    protected $signature = 'mqtt:ws';
    protected $description = 'MQTT listener + WebSocket server using Workerman';

    private $mqtt;
    private $clients = [];

    public function handle()
    {
        $this->info("🎯 Starting MQTT + WebSocket server");

        // WebSocket server
        $ws = new Worker("websocket://0.0.0.0:8080");
        $ws->onConnect = function($connection) {
            $this->clients[] = $connection;
            $this->info("🔌 New WS client connected");
        };
        $ws->onClose = function($connection) {
            $this->clients = array_filter($this->clients, fn($c) => $c !== $connection);
        };

        // MQTT connection
        $host = env('MQTT_HOST', '127.0.0.1');
        $port = (int) env('MQTT_PORT', 1883);
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');

        $settings = (new ConnectionSettings())
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls((bool) env('MQTT_USE_TLS', false));

        $this->mqtt = new MqttClient($host, $port, 'mqtt-ws-' . time());
        $this->mqtt->connect($settings);
        $this->info("✅ Connected to MQTT {$host}:{$port}");

        // Subscribe topic
        $topic = 'users/+/devices/+/sensors/+';
        $this->mqtt->subscribe($topic, function($topic, $message) {
            $payload = json_encode(['topic'=>$topic, 'message'=>$message]);

            // push ke semua WS client
            foreach ($this->clients as $client) {
                $client->send($payload);
            }
        }, 1);

        // Periodic MQTT loop
        $ws->onWorkerStart = function() {
            \Workerman\Timer::add(0.1, function() {
                $this->mqtt->loop(false);
            });
        };

        Worker::runAll();
    }
}
