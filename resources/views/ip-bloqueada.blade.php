{{-- Aviso de la zona pública para la IP bloqueada tras diez accesos fallidos (RNF-01). --}}
<x-plantilla.publica titulo="Acceso bloqueado" descripcion="El acceso está bloqueado desde tu conexión." :indexar="false">
    <div class="alert alert-danger" role="alert">
        <p>Ha habido demasiados intentos fallidos de entrar desde tu conexión, así que el acceso está bloqueado.</p>
        @if ($bloqueo->fin)
            <p class="mb-0">Podrás volver a intentarlo el {{ $bloqueo->fin->format('d/m/Y') }} a las {{ $bloqueo->fin->format('H:i') }}.</p>
        @else
            <p class="mb-0">Es el segundo bloqueo en 30 días y no caduca: llámanos para que lo levantemos.</p>
        @endif
    </div>
    <p>Puedes seguir viendo la carta y pedir como invitado.</p>
</x-plantilla.publica>
