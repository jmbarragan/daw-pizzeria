{{-- Alta y modificación de un producto (G-05, RF-16), con sus alérgenos y su disponibilidad. --}}
@php
    $existe = $producto->exists;
    $precio = $producto->precio !== null ? number_format((float) $producto->precio, 2, ',', '') : '';
    $alergenosMarcados = array_map('intval', (array) old('alergenos', $marcados));
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar producto' : 'Nuevo producto'" activa="carta">
    <x-gestion.pestanas-carta activa="productos" />
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Nombre</dt><dd>De 2 a 60 caracteres y distinto de los demás, contando los productos retirados.</dd>
            <dt>Ingredientes o descripción</dt><dd>Lo lee el cliente en la carta. En las bebidas se puede dejar vacío.</dd>
            <dt>Categoría</dt><dd>La sección de la carta donde sale. Si la categoría está oculta, el producto tampoco se ve.</dd>
            <dt>Precio con IVA</dt><dd>Con coma o con punto y como mucho dos decimales, hasta 999,99 €. Cambiarlo no toca
                los pedidos ya hechos, que guardan el precio de su momento.</dd>
            <dt>Alérgenos</dt><dd>Marca todos los que lleve: el cliente los ve antes de pedir y en el resumen del pedido,
                como exige el Reglamento (UE) 1169/2011.</dd>
            <dt>Disponible</dt><dd>Desmárcalo cuando se agote; volverá a poder pedirse en cuanto lo marques.</dd>
        </dl>
    </x-gestion.ayuda>

    @if ($existe && $producto->retirado_en)
        <p class="alert alert-secondary" role="status">
            Este producto está retirado de la carta desde el {{ $producto->retirado_en->format('d/m/Y') }}.
            Para que vuelva a salir, usa «Volver a la carta» en el listado.
        </p>
    @endif

    <form method="POST" novalidate style="max-width: 40rem"
          action="{{ $existe ? route('gestion.carta.productos.actualizar', $producto) : route('gestion.carta.productos.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
        @endif

        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="60" required
                   value="{{ old('nombre', $producto->nombre) }}"
                   @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
            @error('nombre')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Ingredientes o descripción <span class="text-body-secondary">(opcional)</span></label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="255"
                      @class(['form-control', 'is-invalid' => $errors->has('descripcion')])>{{ old('descripcion', $producto->descripcion) }}</textarea>
            @error('descripcion')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-7">
                <label for="categoria_id" class="form-label">Categoría</label>
                <select id="categoria_id" name="categoria_id" required
                        @class(['form-select', 'is-invalid' => $errors->has('categoria_id')])>
                    <option value="">Elige una</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((int) old('categoria_id', $producto->categoria_id) === $categoria->id)>
                            {{ $categoria->nombre }}{{ $categoria->activa ? '' : ' (oculta)' }}
                        </option>
                    @endforeach
                </select>
                @error('categoria_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-5">
                <label for="precio" class="form-label">Precio con IVA (€)</label>
                <input type="text" id="precio" name="precio" inputmode="decimal" required aria-describedby="ayuda-precio"
                       value="{{ old('precio', $precio) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('precio')])>
                <div id="ayuda-precio" class="form-text">Por ejemplo, 9,50.</div>
                @error('precio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label fs-6">Alérgenos que contiene</legend>
            @error('alergenos.*')
                <div class="text-danger small mb-2">{{ $message }}</div>
            @enderror
            <div class="row row-cols-2 row-cols-md-3 g-1">
                @foreach ($alergenos as $alergeno)
                    <div class="col">
                        <div class="form-check">
                            <input type="checkbox" id="alergeno-{{ $alergeno->id }}" name="alergenos[]" value="{{ $alergeno->id }}"
                                   class="form-check-input" @checked(in_array($alergeno->id, $alergenosMarcados, true))>
                            <label for="alergeno-{{ $alergeno->id }}" class="form-check-label">{{ $alergeno->nombre }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="form-check mb-4">
            <input type="hidden" name="disponible" value="0">
            <input type="checkbox" id="disponible" name="disponible" value="1" class="form-check-input" aria-describedby="ayuda-disponible"
                   @checked(old('disponible', $producto->disponible))>
            <label for="disponible" class="form-check-label">Disponible</label>
            <div id="ayuda-disponible" class="form-text">Desmárcalo si se ha agotado: seguirá en la carta, pero no se podrá pedir.</div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.carta') }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
