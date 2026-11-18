{{-- Alta y modificación de una categoría (RF-15). La misma vista sirve para las dos: decide por si ya existe. --}}
@php
    $existe = $categoria->exists;
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar categoría' : 'Nueva categoría'" activa="carta">
    <x-gestion.pestanas-carta activa="categorias" />
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Nombre</dt><dd>De 2 a 40 caracteres y sin repetir otra categoría.</dd>
            <dt>Orden en la carta</dt><dd>De 0 a 255. Dos categorías con el mismo número salen por orden alfabético.</dd>
            <dt>Visible en la carta</dt><dd>Desmárcala para quitar la sección de la carta sin perder sus productos.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" novalidate style="max-width: 32rem"
          action="{{ $existe ? route('gestion.carta.categorias.actualizar', $categoria) : route('gestion.carta.categorias.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="40" required
                   value="{{ old('nombre', $categoria->nombre) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('nombre')])
                   @error('nombre') aria-describedby="error-nombre" @enderror>
            @error('nombre')
                <div id="error-nombre" class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="orden" class="form-label">Orden en la carta</label>
            <input type="number" id="orden" name="orden" min="0" max="255" required
                   value="{{ old('orden', $categoria->orden) }}" aria-describedby="ayuda-orden"
                   @class(['form-control', 'is-invalid' => $errors->has('orden')])>
            <div id="ayuda-orden" class="form-text">Las categorías con número más bajo salen antes.</div>
            @error('orden')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            {{-- el campo oculto envía 0 cuando la casilla está desmarcada --}}
            <input type="hidden" name="activa" value="0">
            <input type="checkbox" id="activa" name="activa" value="1" class="form-check-input"
                   @checked(old('activa', $categoria->activa))>
            <label for="activa" class="form-check-label">Visible en la carta</label>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.carta.categorias') }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
