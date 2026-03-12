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
        Schema::create('compatibility_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_type_id')->constrained('component_types')->cascadeOnDelete();
            $table->foreignId('target_type_id')->constrained('component_types')->cascadeOnDelete();
            $table->string('source_spec_key');
            $table->string('target_spec_key');
            $table->enum('rule_type', ['must_match', 'must_fit', 'must_fit_dimension', 'must_contain', 'power_check'])->default('must_match');
            $table->json('allowed_values')->nullable();
            $table->integer('power_headroom')->nullable();
            $table->string('message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compatibility_rules');
    }
};
