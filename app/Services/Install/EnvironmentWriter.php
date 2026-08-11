<?php

declare(strict_types=1);

namespace App\Services\Install;

use RuntimeException;

/**
 * Writes .env atomically (temp file + rename) from an allowlisted set of keys.
 * Generates APP_KEY directly in PHP — no shell command. Unknown keys are
 * ignored so untrusted input can never inject arbitrary env lines.
 */
class EnvironmentWriter
{
    private const ALLOWLIST = [
        'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL',
        'APP_LOCALE', 'APP_FALLBACK_LOCALE',
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
        'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    ];

    public function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * @param  array<string,string>  $values
     */
    public function write(array $values): void
    {
        $envPath = base_path('.env');
        $template = is_file($envPath)
            ? (string) file_get_contents($envPath)
            : (string) file_get_contents(base_path('.env.example'));

        // Ensure APP_KEY exists (generate if missing/empty in the template).
        if (! preg_match('/^APP_KEY=.+$/m', $template)) {
            $values['APP_KEY'] = $values['APP_KEY'] ?? $this->generateKey();
        } elseif (isset($values['APP_KEY'])) {
            // allow explicit rotation only if caller asked
        }

        $allow = array_merge(self::ALLOWLIST, ['APP_KEY']);

        foreach ($values as $key => $value) {
            if (! in_array($key, $allow, true)) {
                continue; // ignore anything not allowlisted
            }
            $template = $this->set($template, $key, (string) $value);
        }

        $this->atomicPut($envPath, $template);
    }

    private function set(string $env, string $key, string $value): string
    {
        $line = $key.'='.$this->quote($value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $env)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $env, 1);
        }

        return rtrim($env, "\n")."\n".$line."\n";
    }

    private function quote(string $value): string
    {
        // Quote when the value contains spaces or characters dotenv treats specially.
        if ($value === '' || preg_match('/[\s#"\'=]/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }

    private function atomicPut(string $path, string $contents): void
    {
        $tmp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException('Could not write temporary env file.');
        }
        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Could not finalize .env write.');
        }
    }
}
