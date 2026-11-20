{{-- Seguimiento de un pedido (RF-05): el estado actual. El mismo enlace sirve de principio a fin. --}}
@php
    $estados = [
        'pendiente' => 'Pendiente: lo revisaremos y te escribiremos al aceptarlo, con la hora de recogida.',
        'aceptado' => 'Aceptado: puedes recogerlo a la hora indicada.',
        'listo' => 'Listo para recoger.',
        'recogido' => 'Recogido. ¡Que aproveche!',
        'rechazado' => 'Rechazado.',
        'cancelado' => 'Cancelado.',
        'no_recogido' => 'No se recogió.',
    ];
@endphp

<x-plantilla.publica titulo="Seguimiento del pedido" descripcion="Estado de tu pedido en La Focaccia di Trevi." :indexar="false">
    <h1 class="h2">Pedido <span class="font-monospace">{{ $pedido->codigo }}</span></h1>

    <p class="lead">{{ $estados[$pedido->estado] ?? ucfirst($pedido->estado) }}</p>

    <dl class="row" style="max-width: 32rem">
        <dt class="col-sm-5">Día previsto</dt>
        <dd class="col-sm-7">{{ $pedido->fecha_prevista->format('d/m/Y') }}</dd>
        @if ($pedido->hora_recogida)
            <dt class="col-sm-5">Hora de recogida</dt>
            <dd class="col-sm-7">{{ $pedido->hora_recogida->format('H:i') }}</dd>
        @endif
        @if ($pedido->estado === 'rechazado' && $pedido->motivo_rechazo)
            <dt class="col-sm-5">Motivo</dt>
            <dd class="col-sm-7">{{ $pedido->motivo_rechazo }}</dd>
        @endif
    </dl>
</x-plantilla.publica>
