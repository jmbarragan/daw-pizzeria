{{-- Política de privacidad (RNF-10). El texto es un ajuste que el administrador cambia sin tocar el código. --}}
@php
    $politica = app(\App\Servicios\AjustesPublicos::class)->valor('politica_privacidad');
@endphp

<x-plantilla.publica titulo="Política de privacidad" descripcion="Qué datos guarda La Focaccia di Trevi al recibir un pedido y durante cuánto tiempo.">
    <h1 class="h2">Política de privacidad</h1>

    @if ($politica)
        <div style="max-width: 48rem">{!! nl2br(e($politica)) !!}</div>
    @else
        <p>La política de privacidad todavía no está publicada. Si tienes dudas sobre tus datos, llámanos.</p>
    @endif
</x-plantilla.publica>
