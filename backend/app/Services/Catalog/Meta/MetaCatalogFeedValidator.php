<?php

namespace App\Services\Catalog\Meta;

use App\Exceptions\CatalogChannelException;
use App\Services\Catalog\Feeds\CatalogFeedValidator;

class MetaCatalogFeedValidator implements CatalogFeedValidator
{
    public function __construct(private readonly MetaCatalogItemValidator $items) {}

    public function validate(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new CatalogChannelException('FEED_INVALID_CSV', 'Không thể đọc Meta Catalog CSV.');
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($headers) || ! mb_check_encoding($headers, 'UTF-8')) {
                throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV không phải UTF-8 hợp lệ.');
            }
            if ($headers !== MetaCatalogCsvRenderer::HEADERS) {
                throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV có header không hợp lệ.');
            }

            $seen = [];
            $count = 0;
            $errorsByCode = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $count++;
                if (! mb_check_encoding($row, 'UTF-8')) {
                    throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV không phải UTF-8 hợp lệ.');
                }
                if (count($row) !== count($headers)) {
                    throw new CatalogChannelException('FEED_INVALID_CSV', 'Meta Catalog CSV có số cột không hợp lệ.');
                }
                $data = array_combine($headers, $row);
                $id = trim((string) $data['id']);
                $errors = $this->items->errorsForValues($data);
                if ($id !== '' && isset($seen[$id])) {
                    $errors[] = 'DUPLICATE_ID';
                }
                if ($id !== '') {
                    $seen[$id] = true;
                }
                foreach (array_unique($errors) as $error) {
                    $errorsByCode[$error] = ($errorsByCode[$error] ?? 0) + 1;
                }
            }

            if ($errorsByCode !== []) {
                ksort($errorsByCode);
                throw new CatalogChannelException(
                    'FEED_INVALID_CSV',
                    'Meta Catalog CSV không hợp lệ: '.implode(',', array_keys($errorsByCode)).'.',
                );
            }
        } finally {
            fclose($handle);
        }

        return ['items' => $count, 'valid' => true, 'errors_by_code' => []];
    }
}
