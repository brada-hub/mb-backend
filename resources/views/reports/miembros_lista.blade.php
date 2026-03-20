<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #333; }
        
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #bc1b1b; padding-bottom: 10px; }
        .logo { max-width: 70px; margin-bottom: 5px; }
        .banda-name { font-weight: bold; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; color: #333; }
        h1 { margin: 5px 0 0; color: #bc1b1b; text-transform: uppercase; font-size: 14px; letter-spacing: 1px; }
        .meta { font-size: 9px; color: #888; margin-top: 3px; }
        
        .instrumento-header {
            background-color: #bc1b1b;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 15px;
            margin-bottom: 0;
            border-radius: 4px 4px 0 0;
        }
        
        .tipo-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
            margin-left: 6px;
            letter-spacing: 1px;
        }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { 
            background-color: #f5f5f5; 
            border: 1px solid #ddd; 
            padding: 6px 8px; 
            text-align: left; 
            text-transform: uppercase; 
            font-size: 8px; 
            letter-spacing: 1px;
            color: #666;
        }
        td { border: 1px solid #ddd; padding: 5px 8px; font-size: 10px; }
        tr:nth-child(even) { background-color: #fafafa; }
        
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .total-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .footer { 
            position: fixed; 
            bottom: 10px; 
            left: 0;
            width: 100%; 
            text-align: center; 
            font-size: 8px; 
            color: #aaa; 
            border-top: 1px solid #eee; 
            padding-top: 5px; 
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" class="logo">
        @endif
        <div class="banda-name">{{ $bandaNombre }}</div>
        <h1>{{ $titulo }}</h1>
        <div class="meta">Generado el: {{ $fecha }} &bull; Total: {{ $totalMiembros }} integrantes</div>
    </div>

    @foreach($grupos as $grupo)
        @php
            $esPercusion = in_array(strtoupper($grupo['instrumento']), ['PLATILLO', 'TAMBOR', 'TIMBAL', 'BOMBO', 'PERCUSIÓN', 'PERCUSION', 'SIN INSTRUMENTO']);
        @endphp

        <div class="instrumento-header">
            {{ $grupo['instrumento'] }}
            <span class="total-badge">{{ count($grupo['miembros']) }}</span>
            <span class="tipo-badge">{{ $esPercusion ? 'PERCUSIÓN' : 'VIENTOS' }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="{{ $esPercusion ? '35%' : '25%' }}">Apellidos</th>
                    <th width="{{ $esPercusion ? '35%' : '25%' }}">Nombres</th>
                    <th width="{{ $esPercusion ? '25%' : '15%' }}">Instrumento</th>
                    @if(!$esPercusion)
                        <th width="15%">Tonalidad / Voz</th>
                    @endif
                    <th width="{{ $esPercusion ? '0%' : '15%' }}">Categoría</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo['miembros'] as $index => $m)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-bold">{{ $m->apellidos }}</td>
                    <td>{{ $m->nombres }}</td>
                    <td>{{ $m->instrumento?->instrumento ?? '-' }}</td>
                    @if(!$esPercusion)
                        <td>{{ $m->voz?->nombre_voz ?? '-' }}</td>
                    @endif
                    <td>{{ $m->categoria?->nombre_categoria ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        SIMBA &mdash; Sistema Integral de Gestión de Bandas | {{ $fecha }}
    </div>
</body>
</html>
