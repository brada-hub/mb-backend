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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('id_audit_log');
            $table->unsignedBigInteger('id_user')->nullable();
            $table->unsignedBigInteger('id_banda')->nullable();
            $table->string('event'); // created, updated, deleted, upload, login, security
            $table->string('auditable_type')->nullable(); // Model class
            $table->unsignedBigInteger('auditable_id')->nullable(); // Model ID
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            // Foreign keys if necessary, or just indexes for speed
            $table->index(['id_user', 'id_banda']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
