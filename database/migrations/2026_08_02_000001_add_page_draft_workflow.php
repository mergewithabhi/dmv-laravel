<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('workflow_status', 24)->default('published')->index();
            $table->json('draft_snapshot')->nullable();
            $table->unsignedInteger('draft_lock_version')->default(0);
            $table->timestamp('draft_saved_at')->nullable();
        });

        DB::table('pages')->update([
            'workflow_status' => DB::raw('status'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropIndex(['workflow_status']);
            $table->dropColumn([
                'workflow_status',
                'draft_snapshot',
                'draft_lock_version',
                'draft_saved_at',
            ]);
        });
    }
};
