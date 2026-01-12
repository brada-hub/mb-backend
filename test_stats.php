<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

try {
    $totalConvocados = DB::table('convocatoria_evento')
        ->join('eventos', 'convocatoria_evento.id_evento', '=', 'eventos.id_evento')
        ->where('eventos.fecha', '<', Carbon::today()->toDateString())
        ->count();
    echo "Total Convocados: $totalConvocados\n";

    $year = date('Y');
    $heatmapData = DB::table('eventos')
        ->join('convocatoria_evento', 'eventos.id_evento', '=', 'convocatoria_evento.id_evento')
        ->leftJoin('asistencias', 'convocatoria_evento.id_convocatoria', '=', 'asistencias.id_convocatoria')
        ->select(
            'eventos.fecha as date',
            DB::raw("STRING_AGG(DISTINCT eventos.evento, ', ') as event_names"),
            DB::raw('COUNT(convocatoria_evento.id_convocatoria) as total_convocados'),
            DB::raw("SUM(CASE WHEN asistencias.estado IN ('PUNTUAL', 'RETRASO') THEN 1 ELSE 0 END) as total_presentes")
        )
        ->whereYear('eventos.fecha', $year)
        ->groupBy('eventos.fecha')
        ->get();
    echo "Heatmap Count: " . count($heatmapData) . "\n";
    echo "DONE\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
