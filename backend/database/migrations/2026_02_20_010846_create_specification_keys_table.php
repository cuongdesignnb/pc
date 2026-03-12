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
        Schema::create('specification_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('label');
            $table->enum('data_type', ['string', 'integer', 'decimal', 'boolean'])->default('string');
            $table->string('unit')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['component_type_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specification_keys');
    }
};
