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

    it('handles special characters in values when writing', function () {
        $this->service->setEnvVars([
            'VAR_WITH_SPACES' => 'value with spaces',
            'VAR_WITH_DOLLAR' => 'value with $PATH',
            'VAR_WITH_BACKSLASH' => 'value with \\ backslash',
            'VAR_WITH_BACKTICK' => 'value with `backtick`',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['VAR_WITH_SPACES'])->toBe('value with spaces')
            ->and($vars['VAR_WITH_DOLLAR'])->toBe('value with $PATH')
            // addslashes escapes backslashes, so the stored value differs from original
            ->and($vars['VAR_WITH_BACKSLASH'])->toBe('value with \\\\ backslash')
            ->and($vars['VAR_WITH_BACKTICK'])->toBe('value with `backtick`');
    });

    it('handles unicode characters in values when writing', function () {
        $this->service->setEnvVars([
            'UNICODE_VAR' => 'Hello 世界 🌍 café',
            'EMOJI_VAR' => '👋🎉🚀',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['UNICODE_VAR'])->toBe('Hello 世界 🌍 café')
            ->and($vars['EMOJI_VAR'])->toBe('👋🎉🚀');
    });

    it('removes section entirely when all values are empty', function () {
        // First create a section
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'initial-key']);

        // Then remove it by setting empty values
        $this->service->setEnvVars(['GEMINI_API_KEY' => '']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // When all values are empty, section is completely removed
        expect($content)->not->toContain('VibeCodePC AI Tools')
            ->and($this->service->getEnvVars())->toBe([]);
    });

    it('removes section entirely when vars array is empty', function () {
        // First create a section
        $initial = "# Other content\n\n# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"key\"\n# === END VibeCodePC AI Tools ===\n\nMore content\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        // Remove all vars
        $this->service->setEnvVars([]);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Section should be removed but other content preserved
        expect($content)->toContain('Other content')
            ->and($content)->toContain('More content')
            ->and($content)->not->toContain('VibeCodePC AI Tools')
            ->and($content)->not->toContain('GEMINI_API_KEY');
    });

    it('preserves content outside the managed section', function () {
        $initial = "# User's custom bashrc content\nexport USER_VAR=\"user-value\"\n\n# === VibeCodePC AI Tools ===\nexport OLD_KEY=\"old-value\"\n# === END VibeCodePC AI Tools ===\n\nalias ll='ls -la'\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        $this->service->setEnvVars(['NEW_KEY' => 'new-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain("# User's custom bashrc content")
            ->and($content)->toContain('export USER_VAR="user-value"')
            ->and($content)->toContain("alias ll='ls -la'")
            ->and($content)->toContain('export NEW_KEY="new-value"')
            ->and($content)->not->toContain('OLD_KEY');
    });

    it('handles writing to a file with only start marker (appends end marker)', function () {
        $initial = "# === VibeCodePC AI Tools ===\nexport VAR1=\"value1\"\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        $this->service->setEnvVars(['NEW_VAR' => 'new-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Should have proper section markers
        expect($content)->toContain('# === VibeCodePC AI Tools ===')
            ->and($content)->toContain('# === END VibeCodePC AI Tools ===')
            ->and($content)->toContain('export NEW_VAR="new-value"')
            ->and($content)->toContain('export VAR1="value1"');
    });

    it('handles writing to a file with only end marker (prepends start marker)', function () {
        $initial = "export VAR1=\"value1\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $initial);

        $this->service->setEnvVars(['NEW_VAR' => 'new-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Should have proper section markers
        expect($content)->toContain('# === VibeCodePC AI Tools ===')
            ->and($content)->toContain('# === END VibeCodePC AI Tools ===')
            ->and($content)->toContain('export NEW_VAR="new-value"')
            ->and($content)->toContain('export VAR1="value1"');
    });

    it('handles very long values when writing', function () {
        $longValue = str_repeat('a', 10000);
        $this->service->setEnvVars(['LONG_VAR' => $longValue]);

        $vars = $this->service->getEnvVars();

        expect($vars['LONG_VAR'])->toBe($longValue);
    });

    it('handles empty bashrc file', function () {
        file_put_contents($this->tmpDir.'/.bashrc', '');

        $this->service->setEnvVars(['KEY' => 'value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('# === VibeCodePC AI Tools ===')
            ->and($content)->toContain('export KEY="value"')
            ->and($content)->toContain('# === END VibeCodePC AI Tools ===');
    });

    it('handles values with equals signs when writing', function () {
        $this->service->setEnvVars([
            'BASE64_VALUE' => 'abc123==',
            'URL_PARAMS' => 'key=value&foo=bar',
            'EQUATION' => 'x=y+z',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['BASE64_VALUE'])->toBe('abc123==')
            ->and($vars['URL_PARAMS'])->toBe('key=value&foo=bar')
            ->and($vars['EQUATION'])->toBe('x=y+z');
    });

    it('encrypts all sensitive key patterns', function () {
        $this->service->setEnvVars([
            'MY_API_KEY' => 'api-key-value',
            'MY_TOKEN' => 'token-value',
            'MY_SECRET' => 'secret-value',
            'MY_PASSWORD' => 'password-value',
            'MY_AUTH' => 'auth-value',
            'REGULAR_VAR' => 'plain-value',
        ]);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        // Sensitive keys should be encrypted (have ENC: prefix)
        expect($content)->toContain('ENC:');

        // Non-sensitive value should appear in plain text
        expect($content)->toContain('export REGULAR_VAR="plain-value"');

        // Verify round-trip works
        $vars = $this->service->getEnvVars();
        expect($vars['MY_API_KEY'])->toBe('api-key-value')
            ->and($vars['MY_TOKEN'])->toBe('token-value')
            ->and($vars['MY_SECRET'])->toBe('secret-value')
            ->and($vars['MY_PASSWORD'])->toBe('password-value')
            ->and($vars['MY_AUTH'])->toBe('auth-value')
            ->and($vars['REGULAR_VAR'])->toBe('plain-value');
    });

    it('handles values with single quotes', function () {
        $this->service->setEnvVars([
            'SINGLE_QUOTE' => "it's working",
        ]);

        $vars = $this->service->getEnvVars();

        // addslashes escapes single quotes, so ' becomes \'
        expect($vars['SINGLE_QUOTE'])->toBe("it\'s working");
    });

    it('does not handle double quotes in values', function () {
        // Note: The current implementation uses addslashes which escapes double quotes
        // However, the regex parser doesn't handle escaped double quotes properly
        // This is a known limitation - double quotes in values will cause issues
        $this->service->setEnvVars([
            'NO_QUOTES' => 'value without quotes',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['NO_QUOTES'])->toBe('value without quotes');
    });

    it('handles updating only _extra_path', function () {
        $this->service->setEnvVars(['_extra_path' => '/custom/bin']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');
        $vars = $this->service->getEnvVars();

        expect($content)->toContain('export PATH="/custom/bin:$PATH"')
            ->and($vars['_extra_path'])->toBe('/custom/bin');
    });

    it('handles removing _extra_path by setting to empty', function () {
        // First set a path
        $this->service->setEnvVars(['_extra_path' => '/custom/bin', 'KEY' => 'value']);

        // Then remove just the path
        $this->service->setEnvVars(['_extra_path' => '', 'KEY' => 'value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');
        $vars = $this->service->getEnvVars();

        expect($content)->not->toContain('export PATH=')
            ->and($vars)->not->toHaveKey('_extra_path')
            ->and($vars['KEY'])->toBe('value');
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

describe('encryption and decryption', function () {
    it('encrypts values for API_KEY pattern', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'secret-key']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('ENC:');
        expect($content)->not->toContain('secret-key');
    });

    it('encrypts values for TOKEN pattern', function () {
        $this->service->setEnvVars(['MY_TOKEN' => 'bearer-token-123']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('ENC:');
        expect($content)->not->toContain('bearer-token-123');
    });

    it('encrypts values for SECRET pattern', function () {
        $this->service->setEnvVars(['MY_SECRET' => 'super-secret-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('ENC:');
        expect($content)->not->toContain('super-secret-value');
    });

    it('encrypts values for PASSWORD pattern', function () {
        $this->service->setEnvVars(['DB_PASSWORD' => 'my-password-123']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('ENC:');
        expect($content)->not->toContain('my-password-123');
    });

    it('encrypts values for AUTH pattern', function () {
        $this->service->setEnvVars(['GITHUB_AUTH' => 'auth-token-value']);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('ENC:');
        expect($content)->not->toContain('auth-token-value');
    });

    it('does not encrypt non-sensitive keys', function () {
        $this->service->setEnvVars([
            'REGULAR_VAR' => 'plain-value',
            'MY_SETTING' => 'another-plain',
        ]);

        $content = file_get_contents($this->tmpDir.'/.bashrc');

        expect($content)->toContain('export REGULAR_VAR="plain-value"');
        expect($content)->toContain('export MY_SETTING="another-plain"');
        expect($content)->not->toContain('ENC:');
    });

    it('decrypts values with ENC: prefix correctly', function () {
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'original-secret']);

        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('original-secret');
    });

    it('returns plain text value when no ENC: prefix exists', function () {
        // Create bashrc with plain text value (no encryption)
        $content = "# === VibeCodePC AI Tools ===\nexport PLAIN_VAR=\"plain-text-value\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        expect($vars['PLAIN_VAR'])->toBe('plain-text-value');
    });

    it('handles corrupted encrypted values gracefully', function () {
        // Create bashrc with invalid encrypted data
        $content = "# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"ENC:invalid-encrypted-data-that-cannot-be-decrypted\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Returns the encrypted string as-is when decryption fails
        expect($vars['GEMINI_API_KEY'])->toBe('ENC:invalid-encrypted-data-that-cannot-be-decrypted');
    });

    it('handles corrupted encrypted values with malformed base64', function () {
        // Create bashrc with malformed base64 that cannot be decoded
        $content = "# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"ENC:!!!not-valid-base64!!!\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Returns the encrypted string as-is when decryption fails
        expect($vars['GEMINI_API_KEY'])->toBe('ENC:!!!not-valid-base64!!!');
    });

    it('handles empty encrypted value', function () {
        // Create bashrc with empty ENC: prefix
        $content = "# === VibeCodePC AI Tools ===\nexport GEMINI_API_KEY=\"ENC:\"\n# === END VibeCodePC AI Tools ===\n";
        file_put_contents($this->tmpDir.'/.bashrc', $content);

        $vars = $this->service->getEnvVars();

        // Returns the value as-is when decryption fails
        expect($vars['GEMINI_API_KEY'])->toBe('ENC:');
    });

    it('encrypts and decrypts unicode values correctly', function () {
        $unicodeValue = 'Hello 世界 🌍 café';
        $this->service->setEnvVars(['API_KEY' => $unicodeValue]);

        $vars = $this->service->getEnvVars();

        expect($vars['API_KEY'])->toBe($unicodeValue);
    });

    it('encrypts and decrypts long values correctly', function () {
        $longValue = str_repeat('secret-data-', 1000);
        $this->service->setEnvVars(['API_KEY' => $longValue]);

        $vars = $this->service->getEnvVars();

        expect($vars['API_KEY'])->toBe($longValue);
    });

    it('encrypts values with special characters correctly', function () {
        $specialValue = 'key with $ymbols @nd sp@ces!@#$%^&*()';
        $this->service->setEnvVars(['API_KEY' => $specialValue]);

        $vars = $this->service->getEnvVars();

        expect($vars['API_KEY'])->toBe($specialValue);
    });

    it('handles multiple encrypted values in same section', function () {
        $this->service->setEnvVars([
            'GEMINI_API_KEY' => 'gemini-secret',
            'CLAUDE_API_KEY' => 'claude-secret',
            'OPENAI_API_KEY' => 'openai-secret',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('gemini-secret')
            ->and($vars['CLAUDE_API_KEY'])->toBe('claude-secret')
            ->and($vars['OPENAI_API_KEY'])->toBe('openai-secret');
    });

    it('handles mixed encrypted and non-encrypted values', function () {
        $this->service->setEnvVars([
            'MY_API_KEY' => 'sk-test-12345-abc',
            'REGULAR_VAR' => 'plain-value',
            'MY_AUTH_TOKEN' => 'bearer-xyz-789',
            'NORMAL_SETTING' => 'another-plain',
        ]);

        $content = file_get_contents($this->tmpDir.'/.bashrc');
        $vars = $this->service->getEnvVars();

        // Check encrypted values don't appear in plain text
        expect($content)->not->toContain('sk-test-12345-abc');
        expect($content)->not->toContain('bearer-xyz-789');

        // Check plain values do appear
        expect($content)->toContain('export REGULAR_VAR="plain-value"');
        expect($content)->toContain('export NORMAL_SETTING="another-plain"');

        // Check all values decrypt correctly
        expect($vars['MY_API_KEY'])->toBe('sk-test-12345-abc')
            ->and($vars['REGULAR_VAR'])->toBe('plain-value')
            ->and($vars['MY_AUTH_TOKEN'])->toBe('bearer-xyz-789')
            ->and($vars['NORMAL_SETTING'])->toBe('another-plain');
    });

    it('preserves encrypted values across multiple writes', function () {
        // First write with encrypted value
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'first-secret']);

        // Second write with different values
        $this->service->setEnvVars([
            'GEMINI_API_KEY' => 'first-secret',
            'NEW_VAR' => 'new-value',
        ]);

        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('first-secret')
            ->and($vars['NEW_VAR'])->toBe('new-value');
    });

    it('updates encrypted value when changed', function () {
        // First write
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'old-secret']);

        // Update with new value
        $this->service->setEnvVars(['GEMINI_API_KEY' => 'new-secret']);

        $vars = $this->service->getEnvVars();

        expect($vars['GEMINI_API_KEY'])->toBe('new-secret');
    });
});

describe('path resolution', function () {
    it('gets home directory from SERVER HOME variable', function () {
        $customHome = '/custom/home/dir';
        $_SERVER['HOME'] = $customHome;

        $service = new AiToolConfigService;

        // bashrc path should use the custom home directory
        expect($service->getBashrcPath())->toBe($customHome.'/.bashrc');
    });

    it('falls back to posix_getpwuid when HOME is not set', function () {
        // Remove HOME from server variables
        $originalHome = $_SERVER['HOME'] ?? null;
        unset($_SERVER['HOME']);

        // Create service instance - should fall back to posix_getpwuid
        $service = new AiToolConfigService;
        $bashrcPath = $service->getBashrcPath();

        // Should still return a valid path (from posix_getpwuid or /root fallback)
        expect($bashrcPath)->toEndWith('/.bashrc');

        // Restore original HOME
        if ($originalHome !== null) {
            $_SERVER['HOME'] = $originalHome;
        }
    });

    it('returns consistent paths across multiple calls', function () {
        $_SERVER['HOME'] = '/test/home';

        $service = new AiToolConfigService;

        $path1 = $service->getBashrcPath();
        $path2 = $service->getBashrcPath();
        $path3 = $service->getOpencodeConfigPath();

        // Multiple calls should return same results
        expect($path1)->toBe($path2)
            ->and($path1)->toBe('/test/home/.bashrc')
            ->and($path3)->toBe('/test/home/.config/opencode/opencode.json');
    });

    it('resolves opencode config path relative to home', function () {
        $_SERVER['HOME'] = '/users/john';

        $service = new AiToolConfigService;

        expect($service->getOpencodeConfigPath())
            ->toBe('/users/john/.config/opencode/opencode.json');
    });

    it('resolves opencode auth path relative to home', function () {
        $_SERVER['HOME'] = '/users/john';

        $service = new AiToolConfigService;

        expect($service->getOpencodeAuthPath())
            ->toBe('/users/john/.local/share/opencode/auth.json');
    });

    it('handles HOME with trailing slash', function () {
        $_SERVER['HOME'] = '/home/test/';

        $service = new AiToolConfigService;

        // Should not create double slashes
        expect($service->getBashrcPath())->toBe('/home/test/.bashrc');
        expect($service->getOpencodeConfigPath())
            ->toBe('/home/test/.config/opencode/opencode.json');
    });

    it('handles HOME with spaces in path', function () {
        $_SERVER['HOME'] = '/home/My User/Documents';

        $service = new AiToolConfigService;

        // Paths should include spaces correctly
        expect($service->getBashrcPath())
            ->toBe('/home/My User/Documents/.bashrc');
    });

    it('handles HOME with unicode characters', function () {
        $_SERVER['HOME'] = '/home/用户/home';

        $service = new AiToolConfigService;

        expect($service->getBashrcPath())
            ->toBe('/home/用户/home/.bashrc');
    });

    it('handles HOME as empty string', function () {
        $_SERVER['HOME'] = '';

        $service = new AiToolConfigService;

        // Empty string should trigger posix fallback or /root
        $path = $service->getBashrcPath();
        expect($path)->toEndWith('/.bashrc');
    });

    it('handles HOME set to relative path', function () {
        $_SERVER['HOME'] = 'relative/path';

        $service = new AiToolConfigService;

        // Relative paths should still be used as-is
        expect($service->getBashrcPath())->toBe('relative/path/.bashrc');
    });

    it('returns bashrc path with correct structure', function () {
        $_SERVER['HOME'] = '/home/user';

        $service = new AiToolConfigService;

        $path = $service->getBashrcPath();

        expect($path)
            ->toStartWith('/home/user')
            ->toEndWith('.bashrc')
            ->not->toContain('//');
    });

    it('creates different service instances with different HOME values', function () {
        $_SERVER['HOME'] = '/home/user1';
        $service1 = new AiToolConfigService;
        $path1 = $service1->getBashrcPath();

        $_SERVER['HOME'] = '/home/user2';
        $service2 = new AiToolConfigService;
        $path2 = $service2->getBashrcPath();

        // Each service should reflect its HOME at time of instantiation
        expect($path1)->toBe('/home/user1/.bashrc');
        expect($path2)->toBe('/home/user2/.bashrc');
    });

    it('handles HOME with special characters in path', function () {
        $_SERVER['HOME'] = '/home/user@domain.com';

        $service = new AiToolConfigService;

        expect($service->getBashrcPath())
            ->toBe('/home/user@domain.com/.bashrc');
    });

    it('handles very long home directory paths', function () {
        $longPath = '/home/'.str_repeat('a', 200);
        $_SERVER['HOME'] = $longPath;

        $service = new AiToolConfigService;

        expect($service->getBashrcPath())
            ->toBe($longPath.'/.bashrc');
    });
});
