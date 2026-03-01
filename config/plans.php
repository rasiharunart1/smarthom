<?php

return [
    'free' => [
        'name' => 'Free Tier',
        'max_devices' => 2,
        'max_widgets_per_device' => 5,
        'features' => [
            'realtime' => true,
            'history' => '24h',
            'api_access' => false,
        ],
    ],
    'pro' => [
        'name' => 'Professional',
        'max_devices' => 10,
        'max_widgets_per_device' => 20,
        'features' => [
            'realtime' => true,
            'history' => '30d',
            'api_access' => true,
        ],
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'max_devices' => 100, // Or null for unlimited
        'max_widgets_per_device' => 50,
        'features' => [
            'realtime' => true,
            'history' => '365d',
            'api_access' => true,
        ],
    ],
];
