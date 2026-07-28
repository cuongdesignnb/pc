<?php

namespace App\Services\Catalog\GoogleMerchant;

use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedValidator;
use DOMDocument;
use DOMXPath;

class GoogleMerchantFeedValidator implements CatalogFeedValidator
{
    public function validate(string $path): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->load($path, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new CatalogChannelException('FEED_INVALID_XML', 'Google Merchant XML không hợp lệ.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('g', 'http://base.google.com/ns/1.0');
        $seen = [];
        $count = 0;
        foreach ($xpath->query('//item') as $item) {
            $count++;
            $id = trim((string) $xpath->evaluate('string(g:id)', $item));
            $title = trim((string) $xpath->evaluate('string(g:title)', $item));
            $link = trim((string) $xpath->evaluate('string(g:link)', $item));
            $image = trim((string) $xpath->evaluate('string(g:image_link)', $item));
            $price = (float) strtok(trim((string) $xpath->evaluate('string(g:price)', $item)), ' ');
            if ($id === '' || $title === '' || $link === '' || $image === '' || $price <= 0 || isset($seen[$id])) {
                throw new CatalogChannelException('FEED_INVALID_XML', 'Google Merchant XML có item không hợp lệ hoặc trùng ID.');
            }
            $seen[$id] = true;
        }

        return ['items' => $count, 'valid' => true];
    }
}
