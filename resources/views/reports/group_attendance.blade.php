<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Asistencia</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .summary { margin-bottom: 20px; display: flex; justify-content: space-between; }
        .summary-box { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: inline-block; width: 30%; text-align: center; }
        .danger { color: #dc2626; }
        .success { color: #16a34a; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Asistencia Grupal</h1>
        <p>Periodo: {{ $filters['start_date'] }} al {{ $filters['end_date'] }}</p>
        @if($filters['id_seccion'])
            <p>Sección: {{ $filters['id_seccion'] }}</p>
        @endif
    </div>

    <div class="summary">
        <div class="summary-box">
            <h3>{{ $summary['group_average'] }}%</h3>
            <p>Asistencia Promedio</p>
        </div>
        <div class="summary-box">
            <h3>{{ $summary['total_members_in_report'] }}</h3>
            <p>Miembros Evaluados</p>
        </div>
        <div class="summary-box">
            <h3 class="danger">{{ $summary['desertores_count'] }}</h3>
            <p>En Riesgo</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Instrumento</th>
                <th style="text-align: center;">Total Eventos</th>
                <th style="text-align: center;">Asistencias</th>
                <th style="text-align: center;">Faltas</th>
                <th style="text-align: center;">% Efectividad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item['nombres'] }} {{ $item['apellidos'] }}</td>
                <td>{{ $item['instrumento'] }}</td>
                <td style="text-align: center;">{{ $item['total_events'] }}</td>
                <td style="text-align: center;">{{ $item['present_count'] }}</td>
                <td style="text-align: center;">{{ $item['absent_count'] }}</td>
                <td style="text-align: center;">
                    <span class="{{ $item['rate'] >= 80 ? 'success' : ($item['rate'] < 50 ? 'danger' : '') }}">
                        {{ $item['rate'] }}%
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($desertores) > 0)
    <div style="margin-top: 30px;">
        <h3 class="danger">Miembros en Riesgo (Baja Asistencia)</h3>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Instrumento</th>
                    <th>Tasa de Asistencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach($desertores as $item)
                <tr>
                    <td>{{ $item['nombres'] }} {{ $item['apellidos'] }}</td>
                    <td>{{ $item['instrumento'] }}</td>
                    <td class="danger">{{ $item['rate'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Generado el {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>
