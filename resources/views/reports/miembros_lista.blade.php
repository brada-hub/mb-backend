<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #bc1b1b; padding-bottom: 10px; }
        .logo { max-width: 80px; margin-bottom: 10px; }
        h1 { margin: 0; color: #bc1b1b; text-transform: uppercase; font-size: 18px; }
        .banda-name { font-weight: bold; font-size: 14px; margin-bottom: 5px; }
        .meta { font-size: 9px; color: #666; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; text-transform: uppercase; font-size: 9px; }
        td { border: 1px solid #ddd; padding: 6px; }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
        
        .badge { padding: 2px 5px; border-radius: 3px; font-weight: bold; font-size: 8px; color: white; }
        .bg-red { background-color: #bc1b1b; }
        .bg-green { background-color: #28a745; }
        
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if($banda && $banda->logo)
            {{-- In a real environment we'd use base64 or absolute path --}}
            <img src="{{ public_path(str_replace('/storage/', 'storage/', $banda->logo)) }}" class="logo">
        @endif
        <div class="banda-name">{{ $banda->nombre ?? 'Monster Band' }}</div>
        <h1>{{ $titulo }}</h1>
        <div class="meta">Generado el: {{ $fecha }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="25%">Nombres y Apellidos</th>
                <th width="15%">CI</th>
                <th width="12%">Celular</th>
                <th width="20%">Instrumento / Rol</th>
                <th width="15%">Sección</th>
                <th width="8%" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($miembros as $index => $m)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="font-bold">{{ $m->nombres }} {{ $m->apellidos }}</td>
                <td>{{ $m->ci }}</td>
                <td>{{ $m->celular }}</td>
                <td>
                    {{ $m->instrumento?->instrumento ?? 'N/A' }}<br>
                    <small style="color: #bc1b1b">{{ $m->rol?->rol ?? 'Músico' }}</small>
                </td>
                <td>{{ $m->seccion?->seccion ?? 'N/A' }}</td>
                <td class="text-center">
                    @if($m->user && $m->user->estado)
                        <span style="color: green">Activo</span>
                    @else
                        <span style="color: red">Inactivo</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        SIMBA - Sistema de Gestión de Bandas | {{ $fecha }} | Hoja 1
    </div>
</body>
</html>
