<?php

namespace App\Services\Seo;

use App\Jobs\Seo\SubmitIndexNowUrl;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow (Bing, Yandex, …): when a product or article is published or
 * edited, its storefront URL is sent to api.indexnow.org so it is recrawled
 * quickly. The key is checked by the search engine at
 * https://<storefront>/indexnow-key.txt. Off until "Bật IndexNow" is ticked.
 */
class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public const KEY_PATH = '/indexnow-key.txt';

    /** Fields whose change is worth a recrawl; price and stock updates are not. */
    private const PRODUCT_FIELDS = [
        'name', 'slug', 'category_id', 'description', 'short_description', 'meta_title', 'meta_description',
        'is_active', 'show_on_pc_website', 'manufacture_year', 'warning_info', 'usage_instructions',
    ];

    private const POST_FIELDS = ['title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'meta_title', 'meta_description', 'featured_image'];

    public function __construct(private readonly PublicUrlResolver $urls) {}

    public function enabled(): bool
    {
        return (bool) Setting::get('indexnow_enabled', false) && $this->key() !== null;
    }

    public function key(): ?string
    {
        $key = trim((string) Setting::get('indexnow_key', ''));

        return preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) === 1 ? $key : null;
    }

    public function productChanged(Product $product): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($this->justCreated($product) || $product->wasChanged(self::PRODUCT_FIELDS)) {
            $this->queue($this->urls->productUrl($product));
        }
    }

    public function postChanged(Post $post): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($post->status !== 'published' && ! $post->wasChanged('status')) {
            return;
        }
        if ($this->justCreated($post) || $post->wasChanged(self::POST_FIELDS)) {
            $this->queue($this->urls->postUrl($post));
        }
    }

    /** wasRecentlyCreated stays true on the instance; a later update has changes. */
    private function justCreated(Model $model): bool
    {
        return $model->wasRecentlyCreated && $model->getChanges() === [];
    }

    public function queue(?string $url): void
    {
        if ($url === null || ! $this->enabled()) {
            return;
        }
        // Several saves in a row (admin edit + image sync) send one request.
        SubmitIndexNowUrl::dispatch($url)->afterCommit()->delay(now()->addMinute());
    }

    /** @param  list<string>  $urls */
    public function submit(array $urls): bool
    {
        $key = $this->key();
        $origin = $this->urls->origin();
        if (! $this->enabled() || $key === null || $origin === null || $urls === []) {
            return false;
        }
        $host = (string) parse_url($origin, PHP_URL_HOST);
        $urls = array_values(array_filter($urls, fn (string $url) => parse_url($url, PHP_URL_HOST) === $host));
        if ($urls === []) {
            return false;
        }

        $response = Http::timeout(15)->acceptJson()->post(self::ENDPOINT, [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $origin.self::KEY_PATH,
            'urlList' => $urls,
        ]);
        // 200 and 202 are success; 422/403 mean the key file is not reachable yet.
        if (! in_array($response->status(), [200, 202], true)) {
            Log::warning('IndexNow submission was not accepted.', ['status' => $response->status(), 'urls' => $urls]);

            return false;
        }

        return true;
    }
}
