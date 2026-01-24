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
        Schema::create('plans', function (Blueprint $table) {
            $table->id('id_plan');
            $table->string('nombre')->unique(); // BASIC, PREMIUM, etc
            $table->string('label'); // Nombre visible
            $table->integer('max_miembros')->default(15);
            $table->integer('storage_mb')->default(100);
            $table->boolean('can_upload_audio')->default(false);
            $table->boolean('can_upload_video')->default(false);
            $table->boolean('gps_attendance')->default(false);
            $table->boolean('custom_branding')->default(false);
            $table->decimal('precio_base', 10, 2)->default(0);
            $table->json('features')->nullable(); // Lista de features para el frontend
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
