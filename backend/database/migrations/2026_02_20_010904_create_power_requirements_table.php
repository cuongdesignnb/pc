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
        Schema::create('power_requirements', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->cascadeOnDelete();
            $table->integer('typical_tdp')->nullable()->comment('watts');
            $table->integer('peak_tdp')->nullable()->comment('watts');
            $table->boolean('requires_pcie_power')->default(false);
            $table->integer('pcie_connectors_needed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_requirements');
    }
};
