<?php

use App\Support\PolicyPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Information Bộ Công Thương asks an e-commerce site to show (Nghị định
 * 37/2026/NĐ-CP): the business behind the site in the footer, the required
 * policy pages, and per-product year of manufacture, warnings, usage and
 * storage instructions and the "sản phẩm tân trang làm mới" label.
 *
 * Policy pages are ordinary admin Pages; pages the shop already wrote under
 * the same slug are kept. {{tokens}} in their body are filled
 * from these settings when the page is served (see PolicyPageTokens).
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['legal_business_name', 'HỘ KINH DOANH SHOP CÔNG NGHỆ LAPTOPPLUS', 'text', 'Tên chủ quản website (hộ kinh doanh / doanh nghiệp)'],
        ['legal_registration_number', '', 'text', 'Số Giấy chứng nhận đăng ký hộ kinh doanh / doanh nghiệp'],
        ['legal_registration_date', '', 'text', 'Ngày cấp (VD: 15/03/2024)'],
        ['legal_registration_place', '', 'text', 'Nơi cấp (VD: UBND phường Xuân Phương, Hà Nội)'],
        ['legal_head_office_address', null, 'text', 'Địa chỉ trụ sở chính'],
        ['legal_email', null, 'text', 'Email tiếp nhận thông tin, phản ánh, khiếu nại'],
        ['legal_representative', '', 'text', 'Người đại diện theo pháp luật (họ tên, chức danh)'],
        ['legal_operator', '', 'text', 'Người chịu trách nhiệm quản lý và vận hành website'],
    ];

    private const PRODUCT_SETTINGS = [
        ['product_default_warning_info', "- Không để máy tiếp xúc với nước, chất lỏng, nơi ẩm ướt hoặc nhiệt độ cao.\n- Sử dụng đúng bộ sạc, điện áp theo khuyến cáo của nhà sản xuất; không dùng sạc không rõ nguồn gốc.\n- Không tự ý tháo, mở máy hoặc pin; pin bị phồng, rò rỉ phải ngừng sử dụng và mang đến cửa hàng kiểm tra.\n- Không đặt máy trên chăn, đệm hoặc bề mặt che khe tản nhiệt khi đang hoạt động.\n- Để xa tầm tay trẻ em đối với linh kiện nhỏ và phụ kiện.", 'textarea', 'Thông tin cảnh báo mặc định (dùng khi sản phẩm và danh mục chưa nhập)'],
        ['product_default_usage_instructions', "Hướng dẫn sử dụng:\n- Đọc kỹ hướng dẫn của nhà sản xuất đi kèm sản phẩm trước khi sử dụng.\n- Lần đầu sử dụng: sạc đầy pin, cập nhật hệ điều hành và driver theo hướng dẫn của nhân viên kỹ thuật.\n- Tắt máy đúng cách trước khi di chuyển hoặc cất giữ.\n\nHướng dẫn bảo quản:\n- Bảo quản nơi khô ráo, thoáng mát, tránh ánh nắng trực tiếp và va đập.\n- Vệ sinh máy bằng khăn mềm, khô; không dùng hóa chất tẩy rửa mạnh.\n- Nếu không sử dụng lâu ngày, sạc pin khoảng 50–60% và sạc lại định kỳ 1–2 tháng/lần.", 'textarea', 'Hướng dẫn sử dụng và bảo quản mặc định (dùng khi sản phẩm và danh mục chưa nhập)'],
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('manufacture_year')->nullable()->after('warranty_months');
            // null = follow the category
            $table->boolean('is_refurbished')->nullable()->after('manufacture_year');
            $table->text('warning_info')->nullable()->after('is_refurbished');
            $table->text('usage_instructions')->nullable()->after('warning_info');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('is_refurbished')->default(false)->after('warranty_packages');
            $table->text('warning_info')->nullable()->after('is_refurbished');
            $table->text('usage_instructions')->nullable()->after('warning_info');
        });

        $contact = DB::table('settings')->whereIn('key', ['contact_address', 'contact_email'])->pluck('value', 'key');
        $defaults = [
            'legal_head_office_address' => (string) ($contact['contact_address'] ?? ''),
            'legal_email' => (string) ($contact['contact_email'] ?? ''),
        ];
        foreach (self::SETTINGS as [$key, $value, $type, $label]) {
            $this->insertSetting($key, $value ?? $defaults[$key], $type, $label, 'legal', true);
        }
        foreach (self::PRODUCT_SETTINGS as [$key, $value, $type, $label]) {
            $this->insertSetting($key, $value, $type, $label, 'storefront', true);
        }
        Cache::forget('settings.public');

        // Feature tests expect an empty pages table and create pages themselves.
        if (! app()->runningUnitTests()) {
            PolicyPages::install();
        }
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', array_column(require __DIR__.'/../data/policy_pages.php', 'slug'))->delete();
        DB::table('settings')->whereIn('key', [...array_column(self::SETTINGS, 0), ...array_column(self::PRODUCT_SETTINGS, 0)])->delete();
        Cache::forget('settings.public');
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['is_refurbished', 'warning_info', 'usage_instructions']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['manufacture_year', 'is_refurbished', 'warning_info', 'usage_instructions']));
    }

    private function insertSetting(string $key, string $value, string $type, string $label, string $group, bool $public): void
    {
        if (DB::table('settings')->where('key', $key)->exists()) {
            return;
        }
        DB::table('settings')->insert([
            'key' => $key,
            'value' => $value,
            'group' => $group,
            'type' => $type,
            'options' => null,
            'label' => $label,
            'is_public' => $public,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
