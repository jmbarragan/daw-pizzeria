{{-- Acceso denegado: por ejemplo, un empleado que entra en una pantalla de administrador (CU-20). --}}
<x-plantilla.publica titulo="Sin permiso">
    <h1 class="h3">No tienes permiso para ver esta página</h1>
    <p>Esta sección es solo para el personal con otro rol. Si crees que deberías poder entrar, habla con la persona que administra el local.</p>
    <a class="btn btn-primary" href="{{ url('/') }}">Volver al inicio</a>
</x-plantilla.publica>
