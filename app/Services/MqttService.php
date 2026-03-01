<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

/**
 * MQTT publisher service for Laravel.
 * Publishes control messages to ESP devices using widget key.
 */
class MqttService
{
    protected string $host;
    protected int $port;
    protected ?string $username;
    protected ?string $password;
    protected bool $useTls;

    public function __construct()
    {
        $this->host = env('MQTT_HOST');
        $this->port = (int) env('MQTT_PORT', 8883);
        $this->username = env('MQTT_USERNAME');
        $this->password = env('MQTT_PASSWORD');
        $this->useTls = (bool) env('MQTT_USE_TLS', true);
    }

    /**
     * Publish control command to device using widget key.
     *
     * Topic: users/{userId}/devices/{deviceCode}/control/{widgetKey}
     *
     * @param int|string $userId
     * @param string $deviceCode
     * @param string $widgetKey
     * @param mixed $value
     * @param int $qos Quality of Service (0, 1, or 2)
     * @param bool $retain Retain message on broker
     * @return array
     */
    public function publishWidgetControl($userId, string $deviceCode, string $widgetKey, $value, int $qos = 1, bool $retain = false): array
    {
        $topic = "users/{$userId}/devices/{$deviceCode}/control/{$widgetKey}";
        $payload = (string) $value;

        Log::info('📤 Attempting MQTT publish', [
            'topic' => $topic,
            'payload' => $payload,
            'qos' => $qos,
            'retain' => $retain,
            'broker' => $this->host .  ':' . $this->port
        ]);

        try {
            $settings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setUseTls($this->useTls)
                ->setTlsSelfSignedAllowed(true) // ✅ Allow self-signed certs (testing)
                ->setKeepAliveInterval(30)
                ->setConnectTimeout(10)
                ->setSocketTimeout(5)
                ->setResendTimeout(10);

            $clientId = 'laravel-pub-' . time() . '-' . mt_rand(1000, 9999);
            $mqtt = new MqttClient($this->host, $this->port, $clientId);

            Log::debug('MQTT connecting... ', ['client_id' => $clientId]);
            
            $mqtt->connect($settings, true); // clean session
            
            Log::debug('MQTT connected, publishing.. .');
            
            // ✅ Use the $qos and $retain parameters
            $mqtt->publish($topic, $payload, $qos, $retain);
            
            Log::debug('MQTT published, disconnecting...');
            
            $mqtt->disconnect();

            Log::info('✅ MQTT publish successful', [
                'topic' => $topic,
                'payload' => $payload,
                'qos' => $qos,
                'timestamp' => now()->toIso8601String()
            ]);

            return [
                'success' => true,
                'topic' => $topic,
                'payload' => $payload,
                'qos' => $qos,
                'timestamp' => now()->toIso8601String()
            ];

        } catch (\PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException $e) {
            Log::error('❌ MQTT connection failed', [
                'error' => $e->getMessage(),
                'broker' => $this->host . ':' . $this->port,
                'topic' => $topic
            ]);
            
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'topic' => $topic
            ];

        } catch (\PhpMqtt\Client\Exceptions\DataTransferException $e) {
            Log::error('❌ MQTT data transfer failed', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'payload' => $payload
            ]);
            
            return [
                'success' => false,
                'error' => 'Data transfer failed: ' . $e->getMessage(),
                'topic' => $topic
            ];

        } catch (\Exception $e) {
            Log::error('❌ MQTT publish error', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'topic' => $topic,
                'payload' => $payload,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'topic' => $topic
            ];
        }
    }

    /**
     * Test MQTT connection (for debugging & health checks)
     * 
     * @return array
     */
    public function testConnection(): array
    {
        try {
            $settings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setUseTls($this->useTls)
                ->setTlsSelfSignedAllowed(true)
                ->setConnectTimeout(10);

            $clientId = 'laravel-test-' . time();
            $mqtt = new MqttClient($this->host, $this->port, $clientId);
            
            Log::info('Testing MQTT connection... ', [
                'broker' => $this->host . ':' . $this->port,
                'username' => $this->username
            ]);

            $mqtt->connect($settings, true);
            $mqtt->disconnect();

            Log::info('✅ MQTT connection test successful');

            return [
                'success' => true,
                'message' => 'Connection successful',
                'broker' => $this->host . ':' . $this->port,
                'timestamp' => now()->toIso8601String()
            ];

        } catch (\Exception $e) {
            Log::error('❌ MQTT connection test failed', [
                'error' => $e->getMessage(),
                'broker' => $this->host . ':' . $this->port
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'broker' => $this->host . ':' . $this->port,
                'timestamp' => now()->toIso8601String()
            ];
        }
    }

    /**
     * Publish multiple messages in batch (more efficient)
     * 
     * @param array $messages [[userId, deviceCode, widgetKey, value], ...]
     * @return array
     */
    public function publishBatch(array $messages): array
    {
        $results = [];
        $mqtt = null;

        try {
            $settings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setUseTls($this->useTls)
                ->setTlsSelfSignedAllowed(true)
                ->setKeepAliveInterval(30)
                ->setConnectTimeout(10);

            $clientId = 'laravel-batch-' .  time();
            $mqtt = new MqttClient($this->host, $this->port, $clientId);
            $mqtt->connect($settings, true);

            foreach ($messages as $msg) {
                [$userId, $deviceCode, $widgetKey, $value] = $msg;
                $topic = "users/{$userId}/devices/{$deviceCode}/control/{$widgetKey}";
                $payload = (string) $value;

                try {
                    $mqtt->publish($topic, $payload, 1, false);
                    $results[] = ['success' => true, 'topic' => $topic];
                    Log::info("✅ Batch published: {$topic}");
                } catch (\Exception $e) {
                    $results[] = ['success' => false, 'topic' => $topic, 'error' => $e->getMessage()];
                    Log::error("❌ Batch publish failed: {$topic}", ['error' => $e->getMessage()]);
                }
            }

            $mqtt->disconnect();

            return [
                'success' => true,
                'total' => count($messages),
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('❌ Batch publish connection failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results
            ];
        }
    }

    /**
     * Publish a message to a specific topic.
     * 
     * @param string $topic
     * @param string $message
     * @param int $qos
     * @param bool $retain
     * @return bool
     */
    public function publishToTopic(string $topic, string $message, int $qos = 1, bool $retain = false): bool
    {
        try {
            $settings = (new ConnectionSettings())
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setUseTls($this->useTls)
                ->setTlsSelfSignedAllowed(true)
                ->setConnectTimeout(10);

            $clientId = 'laravel-general-' . time() . '-' . mt_rand(1000, 9999);
            $mqtt = new MqttClient($this->host, $this->port, $clientId);
            $mqtt->connect($settings, true);
            $mqtt->publish($topic, $message, $qos, $retain);
            $mqtt->disconnect();

            Log::info("✅ Published to topic: {$topic}", ['payload' => $message]);
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Failed to publish to topic: {$topic}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get broker connection info (for debugging)
     * 
     * @return array
     */
    public function getBrokerInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'use_tls' => $this->useTls,
            'broker_url' => ($this->useTls ? 'mqtts://' : 'mqtt://') . $this->host .  ':' . $this->port
        ];
    }
}