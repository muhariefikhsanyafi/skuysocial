<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('pinned_at')->nullable()->index();
        });

        Schema::create('user_pinned_posts', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('pinned_at');
        });

        Schema::create('post_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('question', 180);
            $table->timestamps();
        });

        Schema::create('post_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained('post_polls')->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['poll_id', 'position']);
            $table->unique(['poll_id', 'id'], 'post_poll_options_poll_id_id_unique');
        });

        Schema::create('post_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('option_id');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->unique(['poll_id', 'user_id']);
            $table->index('option_id');
            $table->index('user_id');
            $table->foreign(['poll_id', 'option_id'], 'post_poll_votes_poll_option_foreign')
                ->references(['poll_id', 'id'])
                ->on('post_poll_options')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_poll_votes');
        Schema::dropIfExists('post_poll_options');
        Schema::dropIfExists('post_polls');
        Schema::dropIfExists('user_pinned_posts');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['pinned_at']);
            $table->dropColumn('pinned_at');
        });
    }
};
