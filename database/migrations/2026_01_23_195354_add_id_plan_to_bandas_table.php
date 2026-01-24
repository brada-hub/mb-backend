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
        Schema::table('bandas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_plan')->nullable()->after('plan');
            $table->foreign('id_plan')->references('id_plan')->on('plans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bandas', function (Blueprint $table) {
            $table->dropForeign(['id_plan']);
            $table->dropColumn('id_plan');
        });
    }
};
