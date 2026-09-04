<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ComponentType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\ReviewMedia;
use App\Models\Setting;
use App\Models\SpecificationKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceProductDetailSeeder extends Seeder
{
    private const SLUG = 'card-man-hinh-gigabyte-geforce-rtx-4070-super-windforce-oc-12g';

    public function run(): void
    {
        $brand = Brand::where('slug', 'gigabyte')->firstOrFail();
        $category = Category::where('slug', 'vga')->firstOrFail();
        $componentType = ComponentType::where('slug', 'vga')->firstOrFail();

        $product = Product::updateOrCreate(
            ['slug' => self::SLUG],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'component_type_id' => $componentType->id,
                'name' => 'Card màn hình GIGABYTE GeForce RTX 4070 SUPER WINDFORCE OC 12G',
                'sku' => 'GV-N407SWF3OC-12GD',
                'short_description' => 'RTX 4070 SUPER 12GB GDDR6X, kiến trúc Ada Lovelace, WINDFORCE 3X, DLSS 3.5 và Ray Tracing thế hệ 3.',
                'description' => 'GIGABYTE GeForce RTX 4070 SUPER WINDFORCE OC 12G là card đồ họa hiệu năng cao dành cho gaming 2K/4K, đồ họa, render và các tác vụ AI.',
                'price' => 18_990_000,
                'sale_price' => 15_990_000,
                'cost_price' => 14_000_000,
                'stock_quantity' => 18,
                'inventory_source' => 'local',
                'provider' => null,
                'is_active' => true,
                'is_featured' => true,
                'show_on_pc_website' => true,
                'weight' => 1100,
                'warranty_months' => 36,
                'views_count' => 12_840,
                'sold_count' => 1_248,
                'meta_title' => 'GIGABYTE RTX 4070 SUPER WINDFORCE OC 12G',
                'meta_description' => 'Card màn hình GIGABYTE RTX 4070 SUPER WINDFORCE OC 12G, 12GB GDDR6X, DLSS 3.5, Ray Tracing, bảo hành 36 tháng.',
            ]
        );

        $this->seedImages($product);
        $this->seedVariants($product);
        $this->seedHighlights($product);
        $this->seedSpecifications($product, $componentType);
        $this->seedPowerRequirement($product);
        $this->seedDetailBlocks($product);
        $this->seedRelations($product);
        $this->seedServiceSettings();
        $this->seedReviews($product);
        $this->seedQuestions($product);

        $this->command?->info('Reference PDP product seeded: /vga/'.self::SLUG);
    }

    private function seedImages(Product $product): void
    {
        $images = [
            'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=1200&h=1200&fit=contain',
            'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=1200&h=1200&fit=contain',
            'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=1200&h=1200&fit=contain',
            'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=1000&h=1000&fit=crop',
            'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=1000&h=1000&fit=crop',
        ];

        $product->images()->delete();
        foreach ($images as $index => $url) {
            $product->images()->create([
                'url' => $url,
                'alt_text' => $product->name.' - Hình '.($index + 1),
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function seedVariants(Product $product): void
    {
        $product->variants()->delete();
        $variants = [
            ['name' => 'WINDFORCE OC 3 Quạt', 'attributes' => ['Phiên bản' => 'WINDFORCE OC', 'Tản nhiệt' => '3 Quạt'], 'sku' => 'GV-N407SWF3OC-12GD', 'price' => 18_990_000, 'sale_price' => 15_990_000, 'stock_quantity' => 18],
            ['name' => 'GAMING OC 3 Quạt', 'attributes' => ['Phiên bản' => 'GAMING OC', 'Tản nhiệt' => '3 Quạt'], 'sku' => 'DEMO-N407S-GAMING-OC', 'price' => 19_490_000, 'sale_price' => 16_490_000, 'stock_quantity' => 12],
            ['name' => 'AERO OC 3 Quạt', 'attributes' => ['Phiên bản' => 'AERO OC', 'Tản nhiệt' => '3 Quạt'], 'sku' => 'DEMO-N407S-AERO-OC', 'price' => 19_990_000, 'sale_price' => 16_990_000, 'stock_quantity' => 8],
            ['name' => 'DUAL OC 2 Quạt', 'attributes' => ['Phiên bản' => 'DUAL OC', 'Tản nhiệt' => '2 Quạt'], 'sku' => 'DEMO-N407S-DUAL-OC', 'price' => 18_490_000, 'sale_price' => 15_590_000, 'stock_quantity' => 6],
        ];

        foreach ($variants as $index => $variant) {
            $product->variants()->create([...$variant, 'is_active' => true, 'sort_order' => $index]);
        }
    }

    private function seedHighlights(Product $product): void
    {
        $product->highlights()->delete();
        foreach ([
            'NVIDIA GeForce RTX 4070 SUPER với kiến trúc Ada Lovelace',
            '12GB GDDR6X, 192-bit, băng thông bộ nhớ cao',
            'WINDFORCE 3X – hệ thống tản nhiệt 3 quạt',
            'Ray Tracing thế hệ 3, DLSS 3.5 và NVIDIA Reflex',
            'Bảo hành chính hãng 36 tháng',
        ] as $index => $title) {
            $product->highlights()->create(['title' => $title, 'icon' => 'check', 'sort_order' => $index, 'is_active' => true]);
        }
    }

    private function seedSpecifications(Product $product, ComponentType $componentType): void
    {
        $additionalKeys = [
            ['cuda_cores', 'CUDA Cores', 'integer', null],
            ['memory_bus', 'Bus bộ nhớ', 'integer', 'bit'],
            ['memory_bandwidth', 'Băng thông bộ nhớ', 'integer', 'GB/s'],
            ['ray_tracing_cores', 'Ray Tracing Cores', 'string', null],
            ['tensor_cores', 'Tensor Cores', 'string', null],
            ['dlss', 'DLSS', 'string', null],
            ['outputs', 'Cổng xuất hình', 'string', null],
            ['pcie_interface', 'PCI Express', 'string', null],
            ['recommended_psu', 'Nguồn đề nghị', 'integer', 'W'],
            ['dimensions', 'Kích thước', 'string', null],
            ['slot_width', 'Độ dày khe', 'string', null],
            ['sli_support', 'Hỗ trợ SLI', 'string', null],
        ];

        foreach ($additionalKeys as $order => [$key, $label, $type, $unit]) {
            SpecificationKey::updateOrCreate(
                ['component_type_id' => $componentType->id, 'key' => $key],
                ['label' => $label, 'data_type' => $type, 'unit' => $unit, 'is_filterable' => false, 'display_order' => 20 + $order]
            );
        }

        $specValues = [
            'gpu_chip' => 'NVIDIA GeForce RTX 4070 SUPER',
            'vram' => 12,
            'vram_type' => 'GDDR6X',
            'core_clock' => 1980,
            'boost_clock' => 2475,
            'tdp' => 220,
            'length' => 261,
            'power_connectors' => '1 x 12VHPWR',
            'cuda_cores' => 7168,
            'memory_bus' => 192,
            'memory_bandwidth' => 504,
            'ray_tracing_cores' => '3rd Gen',
            'tensor_cores' => '4th Gen',
            'dlss' => 'DLSS 3.5',
            'outputs' => '3 x DisplayPort 1.4a, 1 x HDMI 2.1a',
            'pcie_interface' => 'PCIe 4.0 x16',
            'recommended_psu' => 650,
            'dimensions' => '261 x 126 x 50 mm',
            'slot_width' => '2.5 Slot',
            'sli_support' => 'Không hỗ trợ',
        ];

        $keys = SpecificationKey::where('component_type_id', $componentType->id)->get()->keyBy('key');
        $product->specifications()->delete();
        foreach ($specValues as $key => $value) {
            $specKey = $keys->get($key);
            if (! $specKey) {
                continue;
            }
            $numeric = in_array($specKey->data_type, ['integer', 'decimal'], true);
            $product->specifications()->create([
                'specification_key_id' => $specKey->id,
                'value_numeric' => $numeric ? $value : null,
                'value_string' => $numeric ? null : (string) $value,
            ]);
        }
    }

    private function seedPowerRequirement(Product $product): void
    {
        $product->powerRequirement()->updateOrCreate(
            ['product_id' => $product->id],
            ['typical_tdp' => 220, 'peak_tdp' => 250, 'requires_pcie_power' => true, 'pcie_connectors_needed' => 1]
        );
    }

    private function seedDetailBlocks(Product $product): void
    {
        $product->detailBlocks()->delete();
        $blocks = [
            [
                'type' => 'hero_banner',
                'title' => '4070 SUPER',
                'payload' => [
                    'image_url' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=1200&h=900&fit=crop',
                    'alt' => 'RTX 4070 SUPER performance',
                    'tagline' => 'SUPER Fast. SUPER Powered.',
                    'description' => 'Trải nghiệm hiệu năng gaming, đồ họa và AI với Ada Lovelace, DLSS 3.5 và Ray Tracing.',
                    'features' => ['12GB GDDR6X', 'DLSS 3.5', 'Ray Tracing', 'Reflex'],
                ],
            ],
            [
                'type' => 'benchmark_cards',
                'title' => 'Hiệu năng vượt trội cho mọi tác vụ',
                'payload' => ['cards' => [
                    ['title' => 'AI Benchmark', 'value' => '24.875', 'description' => '+18% so với RTX 3070'],
                    ['title' => '3DMark Time Spy', 'value' => '18.642', 'description' => '+20% so với RTX 3070'],
                    ['title' => 'Port Royal (Ray Tracing)', 'value' => '11.296', 'description' => '+26% so với RTX 3070'],
                ]],
            ],
            [
                'type' => 'use_case_cards',
                'title' => 'Phù hợp cho',
                'payload' => ['cards' => [
                    ['icon' => '⌁', 'title' => 'Gaming 2K/4K', 'description' => 'FPS cao, hình ảnh sắc nét'],
                    ['icon' => '▦', 'title' => 'Đồ họa & Render', 'description' => 'Blender, 3ds Max, Premiere Pro'],
                    ['icon' => 'AI', 'title' => 'AI & Workstation', 'description' => 'Stable Diffusion và AI workload'],
                ]],
            ],
            [
                'type' => 'notice',
                'title' => 'Lưu ý về nguồn',
                'payload' => ['description' => 'Khuyến nghị PSU từ 650W và kiểm tra cổng nguồn 12VHPWR trước khi lắp đặt.'],
            ],
        ];

        foreach ($blocks as $index => $block) {
            $product->detailBlocks()->create([...$block, 'sort_order' => $index, 'is_active' => true]);
        }
    }

    private function seedRelations(Product $product): void
    {
        $product->relations()->delete();
        $relations = [
            ['name' => 'Corsair RM1000x 1000W 80+ Gold', 'type' => 'frequently_bought'],
            ['name' => 'DeepCool LT720 AIO 360mm', 'type' => 'frequently_bought'],
            ['name' => 'Lian Li O11 Dynamic EVO', 'type' => 'frequently_bought'],
            ['name' => 'Seasonic Focus GX-850 850W 80+ Gold', 'type' => 'accessory'],
            ['name' => 'Noctua NH-D15 chromax.black', 'type' => 'accessory'],
            ['name' => 'Corsair iCUE H150i Elite LCD XT 360mm', 'type' => 'accessory'],
            ['name' => 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB', 'type' => 'alternative'],
            ['name' => 'ASUS ROG Strix RTX 4090 OC 24GB', 'type' => 'related'],
            ['name' => 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB', 'type' => 'related'],
            ['name' => 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB', 'type' => 'related'],
            ['name' => 'ASUS Dual RTX 4060 Ti OC 8GB', 'type' => 'related'],
            ['name' => 'Gigabyte RTX 4060 Eagle OC 8GB', 'type' => 'related'],
            ['name' => 'AMD Radeon RX 7900 XTX 24GB', 'type' => 'related'],
        ];

        foreach ($relations as $index => $relation) {
            $related = Product::where('name', $relation['name'])->first();
            if (! $related || $related->id === $product->id) {
                continue;
            }
            $product->relations()->create([
                'related_product_id' => $related->id,
                'relation_type' => $relation['type'],
                'sort_order' => $index,
            ]);
        }
    }

    private function seedServiceSettings(): void
    {
        foreach ([
            'storefront_authenticity_message' => 'Hàng chính hãng, nguồn gốc rõ ràng',
            'storefront_return_policy_short' => 'Đổi trả theo chính sách cửa hàng',
            'storefront_delivery_policy_short' => 'Miễn phí giao hàng cho đơn đủ điều kiện',
            'storefront_technical_support_short' => 'Hỗ trợ kỹ thuật qua Hotline/Zalo',
            'storefront_installment_message' => 'Trả góp linh hoạt theo chính sách hiện hành',
        ] as $key => $value) {
            Setting::set($key, $value);
        }
    }

    private function seedReviews(Product $product): void
    {
        $reviewIds = Review::where('product_id', $product->id)->pluck('id');
        if ($reviewIds->isNotEmpty()) {
            ReviewMedia::whereIn('review_id', $reviewIds)->delete();
        }
        Review::where('product_id', $product->id)->delete();

        $orders = [];
        for ($i = 1; $i <= 3; $i++) {
            $order = Order::updateOrCreate(
                ['order_number' => 'DEMO-RTX4070-'.$i],
                [
                    'subtotal' => 15_990_000,
                    'discount' => 0,
                    'shipping_fee' => 0,
                    'total' => 15_990_000,
                    'payment_status' => 'paid',
                    'payment_method' => 'cod',
                    'checkout_mode' => 'cart',
                    'order_status' => 'delivered',
                    'shipping_name' => 'Khách demo '.$i,
                    'shipping_phone' => '090000000'.$i,
                    'customer_email' => 'demo-order-'.$i.'@example.test',
                    'shipping_address' => 'Hà Nội',
                    'shipping_city' => 'Hà Nội',
                    'checkout_idempotency_key' => (string) Str::uuid(),
                    'order_access_token_hash' => Order::hashAccessToken('reference-demo-'.$i),
                    'kiot_sync_status' => 'not_required',
                ]
            );
            $order->items()->updateOrCreate(
                ['product_id' => $product->id, 'variant_id' => null],
                ['product_name' => $product->name, 'variant_name' => null, 'sku' => $product->sku, 'quantity' => 1, 'price' => 15_990_000, 'total' => 15_990_000]
            );
            $orders[] = $order;
        }

        $featured = [
            ['name' => 'Nguyễn Hoàng Nam', 'rating' => 5, 'title' => 'Hiệu năng rất tốt', 'body' => 'Card mạnh, nhiệt độ mát, chiến game 2K max setting mượt mà. Rất hài lòng!', 'order_id' => $orders[0]->id],
            ['name' => 'Trần Minh Quân', 'rating' => 5, 'title' => 'Render nhanh và chạy rất ổn', 'body' => 'Hiệu năng tốt, chạy render nhanh hơn hẳn. Tản nhiệt vận hành êm.', 'order_id' => $orders[1]->id],
            ['name' => 'Phạm Thị Linh', 'rating' => 4, 'title' => 'Sản phẩm tốt', 'body' => 'Sản phẩm tốt trong tầm giá, đóng gói cẩn thận.', 'order_id' => $orders[2]->id],
        ];

        foreach ($featured as $index => $item) {
            $review = Review::create([
                'product_id' => $product->id,
                'order_id' => $item['order_id'],
                'guest_name' => $item['name'],
                'guest_email' => 'featured-review-'.$index.'@example.test',
                'rating' => $item['rating'],
                'title' => $item['title'],
                'body' => $item['body'],
                'is_approved' => true,
            ]);
            $review->media()->createMany([
                ['url' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=240&h=180&fit=crop', 'sort_order' => 0],
                ['url' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=240&h=180&fit=crop', 'sort_order' => 1],
            ]);
        }

        $counter = 1;
        foreach ([5 => 113, 4 => 10, 3 => 2] as $rating => $amount) {
            for ($i = 0; $i < $amount; $i++) {
                Review::create([
                    'product_id' => $product->id,
                    'guest_name' => 'Khách hàng '.str_pad((string) $counter, 3, '0', STR_PAD_LEFT),
                    'guest_email' => 'reference-review-'.$counter.'@example.test',
                    'rating' => $rating,
                    'title' => $rating === 5 ? 'Rất hài lòng' : 'Đánh giá sản phẩm',
                    'body' => 'Đánh giá demo dùng để kiểm thử giao diện trang chi tiết sản phẩm.',
                    'is_approved' => true,
                ]);
                $counter++;
            }
        }
    }

    private function seedQuestions(Product $product): void
    {
        $product->questions()->delete();
        $featured = [
            ['question' => 'RTX 4070 SUPER có cần nguồn 650W không?', 'answer' => 'Khuyến nghị sử dụng nguồn chất lượng tốt từ 650W.'],
            ['question' => 'Card này có hỗ trợ DLSS 3.5 không?', 'answer' => 'Có. RTX 4070 SUPER hỗ trợ DLSS 3.5.'],
            ['question' => 'Có thể dùng card để AI training không?', 'answer' => 'Có thể dùng cho nhiều workload AI với VRAM 12GB.'],
        ];

        foreach ($featured as $index => $item) {
            $question = ProductQuestion::create([
                'product_id' => $product->id,
                'guest_name' => 'Khách hàng',
                'guest_email' => 'demo-question-'.$index.'@example.test',
                'body' => $item['question'],
                'is_approved' => true,
            ]);
            $question->answers()->create(['body' => $item['answer'], 'is_official' => true, 'is_approved' => true]);
        }

        for ($i = 4; $i <= 16; $i++) {
            ProductQuestion::create([
                'product_id' => $product->id,
                'guest_name' => 'Khách hàng',
                'guest_email' => 'demo-question-'.$i.'@example.test',
                'body' => 'Câu hỏi demo #'.$i.' về sản phẩm RTX 4070 SUPER.',
                'is_approved' => true,
            ]);
        }
    }
}
