<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tools;

/**
 * Minimal YAML emitter, enough for an OpenAPI document.
 *
 * ext-yaml is not installed in most PHP images and the package refuses to take
 * on dependencies, so this writes the subset we produce ourselves: maps, lists,
 * scalars and block scalars for multi-line strings.
 */
final class YamlWriter
{
    public static function dump(mixed $value, int $depth = 0): string
    {
        if (is_array($value) && $value !== []) {
            return array_is_list($value)
                ? self::dumpList($value, $depth)
                : self::dumpMap($value, $depth);
        }

        return str_repeat('  ', $depth) . self::scalar($value) . "\n";
    }

    /**
     * @param array<string, mixed> $map
     */
    private static function dumpMap(array $map, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $out    = '';

        foreach ($map as $key => $value) {
            $key = self::key((string) $key);

            if (is_array($value) && $value !== []) {
                $out .= $indent . $key . ":\n" . self::dump($value, $depth + 1);
                continue;
            }

            $out .= $indent . $key . ': ' . self::inlineScalar($value, $depth) . "\n";
        }

        return $out;
    }

    /**
     * @param list<mixed> $list
     */
    private static function dumpList(array $list, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $out    = '';

        foreach ($list as $value) {
            if (is_array($value) && $value !== []) {
                // Render the child one level deeper, then hoist its first line
                // onto the dash so the block reads as a normal YAML sequence.
                $child = self::dump($value, $depth + 1);
                $break = (int) strpos($child, "\n");
                $out  .= $indent . '- ' . ltrim(substr($child, 0, $break)) . "\n";
                $out  .= substr($child, $break + 1);
                continue;
            }

            $out .= $indent . '- ' . self::inlineScalar($value, $depth) . "\n";
        }

        return $out;
    }

    private static function inlineScalar(mixed $value, int $depth): string
    {
        if (is_string($value) && str_contains($value, "\n")) {
            $indent = str_repeat('  ', $depth + 1);
            $lines  = array_map(
                static fn (string $line): string => $line === '' ? '' : $indent . $line,
                explode("\n", rtrim($value, "\n"))
            );

            return "|-\n" . implode("\n", $lines);
        }

        return self::scalar($value);
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return array_is_list($value) ? '[]' : '{}';
        }

        return self::quote((string) $value);
    }

    private static function key(string $key): string
    {
        return preg_match('/^[A-Za-z0-9_.\/{}-]+$/', $key) === 1 && !self::readsAsScalar($key)
            ? $key
            : self::quote($key);
    }

    private static function quote(string $value): string
    {
        $needsQuotes = $value === ''
            || self::readsAsScalar($value)
            || preg_match('/^[\s>|*&!%@`#\[\]{},"\'-]|[:#]\s|\s$/', $value) === 1
            || str_contains($value, ': ');

        if (!$needsQuotes) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', "\t"], ['\\\\', '\\"', '\\t'], $value) . '"';
    }

    /**
     * True when a plain string would come back out of a YAML parser as
     * something else: a number, a boolean, or a timestamp. "+447711000022" and
     * "2024-11-25T13:28:17Z" both need quotes to survive the round trip.
     */
    private static function readsAsScalar(string $value): bool
    {
        $patterns = [
            '/^(true|false|null|yes|no|on|off|~)$/i',
            '/^[-+]?(\d[\d_]*)(\.\d*)?([eE][-+]?\d+)?$/',
            '/^[-+]?\.(inf|nan)$/i',
            '/^0[xob][0-9a-f_]+$/i',
            '/^\d{4}-\d{2}-\d{2}([T ][\d:.]+.*)?$/',
            '/^\d{1,2}:\d{2}(:\d{2})?$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
