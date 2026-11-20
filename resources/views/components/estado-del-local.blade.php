{{--
    Aviso de abierto o cerrado (RF-02). Se repite en el inicio, la cesta y el
    formulario del pedido: fuera de horario se puede pedir igual, y el aviso
    dice cuándo se revisará; sin ninguna apertura prevista, pide llamar.
--}}
@props(['abierto', 'siguiente', 'telefono' => null])

@php
    $cuando = function ($turno) {
        $dia = $turno->dia;
        $hora = substr($turno->hora_apertura, 0, 5);

        if ($dia->isToday()) {
            return "hoy a las {$hora}";
        }
        if ($dia->isTomorrow()) {
            return "mañana a las {$hora}";
        }

        return 'el '.$dia->locale('es')->isoFormat('dddd D [de] MMMM').' a las '.$hora;
    };
@endphp

@if ($abierto)
    <p {{ $attributes->merge(['class' => 'alert alert-success']) }} role="status">
        Ahora abierto, hasta las {{ substr($abierto->hora_cierre, 0, 5) }}.
    </p>
@elseif ($siguiente)
    <p {{ $attributes->merge(['class' => 'alert alert-warning']) }} role="status">
        Ahora cerrado. Abrimos {{ $cuando($siguiente) }}. Puedes pedir ya: lo revisaremos al abrir, sin garantía de que podamos aceptarlo.
    </p>
@else
    <p {{ $attributes->merge(['class' => 'alert alert-warning']) }} role="status">
        Ahora no tenemos fechas de apertura previstas, así que no podemos recibir pedidos por la web.
        @if ($telefono)
            Llámanos al <a href="tel:{{ $telefono }}">{{ $telefono }}</a>.
        @endif
    </p>
@endif
