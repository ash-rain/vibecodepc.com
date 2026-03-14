<?php

declare(strict_types=1);

use App\Services\AiToolConfigService;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/ai-tool-config-test-'.uniqid();
    mkdir($this->tmpDir, 0755, true);
    $_SERVER['HOME'] = $this->tmpDir;
    $this->service = new AiToolConfigService;
});

afterEach(function () {
    // Clean up tmp files
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->tmpDir);
});

describe('getEnvVars', function () {
    it('returns empty array when bashrc does not exist', function () {
        expect($this->service->getEnvVars())->toBe([]);
    });

    it('returns empty array when no managed section exists', function () {
        file_put_contents($this->tmpDir.'/.bashrc', 'export SOME_VAR="existing"'."\n");

        expect($this->service->getEnvVars())->toBe([]);
    });

    it('reads env vars from the managed section', function () {
        $content = "# unmanaged stuff\n\n# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"my-gemini-key\"\nexport CLAUDE_API_KEY=\"sk-ant-api-123\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toMatchArray([
            'GEMINI_API_KEY' => 'my-gemini-key',
            'CLAUDE_API_KEY' => 'sk-ant-api-123',
        ]);
    });

    it('reads the extra path value from a PATH line', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport PATH=\"/autodev/bin:/root/.opencode/bin:\$PATH\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['_extra_path'])->toBe('/autodev/bin:/root/.opencode/bin');
    });
});

describe('setEnvVars', function () {
    it('creates the bashrc file with the managed section when it does not exist', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'abc123']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)
            ->toContain('# === VibeCodePC AI Tools ===')
            ->toContain('export GEMINI_API_KEY="abc123"')
            ->toContain('# === END VibeCodePC AI Tools ===');
    });

    it('replaces an existing managed section', function () {
        $initial = "line1\n# === VibeCodePC AI Tools ===\nexport OLD_VAR=\"old\"\n# === END VibeCodePC AI Tools ===\nline2\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        $this->service->setEnvVars(['NEW_VAR' => 'new-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)
            ->toContain('export NEW_VAR="new-value"')
            ->not->toContain('OLD_VAR')
            ->toContain('line1')
            ->toContain('line2');
    });

    it('writes the PATH export line for _extra_path', function () {
        $this->service->setEnvVars(['_extra_path' => '/autodev/bin']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('export PATH="/autodev/bin:$PATH"');
    });

    it('omits keys with empty string values', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'key1', 'CLAUDE_API_KEY' => '']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)
            ->toContain('GEMINI_API_KEY')
            ->not->toContain('CLAUDE_API_KEY');
    });

    it('round-trips env vars correctly', function () {
        $this->service->setEnvVars([
            'GEMINI_API_KEY' => 'gemini-key',
            'CLAUDE_API_KEY' => 'claude-key',
            '_extra_path' => '/autodev/bin',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('gemini-key')
            ->and($vars['CLAUDE_API_KEY'])->toBe('claude-key')
            ->and($vars['_extra_path'])->toBe('/autodev/bin');
    });
});

describe('getOpencodeConfig', function () {
    it('returns default config when file does not exist', function () {
        $config = $this->service->getOpencodeConfig();

        expect($config)->toMatchArray([
            '$schema' => 'https://opencode.ai/config.json',
            'permission' => ['*' => 'allow'],
        ]);
    });

    it('reads config from file', function () {
        $dir = $this->tmpDir.'/.config/opencode';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/opencode.json', json_encode([
            'permission' => ['*' => 'deny'],
            'provider' => ['ollama' => ['name' => 'Ollama']],
        ]));

        $config = $this->service->getOpencodeConfig();

        expect($config['permission'])->toBe(['*' => 'deny'])
            ->and($config['provider'])->toHaveKey('ollama');
    });
});

describe('setOpencodeConfig', function () {
    it('creates directory and writes JSON file', function () {
        $this->service->setOpencodeConfig(['permission' => ['*' => 'allow']]);

        $path = $this->tmpDir.'/.config/opencode/opencode.json';

        expect(file_exists($path))->toBeTrue();

        $data = json_decode(file_get_contents($path), true);

        expect($data['permission'])->toBe(['*' => 'allow']);
    });
});

describe('getOpencodeAuth', function () {
    it('returns empty array when file does not exist', function () {
        expect($this->service->getOpencodeAuth())->toBe([]);
    });

    it('reads auth from file', function () {
        $dir = $this->tmpDir.'/.local/share/opencode';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/auth.json', json_encode([
            'anthropic' => ['type' => 'api', 'key' => 'sk-ant-test'],
        ]));

        $auth = $this->service->getOpencodeAuth();

        expect($auth['anthropic'])->toMatchArray(['type' => 'api', 'key' => 'sk-ant-test']);
    });
});

describe('setOpencodeAuth', function () {
    it('creates directory and writes auth JSON file', function () {
        $this->service->setOpencodeAuth(['anthropic' => ['type' => 'api', 'key' => 'sk-ant-test']]);

        $path = $this->tmpDir.'/.local/share/opencode/auth.json';

        expect(file_exists($path))->toBeTrue();

        $data = json_decode(file_get_contents($path), true);

        expect($data['anthropic']['key'])->toBe('sk-ant-test');
    });
});
