<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')
            ->where('section_key', 'community')
            ->whereIn('page_id', DB::table('pages')->where('template_key', 'home')->select('id'))
            ->update(['label' => 'Community and Instagram']);
    }

    public function down(): void
    {
        DB::table('page_sections')
            ->where('section_key', 'community')
            ->whereIn('page_id', DB::table('pages')->where('template_key', 'home')->select('id'))
            ->update(['label' => 'Community and social gallery']);
    }
};
