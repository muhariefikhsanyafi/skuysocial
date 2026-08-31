<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hashtag_post', function (Blueprint $table): void {
            $table->unique(
                ['post_id', 'hashtag_id'],
                'hashtag_post_post_id_hashtag_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('hashtag_post', function (Blueprint $table): void {
            $table->dropUnique('hashtag_post_post_id_hashtag_id_unique');
        });
    }
};
