<?php

return [
    'cloud_url' => env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com'),
    'device_json_path' => env('VIBECODEPC_DEVICE_JSON', storage_path('device.json')),

    'code_server' => [
        'port' => env('CODE_SERVER_PORT') ? (int) env('CODE_SERVER_PORT') : null,
        'config_path' => env('CODE_SERVER_CONFIG', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.config/code-server/config.yaml'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
    ],

    'tunnel' => [
        'config_path' => env('CLOUDFLARED_CONFIG', '/etc/cloudflared/config.yml'),
    ],

    'projects' => [
        'base_path' => env('VIBECODEPC_PROJECTS_PATH', storage_path('app/projects')),
        'max_projects' => (int) env('VIBECODEPC_MAX_PROJECTS', 10),
    ],

    'docker' => [
        'socket' => env('DOCKER_HOST', 'unix:///var/run/docker.sock'),
    ],
];
