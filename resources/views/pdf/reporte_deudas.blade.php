<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planilla de Pagos</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #000; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #333; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }

        th, td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
        }

        th {
            background-color: #ddd;
            text-transform: uppercase;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }

        /* Anchos de columna */
        .col-nro { width: 4%; text-align: center; }
        .col-musico { width: 22%; }
        .col-detalle { width: 48%; }
        .col-total { width: 6%; text-align: center; font-weight: bold; }
        .col-firma { width: 20%; }

        .musician-name { font-weight: bold; font-size: 10px; display: block; text-transform: uppercase; }
        .musician-inst { font-size: 8px; font-style: italic; color: #444; }

        .event-list { width: 100%; border-collapse: collapse; border: none; }
        .event-item td {
            border: none;
            border-bottom: 1px dotted #ccc;
            padding: 2px 0;
            font-size: 9px;
        }
        .event-item:last-child td { border-bottom: none; }

        .date { font-family: monospace; width: 50px; color: #333; display: inline-block; }
        .type-badge {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 1px 3px;
            border-radius: 2px;
            margin-right: 4px;
            display: inline-block;
            border: 1px solid #999;
            width: 45px;
            text-align: center;
        }
        .name { font-weight: normal; }

        .signature-box {
            height: 40px;
            position: relative;
        }
        .signature-line {
            position: absolute;
            bottom: 6px;
            left: 5px;
            right: 5px;
            border-bottom: 1px solid #000;
        }
        .signature-text {
            position: absolute;
            bottom: -2px;
            width: 100%;
            text-align: center;
            font-size: 6px;
            color: #444;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Planilla de Pagos</h1>
        <p>Monster Band • Fecha de Impresión: {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-nro">#</th>
                <th class="col-musico">Músico</th>
                <th class="col-detalle">Detalle de Eventos</th>
                <th class="col-total">Cant.</th>
                <th class="col-firma">Firma</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deudores as $index => $miembro)
                <tr>
                    <td class="col-nro">{{ $loop->iteration }}</td>
                    <td>
                        <span class="musician-name">{{ mb_strtoupper($miembro['nombres']) }} {{ mb_strtoupper($miembro['apellidos']) }}</span>
                        <span class="musician-inst">{{ $miembro['instrumento'] }}</span>
                    </td>
                    <td style="padding: 2px 5px;">
                        <table class="event-list">
                            @foreach($miembro['eventos_list'] as $evento)
                                <tr class="event-item">
                                    <td style="width: 55px;"><span class="date">{{ \Carbon\Carbon::parse($evento['fecha'])->format('d/m/y') }}</span></td>
                                    <td style="width: 55px;"><span class="type-badge">{{ $evento['tipo'] }}</span></td>
                                    <td><span class="name">{{ $evento['nombre'] }}</span></td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                    <td class="col-total">{{ $miembro['total_eventos'] }}</td>
                    <td>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Recibí Conforme</div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
