{{-- Alta y modificación de un alérgeno del catálogo (RF-17). --}}
@php
    $existe = $alergeno->exists;
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar alérgeno' : 'Nuevo alérgeno'" activa="carta">
    <x-gestion.pestanas-carta activa="alergenos" />
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Nombre</dt><dd>De 2 a 40 caracteres y sin repetir otro alérgeno del catálogo.</dd>
            <dt>Lo que ve el cliente</dt><dd>Explica en pocas palabras qué incluye, para que quien tenga una alergia sepa
                si le afecta.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" novalidate style="max-width: 40rem"
          action="{{ $existe ? route('gestion.carta.alergenos.actualizar', $alergeno) : route('gestion.carta.alergenos.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="40" required
                   value="{{ old('nombre', $alergeno->nombre) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
            @error('nombre')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="descripcion" class="form-label">Lo que ve el cliente</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="255" required aria-describedby="ayuda-descripcion"
                      @class(['form-control', 'is-invalid' => $errors->has('descripcion')])>{{ old('descripcion', $alergeno->descripcion) }}</textarea>
            <div id="ayuda-descripcion" class="form-text">
                Sale en la carta junto al nombre del alérgeno. Por ejemplo: «Leche y sus derivados, incluida la lactosa».
            </div>
            @error('descripcion')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.carta.alergenos') }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
