<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key'       => 'site_logo',
                'value'     => null,
                'group'     => 'general',
                'type'      => 'image',
                'label'     => 'Logo website',
                'is_public' => true,
            ],
            [
                'key'       => 'site_logo_white',
                'value'     => null,
                'group'     => 'general',
                'type'      => 'image',
                'label'     => 'Logo trắng (cho nền tối)',
                'is_public' => true,
            ],
            [
                'key'       => 'site_favicon',
                'value'     => null,
                'group'     => 'general',
                'type'      => 'image',
                'label'     => 'Favicon',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(
                ['key' => $s['key']],
                array_merge($s, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'site_logo',
            'site_logo_white',
            'site_favicon',
        ])->delete();
    }
};
