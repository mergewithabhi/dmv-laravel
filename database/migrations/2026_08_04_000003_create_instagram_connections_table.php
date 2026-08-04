<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('instagram_user_id')->unique();
            $table->string('username')->nullable();
            $table->text('access_token');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_connections');
    }
};
