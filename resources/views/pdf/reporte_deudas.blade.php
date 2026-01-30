<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planilla de Pagos - SIMBA</title>
    <style>
        @page { margin: 30px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1f2937; margin: 0; padding: 0; }

        .header { margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 900; text-transform: uppercase; color: #111827; }
        .header p { margin: 2px 0 0; font-size: 10px; color: #6b7280; font-weight: 500; }

        .instrument-section {
            background-color: #f8fafc;
            padding: 8px 12px;
            margin-top: 15px;
            margin-bottom: 0;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            border-left: 4px solid #4f46e5;
        }
        .instrument-section h2 {
            margin: 0;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #475569;
        }

        table.main-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        table.main-table th {
            background-color: #f1f5f9;
            padding: 10px 8px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        table.main-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            vertical-align: middle;
            font-size: 9px;
        }

        .col-nro { width: 30px; text-align: center; color: #94a3b8; }
        .col-musico { width: 140px; }
        .col-detalle { }
        .col-total { width: 40px; text-align: center; font-weight: 900; font-size: 11px; color: #0f172a; }
        .col-firma { width: 100px; }

        .musician-name { font-weight: 700; color: #0f172a; font-size: 9.5px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .musician-inst { font-size: 7.5px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Inner Event Table - No nested borders */
        .event-subtable { width: 100%; border-collapse: collapse; border: none; }
        .event-subtable td {
            border: none;
            border-bottom: 1px dotted #e2e8f0;
            padding: 4px 0;
            font-size: 8.5px;
            line-height: normal;
        }
        .event-subtable tr:last-child td { border-bottom: none; }

        .date-cell { width: 55px; font-family: monospace; font-weight: 700; color: #475569; }
        .type-cell { width: 60px; text-align: center; }
        .type-badge {
            font-size: 7px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 1px 3px;
            border-radius: 2px;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            display: block;
            margin: 0 auto;
        }
        .name-cell { color: #1e293b; padding-left: 5px !important; }

        .signature-box { height: 45px; width: 100%; position: relative; }
        .signature-line { position: absolute; bottom: 15px; left: 0; right: 0; border-bottom: 0.5pt solid #334155; }
        .signature-text { position: absolute; bottom: 5px; width: 100%; text-align: center; font-size: 6.5px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }

        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div style="float: right; text-align: right; margin-top: 5px;">
            <p style="color: #4f46e5; font-weight: 900; font-size: 13px;">{{ $banda['nombre'] }}</p>
            <p>Emisión: {{ date('d/m/Y') }}</p>
        </div>

        @if($banda['logo'])
            <img src="{{ $banda['logo'] }}" style="float: left; height: 45px; margin-right: 15px;">
        @endif

        <h1>Planilla de Pagos</h1>
        <p>Control de Retribuciones por Servicios Musicales</p>
        <div style="clear: both;"></div>
    </div>

    @php
        $orderMap = [
            'PLATILLO' => 1, 'TAMBOR' => 2, 'TIMBAL' => 3, 'BOMBO' => 4,
            'TROMBON' => 5, 'CLARINETE' => 6, 'BARITONO' => 7, 'TROMPETA' => 8, 'HELICON' => 9
        ];

        $grouped = collect($deudores)->groupBy('instrumento')->sortBy(function($mems, $key) use ($orderMap) {
            return $orderMap[strtoupper($key)] ?? 99;
        });
    @endphp

    @foreach($grouped as $instrumento => $miembros)
        <div class="instrument-section">
            <h2>{{ $instrumento }}</h2>
        </div>
        <table class="main-table">
            <thead>
                <tr>
                    <th class="col-nro">#</th>
                    <th class="col-musico">Músico / Integrante</th>
                    <th class="col-detalle">Actividades por Cobrar</th>
                    <th class="col-total">Cant.</th>
                    <th class="col-firma">Firma de Recibido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($miembros as $index => $miembro)
                    <tr>
                        <td class="col-nro">{{ $loop->iteration }}</td>
                        <td class="col-musico">
                            <span class="musician-name">{{ $miembro['nombres'] }} {{ $miembro['apellidos'] }}</span>
                            <span class="musician-inst">{{ $miembro['instrumento'] }}</span>
                        </td>
                        <td style="padding: 0 8px;">
                            <table class="event-subtable">
                                @foreach($miembro['eventos_list'] as $evento)
                                    <tr>
                                        <td class="date-cell">{{ \Carbon\Carbon::parse($evento['fecha'])->format('d/m/y') }}</td>
                                        <td class="type-cell"><span class="type-badge">{{ $evento['tipo'] }}</span></td>
                                        <td class="name-cell">{{ $evento['nombre'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                        <td class="col-total">{{ $miembro['total_eventos'] }}</td>
                        <td class="col-firma">
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div class="signature-text">Recibí Conforme</div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        SIMBA OS v2.0 - Planilla de Pagos - Generado el {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>
