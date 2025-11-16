<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mabar_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->onDelete('cascade');
            // booking_id menunjuk ke tabel bookings
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['open_play', 'team_challenge', 'mini_tournament']);
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('slots_total');
            $table->integer('price_per_slot');
            $table->text('payment_instructions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mabar_sessions');
    }
};
