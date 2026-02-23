<?php

return [
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    'device_app_port' => (int) env('DEVICE_APP_PORT', 8081),

    'tunnel_token' => env('CLOUD_TUNNEL_TOKEN'),
    'tunnel_token_file_path' => env('TUNNEL_TOKEN_PATH', '/tunnel/token'),
];
