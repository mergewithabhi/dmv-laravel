<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->unique()->constrained('media_assets')->restrictOnDelete();
            $table->string('title');
            $table->text('caption')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('navigation_items')
            ->where('location', 'primary')
            ->where('label', 'Policies')
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ([
            ['location' => 'primary', 'position' => 55],
            ['location' => 'footer', 'position' => 55],
        ] as $menu) {
            DB::table('navigation_items')->updateOrInsert(
                ['location' => $menu['location'], 'label' => 'Gallery'],
                [
                    'parent_id' => null,
                    'url' => '/gallery',
                    'icon_media_id' => null,
                    'target' => '_self',
                    'position' => $menu['position'],
                    'is_enabled' => true,
                    'deleted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        Cache::forget('site.chrome.scalar');
    }

    public function down(): void
    {
        DB::table('navigation_items')
            ->where('label', 'Gallery')
            ->whereIn('location', ['primary', 'footer'])
            ->delete();
        DB::table('navigation_items')
            ->where('location', 'primary')
            ->where('label', 'Policies')
            ->update(['is_enabled' => true, 'updated_at' => now()]);

        Schema::dropIfExists('gallery_items');
        Cache::forget('site.chrome.scalar');
    }
};
