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

    it('parses bashrc with multiple export statements', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"key1\"\nexport CLAUDE_API_KEY=\"key2\"\nexport OPENAI_API_KEY=\"key3\"\nexport ANTHROPIC_API_KEY=\"key4\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toHaveCount(4)
            ->and($vars['GEMINI_API_KEY'])->toBe('key1')
            ->and($vars['CLAUDE_API_KEY'])->toBe('key2')
            ->and($vars['OPENAI_API_KEY'])->toBe('key3')
            ->and($vars['ANTHROPIC_API_KEY'])->toBe('key4');
    });

    it('parses PATH modifications with single path', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport PATH=\"/custom/bin:\$PATH\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['_extra_path'])->toBe('/custom/bin');
    });

    it('parses PATH modifications with complex paths', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport PATH=\"/usr/local/bin:/opt/bin:/home/user/.local/bin:\$PATH\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['_extra_path'])->toBe('/usr/local/bin:/opt/bin:/home/user/.local/bin');
    });

    it('handles encrypted values correctly', function () {
        // Set encrypted values first
        $this->service->setEnvVars([
            'GEMINI_API_KEY' => 'secret-gemini-key',
            'CLAUDE_API_KEY' => 'secret-claude-key',
        ]);

        // Now read them back
        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('secret-gemini-key')
            ->and($vars['CLAUDE_API_KEY'])->toBe('secret-claude-key');
    });

    it('handles empty values in exports', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport EMPTY_VAR=\"\"\nexport NON_EMPTY=\"value\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['EMPTY_VAR'])->toBe('')
            ->and($vars['NON_EMPTY'])->toBe('value');
    });

    it('handles values with special characters', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport SPECIAL_VAR=\"value with spaces and symbols !@#\$%\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['SPECIAL_VAR'])->toBe('value with spaces and symbols !@#$%');
    });

    it('parses section at beginning of file', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport VAR1=\"value1\"\n# === END VibeCodePC AI Tools ===\n\n# Other stuff\nexport OTHER=\"other\"\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['VAR1'])->toBe('value1')
            ->and($vars)->toHaveCount(1);
    });

    it('parses section at end of file', function () {
        $content = "# Other stuff\nexport OTHER=\"other\"\n\n# === VibeCodePC AI Tools ===\nexport VAR1=\"value1\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['VAR1'])->toBe('value1');
    });

    it('ignores variables outside managed section', function () {
        $content = "export OUTSIDE=\"outside-value\"\n\n# === VibeCodePC AI Tools ===\nexport INSIDE=\"inside-value\"\n# === END VibeCodePC AI Tools ===\n\nexport ALSO_OUTSIDE=\"also-outside\"\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['INSIDE'])->toBe('inside-value')
            ->and(isset($vars['OUTSIDE']))->toBeFalse()
            ->and(isset($vars['ALSO_OUTSIDE']))->toBeFalse();
    });

    it('handles variables with numbers in names', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport VAR_123=\"numbered\"\nexport API_KEY_V2=\"version2\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['VAR_123'])->toBe('numbered')
            ->and($vars['API_KEY_V2'])->toBe('version2');
    });

    it('skips lowercase variable names', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport UPPERCASE=\"valid\"\nexport lowercase=\"invalid\"\nexport MixedCase=\"also-invalid\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['UPPERCASE'])->toBe('valid')
            ->and(isset($vars['lowercase']))->toBeFalse()
            ->and(isset($vars['MixedCase']))->toBeFalse();
    });

    it('handles very long values', function () {
        $longValue = str_repeat('a', 10000);
        $content = "# === VibeCodePC AI Tools ===\nexport LONG_VAR=\"{$longValue}\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['LONG_VAR'])->toBe($longValue);
    });

    it('handles unicode values', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport UNICODE=\"Hello 世界 🌍 café\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['UNICODE'])->toBe('Hello 世界 🌍 café');
    });

    it('handles extra whitespace around section markers', function () {
        $content = "# === VibeCodePC AI Tools === \nexport VAR1=\"value1\"\n # === END VibeCodePC AI Tools === \n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Implementation still finds markers even with trailing whitespace
        expect($vars['VAR1'])->toBe('value1');
    });

    it('ignores PATH line that does not end with :\$PATH', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport PATH=\"/wrong/path\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // PATH variable should not be parsed unless it ends with :$PATH
        expect($vars)->not->toHaveKey('_extra_path')
            ->and($vars)->not->toHaveKey('PATH');
    });

    it('handles only start marker present', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport VAR1=\"value1\"\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toBe([]);
    });

    it('handles only end marker present', function () {
        $content = "export VAR1=\"value1\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toBe([]);
    });

    it('handles reversed section markers', function () {
        $content = "# === END VibeCodePC AI Tools ===\nexport VAR1=\"value1\"\n# === VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toBe([]);
    });

    it('ignores commented export lines', function () {
        $content = "# === VibeCodePC AI Tools ===\n# export COMMENTED=\"ignored\"\nexport ACTIVE=\"active\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['ACTIVE'])->toBe('active')
            ->and(isset($vars['COMMENTED']))->toBeFalse();
    });

    it('handles multiple PATH lines (uses first one)', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport PATH=\"/first/path:\$PATH\"\nexport PATH=\"/second/path:\$PATH\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Should use the first PATH line found
        expect($vars['_extra_path'])->toBe('/first/path');
    });

    it('handles bashrc with multiple sections (uses first complete)', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport VAR1=\"first\"\n# === END VibeCodePC AI Tools ===\n\n# === VibeCodePC AI Tools ===\nexport VAR2=\"second\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Should read from first complete section found
        expect($vars['VAR1'])->toBe('first');
    });

    it('handles values with equals signs', function () {
        $content = "# === VibeCodePC AI Tools ===\nexport BASE64=\"abc123=\"\nexport URL=\"https://example.com?key=value&foo=bar\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['BASE64'])->toBe('abc123=')
            ->and($vars['URL'])->toBe('https://example.com?key=value&foo=bar');
    });

    it('handles empty managed section', function () {
        $content = "# === VibeCodePC AI Tools ===\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars)->toBe([]);
    });
});

describe('setEnvVars', function () {
    it('creates the bashrc file with the managed section when it does not exist', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'abc123']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)
            ->toContain('# === VibeCodePC AI Tools ===')
            ->toContain('export GEMINI_API_KEY="')
            ->toContain('# === END VibeCodePC AI Tools ===');

        // Verify the value is encrypted by checking for ENC: prefix
        expect($content)->toContain('ENC:');

        // Verify round-trip decryption works
        $vars = $this->service->getEnvVars();
        expect($vars['GEMINI_API_KEY'])->toBe('abc123');
    });

    it('replaces an existing managed section', function () {
        $initial = "line1\n# === VibeCodePC AI Tools ===\nexport OLD_VAR=\"old\"\n# === END VibeCodePC AI Tools ===\nline2\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        // Use a non-sensitive key so it's not encrypted
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

    it('encrypts sensitive keys like API keys', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'secret-key-123']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Value should be encrypted with ENC: prefix
        expect($content)->toContain('ENC:');

        // Raw key should not appear in file
        expect($content)->not->toContain('secret-key-123');
    });

    it('does not encrypt non-sensitive keys', function () {
        $this->service->setEnvVars(['MY_CUSTOM_VAR' => 'plain-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Non-sensitive value should appear in plain text
        expect($content)->toContain('export MY_CUSTOM_VAR="plain-value"');
        expect($content)->not->toContain('ENC:');
    });

    it('decrypts encrypted values when reading', function () {
        $this->service->setEnvVars(['OPENAI_API_KEY' => 'sk-openai-secret']);

        $vars = $this->service->getEnvVars();

        expect($vars['OPENAI_API_KEY'])->toBe('sk-openai-secret');
    });

    it('gracefully handles corrupted encrypted values', function () {
        // Create bashrc with invalid encrypted data
        $content = "# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"ENC:invalid-encrypted-data\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Should return the encrypted string as-is when decryption fails
        expect($vars['GEMINI_API_KEY'])->toBe('ENC:invalid-encrypted-data');
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
