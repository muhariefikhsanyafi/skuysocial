<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_appreciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->unsignedTinyInteger('points_spent');
            $table->timestamps();
            $table->unique(
                ['user_id', 'post_id'],
                'post_appreciations_user_post_unique',
            );
            $table->index(
                ['post_id', 'type'],
                'post_appreciations_post_type_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_appreciations');
    }
};
