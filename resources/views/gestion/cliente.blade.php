{{-- Alta y modificación de un cliente (RF-18). La misma vista sirve para las dos: con un cliente guardado, edita. --}}
@php
    $existe = $cliente->exists;
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar cliente' : 'Nuevo cliente'" activa="clientes">
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Nombre</dt><dd>De 2 a 80 caracteres.</dd>
            <dt>Teléfono</dt><dd>9 cifras, o con el prefijo de su país (+…) si no es español.</dd>
            <dt>Correo</dt><dd>Opcional. Si el cliente tiene cuenta, no puede ser el de otra cuenta. La contraseña solo
                la cambia el propio cliente.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" novalidate style="max-width: 32rem"
          action="{{ $existe ? route('gestion.clientes.actualizar', $cliente) : route('gestion.clientes.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="80" required autocomplete="off"
                   value="{{ old('nombre', $cliente->nombre) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" required inputmode="tel" autocomplete="off"
                   value="{{ old('telefono', $cliente->telefono) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('telefono')])>
            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="email" class="form-label">Correo <span class="text-body-secondary">(opcional)</span></label>
            <input type="email" id="email" name="email" maxlength="254" autocomplete="off"
                   value="{{ old('email', $cliente->email) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('email')])>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.clientes') }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
