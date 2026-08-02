<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 40)->default('*');
            $table->string('section_key', 80)->default('*');
            $table->string('field_group', 24)->default('*');
            $table->timestamps();
            $table->unique(
                ['user_id', 'template_key', 'section_key', 'field_group'],
                'content_permissions_grant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_permissions');
    }
};
