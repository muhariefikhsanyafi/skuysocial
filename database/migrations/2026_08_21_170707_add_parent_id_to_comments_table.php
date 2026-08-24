<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Menambahkan parent_id. cascadeOnDelete memastikan jika komentar induk dihapus, balasannya ikut terhapus
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete()->after('post_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};