{{-- Informes por intervalo de fechas (G-08, RF-20): ventas cobradas, productos más vendidos y pedidos por estado. --}}

<x-plantilla.gestion titulo="Informes" activa="informes">
    <x-gestion.ayuda>
        <dl class="mb-0">
            <dt>Ventas cobradas</dt><dd>Lo que se ha cobrado cada día: los pedidos recogidos, por el día en que se pagaron.</dd>
            <dt>Productos más vendidos</dt><dd>Unidades e importe de cada producto en esos mismos pedidos, del que más se vende al que menos.</dd>
            <dt>Pedidos por estado</dt><dd>Cuántos pedidos hay en cada estado entre los que tenían su fecha prevista en
                esos días, se hayan recogido o no.</dd>
            <dt>Fechas</dt><dd>Por defecto, el mes en curso. Los dos días se incluyen.</dd>
        </dl>
    </x-gestion.ayuda>

    <form method="GET" action="{{ route('gestion.informes') }}" class="row g-2 align-items-end mb-4">
        <div class="col-sm-4">
            <label for="informe" class="form-label">Informe</label>
            <select id="informe" name="informe" class="form-select">
                @foreach (\App\Servicios\Informes::TIPOS as $clave => $titulo)
                    <option value="{{ $clave }}" @selected($informe === $clave)>{{ $titulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde->toDateString() }}"
                   @class(['form-control', 'is-invalid' => $errors->has('desde')])>
        </div>
        <div class="col-sm-3">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta->toDateString() }}"
                   @class(['form-control', 'is-invalid' => $errors->has('hasta')])>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-primary w-100">Ver</button>
        </div>
        @if ($errors->any())
            <div class="col-12"><div class="alert alert-danger py-2 mb-0" role="alert">{{ $errors->first() }}</div></div>
        @endif
    </form>

    <h2 class="h5">{{ \App\Servicios\Informes::TIPOS[$informe] }}
        <span class="text-body-secondary fw-normal">del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</span></h2>

    @if ($resultado['filas'] === [])
        <p class="alert alert-info">No hay datos para estas fechas.</p>
    @else
        <div class="table-responsive" style="max-width: 40rem">
            @include('gestion.informe-tabla', ['informe' => $informe, 'resultado' => $resultado])
        </div>
        <p><a href="{{ route('gestion.informes.pdf', ['informe' => $informe, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}"
              class="btn btn-outline-primary">Descargar PDF</a></p>
    @endif
</x-plantilla.gestion>
