<?php

namespace App\Support;

/**
 * JSON-LD typed for one page in admin (LocalBusiness, FAQPage…). Accepts an
 * object, a list of objects or {"@graph": [...]} and keeps only objects that
 * name a schema.org @type, so the storefront can print it as-is.
 */
class PageSchema
{
    /** @return list<array<string, mixed>>|null null when the text is not valid JSON-LD */
    public static function parse(?string $json): ?array
    {
        if (blank($json)) {
            return [];
        }
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            return null;
        }
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $data = $data['@graph'];
        }
        $items = array_is_list($data) ? $data : [$data];
        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item) || ! is_string($item['@type'] ?? null) && ! is_array($item['@type'] ?? null)) {
                return null;
            }
        }

        return array_map(function (array $item): array {
            unset($item['@context']);

            return $item;
        }, $items);
    }
}
