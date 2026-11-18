{{-- Contraseña nueva, a la que se llega desde el enlace del correo. --}}
<x-plantilla.gestion titulo="Elegir una contraseña nueva" :con-menu="false">
    <form method="POST" action="{{ route('gestion.contrasena.guardar') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="username"
                   required @class(['form-control', 'is-invalid' => $errors->has('email')])>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="contrasena" class="form-label">Contraseña nueva</label>
            <input type="password" id="contrasena" name="contrasena" autocomplete="new-password"
                   aria-describedby="ayuda-contrasena" required
                   @class(['form-control', 'is-invalid' => $errors->has('contrasena')])>
            <div id="ayuda-contrasena" class="form-text">Al menos 10 caracteres, con letras y números.</div>
            @error('contrasena')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="contrasena_confirmation" class="form-label">Repite la contraseña</label>
            <input type="password" id="contrasena_confirmation" name="contrasena_confirmation"
                   autocomplete="new-password" required class="form-control">
        </div>

        <button type="submit" class="btn btn-primary w-100 justify-content-center">Guardar la contraseña</button>
    </form>
</x-plantilla.gestion>
