<?php

return [
    'host' => env('MQTT_HOST', 'broker.hivemq.com'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'use_tls' => env('MQTT_USE_TLS', false),
    'websocket_port' => env('MQTT_WEBSOCKET_PORT', 8884),
    'websocket_path' => env('MQTT_WEBSOCKET_PATH', '/mqtt'),
    'protocol' => env('MQTT_PROTOCOL', 'wss'),
];
