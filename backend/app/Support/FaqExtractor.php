<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pull question/answer pairs out of an article or category description for
 * FAQPage structured data. A FAQ section starts at a heading such as
 * "Hỏi đáp", "Câu hỏi thường gặp" or "FAQ"; inside it every lower heading
 * (or a paragraph that is only bold text ending in "?") is a question and
 * the text that follows, up to the next question, is its answer.
 */
class FaqExtractor
{
    private const SECTION_TITLE = '/h[ỏo]i\s*(?:&|và)?\s*[đd][áa]p|c[âa]u\s+h[ỏo]i\s+th[ưu][ờo]ng\s+g[ặa]p|\bfaqs?\b/iu';

    private const MAX_ITEMS = 20;

    /** @return array<int, array{question: string, answer: string}> */
    public static function extract(?string $html): array
    {
        if (blank($html) || ! preg_match(self::SECTION_TITLE, strip_tags((string) $html))) {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="faq-root">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('faq-root');
        if (! $root) {
            return [];
        }

        $items = [];
        $sectionLevel = null;
        $question = null;
        $answer = [];
        $flush = function () use (&$items, &$question, &$answer): void {
            $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $answer)) ?? '');
            if ($question !== null && $text !== '') {
                $items[] = ['question' => $question, 'answer' => $text];
            }
            $question = null;
            $answer = [];
        };

        foreach (self::blocks($root) as $node) {
            $text = self::text($node);
            $level = self::headingLevel($node);

            if ($sectionLevel === null) {
                if ($level !== null && preg_match(self::SECTION_TITLE, $text)) {
                    $sectionLevel = $level;
                }

                continue;
            }

            if ($level !== null && $level <= $sectionLevel) {
                break; // The FAQ section ended at the next heading of its rank.
            }

            if ($level !== null || self::isBoldQuestion($node, $text)) {
                $flush();
                $question = $text !== '' ? $text : null;

                continue;
            }

            if ($question !== null && $text !== '') {
                $answer[] = $text;
            }
        }
        $flush();

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /** @return iterable<int, DOMNode> */
    private static function blocks(DOMNode $root): iterable
    {
        foreach ($root->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            // Editors often wrap content in a single container; look through it.
            if (in_array(strtolower($child->tagName), ['div', 'section', 'article'], true)) {
                yield from self::blocks($child);

                continue;
            }
            yield $child;
        }
    }

    private static function headingLevel(DOMNode $node): ?int
    {
        return $node instanceof DOMElement && preg_match('/^h([1-6])$/i', $node->tagName, $match)
            ? (int) $match[1]
            : null;
    }

    private static function isBoldQuestion(DOMNode $node, string $text): bool
    {
        if (! $node instanceof DOMElement || strtolower($node->tagName) !== 'p' || ! str_ends_with($text, '?')) {
            return false;
        }
        $bold = '';
        foreach ($node->getElementsByTagName('strong') as $strong) {
            $bold .= $strong->textContent;
        }
        foreach ($node->getElementsByTagName('b') as $strong) {
            $bold .= $strong->textContent;
        }

        return trim(preg_replace('/\s+/u', ' ', $bold) ?? '') === $text;
    }

    private static function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
