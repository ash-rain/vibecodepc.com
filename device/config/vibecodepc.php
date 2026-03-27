<?php

return [
    'cloud_url' => env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com'),
    'cloud_browser_url' => env('VIBECODEPC_CLOUD_BROWSER_URL', env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com')),
    'cloud_domain' => parse_url(env('VIBECODEPC_CLOUD_BROWSER_URL', env('VIBECODEPC_CLOUD_URL', 'https://vibecodepc.com')), PHP_URL_HOST),
    'device_json_path' => env('VIBECODEPC_DEVICE_JSON', storage_path('device.json')),

    'pairing' => [
        'required' => env('VIBECODEPC_PAIRING_REQUIRED', false),
    ],

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

    'container' => [
        'timeout' => [
            'start' => (int) env('CONTAINER_TIMEOUT_START', 120),
            'stop' => (int) env('CONTAINER_TIMEOUT_STOP', 60),
            'exec' => (int) env('CONTAINER_TIMEOUT_EXEC', 30),
            'remove' => (int) env('CONTAINER_TIMEOUT_REMOVE', 60),
        ],
        'logs' => [
            'default_lines' => (int) env('CONTAINER_LOGS_DEFAULT_LINES', 50),
        ],
        'defaults' => [
            'cpu' => env('CONTAINER_DEFAULT_CPU', '0%'),
            'memory' => env('CONTAINER_DEFAULT_MEMORY', '0B'),
        ],
    ],

    'backup' => [
        'tables' => [
            'ai_providers',
            'tunnel_configs',
            'github_credentials',
            'device_state',
            'wizard_progress',
            'cloud_credentials',
        ],
    ],

    'http_client' => [
        'timeout' => [
            'default' => (int) env('HTTP_CLIENT_TIMEOUT_DEFAULT', 10),
            'authenticated' => (int) env('HTTP_CLIENT_TIMEOUT_AUTHENTICATED', 30),
        ],
    ],

    'config_files' => [
        'boost' => [
            'path' => env('VIBECODEPC_BOOST_JSON_PATH', base_path('boost.json')),
            'label' => 'Boost Configuration',
            'description' => 'Controls AI agents and skills for this project.',
            'editable' => true,
            'scope' => 'global',
        ],
        'opencode_global' => [
            'path' => env('OPENCODE_CONFIG_PATH', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.config/opencode/opencode.json'),
            'label' => 'OpenCode Global',
            'description' => 'Global OpenCode CLI and VS Code extension settings.',
            'editable' => true,
            'scope' => 'global',
        ],
        'opencode_project' => [
            'path_template' => '{project_path}/opencode.json',
            'label' => 'OpenCode Project',
            'description' => 'Project-level OpenCode configuration in workspace root.',
            'editable' => true,
            'scope' => 'project',
            'parent_key' => 'opencode_global',
        ],
        'claude_global' => [
            'path' => env('CLAUDE_CONFIG_PATH', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.claude/settings.json'),
            'label' => 'Claude Code Global',
            'description' => 'Global Claude Code settings and preferences.',
            'editable' => true,
            'scope' => 'global',
        ],
        'claude_project' => [
            'path_template' => '{project_path}/.claude/settings.json',
            'label' => 'Claude Code Project',
            'description' => 'Project-level Claude Code configuration in .claude directory.',
            'editable' => true,
            'scope' => 'project',
            'parent_key' => 'claude_global',
        ],
        'copilot_instructions' => [
            'path' => base_path('.github/copilot-instructions.md'),
            'label' => 'GitHub Copilot Instructions',
            'description' => 'Custom instructions for GitHub Copilot in this project.',
            'editable' => true,
            'scope' => 'global',
        ],
        'barx' => [
            'path' => env('VIBECODEPC_BARX_PATH', ($_SERVER['HOME'] ?? '/home/vibecodepc').'/.barx'),
            'label' => 'Barx Environment Config',
            'description' => 'Environment variables and PATH configuration for Barx AI tools.',
            'editable' => true,
            'scope' => 'global',
        ],
    ],

    'config_editor' => [
        'backup_retention_days' => (int) env('CONFIG_EDITOR_BACKUP_RETENTION_DAYS', 30),
        'max_file_size_kb' => (int) env('CONFIG_EDITOR_MAX_FILE_SIZE_KB', 64),
        'backup_directory' => env('CONFIG_EDITOR_BACKUP_DIR', storage_path('app/backups/config')),
    ],
];
