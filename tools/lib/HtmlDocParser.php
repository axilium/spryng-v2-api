<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tools;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Turns a chunk of developer-portal HTML into an ordered list of blocks.
 *
 * The portal stores every documentation section as a single HTML string that
 * mixes prose, <table> parameter listings and <pre><code> samples. Both the
 * markdown dump and the OpenAPI builder need the same three block types, in
 * document order, so the "Query parameters" heading can be tied to the table
 * that follows it.
 *
 * Block shapes:
 *   ['type' => 'text',  'text' => string]
 *   ['type' => 'code',  'code' => string]
 *   ['type' => 'table', 'headers' => list<string>, 'rows' => list<list<string>>]
 */
final class HtmlDocParser
{
    private const BLOCK_ELEMENTS = ['p', 'div', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function blocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        // The portal uses <br> as its only line break, inside <pre> as well.
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><body><div>' . $html . '</div></body>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return [];
        }

        $blocks = [];
        $buffer = '';
        self::walk($root, $blocks, $buffer);
        self::flush($blocks, $buffer);

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    public static function toMarkdown(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            $parts[] = match ($block['type']) {
                'code'  => "```\n" . $block['code'] . "\n```",
                'table' => self::tableToMarkdown($block),
                default => $block['text'],
            };
        }

        return implode("\n\n", $parts);
    }

    /**
     * Plain text of a block, with the markdown emphasis markers removed.
     */
    public static function plainText(array $block): string
    {
        return $block['type'] === 'text' ? str_replace('**', '', $block['text']) : '';
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function walk(DOMNode $node, array &$blocks, string &$buffer): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $buffer .= $child->nodeValue ?? '';
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);

            if ($name === 'table') {
                self::flush($blocks, $buffer);
                $blocks[] = self::table($child);
                continue;
            }

            if ($name === 'pre') {
                self::flush($blocks, $buffer);
                $blocks[] = ['type' => 'code', 'code' => trim($child->textContent)];
                continue;
            }

            if ($name === 'strong' || $name === 'b') {
                $text = self::collapse($child->textContent);
                if ($text !== '') {
                    $buffer .= '**' . $text . '**';
                }
                continue;
            }

            if ($name === 'a') {
                $buffer .= '[' . self::collapse($child->textContent) . '](' . $child->getAttribute('href') . ')';
                continue;
            }

            if ($name === 'code') {
                $buffer .= '`' . self::collapse($child->textContent) . '`';
                continue;
            }

            if ($name === 'li') {
                $buffer .= "\n- ";
                self::walk($child, $blocks, $buffer);
                continue;
            }

            if (in_array($name, self::BLOCK_ELEMENTS, true)) {
                $buffer .= "\n\n";
                self::walk($child, $blocks, $buffer);
                $buffer .= "\n\n";
                continue;
            }

            self::walk($child, $blocks, $buffer);
        }
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function flush(array &$blocks, string &$buffer): void
    {
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", $buffer));
        $text = trim((string) preg_replace('/[ \t]+/', ' ', $text));

        if ($text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $text];
        }

        $buffer = '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function table(DOMElement $table): array
    {
        $headers = [];
        $rows    = [];

        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells    = [];
            $isHeader = false;

            foreach ($row->childNodes as $cell) {
                if (!$cell instanceof DOMElement) {
                    continue;
                }

                $name = strtolower($cell->nodeName);
                if ($name !== 'td' && $name !== 'th') {
                    continue;
                }

                $cells[] = self::collapse($cell->textContent);
                $isHeader = $isHeader || $name === 'th';
            }

            if ($cells === []) {
                continue;
            }

            if ($isHeader && $headers === []) {
                $headers = $cells;
                continue;
            }

            $rows[] = $cells;
        }

        return ['type' => 'table', 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function tableToMarkdown(array $block): string
    {
        $headers = $block['headers'];
        $rows    = $block['rows'];

        if ($headers === []) {
            $width   = max(array_map('count', $rows ?: [[]]));
            $headers = array_fill(0, max($width, 1), ' ');
        }

        $escape = static fn (string $cell): string => str_replace('|', '\\|', $cell);
        $line   = static fn (array $cells): string => '| ' . implode(' | ', $cells) . ' |';

        $lines = [
            $line(array_map($escape, $headers)),
            $line(array_fill(0, count($headers), '---')),
        ];

        foreach ($rows as $row) {
            $row = array_pad(array_slice($row, 0, count($headers)), count($headers), '');
            $lines[] = $line(array_map($escape, $row));
        }

        return implode("\n", $lines);
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
