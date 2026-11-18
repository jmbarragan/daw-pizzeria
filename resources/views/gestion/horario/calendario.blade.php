{{-- Calendario de apertura (G-07, RF-19): 14 días seguidos; una fecha sin turnos está cerrada. --}}
<x-plantilla.gestion titulo="Horario" activa="horario">
    <x-gestion.ayuda>
        <p>Cada fila es un turno de apertura. Una fecha sin turnos está cerrada, como un festivo o el día de descanso, y
            con dos turnos tienes turno partido. Los clientes solo pueden elegir recogidas dentro de estos turnos.</p>
        <p class="mb-0">«Pedidos» cuenta los que se recogen en cada turno. Un turno con pedidos no se puede borrar, y
            sus horas solo cambian si siguen incluyendo las recogidas de los pedidos aceptados.</p>
    </x-gestion.ayuda>
    <nav class="d-flex flex-wrap gap-2 align-items-center mb-3" aria-label="Fechas del calendario">
        <a href="{{ route('gestion.horario', ['desde' => $desde->copy()->subDays(14)->toDateString()]) }}" class="btn btn-outline-secondary">
            14 días antes
        </a>
        <a href="{{ route('gestion.horario') }}" class="btn btn-outline-secondary">Hoy</a>
        <a href="{{ route('gestion.horario', ['desde' => $desde->copy()->addDays(14)->toDateString()]) }}" class="btn btn-outline-secondary">
            14 días después
        </a>
        <span class="ms-md-auto">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</span>
    </nav>

    <div class="table-responsive">
        <table class="table align-middle">
            <caption class="visually-hidden">Turnos de apertura del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</caption>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Turno</th>
                    <th scope="col">Apertura</th>
                    <th scope="col">Cierre</th>
                    <th scope="col">Pedidos</th>
                    <th scope="col"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dias as $dia)
                    @php
                        $delDia = $turnos->get($dia->toDateString(), collect());
                        $fecha = ucfirst($dia->locale('es')->isoFormat('ddd DD/MM/YYYY'));
                        $pasado = $dia->lt(today());
                    @endphp
                    @forelse ($delDia as $turno)
                        <tr>
                            <th scope="row" class="fw-normal">{{ $loop->first ? $fecha : '' }}<span class="visually-hidden">{{ $loop->first ? '' : $fecha }}</span></th>
                            <td>{{ $turno->turno }}</td>
                            <td>{{ substr($turno->hora_apertura, 0, 5) }}</td>
                            <td>{{ substr($turno->hora_cierre, 0, 5) }}</td>
                            <td>{{ $turno->pedidos_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('gestion.horario.turnos.editar', $turno) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar<span class="visually-hidden"> turno {{ $turno->turno }} del {{ $dia->format('d/m/Y') }}</span>
                                </a>
                                <form method="POST" action="{{ route('gestion.horario.turnos.borrar', $turno) }}" class="d-inline"
                                      data-confirmar="¿Borrar el turno {{ $turno->turno }} del {{ $dia->format('d/m/Y') }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Borrar<span class="visually-hidden"> turno {{ $turno->turno }} del {{ $dia->format('d/m/Y') }}</span>
                                    </button>
                                </form>
                                @if ($loop->last && ! $pasado)
                                    <a href="{{ route('gestion.horario.turnos.nuevo', ['dia' => $dia->toDateString()]) }}" class="btn btn-sm btn-outline-primary">
                                        Otro turno<span class="visually-hidden"> el {{ $dia->format('d/m/Y') }}</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="table-light">
                            <th scope="row" class="fw-normal">{{ $fecha }}</th>
                            <td colspan="4">Cerrado</td>
                            <td class="text-end">
                                @unless ($pasado)
                                    <a href="{{ route('gestion.horario.turnos.nuevo', ['dia' => $dia->toDateString()]) }}" class="btn btn-sm btn-outline-primary">
                                        Abrir<span class="visually-hidden"> el {{ $dia->format('d/m/Y') }}</span>
                                    </a>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    </div>
</x-plantilla.gestion>
