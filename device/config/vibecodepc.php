<?php

return [
    'cloud_url' => env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com'),
    'device_json_path' => env('VIBECODEPC_DEVICE_JSON', '/etc/vibecodepc/device.json'),

    'code_server' => [
        'port' => (int) env('CODE_SERVER_PORT', 8443),
        'config_path' => env('CODE_SERVER_CONFIG', '/home/vibecodepc/.config/code-server/config.yaml'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
    ],

    'tunnel' => [
        'config_path' => env('CLOUDFLARED_CONFIG', '/etc/cloudflared/config.yml'),
    ],
];
