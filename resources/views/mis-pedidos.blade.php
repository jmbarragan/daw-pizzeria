{{-- Historial del cliente registrado (P-08, CU-09): ver el seguimiento o repetir un pedido. --}}
<x-plantilla.publica titulo="Mis pedidos" descripcion="Tus pedidos en La Focaccia di Trevi." :indexar="false">
    <h1 class="h2">Mis pedidos</h1>
    <p>Aquí salen el pedido con el que creaste la cuenta y los que hagas con la sesión iniciada. «Repetir» pone sus
        productos en la cesta con los precios de hoy.</p>

    @if ($pedidos->isEmpty())
        <p>Todavía no hay pedidos. <a href="{{ route('carta') }}">Ver la carta</a></p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Tus pedidos, de los más nuevos a los más antiguos</caption>
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Fecha</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        <tr>
                            <td class="font-monospace">{{ $pedido->codigo }}</td>
                            <td>{{ $pedido->creado_en->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format((float) $pedido->importe, 2, ',', '.') }} €</td>
                            <td>{{ \App\Models\Pedido::ETIQUETAS[$pedido->estado] }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('seguimiento', $pedido->token_seguimiento) }}" class="btn btn-sm btn-outline-secondary">
                                    Ver<span class="visually-hidden"> el pedido {{ $pedido->codigo }}</span>
                                </a>
                                <form method="POST" action="{{ route('mis-pedidos.repetir', $pedido) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Repetir<span class="visually-hidden"> el pedido {{ $pedido->codigo }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $pedidos->links() }}
    @endif
</x-plantilla.publica>
