<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('comment_likes', function (Blueprint $table) {
            $table->dropUnique(['comment_id', 'user_id']);
        });
    }
};
