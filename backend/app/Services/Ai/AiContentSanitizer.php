<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Str;

class AiContentSanitizer
{
    public function parse(string $raw, string $type, string $fallbackTitle = ''): array
    {
        $raw = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)));
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            if ($raw === '') {
                throw new AiProviderException('AI provider không trả về nội dung.');
            }
            $data = ['content' => $raw];
        }

        $title = trim((string) ($data['title'] ?? $fallbackTitle));
        $content = (string) ($data['content'] ?? $data['body'] ?? $data['description'] ?? '');
        $content = $this->html($content, $type);
        if ($content === '') {
            throw new AiProviderException('AI provider không trả về nội dung hợp lệ.');
        }

        $excerpt = trim((string) ($data['excerpt'] ?? Str::limit(strip_tags($content), 300, '')));
        $metaDescription = trim((string) ($data['meta_desc'] ?? $data['meta_description'] ?? $excerpt));

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'content' => $content,
            'body' => $content,
            'short_description' => trim((string) ($data['short_description'] ?? $excerpt)),
            'meta_title' => Str::limit(trim((string) ($data['meta_title'] ?? $title)), 60, ''),
            'meta_desc' => Str::limit($metaDescription, 160, ''),
            'meta_description' => Str::limit($metaDescription, 160, ''),
            'meta_keywords' => trim((string) ($data['meta_keywords'] ?? '')),
            'tags' => array_values(array_filter((array) ($data['tags'] ?? []), 'is_string')),
        ];
    }

    public function html(string $html, string $type): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/\[IMG:[^\]]*\]|\{\{[^}]+\}\}/i', '', $html);
        $html = preg_replace('/\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = strip_tags($html, '<h2><h3><p><ul><ol><li><strong><em><blockquote><br><a>');
        $html = preg_replace('/\s(href|src)\s*=\s*["\']\s*(?:javascript:|data:|vbscript:)[^"\']*["\']/i', '', $html);
        if (in_array($type, ['product_description', 'category_description'], true)) {
            $html = preg_replace('/<h1\b[^>]*>(.*?)<\/h1>/is', '<h2>$1</h2>', $html);
        }

        return trim($html);
    }
}
