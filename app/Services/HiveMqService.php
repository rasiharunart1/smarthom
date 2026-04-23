<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HiveMQ Cloud REST API Service
 *
 * Manages per-device MQTT credentials via HiveMQ Cloud Control Plane API.
 * Each device gets a unique username/password — compromise of one device
 * does NOT expose other devices' connections.
 *
 * Setup (in .env):
 *   HIVEMQ_API_URL=https://api.hivemq.cloud
 *   HIVEMQ_API_KEY=your-api-key-from-hivemq-dashboard
 *   HIVEMQ_CLUSTER_ID=your-cluster-id  (e.g. "9b7d755e")
 *
 * HiveMQ Cloud API docs:
 *   https://docs.hivemq.com/hivemq-cloud/api-documentation.html
 */
class HiveMqService
{
    protected string  $apiUrl;
    protected string  $apiKey;
    protected string  $clusterId;
    protected bool    $enabled;

    public function __construct()
    {
        $this->apiUrl    = rtrim(config('hivemq.api_url', 'https://api.hivemq.cloud'), '/');
        $this->apiKey    = config('hivemq.api_key', '');
        $this->clusterId = config('hivemq.cluster_id', '');
        $this->enabled   = !empty($this->apiKey) && !empty($this->clusterId);
    }

    /**
     * Check if HiveMQ API is configured (API key + cluster ID are set).
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Provision unique MQTT credentials for a device.
     *
     * Generates a random username and password, registers them with HiveMQ Cloud,
     * then persists them on the device record.
     *
     * @return array ['mqtt_username' => ..., 'mqtt_password' => ...]
     */
    public function provisionDevice(Device $device): array
    {
        $username = 'dev_' . strtolower($device->device_code) . '_' . Str::random(6);
        $password = Str::random(32);

        if ($this->enabled) {
            $success = $this->createCredential($username, $password);

            if (!$success) {
                Log::error('HiveMQ provisioning failed — falling back to shared credentials', [
                    'device_code' => $device->device_code,
                ]);
                // Return null to signal fallback to shared creds
                return ['mqtt_username' => null, 'mqtt_password' => null];
            }
        } else {
            Log::info('HiveMQ API not configured — skipping per-device credential provisioning. '
                . 'Set HIVEMQ_API_KEY and HIVEMQ_CLUSTER_ID in .env to enable.', [
                'device_code' => $device->device_code,
            ]);
        }

        // Persist to DB (encrypted via Laravel model casting if configured)
        $device->update([
            'mqtt_username' => $username,
            'mqtt_password' => $password,
        ]);

        Log::info('Device MQTT credentials provisioned', [
            'device_code'  => $device->device_code,
            'mqtt_username'=> $username,
            'via_api'      => $this->enabled,
        ]);

        return ['mqtt_username' => $username, 'mqtt_password' => $password];
    }

    /**
     * Revoke a device's MQTT credentials when the device is deleted or disapproved.
     */
    public function revokeDevice(Device $device): bool
    {
        if (!$this->enabled || !$device->mqtt_username) {
            return true; // Nothing to do
        }

        $success = $this->deleteCredential($device->mqtt_username);

        if ($success) {
            $device->update(['mqtt_username' => null, 'mqtt_password' => null]);
            Log::info('Device MQTT credentials revoked', ['device_code' => $device->device_code]);
        } else {
            Log::error('Failed to revoke HiveMQ credentials', ['device_code' => $device->device_code]);
        }

        return $success;
    }

    /**
     * Rotate credentials for a device (revoke old, issue new).
     */
    public function rotateDevice(Device $device): array
    {
        if ($device->mqtt_username && $this->enabled) {
            $this->deleteCredential($device->mqtt_username);
        }

        return $this->provisionDevice($device);
    }

    // ─────────────────────────────────────────────────────────────
    // HiveMQ Cloud REST API calls
    // ─────────────────────────────────────────────────────────────

    /**
     * Create MQTT credentials in HiveMQ Cloud via REST API.
     */
    protected function createCredential(string $username, string $password): bool
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->post("{$this->apiUrl}/api/v1/clusters/{$this->clusterId}/credentials", [
                    'credentials' => [[
                        'name'     => $username,
                        'password' => $password,
                        'roles'    => ['ROLE_DEVICE'],
                    ]],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('HiveMQ create credential failed', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'username' => $username,
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('HiveMQ API exception (create)', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete MQTT credentials from HiveMQ Cloud via REST API.
     */
    protected function deleteCredential(string $username): bool
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->delete("{$this->apiUrl}/api/v1/clusters/{$this->clusterId}/credentials/{$username}");

            return $response->successful() || $response->status() === 404;

        } catch (\Exception $e) {
            Log::error('HiveMQ API exception (delete)', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Return broker connection info.
     */
    public function getBrokerInfo(): array
    {
        return [
            'host'       => config('mqtt.host'),
            'port'       => (int) config('mqtt.port', 8883),
            'use_tls'    => (bool) config('mqtt.use_tls', true),
            'api_enabled'=> $this->enabled,
            'cluster_id' => $this->clusterId ?: '(not configured)',
        ];
    }
}
