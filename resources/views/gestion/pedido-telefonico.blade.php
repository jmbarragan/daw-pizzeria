{{-- Alta de un pedido telefónico (RF-14): nace aceptado, con su hora de recogida. --}}
<x-plantilla.gestion titulo="Pedido telefónico" activa="pedidos">
    <x-gestion.ayuda>
        <p>Apunta lo que te pide el cliente: su nombre, su teléfono y las unidades de cada producto. El correo es
            opcional; si lo das, le llega la confirmación con la hora y el enlace para seguir el pedido.</p>
        <p class="mb-0">El pedido queda aceptado para el primer día con huecos y la hora que elijas. Aquí no hay
            máximo de pizzas: los pedidos grandes son justo los que llegan por teléfono.</p>
    </x-gestion.ayuda>

    <p><a href="{{ route('gestion.pedidos') }}">← Volver al panel</a></p>

    @if (! $dia)
        <p class="alert alert-warning">No hay ninguna franja de recogida libre en las próximas dos semanas: abre antes un turno en el horario.</p>
    @else
        <form method="POST" action="{{ route('gestion.pedidos.telefonico.guardar') }}" novalidate>
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="80" required value="{{ old('nombre') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" required inputmode="tel" value="{{ old('telefono') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('telefono')])>
                    @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Correo <span class="text-body-secondary">(opcional)</span></label>
                    <input type="email" id="email" name="email" maxlength="254" value="{{ old('email') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('email')])>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <fieldset class="mb-3">
                <legend class="h5">Productos</legend>
                @error('unidades') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                @error('unidades.*') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                @foreach ($categorias as $categoria)
                    <h2 class="h6 mt-3">{{ $categoria->nombre }}</h2>
                    <div class="row row-cols-1 row-cols-md-2 g-2">
                        @foreach ($categoria->productos as $producto)
                            <div class="col">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="number" id="unidades-{{ $producto->id }}" name="unidades[{{ $producto->id }}]"
                                           min="0" max="20" value="{{ old('unidades.'.$producto->id, 0) }}" class="form-control" style="width: 5rem">
                                    <label for="unidades-{{ $producto->id }}">
                                        {{ $producto->nombre }}
                                        <span class="text-body-secondary small">{{ number_format((float) $producto->precio, 2, ',', '.') }} €</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </fieldset>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="hora" class="form-label">Recogida el {{ $dia->locale('es')->isoFormat('dddd D/MM') }}</label>
                    <select id="hora" name="hora" required @class(['form-select', 'is-invalid' => $errors->has('hora')])>
                        <option value="">Elige la hora…</option>
                        @foreach ($franjas as $franja)
                            <option value="{{ $franja }}" @selected(old('hora') === $franja)>{{ $franja }}</option>
                        @endforeach
                    </select>
                    @error('hora') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
                    <input type="text" id="observaciones" name="observaciones" maxlength="255" value="{{ old('observaciones') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('observaciones')])>
                    @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Apuntar pedido</button>
        </form>
    @endif
</x-plantilla.gestion>
