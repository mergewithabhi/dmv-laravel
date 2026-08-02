<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'navigation_items',
        'social_links',
        'redirects',
        'seasons',
        'teams',
        'venues',
        'roster_memberships',
        'staff_assignments',
        'standings',
        'categories',
        'sponsor_tiers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
