<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'navigation_items',
            'social_links',
            'redirects',
            'seasons',
            'teams',
            'venues',
            'people',
            'roster_memberships',
            'staff_assignments',
            'games',
            'standings',
            'categories',
            'posts',
            'sponsor_tiers',
            'sponsors',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }

        foreach (['people', 'seasons', 'teams', 'venues', 'posts', 'sponsors'] as $table) {
            DB::table($table)
                ->where('workflow_status', 'in_review')
                ->update([
                    'workflow_status' => 'draft',
                    'submitted_by' => null,
                ]);
            DB::table($table)->where('status', 'in_review')->update(['status' => 'draft']);
        }

        DB::table('games')
            ->where('workflow_status', 'in_review')
            ->update([
                'workflow_status' => 'draft',
                'submitted_by' => null,
            ]);
        DB::table('games')
            ->where('publication_status', 'in_review')
            ->update(['publication_status' => 'draft']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'navigation_items',
            'social_links',
            'redirects',
            'seasons',
            'teams',
            'venues',
            'people',
            'roster_memberships',
            'staff_assignments',
            'games',
            'standings',
            'categories',
            'posts',
            'sponsor_tiers',
            'sponsors',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
