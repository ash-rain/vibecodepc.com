<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class AiToolConfigService
{
    private const SECTION_START = '# === VibeCodePC AI Tools ===';

    private const SECTION_END = '# === END VibeCodePC AI Tools ===';

    private const ENCRYPTED_PREFIX = 'ENC:';

    private function getHomeDir(): string
    {
        if (isset($_SERVER['HOME']) && is_string($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }

        $info = posix_getpwuid(posix_geteuid());

        return is_array($info) ? $info['dir'] : '/root';
    }

    public function getBashrcPath(): string
    {
        return $this->getHomeDir().'/.bashrc';
    }

    public function getOpencodeConfigPath(): string
    {
        return $this->getHomeDir().'/.config/opencode/opencode.json';
    }

    public function getOpencodeAuthPath(): string
    {
        return $this->getHomeDir().'/.local/share/opencode/auth.json';
    }

    /**
     * Read the VibeCodePC-managed env vars from ~/.bashrc.
     *
     * Returns a map of KEY => value. The special key `_extra_path`
     * holds any PATH prefix configured through the UI.
     *
     * @return array<string, string>
     */
    public function getEnvVars(): array
    {
        $path = $this->getBashrcPath();

        if (! file_exists($path)) {
            return [];
        }

        $content = (string) file_get_contents($path);

        $start = strpos($content, self::SECTION_START);
        $end = strpos($content, self::SECTION_END);

        if ($start === false || $end === false) {
            return [];
        }

        $section = substr($content, $start + strlen(self::SECTION_START), $end - $start - strlen(self::SECTION_START));

        $vars = [];

        // Parse regular export statements (skip PATH — handled separately)
        preg_match_all('/^export (?!PATH=)([A-Z_][A-Z0-9_]*)="([^"]*)"$/m', $section, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $vars[$match[1]] = $this->decryptIfEncrypted($match[2]);
        }

        // Parse the PATH extra prefix line: export PATH="/some/path:$PATH"
        if (preg_match('/^export PATH="(.+?):\$PATH"$/m', $section, $pathMatch)) {
            $vars['_extra_path'] = $pathMatch[1];
        }

        return $vars;
    }

    /**
     * Determine if a key represents sensitive data that should be encrypted.
     *
     * @param  string  $key  The environment variable key
     */
    private function isSensitiveKey(string $key): bool
    {
        $sensitivePatterns = ['_API_KEY', '_TOKEN', '_SECRET', '_PASSWORD', '_AUTH'];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encrypt a value if it's sensitive.
     *
     * @param  string  $key  The environment variable key
     * @param  string  $value  The value to potentially encrypt
     */
    private function encryptIfSensitive(string $key, string $value): string
    {
        if (! $this->isSensitiveKey($key)) {
            return $value;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    /**
     * Decrypt a value if it's encrypted.
     *
     * @param  string  $value  The potentially encrypted value
     */
    private function decryptIfEncrypted(string $value): string
    {
        if (! str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        try {
            $encrypted = substr($value, strlen(self::ENCRYPTED_PREFIX));

            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Write the given env vars into the VibeCodePC-managed section of ~/.bashrc.
     *
     * Pass `_extra_path` in $vars to write a PATH prefix line.
     * Pass an empty string for a key to omit it from the section.
     *
     * @param  array<string, string>  $vars
     */
    public function setEnvVars(array $vars): void
    {
        $path = $this->getBashrcPath();
        $content = file_exists($path) ? (string) file_get_contents($path) : '';

        // Build the new section content
        $lines = [];

        if (! empty($vars['_extra_path'])) {
            $lines[] = 'export PATH="'.addslashes($vars['_extra_path']).':$PATH"';
        }

        unset($vars['_extra_path']);

        foreach ($vars as $key => $value) {
            if ($value !== '') {
                $encryptedValue = $this->encryptIfSensitive($key, $value);
                $lines[] = 'export '.$key.'="'.addslashes($encryptedValue).'"';
            }
        }

        // Find existing section
        $start = strpos($content, self::SECTION_START);
        $end = strpos($content, self::SECTION_END);

        // If there are no vars to write, remove the section entirely
        if (empty($lines)) {
            if ($start !== false && $end !== false) {
                $endOffset = $end + strlen(self::SECTION_END);
                $before = rtrim(substr($content, 0, $start));
                $after = ltrim(substr($content, $endOffset));
                $content = $before;
                if ($before !== '' && $after !== '') {
                    $content .= "\n\n".$after;
                } elseif ($after !== '') {
                    $content .= $after;
                }
            }
            file_put_contents($path, $content);

            return;
        }

        // Build section with markers
        array_unshift($lines, self::SECTION_START);
        $lines[] = self::SECTION_END;
        $newSection = implode("\n", $lines);

        if ($start !== false && $end !== false) {
            $endOffset = $end + strlen(self::SECTION_END);
            $content = substr($content, 0, $start).$newSection.substr($content, $endOffset);
        } else {
            $content = rtrim($content)."\n\n".$newSection."\n";
        }

        file_put_contents($path, $content);
    }

    /**
     * Read the opencode configuration JSON.
     *
     * Returns a default config structure if the file does not exist.
     *
     * @return array<string, mixed>
     */
    public function getOpencodeConfig(): array
    {
        $path = $this->getOpencodeConfigPath();

        if (! file_exists($path)) {
            return [
                '$schema' => 'https://opencode.ai/config.json',
                'permission' => ['*' => 'allow'],
            ];
        }

        $json = (string) file_get_contents($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Write the opencode configuration JSON.
     *
     * @param  array<string, mixed>  $config
     */
    public function setOpencodeConfig(array $config): void
    {
        $path = $this->getOpencodeConfigPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Read the opencode auth JSON.
     *
     * @return array<string, mixed>
     */
    public function getOpencodeAuth(): array
    {
        $path = $this->getOpencodeAuthPath();

        if (! file_exists($path)) {
            return [];
        }

        $json = (string) file_get_contents($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Write the opencode auth JSON.
     *
     * @param  array<string, mixed>  $auth
     */
    public function setOpencodeAuth(array $auth): void
    {
        $path = $this->getOpencodeAuthPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
