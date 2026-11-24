{{-- Pedido recibido (P-05): el código, el estado pendiente y el enlace de seguimiento, visible aunque falle el correo. --}}
<x-plantilla.publica titulo="Pedido recibido" descripcion="Hemos recibido tu pedido para recoger en La Focaccia di Trevi." :indexar="false">
    <h1 class="h2">Hemos recibido tu pedido <strong class="font-monospace">{{ $pedido->codigo }}</strong></h1>

    <p class="lead">Está <strong>pendiente</strong>: te escribiremos a {{ $pedido->cliente->email }} cuando lo aceptemos, con la hora de recogida.</p>

    <p>Con este enlace puedes ver cómo va el pedido cuando quieras; guárdalo, porque es el mismo hasta el final:</p>
    <p><a href="{{ $enlace }}" class="btn btn-primary">Ver el seguimiento</a></p>
    <p class="small text-break"><span class="visually-hidden">Dirección del enlace: </span>{{ $enlace }}</p>

    @if ($registro === 'ofrecer')
        <section class="card mt-4" style="max-width: 32rem" aria-labelledby="titulo-registro">
            <div class="card-body">
                <h2 id="titulo-registro" class="h5">¿Quieres guardar tus pedidos?</h2>
                <p>Crea una cuenta con tu correo para ver este pedido y los siguientes en «Mis pedidos» y repetirlos. Solo
                    tienes que elegir una contraseña; es opcional.</p>
                <form method="POST" action="{{ route('cuenta.registrar') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña</label>
                        <input type="password" id="contrasena" name="contrasena" autocomplete="new-password" required
                               aria-describedby="ayuda-contrasena" @class(['form-control', 'is-invalid' => $errors->has('contrasena')])>
                        <div id="ayuda-contrasena" class="form-text">Al menos 10 caracteres, con letras y números.</div>
                        @error('contrasena') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="contrasena_confirmation" class="form-label">Repite la contraseña</label>
                        <input type="password" id="contrasena_confirmation" name="contrasena_confirmation"
                               autocomplete="new-password" required class="form-control">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Crear la cuenta</button>
                </form>
            </div>
        </section>
    @elseif ($registro === 'ya-hay-cuenta')
        <p class="mt-4">Ya tienes una cuenta con este correo. <a href="{{ route('acceder') }}">Entra</a> para ver tus pedidos.</p>
    @endif

    <p class="mt-4"><a href="{{ route('carta') }}">Volver a la carta</a></p>
</x-plantilla.publica>
