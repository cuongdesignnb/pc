<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Models\ProductDetailBlock;
use App\Models\Setting;

class ProductContactFooterService
{
    public function configuration(): array
    {
        $website = trim((string) Setting::get('storefront_product_contact_footer_website_url', ''));
        $maps = trim((string) Setting::get('storefront_product_contact_footer_maps_url', ''));

        return [
            'enabled' => (bool) Setting::get('storefront_product_contact_footer_enabled', false),
            'title' => trim((string) Setting::get('storefront_product_contact_footer_title', '')),
            'address' => trim((string) Setting::get('contact_address', '')),
            'landmark' => trim((string) Setting::get('storefront_product_contact_footer_landmark', '')),
            'phone' => trim((string) Setting::get('contact_phone', '')),
            'hotline' => trim((string) Setting::get('contact_hotline', '')),
            'website_url' => $this->httpsUrl($website),
            'website_label' => $this->websiteLabel($website),
            'maps_url' => $this->httpsUrl($maps),
        ];
    }

    public function isConfigured(): bool
    {
        $config = $this->configuration();

        return $config['enabled']
            && $config['title'] !== ''
            && $config['address'] !== ''
            && ($config['phone'] !== '' || $config['hotline'] !== '')
            && $config['website_url'] !== ''
            && $config['maps_url'] !== '';
    }

    public function ensure(Product $product): ProductDetailBlock
    {
        $block = $product->detailBlocks()->where('type', 'contact_footer')->first();
        $payload = [
            'managed_by' => 'ai-product-content-campaign',
            'setting_scope' => 'storefront_product_contact_footer',
        ];

        if (! $block) {
            return $product->detailBlocks()->create([
                'type' => 'contact_footer',
                'title' => null,
                'payload' => $payload,
                'sort_order' => 999,
                'is_active' => true,
            ]);
        }

        if (($block->payload['managed_by'] ?? null) === 'ai-product-content-campaign') {
            $block->update(['payload' => $payload, 'is_active' => true]);
        }

        return $block;
    }

    private function httpsUrl(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            ? $url
            : '';
    }

    private function websiteLabel(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./i', '', $host) : '';
    }
}
