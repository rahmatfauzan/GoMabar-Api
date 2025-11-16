<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/...._create_field_operating_hours_table.php
public function up(): void
{
    Schema::create('field_operating_hours', function (Blueprint $table) {
        $table->id();
        $table->foreignId('field_id')->constrained('fields')->onDelete('cascade');
        $table->tinyInteger('day_of_week'); // 1=Senin, 7=Minggu
        
        // --- TAMBAHKAN KOLOM INI ---
        $table->boolean('is_open')->default(true); // Default-nya Buka
        // -------------------------

        $table->time('start_time')->default('08:00'); // Beri default
        $table->time('end_time')->default('22:00');   // Beri default
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_operating_hours');
    }
};
