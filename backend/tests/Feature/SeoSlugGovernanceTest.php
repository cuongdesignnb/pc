<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\Seo\SlugRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SeoSlugGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_path_cannot_be_reused_by_a_different_current_entity(): void
    {
        Category::create([
            'name' => 'Linh kiện',
            'slug' => 'linh-kien',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('URL /linh-kien đang là URL canonical của entity khác.');

        app(SlugRedirectService::class)->assertPathAvailable('/linh-kien');
    }
}
