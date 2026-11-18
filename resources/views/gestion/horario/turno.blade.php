{{-- Alta y modificación de un turno (G-07, RF-19). Al editar solo cambian las horas. --}}
@php
    $existe = $turno->exists;
    $fecha = $turno->dia ? ucfirst($turno->dia->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) : '';
@endphp

<x-plantilla.gestion :titulo="$existe ? 'Editar turno' : 'Nuevo turno'" activa="horario">
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Fecha</dt><dd>Solo al añadir el turno, de hoy en adelante; después no se cambia, para no mover las
                recogidas. El número de turno se pone solo, por orden de alta.</dd>
            <dt>Apertura y cierre</dt><dd>En horas y minutos. Un turno no puede pasar de medianoche ni pisar otro turno
                del mismo día, aunque sí puede empezar justo cuando acaba el anterior.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="POST" novalidate style="max-width: 32rem"
          action="{{ $existe ? route('gestion.horario.turnos.actualizar', $turno) : route('gestion.horario.turnos.guardar') }}">
        @csrf
        @if ($existe)
            @method('PUT')
            <p>Turno {{ $turno->turno }} del {{ $fecha }}. Para abrir otro día, añade un turno en esa fecha.</p>
        @else
            <div class="mb-3">
                <label for="dia" class="form-label">Fecha</label>
                <input type="date" id="dia" name="dia" required min="{{ today()->toDateString() }}"
                       value="{{ old('dia', $turno->dia?->toDateString()) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('dia')])>
                @error('dia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-6">
                <label for="hora_apertura" class="form-label">Apertura</label>
                <input type="time" id="hora_apertura" name="hora_apertura" required
                       value="{{ old('hora_apertura', $turno->hora_apertura ? substr($turno->hora_apertura, 0, 5) : '') }}"
                       @class(['form-control', 'is-invalid' => $errors->has('hora_apertura')])>
                @error('hora_apertura')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6">
                <label for="hora_cierre" class="form-label">Cierre</label>
                <input type="time" id="hora_cierre" name="hora_cierre" required
                       value="{{ old('hora_cierre', $turno->hora_cierre ? substr($turno->hora_cierre, 0, 5) : '') }}"
                       @class(['form-control', 'is-invalid' => $errors->has('hora_cierre')])>
                @error('hora_cierre')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ route('gestion.horario', ['desde' => $turno->dia?->toDateString()]) }}" class="btn btn-link">Cancelar</a>
    </form>
</x-plantilla.gestion>
