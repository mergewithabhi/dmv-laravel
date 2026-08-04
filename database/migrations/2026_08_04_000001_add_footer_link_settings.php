<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('site_settings')->insertOrIgnore([
            [
                'group' => 'footer',
                'key' => 'footer.link_text',
                'label' => 'Footer link text',
                'type' => 'text',
                'value' => json_encode(['value' => '']),
                'is_public' => true,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'footer',
                'key' => 'footer.link_url',
                'label' => 'Footer link URL',
                'type' => 'url',
                'value' => json_encode(['value' => '']),
                'is_public' => true,
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->whereIn('key', ['footer.link_text', 'footer.link_url'])
            ->delete();
    }
};
