<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate (cộng tác viên) program: a CTV shares ?ref=CODE links, the first
 * CTV to bring a customer keeps that customer's phone number for good, and
 * every order from that phone earns the CTV a commission.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['affiliate_referral_discount', '200000', 'number', 'Số tiền giảm cho khách mua lần đầu bằng mã CTV (VNĐ)'],
        ['affiliate_discount_min_non_laptop', '2000000', 'number', 'Đơn không có laptop phải từ số tiền này mới được giảm (VNĐ)'],
        ['affiliate_laptop_category_slugs', '[]', 'json', 'Danh mục laptop (đơn có hàng thuộc các danh mục này luôn được giảm)'],
        ['affiliate_approve_days', '7', 'number', 'Số ngày sau khi giao hàng thì hoa hồng tự chuyển sang Được rút'],
        ['affiliate_min_payout', '100000', 'number', 'Số dư tối thiểu mỗi lần rút (VNĐ)'],
        ['affiliate_id_card_required', '0', 'boolean', 'Bắt CTV nhập số CCCD khi đăng ký'],
    ];

    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->nullable()->unique();
            $table->string('phone', 20)->index();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('id_card_number', 20)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->json('payout_days')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('landing_url', 1000)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // One row per customer phone: the unique phone is what keeps the
        // first CTV's claim on the customer.
        Schema::create('affiliate_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('link');
            $table->foreignId('first_discount_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Sales made by phone or in the shop, recorded by staff.
        Schema::create('affiliate_external_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 20);
            $table->string('kiot_invoice_code', 50)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 0);
            $table->date('sold_at');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('external_order_id')->nullable()->unique()->constrained('affiliate_external_orders')->nullOnDelete();
            $table->decimal('base_amount', 15, 0)->default(0);
            $table->decimal('amount', 15, 0)->default(0);
            $table->json('breakdown')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approve_after')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('affiliate_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('referral_code', 20)->nullable()->after('affiliate_id');
            $table->decimal('referral_discount', 15, 0)->default(0)->after('discount');
        });

        // {type: percent|fixed|tiers, value, per: item|order, tiers: [{min_price, amount}]}
        Schema::table('categories', function (Blueprint $table): void {
            $table->json('affiliate_commission')->nullable()->after('warranty_packages');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->json('affiliate_commission')->nullable()->after('warranty_months');
        });

        foreach (self::SETTINGS as [$key, $value, $type, $label]) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'affiliate',
                'type' => $type,
                'options' => null,
                'label' => $label,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
        Cache::forget('settings.public');

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('affiliate_commission'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('affiliate_commission'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('affiliate_id');
            $table->dropColumn(['referral_code', 'referral_discount']);
        });
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_external_orders');
        Schema::dropIfExists('affiliate_customers');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliates');
    }
};
