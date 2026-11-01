<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Withdrawals of a CTV's approved commission. A payout is created on one of
 * the CTV's payout days (or when the CTV asks), stays "pending" until staff
 * transfer the money and press "Đã chuyển", then becomes "paid" (Đã rút).
 * The bank details are copied so a later change does not rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20)->default('schedule');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // The discount now applies to every order of a CTV's customer.
        DB::table('settings')->where('key', 'affiliate_referral_discount')
            ->update(['label' => 'Số tiền giảm cho mỗi đơn của khách CTV (VNĐ)']);

        // The shop's Pixel "Laptopplus.vn Event Data". The field held the
        // fanpage URL, which is not a Pixel ID, so the Pixel never loaded; a
        // valid ID already entered is left alone.
        $pixel = DB::table('settings')->where('key', 'facebook_pixel_id')->first();
        if (! app()->runningUnitTests() && $pixel && preg_match('/^\d{6,20}$/', trim((string) $pixel->value)) !== 1) {
            DB::table('settings')->where('key', 'facebook_pixel_id')->update(['value' => '280692737984096']);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
    }
};
