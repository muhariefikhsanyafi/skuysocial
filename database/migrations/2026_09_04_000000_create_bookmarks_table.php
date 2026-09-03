<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(
                ['user_id', 'post_id'],
                'bookmarks_user_id_post_id_unique',
            );
            $table->index(
                ['user_id', 'created_at', 'id'],
                'bookmarks_user_id_created_at_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
