{{-- Panel de pedidos (G-02, RF-11): pendientes, los de hoy y búsqueda por código, teléfono, fecha o estado. --}}
<x-plantilla.gestion titulo="Pedidos" activa="pedidos">
    <x-gestion.ayuda>
        <p>«Pendientes» son los pedidos que esperan respuesta, del más antiguo al más nuevo: ábrelos por orden para
            aceptarlos con su hora de recogida o rechazarlos. «Hoy» reúne todos los que se recogen hoy, por hora.</p>
        <p class="mb-0">Para buscar, basta con rellenar uno de los campos: el código (vale el principio), una parte del
            teléfono, el día previsto o el estado. Un pedido que llega por teléfono se da de alta con «Pedido telefónico».</p>
    </x-gestion.ayuda>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <ul class="nav nav-tabs flex-grow-1">
            <li class="nav-item">
                <a href="{{ route('gestion.pedidos') }}" @class(['nav-link', 'active' => $vista === 'pendientes'])
                   @if ($vista === 'pendientes') aria-current="page" @endif>Pendientes ({{ $pendientes }})</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('gestion.pedidos', ['vista' => 'hoy']) }}" @class(['nav-link', 'active' => $vista === 'hoy'])
                   @if ($vista === 'hoy') aria-current="page" @endif>Hoy</a>
            </li>
            @if ($vista === 'buscar')
                <li class="nav-item"><span class="nav-link active" aria-current="page">Búsqueda</span></li>
            @endif
        </ul>
        <a href="{{ route('gestion.pedidos.telefonico') }}" class="btn btn-primary">Pedido telefónico</a>
    </div>

    <form method="GET" action="{{ route('gestion.pedidos') }}" class="row g-2 align-items-end mb-4" role="search">
        <div class="col-6 col-md-2">
            <label for="codigo" class="form-label small mb-1">Código</label>
            <input type="search" id="codigo" name="codigo" maxlength="8" value="{{ $filtros['codigo'] }}" class="form-control text-uppercase">
        </div>
        <div class="col-6 col-md-3">
            <label for="telefono" class="form-label small mb-1">Teléfono</label>
            <input type="search" id="telefono" name="telefono" inputmode="tel" maxlength="20" value="{{ $filtros['telefono'] }}" class="form-control">
        </div>
        <div class="col-6 col-md-3">
            <label for="fecha" class="form-label small mb-1">Día previsto</label>
            <input type="date" id="fecha" name="fecha" value="{{ $filtros['fecha'] }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
            <label for="estado" class="form-label small mb-1">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach (\App\Models\Pedido::ETIQUETAS as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-secondary">Buscar</button>
            @if ($vista === 'buscar')
                <a href="{{ route('gestion.pedidos') }}" class="btn btn-link">Limpiar</a>
            @endif
        </div>
    </form>

    @if ($pedidos->isEmpty())
        <p>
            @switch($vista)
                @case('pendientes') No hay pedidos pendientes. @break
                @case('hoy') Hoy no hay pedidos para recoger. @break
                @default Ningún pedido coincide con la búsqueda.
            @endswitch
        </p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">Pedidos</caption>
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">{{ $vista === 'hoy' ? 'Recogida' : 'Hecho' }}</th>
                        <th scope="col">Cliente</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        <tr>
                            <td>
                                <a href="{{ route('gestion.pedidos.detalle', $pedido) }}" class="font-monospace fw-bold">{{ $pedido->codigo }}</a>
                                @if ($pedido->canal === 'telefono')
                                    <span class="badge text-bg-light border">Teléfono</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if ($vista === 'hoy')
                                    {{ $pedido->hora_recogida?->format('H:i') ?? 'Sin hora' }}
                                @else
                                    {{ $pedido->creado_en->isToday() ? $pedido->creado_en->format('H:i') : $pedido->creado_en->format('d/m H:i') }}
                                @endif
                            </td>
                            <td>{{ $pedido->cliente_nombre }}</td>
                            <td class="text-end text-nowrap">{{ number_format((float) $pedido->importe, 2, ',', '.') }} €</td>
                            <td>{{ \App\Models\Pedido::ETIQUETAS[$pedido->estado] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $pedidos->links() }}
    @endif
</x-plantilla.gestion>
