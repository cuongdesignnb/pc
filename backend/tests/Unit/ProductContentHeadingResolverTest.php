<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Services\Ai\ProductContentHeadingResolver;
use PHPUnit\Framework\TestCase;

class ProductContentHeadingResolverTest extends TestCase
{
    public function test_explicit_heading_configuration_is_respected(): void
    {
        $product = new Product;

        $this->assertSame('Cấu hình chi tiết', (new ProductContentHeadingResolver)->resolve($product, 'configuration'));
        $this->assertSame('Thông số kỹ thuật', (new ProductContentHeadingResolver)->resolve($product, 'specifications'));
    }

    public function test_auto_heading_uses_configuration_for_pc_categories(): void
    {
        $category = new Category(['name' => 'PC Gaming', 'slug' => 'pc-gaming']);
        $product = new Product;
        $product->setRelation('category', $category);

        $this->assertSame('Cấu hình chi tiết', (new ProductContentHeadingResolver)->resolve($product));
    }

    public function test_auto_heading_defaults_to_technical_specifications(): void
    {
        $category = new Category(['name' => 'Màn hình', 'slug' => 'man-hinh']);
        $product = new Product;
        $product->setRelation('category', $category);

        $resolver = new ProductContentHeadingResolver;

        $this->assertSame('Thông số kỹ thuật', $resolver->resolve($product));
        $this->assertSame('Điểm nổi bật', $resolver->contentHeading(true));
        $this->assertSame('Thông tin sản phẩm', $resolver->contentHeading(false));
    }
}
