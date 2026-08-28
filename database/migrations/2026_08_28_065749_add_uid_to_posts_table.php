<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Post;

return new class extends Migration
{
    public function up()
    {
        // 1. Tambahkan kolom uid di tabel posts
        Schema::table('posts', function (Blueprint $table) {
            $table->string('uid', 20)->nullable()->unique()->after('id');
        });

        // 2. Buatkan UID acak 11 karakter (seperti Threads/YouTube) untuk postingan yang sudah ada
        foreach (Post::all() as $post) {
            $post->uid = Str::random(11);
            $post->save();
        }
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};