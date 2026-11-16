<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * media.public_slug: the fixed link /files/{public_slug}. It survives "Thay
 * file" (replace), so a PDF or driver link shared with customers keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('media', 'public_slug')) {
            Schema::table('media', function (Blueprint $table) {
                $table->string('public_slug', 191)->nullable()->unique()->after('path');
            });
        }

        $used = DB::table('media')->whereNotNull('public_slug')->pluck('public_slug')->flip()->all();
        DB::table('media')->whereNull('public_slug')->select(['id', 'name', 'file_name'])
            ->chunkById(500, function ($rows) use (&$used): void {
                foreach ($rows as $row) {
                    $base = Media::slugCandidate((string) ($row->name ?: pathinfo((string) $row->file_name, PATHINFO_FILENAME)));
                    $unique = $base;
                    for ($i = 2; isset($used[$unique]); $i++) {
                        $unique = $base.'-'.$i;
                    }
                    $used[$unique] = true;
                    DB::table('media')->where('id', $row->id)->update(['public_slug' => $unique]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('media', 'public_slug')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropUnique(['public_slug']);
                $table->dropColumn('public_slug');
            });
        }
    }
};
