{{-- Ajustes del local y bloqueos de IP (G-10, RF-21), solo para el administrador. --}}
@php
    $campo = fn (string $clave) => old($clave, $valores[$clave] ?? '');
@endphp

<x-plantilla.gestion titulo="Ajustes" activa="ajustes">
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Datos del local</dt><dd>El teléfono y la dirección salen en la web y en los correos; la política de
                privacidad es la que acepta el cliente al pedir.</dd>
            <dt>Correo saliente</dt><dd>El servidor por el que salen los correos a los clientes. Si falta alguno de los
                cuatro datos, se usa el que viene en la instalación. La contraseña se guarda cifrada y no se vuelve a
                mostrar: déjala vacía para conservar la que hay.</dd>
            <dt>IP bloqueadas</dt><dd>Tras diez accesos fallidos desde una misma conexión, se bloquea 24 horas; la segunda vez
                en 30 días, para siempre. «Desbloquear» la deja entrar otra vez y apunta quién lo ha hecho.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" action="{{ route('gestion.ajustes.guardar') }}" novalidate class="mb-5" style="max-width: 44rem">
        @csrf
        @method('PUT')

        <fieldset class="mb-4">
            <legend class="h5">Datos del local</legend>
            <div class="row g-3">
                <div class="col-sm-5">
                    <label for="telefono_local" class="form-label">Teléfono</label>
                    <input type="tel" id="telefono_local" name="telefono_local" required value="{{ $campo('telefono_local') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('telefono_local')])>
                    @error('telefono_local') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-7">
                    <label for="direccion_local" class="form-label">Dirección</label>
                    <input type="text" id="direccion_local" name="direccion_local" maxlength="150" required value="{{ $campo('direccion_local') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('direccion_local')])>
                    @error('direccion_local') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="politica_privacidad" class="form-label">Política de privacidad</label>
                    <textarea id="politica_privacidad" name="politica_privacidad" rows="5" maxlength="5000" required
                              @class(['form-control', 'is-invalid' => $errors->has('politica_privacidad')])>{{ $campo('politica_privacidad') }}</textarea>
                    @error('politica_privacidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="mb-4">
            <legend class="h5">Correo saliente (SMTP)</legend>
            <div class="row g-3">
                <div class="col-sm-8">
                    <label for="smtp_servidor" class="form-label">Servidor</label>
                    <input type="text" id="smtp_servidor" name="smtp_servidor" maxlength="255" required autocomplete="off"
                           value="{{ $campo('smtp_servidor') }}" @class(['form-control', 'is-invalid' => $errors->has('smtp_servidor')])>
                    @error('smtp_servidor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-4">
                    <label for="smtp_puerto" class="form-label">Puerto</label>
                    <input type="number" id="smtp_puerto" name="smtp_puerto" min="1" max="65535" required aria-describedby="ayuda-puerto"
                           value="{{ $campo('smtp_puerto') }}" @class(['form-control', 'is-invalid' => $errors->has('smtp_puerto')])>
                    <div id="ayuda-puerto" class="form-text">587 con STARTTLS o 465 con TLS.</div>
                    @error('smtp_puerto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label for="smtp_usuario" class="form-label">Usuario</label>
                    <input type="text" id="smtp_usuario" name="smtp_usuario" maxlength="254" required autocomplete="off"
                           value="{{ $campo('smtp_usuario') }}" @class(['form-control', 'is-invalid' => $errors->has('smtp_usuario')])>
                    @error('smtp_usuario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label for="smtp_contrasena" class="form-label">Contraseña</label>
                    <input type="password" id="smtp_contrasena" name="smtp_contrasena" maxlength="255" autocomplete="new-password"
                           aria-describedby="ayuda-contrasena" @class(['form-control', 'is-invalid' => $errors->has('smtp_contrasena')])>
                    <div id="ayuda-contrasena" class="form-text">
                        {{ $hayContrasena ? 'Hay una guardada. Deja el campo vacío para conservarla.' : 'No hay ninguna: hasta que la pongas, se usa el correo de la instalación.' }}
                    </div>
                    @error('smtp_contrasena') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary">Guardar los ajustes</button>
        @if ($cambiado?->actualizado_en)
            <span class="ms-2 small text-body-secondary">Último cambio: {{ $cambiado->actualizado_en->format('d/m/Y H:i') }}</span>
        @endif
    </form>

    <h2 class="h5">IP bloqueadas en los últimos 30 días</h2>
    @if ($bloqueos->isEmpty())
        <p>No ha habido ningún bloqueo.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle" style="max-width: 44rem">
                <thead>
                    <tr>
                        <th scope="col">IP</th>
                        <th scope="col">Desde</th>
                        <th scope="col">Hasta</th>
                        <th scope="col"><span class="visually-hidden">Estado o acción</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bloqueos as $bloqueo)
                        <tr>
                            <td class="font-monospace">{{ $bloqueo->ip }}</td>
                            <td>{{ $bloqueo->inicio->format('d/m H:i') }}</td>
                            <td>{{ $bloqueo->fin ? $bloqueo->fin->format('d/m H:i') : 'Permanente' }}</td>
                            <td class="text-end">
                                @if ($bloqueo->desbloqueado_en)
                                    <span class="small text-body-secondary">Desbloqueada el {{ $bloqueo->desbloqueado_en->format('d/m H:i') }}
                                        por {{ $bloqueo->desbloqueadoPor?->nombre ?? '—' }}</span>
                                @elseif ($bloqueo->fin && $bloqueo->fin->isPast())
                                    <span class="small text-body-secondary">Ya ha caducado</span>
                                @else
                                    <form method="POST" action="{{ route('gestion.ajustes.desbloquear', $bloqueo) }}"
                                          data-confirmar="¿Desbloquear la IP {{ $bloqueo->ip }}?">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Desbloquear<span class="visually-hidden"> {{ $bloqueo->ip }}</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-plantilla.gestion>
