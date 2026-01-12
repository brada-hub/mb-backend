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
        if (Schema::hasTable('recursos')) {
            DB::statement('ALTER TABLE recursos ALTER COLUMN id_voz DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recursos')) {
            DB::statement('ALTER TABLE recursos ALTER COLUMN id_voz SET NOT NULL');
        }
    }
};
