<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specification_key_id')->constrained()->cascadeOnDelete();
            $table->string('value_string')->nullable();
            $table->decimal('value_numeric', 15, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'specification_key_id']);
            $table->index(['specification_key_id', 'value_string']);
            $table->index(['specification_key_id', 'value_numeric']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
