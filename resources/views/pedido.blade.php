{{-- Datos y confirmación del pedido (P-04, RF-04): sin cuenta, con la política de privacidad aceptada. --}}
<x-plantilla.publica titulo="Tus datos" descripcion="Deja tu nombre, tu teléfono y tu correo para recoger tu pedido en La Focaccia di Trevi.">
    <h1 class="h2">Tus datos</h1>

    <x-estado-del-local :abierto="$abierto" :siguiente="$siguiente" :telefono="$telefono" />

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('pedido.confirmar') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="80" required autocomplete="name"
                           value="{{ old('nombre') }}" @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
                    @error('nombre')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" required autocomplete="tel" inputmode="tel"
                           aria-describedby="ayuda-telefono" value="{{ old('telefono') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('telefono')])>
                    <div id="ayuda-telefono" class="form-text">Te llamamos solo si hay algún problema con el pedido.</div>
                    @error('telefono')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" id="email" name="email" maxlength="254" required autocomplete="email"
                           aria-describedby="ayuda-email" value="{{ old('email') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('email')])>
                    <div id="ayuda-email" class="form-text">Te enviaremos el enlace para seguir el pedido y la hora de recogida.</div>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones <span class="text-body-secondary">(opcional)</span></label>
                    <textarea id="observaciones" name="observaciones" rows="2" maxlength="255" aria-describedby="ayuda-observaciones"
                              @class(['form-control', 'is-invalid' => $errors->has('observaciones')])>{{ old('observaciones') }}</textarea>
                    <div id="ayuda-observaciones" class="form-text">Por ejemplo, una alergia que debamos tener en cuenta.</div>
                    @error('observaciones')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" id="privacidad" name="privacidad" value="1" required
                           @class(['form-check-input', 'is-invalid' => $errors->has('privacidad')]) @checked(old('privacidad'))>
                    <label for="privacidad" class="form-check-label">
                        He leído la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">política de privacidad</a>
                        <span class="visually-hidden">(se abre en otra pestaña)</span>
                    </label>
                    @error('privacidad')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Confirmar pedido</button>
                <a href="{{ route('cesta') }}" class="btn btn-link">Volver a la cesta</a>
            </form>
        </div>

        <aside class="col-lg-5" aria-labelledby="titulo-resumen">
            <div class="border rounded p-3 bg-white">
                <h2 id="titulo-resumen" class="h5">Tu pedido</h2>
                <ul class="list-unstyled mb-2">
                    @foreach ($lineas as ['producto' => $producto, 'unidades' => $unidades, 'importe' => $importe])
                        <li class="d-flex justify-content-between gap-2">
                            <span>{{ $unidades }} × {{ $producto->nombre }}</span>
                            <span class="text-nowrap">{{ number_format($importe, 2, ',', '.') }} €</span>
                        </li>
                    @endforeach
                </ul>
                <p class="d-flex justify-content-between fw-bold border-top pt-2 mb-2">
                    <span>Total</span><span>{{ number_format($total, 2, ',', '.') }} €</span>
                </p>
                <p class="small mb-1">
                    Alérgenos: {{ $alergenos->isEmpty() ? 'ninguno declarado' : mb_strtolower($alergenos->implode(', ')) }}.
                </p>
                <p class="small mb-0">Pago al recoger.</p>
            </div>
        </aside>
    </div>
</x-plantilla.publica>
