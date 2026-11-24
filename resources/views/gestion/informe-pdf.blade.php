{{-- Informe en PDF (CU-18): cabecera del local, intervalo, la misma tabla que la pantalla y la fecha en que se generó. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ \App\Servicios\Informes::TIPOS[$informe] }}</title>
    <style>
        /* DejaVu Sans viene con dompdf y tiene las tildes y el símbolo del euro */
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #222; }
        .local { font-size: 9pt; color: #666; text-transform: uppercase; letter-spacing: .05em; margin: 0; }
        h1 { font-size: 16pt; margin: 4pt 0 2pt; }
        .intervalo { margin: 0 0 14pt; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5pt 6pt; border-bottom: 1px solid #ccc; text-align: left; }
        thead th { border-bottom: 2px solid #222; }
        tfoot th { border-top: 2px solid #222; border-bottom: none; }
        .text-end { text-align: right; }
        .pie { margin-top: 18pt; font-size: 8pt; color: #777; }
    </style>
</head>
<body>
    <p class="local">{{ config('app.name') }}</p>
    <h1>{{ \App\Servicios\Informes::TIPOS[$informe] }}</h1>
    <p class="intervalo">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>

    @include('gestion.informe-tabla', ['informe' => $informe, 'resultado' => $resultado])

    <p class="pie">Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }}.</p>
</body>
</html>
