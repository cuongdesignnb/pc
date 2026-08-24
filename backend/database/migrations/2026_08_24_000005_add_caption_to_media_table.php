<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media') && ! Schema::hasColumn('media', 'caption')) {
            Schema::table('media', function (Blueprint $table) {
                $table->text('caption')->nullable()->after('alt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media') && Schema::hasColumn('media', 'caption')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropColumn('caption');
            });
        }
    }
};
