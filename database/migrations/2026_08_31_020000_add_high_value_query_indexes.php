<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'id'],
                'posts_created_at_id_index',
            );
            $table->index(
                ['user_id', 'created_at', 'id'],
                'posts_user_id_created_at_id_index',
            );
            $table->index(
                ['topic', 'created_at', 'id'],
                'posts_topic_created_at_id_index',
            );
            $table->index('repost_id', 'posts_repost_id_index');
        });

        Schema::table('hashtag_post', function (Blueprint $table): void {
            $table->index(
                ['hashtag_id', 'post_id'],
                'hashtag_post_hashtag_id_post_id_index',
            );
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->index(
                ['post_id', 'parent_id', 'created_at', 'id'],
                'comments_post_id_parent_id_created_at_id_index',
            );
        });

        Schema::table('likes', function (Blueprint $table): void {
            $table->index(
                ['post_id', 'user_id'],
                'likes_post_id_user_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('likes', function (Blueprint $table): void {
            $table->dropIndex('likes_post_id_user_id_index');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_post_id_parent_id_created_at_id_index');
        });

        Schema::table('hashtag_post', function (Blueprint $table): void {
            $table->dropIndex('hashtag_post_hashtag_id_post_id_index');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('posts_repost_id_index');
            $table->dropIndex('posts_topic_created_at_id_index');
            $table->dropIndex('posts_user_id_created_at_id_index');
            $table->dropIndex('posts_created_at_id_index');
        });
    }
};
