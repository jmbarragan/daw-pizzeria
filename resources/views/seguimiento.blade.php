{{-- Seguimiento de un pedido (P-06): estado, recogida, líneas y total. El mismo enlace sirve de principio a fin. --}}
@php
    $estados = [
        'pendiente' => 'Pendiente: lo revisaremos y te escribiremos al aceptarlo, con la hora de recogida.',
        'aceptado' => 'Aceptado: lo estamos preparando.',
        'listo' => 'Listo para recoger.',
        'recogido' => 'Recogido. ¡Que aproveche!',
        'rechazado' => 'Rechazado.',
        'cancelado' => 'Cancelado.',
        'no_recogido' => 'No se recogió.',
    ];
    // el camino normal; los otros finales no tienen pasos
    $pasos = ['pendiente' => 'Recibido', 'aceptado' => 'Aceptado', 'listo' => 'Listo', 'recogido' => 'Recogido'];
    $actual = array_search($pedido->estado, array_keys($pasos), true);
@endphp

<x-plantilla.publica titulo="Seguimiento del pedido" descripcion="Estado de tu pedido en La Focaccia di Trevi." :indexar="false">
    <h1 class="h2">Pedido <span class="font-monospace">{{ $pedido->codigo }}</span></h1>
    <p class="text-body-secondary">Hecho el {{ $pedido->creado_en->format('d/m/Y \a \l\a\s H:i') }}</p>

    @if ($actual !== false)
        <ol class="list-inline mb-3" aria-label="Avance del pedido">
            @foreach (array_values($pasos) as $n => $paso)
                <li @class(['list-inline-item badge', 'text-bg-primary' => $n === $actual, 'text-bg-success' => $n < $actual,
                            'text-bg-light border' => $n > $actual]) aria-current="{{ $n === $actual ? 'step' : 'false' }}">{{ $paso }}</li>
            @endforeach
        </ol>
    @endif

    <p class="lead">{{ $estados[$pedido->estado] ?? ucfirst($pedido->estado) }}</p>

    @if (in_array($pedido->estado, ['aceptado', 'listo'], true))
        <p class="fs-5">Recógelo hacia las <strong>{{ $pedido->hora_recogida->format('H:i') }}</strong>
            del {{ $pedido->hora_recogida->format('d/m') }}@if ($direccion) en <strong>{{ $direccion }}</strong>@endif.</p>
    @endif

    <dl class="row" style="max-width: 32rem">
        <dt class="col-sm-5">Día previsto</dt>
        <dd class="col-sm-7">{{ $pedido->fecha_prevista->format('d/m/Y') }}</dd>
        @if ($pedido->estado === 'rechazado' && $pedido->motivo_rechazo)
            <dt class="col-sm-5">Motivo</dt>
            <dd class="col-sm-7">{{ $pedido->motivo_rechazo }}</dd>
        @endif
    </dl>

    <h2 class="h5 mt-4">Tu pedido</h2>
    <table class="table table-sm" style="max-width: 32rem">
        <thead>
            <tr><th scope="col">Producto</th><th scope="col" class="text-end">Uds.</th><th scope="col" class="text-end">Importe</th></tr>
        </thead>
        <tbody>
            @foreach ($pedido->lineas as $linea)
                <tr>
                    <td>{{ $linea->nombre_producto }}</td>
                    <td class="text-end">{{ $linea->cantidad }}</td>
                    <td class="text-end">{{ number_format($linea->cantidad * (float) $linea->precio_unitario, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th scope="row" colspan="2">Total</th><th class="text-end">{{ number_format((float) $total, 2, ',', '.') }} €</th></tr>
        </tfoot>
    </table>
    <p>Pago en el local, al recogerlo.</p>

    @if ($pedido->estado === 'pendiente')
        <p class="mt-4"><a href="{{ route('seguimiento.cancelacion', $pedido->token_seguimiento) }}" class="btn btn-outline-danger">Cancelar pedido</a></p>
    @elseif (in_array($pedido->estado, ['aceptado', 'listo'], true) && $telefono)
        <p class="mt-4 small">¿Necesitas cambiar algo? Llámanos al <a href="tel:{{ $telefono }}">{{ $telefono }}</a>.</p>
    @endif
</x-plantilla.publica>
