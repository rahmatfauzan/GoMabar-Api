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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name_orders')->nullable();
            $table->string('phone_orders')->nullable();
            $table->string('invoice_number')->unique();
            $table->enum('status', ['waiting_payment', 'active', 'failed', 'cancelled'])->default('waiting_payment');
            $table->foreignId('field_id')->constrained('fields')->onDelete('cascade');
            $table->date('booking_date');
            $table->json('booked_slots');
            $table->integer('price');
            // Kolom mabar_session_id DIHAPUS dari sini
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
