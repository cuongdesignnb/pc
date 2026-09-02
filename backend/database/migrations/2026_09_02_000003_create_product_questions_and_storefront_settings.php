<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_email')->nullable();
            $table->text('body');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_approved', 'created_at']);
        });

        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('product_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_official')->default(true);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->index(['question_id', 'is_approved', 'created_at']);
        });

        $settings = [
            ['storefront_authenticity_message', 'Cam kết chính hãng'],
            ['storefront_return_policy_short', 'Chính sách đổi trả ngắn'],
            ['storefront_delivery_policy_short', 'Chính sách giao hàng ngắn'],
            ['storefront_technical_support_short', 'Hỗ trợ kỹ thuật ngắn'],
            ['storefront_installment_message', 'Thông tin trả góp'],
        ];

        foreach ($settings as [$key, $label]) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => '',
                    'group' => 'storefront',
                    'type' => 'text',
                    'label' => $label,
                    'is_public' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_answers');
        Schema::dropIfExists('product_questions');

        DB::table('settings')->whereIn('key', [
            'storefront_authenticity_message',
            'storefront_return_policy_short',
            'storefront_delivery_policy_short',
            'storefront_technical_support_short',
            'storefront_installment_message',
        ])->delete();
    }
};
