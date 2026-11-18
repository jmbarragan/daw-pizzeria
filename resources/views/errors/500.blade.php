{{-- Error del servidor. Sin cesta: si el fallo es de la base de datos, la sesión tampoco estaría disponible. --}}
<x-plantilla.publica titulo="Error" :con-cesta="false">
    <h1 class="h3">Algo ha fallado en el servidor</h1>
    <p>No es culpa tuya. Vuelve a intentarlo dentro de un momento; si tienes prisa, llámanos y te tomamos el pedido por teléfono.</p>
    <a class="btn btn-primary" href="{{ url('/') }}">Volver al inicio</a>
</x-plantilla.publica>
