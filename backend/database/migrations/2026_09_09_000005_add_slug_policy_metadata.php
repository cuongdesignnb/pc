<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['categories', 'products', 'posts', 'post_categories', 'pages', 'brands'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('slug_source')->nullable();
                $table->string('slug_policy_version', 32)->nullable();
                $table->timestamp('slug_locked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['slug_source', 'slug_policy_version', 'slug_locked_at']);
            });
        }
    }
};
