{{-- Tabla de un informe con su total. La comparten la pantalla (G-08) y el PDF (CU-18), así que los dos dicen lo mismo. --}}
@php
    $euros = fn (float $importe): string => number_format($importe, 2, ',', '.').' €';
    $columnas = [
        'ventas' => ['dia' => 'Día', 'pedidos' => 'Pedidos', 'importe' => 'Cobrado'],
        'productos' => ['producto' => 'Producto', 'unidades' => 'Unidades', 'importe' => 'Importe'],
        'estados' => ['estado' => 'Estado', 'pedidos' => 'Pedidos', 'importe' => 'Importe'],
    ][$informe];
    $numericas = array_slice(array_keys($columnas), 1);
@endphp

<table class="table align-middle tabla-informe">
    <thead>
        <tr>
            @foreach ($columnas as $nombre)
                <th scope="col" @class(['text-end' => ! $loop->first])>{{ $nombre }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($resultado['filas'] as $fila)
            <tr>
                <td>
                    @if ($informe === 'ventas')
                        {{ \Illuminate\Support\Carbon::parse($fila['dia'])->locale('es')->isoFormat('dddd D/MM/YYYY') }}
                    @elseif ($informe === 'estados')
                        {{ \App\Models\Pedido::ETIQUETAS[$fila['estado']] }}
                    @else
                        {{ $fila['producto'] }}
                    @endif
                </td>
                @foreach ($numericas as $columna)
                    <td class="text-end">{{ $columna === 'importe' ? $euros($fila[$columna]) : $fila[$columna] }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td class="fw-bold">Total</td>
            @foreach ($numericas as $columna)
                <td class="text-end fw-bold">{{ $columna === 'importe' ? $euros($resultado['total'][$columna]) : $resultado['total'][$columna] }}</td>
            @endforeach
        </tr>
    </tfoot>
</table>
