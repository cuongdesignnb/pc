<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable()->after('avatar');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('ward_code', 20)->nullable()->after('ward');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn(['province_code', 'ward_code']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['date_of_birth', 'gender']);
        });
    }
};
