<?php

namespace App\Services\Catalog\Meta;

use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedValidator;

class MetaCatalogFeedValidator implements CatalogFeedValidator
{
    public function validate(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new CatalogChannelException('FEED_INVALID_CSV', 'Không thể đọc Meta Catalog CSV.');
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers !== MetaCatalogCsvRenderer::HEADERS) {
                throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV có header không hợp lệ.');
            }

            $seen = [];
            $count = 0;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $count++;
                if (count($row) !== count($headers)) {
                    throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV có số cột không hợp lệ.');
                }
                $data = array_combine($headers, $row);
                $id = trim((string) $data['id']);
                $price = (float) strtok(trim((string) $data['price']), ' ');
                if ($id === '' || isset($seen[$id]) || $price <= 0 || $data['link'] === '' || $data['image_link'] === '') {
                    throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV có item không hợp lệ hoặc trùng ID.');
                }
                $seen[$id] = true;
            }
        } finally {
            fclose($handle);
        }

        return ['items' => $count, 'valid' => true];
    }
}
