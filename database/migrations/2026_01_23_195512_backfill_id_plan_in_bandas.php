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
        $plans = DB::table('plans')->get()->pluck('id_plan', 'nombre');

        foreach ($plans as $nombre => $id) {
            DB::table('bandas')
                ->where('plan', $nombre)
                ->update(['id_plan' => $id]);
        }

        // Set default BASIC for others
        $basicId = $plans['BASIC'] ?? null;
        if ($basicId) {
            DB::table('bandas')
                ->whereNull('id_plan')
                ->update(['id_plan' => $basicId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bandas', function (Blueprint $table) {
            //
        });
    }
};
