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
        Schema::table('instrumentos', function (Blueprint $table) {
            $table->string('icon_slug')->nullable()->after('instrumento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrumentos', function (Blueprint $table) {
            $table->dropColumn('icon_slug');
        });
    }
};
