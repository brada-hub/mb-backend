<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * TABLA: NOTIFICACIONES
     * ═══════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->onDelete('cascade');

            $table->string('tipo', 50); // evento, pago, sistema, etc.
            $table->string('titulo', 200);
            $table->text('mensaje');
            $table->json('data')->nullable(); // Datos adicionales

            $table->boolean('leida')->default(false);
            $table->timestamp('leida_at')->nullable();

            $table->boolean('enviada_whatsapp')->default(false);
            $table->timestamp('enviada_whatsapp_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
