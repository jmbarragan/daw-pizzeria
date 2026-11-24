{{-- Petición del enlace para cambiar la contraseña (CU-08, 2a). --}}
<x-plantilla.gestion titulo="Recuperar la contraseña" :con-menu="false">
    <x-gestion.ayuda>
        <p class="mb-0">El enlace sirve una hora y una sola vez. Por seguridad, la respuesta es la misma aunque el correo no
            sea de nadie: si no te llega en unos minutos, mira en el correo no deseado o pide a un administrador que revise
            tu cuenta.</p>
    </x-gestion.ayuda>
    @if (session('estado'))
        <div class="alert alert-success" role="status">{{ session('estado') }}</div>
    @endif

    <p>Escribe el correo con el que entras en la gestión y te enviaremos un enlace para elegir otra contraseña.</p>

    <form method="POST" action="{{ route('gestion.contrasena.enlace') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username"
                   required autofocus @class(['form-control', 'is-invalid' => $errors->has('email')])>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 justify-content-center">Enviar el enlace</button>
    </form>

    <p class="mt-3 text-center"><a href="{{ route('gestion.acceso') }}">Volver al inicio de sesión</a></p>
</x-plantilla.gestion>
