{{-- Alta y modificación de una cuenta del personal (RF-21). Con una cuenta guardada, edita. --}}
@php
    $existe = $usuario->exists;
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar cuenta' : 'Nueva cuenta'" activa="usuarios">
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Correo</dt><dd>Es con el que entra en la gestión; no puede ser el de otra persona del personal.</dd>
            <dt>Rol</dt><dd>El empleado lleva los pedidos, la carta, los clientes, el horario y los informes. El
                administrador, además, las cuentas del personal y los ajustes del local.</dd>
            <dt>Contraseña</dt><dd>No se escribe aquí: {{ $existe ? 'si la ha olvidado, la cambia desde «¿Has olvidado la contraseña?».' : 'al guardar, le llega un enlace para elegirla.' }}</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" novalidate style="max-width: 32rem"
          action="{{ $existe ? route('gestion.usuarios.actualizar', $usuario) : route('gestion.usuarios.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre y apellidos</label>
            <input type="text" id="nombre" name="nombre" maxlength="80" required autocomplete="off"
                   value="{{ old('nombre', $usuario->nombre) }}" @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Correo</label>
            <input type="email" id="email" name="email" maxlength="254" required autocomplete="off"
                   value="{{ old('email', $usuario->email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')])>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <fieldset class="mb-4">
            <legend class="form-label fs-6">Rol</legend>
            @foreach ([\App\Models\Usuario::ROL_EMPLEADO => 'Empleado', \App\Models\Usuario::ROL_ADMINISTRADOR => 'Administrador'] as $valor => $texto)
                <div class="form-check">
                    <input type="radio" id="rol-{{ $valor }}" name="rol" value="{{ $valor }}" class="form-check-input"
                           @checked(old('rol', $usuario->rol) === $valor)>
                    <label for="rol-{{ $valor }}" class="form-check-label">{{ $texto }}</label>
                </div>
            @endforeach
            @error('rol') <div class="text-danger small">{{ $message }}</div> @enderror
        </fieldset>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.usuarios') }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
