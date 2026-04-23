<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HiveMQ Cloud Control Plane API
    |--------------------------------------------------------------------------
    |
    | Used by HiveMqService to provision per-device MQTT credentials.
    | Configure these in your .env file.
    |
    | HIVEMQ_API_URL    — Base URL (default: https://api.hivemq.cloud)
    | HIVEMQ_API_KEY    — API key from HiveMQ Cloud dashboard > API Access
    | HIVEMQ_CLUSTER_ID — Your cluster ID (e.g. the subdomain before .hivemq.cloud)
    |
    */

    'api_url'    => env('HIVEMQ_API_URL', 'https://api.hivemq.cloud'),
    'api_key'    => env('HIVEMQ_API_KEY', ''),
    'cluster_id' => env('HIVEMQ_CLUSTER_ID', ''),
];
