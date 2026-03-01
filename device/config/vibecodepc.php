<?php

return [
    'cloud_url' => env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com'),
    'cloud_browser_url' => env('VIBECODEPC_CLOUD_BROWSER_URL', env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com')),
    'cloud_domain' => parse_url(env('VIBECODEPC_CLOUD_BROWSER_URL', env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com')), PHP_URL_HOST),
    'device_json_path' => env('VIBECODEPC_DEVICE_JSON', storage_path('device.json')),

    'code_server' => [
        'port' => env('CODE_SERVER_PORT') ? (int) env('CODE_SERVER_PORT') : null,
        'config_path' => env('CODE_SERVER_CONFIG', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.config/code-server/config.yaml'),
        'settings_path' => env('CODE_SERVER_SETTINGS', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.local/share/code-server/User/settings.json'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
    ],

    'tunnel' => [
        'config_path' => env('CLOUDFLARED_CONFIG', storage_path('app/cloudflared/config.yml')),
        'device_app_port' => (int) env('DEVICE_APP_PORT', 8081),
        'token_file_path' => env('TUNNEL_TOKEN_PATH', storage_path('tunnel/token')),
        'origin_host' => env('TUNNEL_ORIGIN_HOST'),
    ],

    'projects' => [
        'base_path' => env('VIBECODEPC_PROJECTS_PATH', storage_path('app/projects')),
        'max_projects' => (int) env('VIBECODEPC_MAX_PROJECTS', 10),
    ],

    'docker' => [
        'socket' => env('DOCKER_HOST', 'unix:///var/run/docker.sock'),
        'host_projects_path' => env('DOCKER_HOST_PROJECTS_PATH'),
    ],
];
