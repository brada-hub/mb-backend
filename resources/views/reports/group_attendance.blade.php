<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Asistencia Grupal - SIMBA</title>
    <style>
        @page { margin: 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; background-color: #fff; }

        /* Brand Colors */
        .text-primary { color: #4f46e5; }
        .bg-primary { background-color: #4f46e5; }
        .text-danger { color: #ef4444; }
        .text-success { color: #10b981; }

        .header { margin-bottom: 30px; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; position: relative; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.5px; color: #111827; }
        .header p { margin: 4px 0; color: #6b7280; font-weight: 500; font-size: 11px; }

        table.summary-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .summary-box {
            background: #f9fafb;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
        }
        .summary-box h3 { margin: 0; font-size: 18px; font-weight: 900; color: #111827; }
        .summary-box p { margin: 3px 0 0; color: #6b7280; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }

        .instrument-header {
            background-color: #f8fafc;
            padding: 8px 12px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #4f46e5;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #4f46e5;
        }
        .instrument-header h2 {
            margin: 0;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        table.data-table th {
            padding: 10px 8px;
            text-align: left;
            background-color: #f1f5f9;
            color: #64748b;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #e2e8f0;
        }
        table.data-table td {
            padding: 8px;
            vertical-align: middle;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }

        .name-cell { font-weight: 700; color: #0f172a; width: 45%; }
        .stat-cell { text-align: center; font-weight: 600; color: #475569; width: 13%; }
        .rate-cell { text-align: right; font-weight: 900; font-size: 12px; width: 16%; }

        .footer { position: fixed; bottom: -20px; left: 0; right: 0; height: 30px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div style="float: right; text-align: right; margin-top: 5px;">
            <p style="color: #4f46e5; font-weight: 900; font-size: 14px;">{{ $banda['nombre'] }}</p>
            <p>Reporte de Gestión</p>
        </div>

        @if($banda['logo'])
            <img src="{{ $banda['logo'] }}" style="float: left; height: 50px; margin-right: 15px;">
        @endif

        <h1>Análisis de Asistencia</h1>
        <p>Periodo: <strong>{{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}</strong> al <strong>{{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}</strong></p>
        <div style="clear: both;"></div>
    </div>

    <table class="summary-table">
        <tr>
            <td style="padding-right: 10px; width: 33%;">
                <div class="summary-box">
                    <h3>{{ $summary['group_average'] }}%</h3>
                    <p>Efectividad Musical</p>
                </div>
            </td>
            <td style="padding: 0 10px; width: 33%;">
                <div class="summary-box">
                    <h3>{{ $summary['total_members_in_report'] }}</h3>
                    <p>Integrantes Activos</p>
                </div>
            </td>
            <td style="padding-left: 10px; width: 33%;">
                <div class="summary-box">
                    <h3 class="{{ $summary['desertores_count'] > 0 ? 'text-danger' : '' }}">{{ $summary['desertores_count'] }}</h3>
                    <p>Alertas de Riesgo</p>
                </div>
            </td>
        </tr>
    </table>

    @php
        $orderMap = [
            'PLATILLO' => 1, 'TAMBOR' => 2, 'TIMBAL' => 3, 'BOMBO' => 4,
            'TROMBON' => 5, 'CLARINETE' => 6, 'BARITONO' => 7, 'TROMPETA' => 8, 'HELICON' => 9
        ];

        $grouped = collect($report)->groupBy('instrumento')->sortBy(function($mems, $key) use ($orderMap) {
            return $orderMap[strtoupper($key)] ?? 99;
        });
    @endphp

    @foreach($grouped as $instrumento => $miembros)
        <div class="instrument-header">
            <h2>{{ $instrumento }}</h2>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="name-cell">Integrante</th>
                    <th class="stat-cell">Eventos</th>
                    <th class="stat-cell">Asist.</th>
                    <th class="stat-cell">Faltas</th>
                    <th class="rate-cell">Desempeño</th>
                </tr>
            </thead>
            <tbody>
                @foreach($miembros as $item)
                <tr>
                    <td class="name-cell">{{ $item['nombres'] }} {{ $item['apellidos'] }}</td>
                    <td class="stat-cell">{{ $item['total_events'] }}</td>
                    <td class="stat-cell" style="color: #10b981;">{{ $item['present_count'] }}</td>
                    <td class="stat-cell" style="color: #ef4444;">{{ $item['absent_count'] + $item['unmarked_count'] }}</td>
                    <td class="rate-cell">
                        <span class="{{ $item['rate'] >= 80 ? 'text-success' : ($item['rate'] < 50 ? 'text-danger' : '') }}">
                            {{ $item['rate'] }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if(count($desertores) > 0)
    <div style="page-break-before: always; margin-top: 30px;">
        <div class="instrument-header" style="border-left-color: #ef4444; background-color: #fef2f2;">
            <h2 style="color: #991b1b;">Alertas de Baja Asistencia (Menor al 50%)</h2>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="name-cell">Nombre</th>
                    <th>Instrumento</th>
                    <th class="rate-cell">Efectividad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($desertores as $item)
                <tr>
                    <td class="name-cell text-danger">{{ $item['nombres'] }} {{ $item['apellidos'] }}</td>
                    <td style="font-size: 9px; text-transform: uppercase;">{{ $item['instrumento'] }}</td>
                    <td class="rate-cell text-danger">{{ $item['rate'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        SIMBA OS v2.0 - Sistema Inteligente para Bandas de Bolivia - Generado el {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>
