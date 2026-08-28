<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            // Menambahkan kolom repost_id yang merujuk ke tabel posts itu sendiri
            $table->foreignId('repost_id')->nullable()->constrained('posts')->cascadeOnDelete()->after('id');
            // Mengizinkan kolom konten kosong khusus untuk tindakan repost
            $table->string('content', 1000)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['repost_id']);
            $table->dropColumn('repost_id');
            $table->string('content', 1000)->nullable(false)->change();
        });
    }
};