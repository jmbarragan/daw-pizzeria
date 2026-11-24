{{-- Inicio de sesión del personal (G-01). El error es el mismo falle el correo o la contraseña. --}}
<x-plantilla.gestion titulo="Inicio de sesión" :con-menu="false">
    <x-gestion.ayuda>
        <p>Entra con el correo y la contraseña de tu cuenta del personal. Si te acaban de dar de alta, elige antes tu
            contraseña con el enlace que te ha llegado por correo.</p>
        <p class="mb-0">Tras diez intentos fallidos desde la misma conexión, el acceso se bloquea 24 horas; un administrador
            puede levantarlo antes desde «Ajustes».</p>
    </x-gestion.ayuda>
    @if (session('estado'))
        <div class="alert alert-success" role="status">{{ session('estado') }}</div>
    @endif

    <form method="POST" action="{{ route('gestion.entrar') }}" novalidate>
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
            @error('contrasena')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 justify-content-center">Entrar</button>
    </form>

    <p class="mt-3 text-center">
        <a href="{{ route('gestion.contrasena.olvidada') }}">¿Has olvidado la contraseña?</a>
    </p>
</x-plantilla.gestion>
