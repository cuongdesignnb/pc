<?php

namespace App\Services\Catalog\Meta;

final class MetaCatalogSchema
{
    public const HEADERS = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'link',
        'image_link',
        'brand',
        'price',
        'google_product_category',
        'fb_product_category',
        'quantity_to_sell_on_facebook',
        'sale_price',
        'sale_price_effective_date',
        'item_group_id',
        'gender',
        'color',
        'size',
        'age_group',
        'material',
        'pattern',
        'shipping',
        'shipping_weight',
        'offer_disclaimer',
        'offer_disclaimer_url',
        'video[0].url',
        'video[0].tag[0]',
        'gtin',
        'product_tags[0]',
        'product_tags[1]',
        'style[0]',
    ];

    public const REQUIRED = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'link',
        'image_link',
        'brand',
    ];
}
