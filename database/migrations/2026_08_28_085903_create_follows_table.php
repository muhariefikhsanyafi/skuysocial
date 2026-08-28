<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            // ID orang yang menekan tombol follow (Pengikut)
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            // ID orang yang di-follow (Yang Diikuti)
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Mencegah duplikasi (1 user hanya bisa follow user yang sama 1 kali)
            $table->unique(['follower_id', 'following_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('follows');
    }
};