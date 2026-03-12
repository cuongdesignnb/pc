<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('media');

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // display name
            $table->string('file_name');               // original filename
            $table->string('path');                     // relative path on disk
            $table->string('disk')->default('public');  // storage disk
            $table->string('mime_type');
            $table->unsignedBigInteger('size');         // bytes
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('folder')->default('/');     // virtual folder
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('folder');
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
