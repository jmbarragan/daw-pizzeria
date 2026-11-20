{{-- Confirmación antes de cancelar un pedido pendiente (CU-06). --}}
<x-plantilla.publica titulo="Cancelar el pedido" descripcion="Confirma si quieres cancelar tu pedido." :indexar="false">
    <h1 class="h2">¿Cancelar el pedido <span class="font-monospace">{{ $pedido->codigo }}</span>?</h1>

    <p class="lead">Si lo cancelas, no lo prepararemos. No se puede deshacer: tendrías que hacer otro pedido.</p>

    <form method="POST" action="{{ route('seguimiento.cancelar', $pedido->token_seguimiento) }}" class="d-flex flex-wrap gap-2">
        @csrf
        <button type="submit" class="btn btn-danger">Sí, cancelar el pedido</button>
        <a href="{{ route('seguimiento', $pedido->token_seguimiento) }}" class="btn btn-outline-secondary">No, volver al seguimiento</a>
    </form>
</x-plantilla.publica>
