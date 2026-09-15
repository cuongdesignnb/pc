<?php

namespace App\Services\Catalog\Meta;

use App\Data\Catalog\CatalogProductData;
use App\Data\Catalog\CatalogValidationResult;
use App\Services\Catalog\CatalogProductValidator;
use DateTimeImmutable;

class MetaCatalogItemValidator
{
    private const ROW_SCOPED_SOURCE_ERRORS = [
        'SKU_MISSING',
        'TITLE_MISSING',
        'PRICE_MISSING',
        'PRODUCT_URL_MISSING',
        'IMAGE_MISSING',
    ];

    public function __construct(private readonly CatalogProductValidator $products) {}

    public function validate(MetaCatalogItem $item, CatalogProductData $source): CatalogValidationResult
    {
        $sourceValidation = $this->products->validate($source);
        $errors = array_values(array_diff($sourceValidation->errors, self::ROW_SCOPED_SOURCE_ERRORS));
        $errors = array_merge($errors, $this->errorsForValues($item->values));

        if (in_array($item->value('brand'), ['Generic', 'Unbranded'], true) && ! $item->approvedUnbranded) {
            $errors[] = 'BRAND_MISSING';
        }

        $errors = array_values(array_unique($errors));

        return new CatalogValidationResult(
            valid: $errors === [],
            errors: $errors,
            warnings: $sourceValidation->warnings,
        );
    }

    /**
     * Validate only fields represented in a Meta CSV row. This method is also
     * used when validating an already-generated artifact.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    public function errorsForValues(array $values): array
    {
        $errors = [];
        $value = static fn (string $key): string => trim((string) ($values[$key] ?? ''));

        if ($value('id') === '') {
            $errors[] = 'ID_MISSING';
        }
        if ($value('title') === '') {
            $errors[] = 'TITLE_MISSING';
        }

        $description = $value('description');
        $plainDescription = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plainDescription === '' || preg_match('/<[^>]+>/u', $description) === 1) {
            $errors[] = 'DESCRIPTION_MISSING';
        }

        if (! in_array($value('availability'), ['in stock', 'out of stock'], true)) {
            $errors[] = 'AVAILABILITY_INVALID';
        }
        if (! in_array($value('condition'), ['new', 'used', 'refurbished'], true)) {
            $errors[] = 'CONDITION_INVALID';
        }
        if (! $this->products->isPublicHttpsUrl($value('link'))) {
            $errors[] = 'PRODUCT_URL_MISSING';
        }
        if (! $this->products->isPublicHttpsUrl($value('image_link'))) {
            $errors[] = 'IMAGE_MISSING';
        }
        if ($value('brand') === '') {
            $errors[] = 'BRAND_MISSING';
        }

        $priceValue = $value('price');
        $price = $this->money($priceValue);
        if ($priceValue === '') {
            $errors[] = 'PRICE_MISSING';
        } elseif ($price === null || $price['amount'] <= 0) {
            $errors[] = 'PRICE_INVALID';
        }

        $quantity = $value('quantity_to_sell_on_facebook');
        if ($quantity === '' || ! ctype_digit($quantity)) {
            $errors[] = 'QUANTITY_INVALID';
        }

        foreach (['google_product_category', 'fb_product_category'] as $categoryField) {
            $category = $value($categoryField);
            if ($category !== '' && (! ctype_digit($category) || (int) $category <= 0)) {
                $errors[] = 'CATEGORY_INVALID';
            }
        }

        $salePriceValue = $value('sale_price');
        $salePeriod = $value('sale_price_effective_date');
        if ($salePriceValue !== '') {
            $salePrice = $this->money($salePriceValue);
            if (
                $salePrice === null
                || $price === null
                || $salePrice['currency'] !== $price['currency']
                || $salePrice['amount'] <= 0
                || $salePrice['amount'] >= $price['amount']
            ) {
                $errors[] = 'PRICE_INVALID';
            }
        }
        if ($salePeriod !== '' && ($salePriceValue === '' || ! $this->validSalePeriod($salePeriod))) {
            $errors[] = 'PRICE_INVALID';
        }

        return array_values(array_unique($errors));
    }

    /** @return array{amount:int,currency:string}|null */
    private function money(string $value): ?array
    {
        if (preg_match('/^([0-9]+) ([A-Z]{3})$/D', trim($value), $matches) !== 1) {
            return null;
        }

        return ['amount' => (int) $matches[1], 'currency' => $matches[2]];
    }

    private function validSalePeriod(string $value): bool
    {
        $parts = explode('/', $value, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $start = $this->strictDate($parts[0]);
        $end = $this->strictDate($parts[1]);

        return $start !== null && $end !== null && $start < $end;
    }

    private function strictDate(string $value): ?DateTimeImmutable
    {
        foreach (['!Y-m-d', DateTimeImmutable::ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, trim($value));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }
}
