<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article authors (Nội dung → Tác giả). posts.author_id is optional: a post
 * without one keeps showing the admin user who wrote it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('slug', 160)->unique();
                $table->string('avatar', 500)->nullable();
                $table->string('job_title', 150)->nullable();
                $table->text('bio')->nullable();
                $table->json('social_links')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('posts', 'author_id')) {
            Schema::table('posts', function (Blueprint $table) {
                // No database constraint: deleting an author clears the column in
                // the admin controller, which behaves the same on SQLite and MySQL.
                $table->unsignedBigInteger('author_id')->nullable()->after('user_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('posts', 'author_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex(['author_id']);
                $table->dropColumn('author_id');
            });
        }
        Schema::dropIfExists('authors');
    }
};
