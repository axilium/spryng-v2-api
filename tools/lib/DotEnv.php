<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tools;

/**
 * Reads a .env file into the environment.
 *
 * Small on purpose. The package takes no dependencies, and this only has to
 * feed credentials to a development script, so vlucas/phpdotenv would be a
 * dependency bought for nothing.
 *
 * Values already present in the environment win, so an explicit
 * SPRYNG_API_KEY=... in front of the command still overrides the file.
 */
final class DotEnv
{
    /**
     * @return list<string> The names that were loaded from the file.
     */
    public static function load(string $file): array
    {
        if (!is_readable($file)) {
            return [];
        }

        $loaded = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = self::unquote(trim($value));

            if ($name === '' || $value === '' || getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $loaded[]    = $name;
        }

        return $loaded;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        // Strip a trailing comment on an unquoted value.
        return trim((string) preg_replace('/\s+#.*$/', '', $value));
    }
}
