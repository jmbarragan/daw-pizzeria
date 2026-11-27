{{-- Inicio (P-01, RF-02): estado del local, invitación a la carta y horario de los próximos siete días. --}}
<x-plantilla.publica titulo="Pedidos para recoger">
    <x-estado-del-local :abierto="$abierto" :siguiente="$siguiente" :telefono="$telefono" />

    <h1 class="h2">Pizzas al horno de leña para recoger en el local</h1>
    <p class="lead">Elige en la carta, deja tu nombre y tu teléfono y te avisamos por correo de la hora de recogida.</p>
    <a class="btn btn-primary btn-lg" href="{{ route('carta') }}">Ver la carta</a>

    <section aria-labelledby="titulo-horario" class="mt-5" style="max-width: 32rem">
        <h2 id="titulo-horario" class="h4">Horario de los próximos días</h2>
        <dl class="row mb-0 border-top pt-2">
            @foreach ($dias as $fecha => $turnos)
                @php($dia = \Illuminate\Support\Carbon::parse($fecha))
                <dt class="col-5 fw-normal">
                    {{ $dia->isToday() ? 'Hoy' : ucfirst($dia->locale('es')->isoFormat('dddd D/MM')) }}
                </dt>
                <dd class="col-7 mb-2">
                    @forelse ($turnos as $turno)
                        {{ substr($turno->hora_apertura, 0, 5) }}–{{ substr($turno->hora_cierre, 0, 5) }}@if (! $loop->last) y @endif
                    @empty
                        Cerrado
                    @endforelse
                </dd>
            @endforeach
        </dl>
    </section>
</x-plantilla.publica>
