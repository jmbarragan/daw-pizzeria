{{-- Acceso del cliente registrado (P-08, CU-08). Da el mismo error si falla el correo, la contraseña o la confirmación. --}}
<x-plantilla.publica titulo="Acceder" descripcion="Entra en tu cuenta para ver tus pedidos y repetirlos." :indexar="false">
    <div style="max-width: 26rem" class="mx-auto">
        <h1 class="h2">Acceder</h1>
        <p>Con tu cuenta ves tus pedidos y puedes repetir uno. Para pedir no hace falta.</p>

        <form method="POST" action="{{ route('entrar') }}" novalidate>
            @csrf

            @error('email')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username"
                       required autofocus @class(['form-control', 'is-invalid' => $errors->has('email')])>
            </div>

            <div class="mb-3">
                <label for="contrasena" class="form-label">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" autocomplete="current-password"
                       required @class(['form-control', 'is-invalid' => $errors->has('contrasena')])>
                @error('contrasena') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>

        <p class="mt-3 text-center"><a href="{{ route('contrasena.olvidada') }}">¿Has olvidado la contraseña?</a></p>
        <p class="small text-body-secondary">¿Aún no tienes cuenta? Al acabar un pedido puedes crearla con solo elegir una contraseña.</p>
    </div>
</x-plantilla.publica>
