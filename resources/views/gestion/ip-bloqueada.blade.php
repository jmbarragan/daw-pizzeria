{{-- Aviso para la IP bloqueada tras diez accesos fallidos (RNF-01). --}}
<x-plantilla.gestion titulo="Acceso bloqueado" :con-menu="false">
    <div class="alert alert-danger" role="alert">
        <p>Ha habido demasiados intentos fallidos de entrar desde tu conexión, así que el acceso está bloqueado.</p>
        @if ($bloqueo->fin)
            <p class="mb-0">Podrás volver a intentarlo el {{ $bloqueo->fin->format('d/m/Y') }} a las {{ $bloqueo->fin->format('H:i') }}.</p>
        @else
            <p class="mb-0">Es el segundo bloqueo en 30 días y no caduca: pide al administrador de la pizzería que lo levante.</p>
        @endif
    </div>
</x-plantilla.gestion>
