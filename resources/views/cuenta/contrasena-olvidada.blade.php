{{-- Petición del enlace para cambiar la contraseña de un cliente. La respuesta no dice si el correo tiene cuenta. --}}
<x-plantilla.publica titulo="Contraseña olvidada" descripcion="Pide un enlace para elegir otra contraseña." :indexar="false">
    <div style="max-width: 26rem" class="mx-auto">
        <h1 class="h2">¿Has olvidado la contraseña?</h1>
        <p>Escribe el correo de tu cuenta y te mandaremos un enlace para elegir otra.</p>

        <form method="POST" action="{{ route('contrasena.enlace') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username"
                       required autofocus @class(['form-control', 'is-invalid' => $errors->has('email')])>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar el enlace</button>
        </form>

        <p class="mt-3 text-center"><a href="{{ route('acceder') }}">Volver a «Acceder»</a></p>
    </div>
</x-plantilla.publica>
