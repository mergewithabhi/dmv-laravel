<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'people',
        'seasons',
        'teams',
        'venues',
        'games',
        'posts',
        'sponsors',
    ];

    private const TABLES_WITHOUT_APPROVAL_COLUMNS = [
        'seasons',
        'teams',
        'venues',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('workflow_status', 24)->default('draft')->index();
                $table->json('draft_snapshot')->nullable();
                $table->unsignedInteger('draft_lock_version')->default(0);
                $table->timestamp('draft_saved_at')->nullable();
            });

            $statusColumn = $tableName === 'games' ? 'publication_status' : 'status';
            DB::table($tableName)->update([
                'workflow_status' => DB::raw($statusColumn),
            ]);
        }

        foreach (self::TABLES_WITHOUT_APPROVAL_COLUMNS as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('publish_at')->nullable()->index();
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES_WITHOUT_APPROVAL_COLUMNS as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('submitted_by');
                $table->dropConstrainedForeignId('approved_by');
                $table->dropIndex(['publish_at']);
                $table->dropColumn('publish_at');
            });
        }

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['workflow_status']);
                $table->dropColumn([
                    'workflow_status',
                    'draft_snapshot',
                    'draft_lock_version',
                    'draft_saved_at',
                ]);
            });
        }
    }
};
