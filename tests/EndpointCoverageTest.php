<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the promise that every documented endpoint has a client method.
 *
 * It reads docs/api/endpoints.json, written by tools/build-openapi.php, and
 * looks for a matching call in src/Resource. That is source inspection rather
 * than behaviour, deliberately: the point is to notice when Spryng publishes an
 * endpoint this package does not cover yet. Refreshing the documentation with
 * `fin docs` then turns a silent gap into a failing test.
 */
final class EndpointCoverageTest extends TestCase
{
    public function testEveryDocumentedEndpointHasAClientMethod(): void
    {
        $documented = $this->documentedEndpoints();

        if ($documented === []) {
            self::markTestSkipped('docs/api/endpoints.json is missing; run `fin docs` to regenerate it.');
        }

        $implemented = $this->implementedEndpoints();
        $missing     = [];

        foreach ($documented as $endpoint) {
            $key = $endpoint['method'] . ' ' . $this->normalise($endpoint['path']);

            if (!in_array($key, $implemented, true)) {
                $missing[] = $key . ' (' . $endpoint['operationId'] . ')';
            }
        }

        self::assertSame([], $missing, sprintf(
            "%d documented endpoint(s) have no method in src/Resource:\n  %s",
            count($missing),
            implode("\n  ", $missing)
        ));
    }

    public function testItCoversTheWholeDocumentedSurface(): void
    {
        $documented = $this->documentedEndpoints();

        if ($documented === []) {
            self::markTestSkipped('docs/api/endpoints.json is missing; run `fin docs` to regenerate it.');
        }

        self::assertGreaterThanOrEqual(61, count($documented));
    }

    /**
     * @return list<array{method: string, path: string, operationId: string}>
     */
    private function documentedEndpoints(): array
    {
        $file = dirname(__DIR__) . '/docs/api/endpoints.json';

        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Every "METHOD path" the resource classes can produce, with ids reduced to
     * a placeholder so /messages/{messageId} and rawurlencode($id) line up.
     *
     * @return list<string>
     */
    private function implementedEndpoints(): array
    {
        $calls = [];

        foreach (glob(dirname(__DIR__) . '/src/Resource/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            // Matches: $this->client->get('/messages/' . rawurlencode($id) . '/x'
            $pattern = "/\\\$this->client->(get|post|put|patch|delete)\(\s*'([^']+)'"
                . "((?:\s*\.\s*rawurlencode\([^)]*\)(?:\s*\.\s*'[^']*')?)*)/";

            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $path = $match[2];

                preg_match_all("/rawurlencode\([^)]*\)(?:\s*\.\s*'([^']*)')?/", $match[3] ?? '', $segments, PREG_SET_ORDER);

                foreach ($segments as $segment) {
                    $path .= '{id}' . ($segment[1] ?? '');
                }

                $calls[] = strtoupper($match[1]) . ' ' . $this->normalise($path);
            }
        }

        return array_values(array_unique($calls));
    }

    private function normalise(string $path): string
    {
        return rtrim((string) preg_replace('/\{[^}]+\}/', '{id}', $path), '/');
    }
}
