{{-- Detalle y decisión de un pedido (G-03, RF-12): aceptar con hora de recogida o rechazar con motivo. --}}
@php
    $total = $pedido->lineas->sum(fn ($linea) => $linea->cantidad * (float) $linea->precio_unitario);
@endphp

<x-plantilla.gestion :titulo="'Pedido '.$pedido->codigo" activa="pedidos">
    <x-gestion.ayuda>
        <p>Antes de aceptar, revisa las observaciones y los alérgenos. La hora de recogida solo ofrece franjas de 15
            minutos de los turnos del día previsto que aún no han pasado; si no hay ninguna, el día ya ha terminado y
            solo se puede rechazar.</p>
        <p>Si el cliente cancela mientras decides, la decisión no se aplica y te avisamos. El motivo de un rechazo le
            llega al cliente por correo, así que escríbelo para él.</p>
        <p class="mb-0">Un pedido aceptado pasa a «Listo para recoger» al salir del horno. Cuando el cliente paga y se lo
            lleva, márcalo como «Recogido y pagado»; si no viene, «No recogido», sin pago. Solo salen los pasos que tocan.</p>
    </x-gestion.ayuda>

    <p><a href="{{ route('gestion.pedidos') }}">← Volver al panel</a></p>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="titulo-pedido">
            <h2 id="titulo-pedido" class="h5">
                {{ \App\Models\Pedido::ETIQUETAS[$pedido->estado] }}
                @if ($pedido->canal === 'telefono')
                    · por teléfono
                @endif
            </h2>
            <dl class="row mb-3">
                <dt class="col-sm-4">Cliente</dt>
                <dd class="col-sm-8">{{ $pedido->cliente->nombre }}</dd>
                <dt class="col-sm-4">Teléfono</dt>
                <dd class="col-sm-8"><a href="tel:{{ $pedido->cliente->telefono }}">{{ $pedido->cliente->telefono }}</a></dd>
                <dt class="col-sm-4">Hecho</dt>
                <dd class="col-sm-8">{{ $pedido->creado_en->format('d/m/Y H:i') }}</dd>
                <dt class="col-sm-4">Día previsto</dt>
                <dd class="col-sm-8">{{ $pedido->fecha_prevista->format('d/m/Y') }}</dd>
                @if ($pedido->hora_recogida)
                    <dt class="col-sm-4">Recogida</dt>
                    <dd class="col-sm-8">{{ $pedido->hora_recogida->format('H:i') }}</dd>
                @endif
                @if ($pedido->usuario)
                    <dt class="col-sm-4">Decidido por</dt>
                    <dd class="col-sm-8">{{ $pedido->usuario->nombre }}, el {{ $pedido->decidido_en->format('d/m H:i') }}</dd>
                @endif
                @if ($pedido->pagado_en)
                    <dt class="col-sm-4">Pagado</dt>
                    <dd class="col-sm-8">{{ $pedido->pagado_en->format('d/m H:i') }}</dd>
                @endif
                @if ($pedido->motivo_rechazo)
                    <dt class="col-sm-4">Motivo del rechazo</dt>
                    <dd class="col-sm-8">{{ $pedido->motivo_rechazo }}</dd>
                @endif
            </dl>

            <table class="table table-sm">
                <caption class="visually-hidden">Líneas del pedido</caption>
                <tbody>
                    @foreach ($pedido->lineas as $linea)
                        <tr>
                            <td>{{ $linea->cantidad }} × {{ $linea->nombre_producto }}</td>
                            <td class="text-end text-nowrap">{{ number_format($linea->cantidad * (float) $linea->precio_unitario, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total</th>
                        <td class="text-end fw-bold text-nowrap">{{ number_format($total, 2, ',', '.') }} €</td>
                    </tr>
                </tfoot>
            </table>

            <p class="mb-1"><strong>Alérgenos:</strong> {{ $alergenos->isEmpty() ? 'ninguno declarado' : mb_strtolower($alergenos->implode(', ')) }}.</p>
            <p><strong>Observaciones:</strong> {{ $pedido->observaciones ?: 'ninguna' }}.</p>
        </section>

        @if (isset(\App\Servicios\CambiosDeEstado::SIGUIENTES[$pedido->estado]))
            @php
                $botones = [
                    'listo' => ['Listo para recoger', 'btn-primary', null],
                    'recogido' => ['Recogido y pagado', 'btn-primary', '¿El cliente ha recogido y pagado el pedido '.$pedido->codigo.'?'],
                    'no_recogido' => ['No recogido', 'btn-outline-danger', '¿Cerrar el pedido '.$pedido->codigo.' como no recogido, sin pago?'],
                ];
            @endphp
            <section class="col-lg-5" aria-labelledby="titulo-siguiente">
                <div class="border rounded p-3 bg-white">
                    <h2 id="titulo-siguiente" class="h5">Siguiente paso</h2>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (\App\Servicios\CambiosDeEstado::SIGUIENTES[$pedido->estado] as $siguiente)
                            <form method="POST" action="{{ route('gestion.pedidos.avanzar', $pedido) }}"
                                  @if ($botones[$siguiente][2]) data-confirmar="{{ $botones[$siguiente][2] }}" @endif>
                                @csrf
                                <input type="hidden" name="estado" value="{{ $siguiente }}">
                                <button type="submit" class="btn {{ $botones[$siguiente][1] }}">{{ $botones[$siguiente][0] }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($pedido->estado === 'pendiente')
            <section class="col-lg-5" aria-label="Decidir el pedido">
                <form method="POST" action="{{ route('gestion.pedidos.aceptar', $pedido) }}" class="border rounded p-3 mb-3 bg-white">
                    @csrf
                    <h2 class="h5">Aceptar</h2>
                    @if ($franjas === [])
                        <p class="mb-0">No quedan horas de recogida el {{ $pedido->fecha_prevista->format('d/m/Y') }}: el día ya ha terminado.</p>
                    @else
                        <label for="hora" class="form-label">Hora de recogida</label>
                        <select id="hora" name="hora" required @class(['form-select mb-3', 'is-invalid' => $errors->has('hora')])>
                            @foreach ($franjas as $franja)
                                <option value="{{ $franja }}" @selected(old('hora') === $franja)>{{ $franja }}</option>
                            @endforeach
                        </select>
                        @error('hora')
                            <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-primary">Aceptar pedido</button>
                    @endif
                </form>

                <form method="POST" action="{{ route('gestion.pedidos.rechazar', $pedido) }}" class="border rounded p-3 bg-white">
                    @csrf
                    <h2 class="h5">Rechazar</h2>
                    <label for="motivo" class="form-label">Motivo</label>
                    <textarea id="motivo" name="motivo" rows="2" maxlength="255" required
                              @class(['form-control mb-3', 'is-invalid' => $errors->has('motivo')])>{{ old('motivo') }}</textarea>
                    @error('motivo')
                        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="btn btn-outline-danger">Rechazar pedido</button>
                </form>
            </section>
        @endif
    </div>
</x-plantilla.gestion>
