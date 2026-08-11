<?php

declare(strict_types=1);

namespace App\Services\Install;

class RequirementsChecker
{
    private const MIN_PHP = '8.3.0';

    private const EXTENSIONS = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'ctype', 'json', 'curl', 'fileinfo'];

    private const WRITABLE = ['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'];

    /** @return array{php:array,extensions:array<string,bool>,writable:array<string,bool>} */
    public function results(): array
    {
        return [
            'php' => [
                'required' => self::MIN_PHP,
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            ],
            'extensions' => collect(self::EXTENSIONS)
                ->mapWithKeys(fn (string $e) => [$e => extension_loaded($e)])->all(),
            'writable' => collect(self::WRITABLE)
                ->mapWithKeys(fn (string $p) => [$p => is_writable(base_path($p))])->all(),
        ];
    }

    public function satisfied(): bool
    {
        $r = $this->results();

        return $r['php']['passed']
            && ! in_array(false, $r['extensions'], true)
            && ! in_array(false, $r['writable'], true);
    }
}
