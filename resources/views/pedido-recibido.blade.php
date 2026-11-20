{{-- Pedido recibido (P-05): el código, el estado pendiente y el enlace de seguimiento, visible aunque falle el correo. --}}
<x-plantilla.publica titulo="Pedido recibido" descripcion="Hemos recibido tu pedido para recoger en La Focaccia di Trevi." :indexar="false">
    <h1 class="h2">Hemos recibido tu pedido <strong class="font-monospace">{{ $pedido->codigo }}</strong></h1>

    <p class="lead">Está <strong>pendiente</strong>: te escribiremos a {{ $pedido->cliente->email }} cuando lo aceptemos, con la hora de recogida.</p>

    <p>Con este enlace puedes ver cómo va el pedido cuando quieras; guárdalo, porque es el mismo hasta el final:</p>
    <p><a href="{{ $enlace }}" class="btn btn-primary">Ver el seguimiento</a></p>
    <p class="small text-break"><span class="visually-hidden">Dirección del enlace: </span>{{ $enlace }}</p>

    <p class="mt-4"><a href="{{ route('carta') }}">Volver a la carta</a></p>
</x-plantilla.publica>
