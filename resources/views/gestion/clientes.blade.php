{{-- Clientes (G-06, RF-18): listado con búsqueda, alta, modificación y borrado o anonimización. --}}
<x-plantilla.gestion titulo="Clientes" activa="clientes">
    <x-gestion.ayuda>
        <p>Cada pedido de un invitado crea su propio cliente, así que una misma persona puede salir varias veces. Los
            clientes con cuenta salen una sola vez y su correo no se repite.</p>
        <p class="mb-0">«Borrar» borra del todo a un cliente sin pedidos. Si tiene pedidos, solo se quitan su teléfono,
            su correo y su cuenta, y se conserva el nombre. Con un pedido sin cerrar no se puede borrar, porque hace falta
            su correo para avisarle.</p>
    </x-gestion.ayuda>

    <form method="GET" action="{{ route('gestion.clientes') }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
        <label for="buscar" class="visually-hidden">Buscar por nombre o teléfono</label>
        <input type="search" id="buscar" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width: 20rem"
               placeholder="Buscar por nombre o teléfono…">
        <button type="submit" class="btn btn-outline-secondary">Buscar</button>
        @if ($buscar !== '')
            <a href="{{ route('gestion.clientes') }}" class="btn btn-link">Ver todos</a>
        @endif
        <a href="{{ route('gestion.clientes.nuevo') }}" class="btn btn-primary ms-auto">Nuevo cliente</a>
    </form>

    @if ($clientes->isEmpty())
        <p>{{ $buscar === '' ? 'Todavía no hay clientes.' : 'Ningún cliente coincide con la búsqueda.' }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Clientes, de los más nuevos a los más antiguos</caption>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Pedidos</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clientes as $cliente)
                        <tr>
                            <td>{{ $cliente->nombre }}</td>
                            <td>{{ $cliente->telefono ?? '—' }}</td>
                            <td class="text-break">{{ $cliente->email ?? '—' }}</td>
                            <td>{{ $cliente->anonimizado_en ? 'Anonimizado' : ($cliente->esInvitado() ? 'Invitado' : 'Con cuenta') }}</td>
                            <td>{{ $cliente->pedidos_count }}</td>
                            <td class="text-end text-nowrap">
                                @unless ($cliente->anonimizado_en)
                                    <a href="{{ route('gestion.clientes.editar', $cliente) }}" class="btn btn-sm btn-outline-secondary">
                                        Editar<span class="visually-hidden"> {{ $cliente->nombre }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('gestion.clientes.borrar', $cliente) }}" class="d-inline"
                                          data-confirmar="{{ $cliente->pedidos_count > 0
                                              ? "«{$cliente->nombre}» tiene pedidos: se quitarán su teléfono, su correo y su cuenta, y se conservará el nombre. ¿Seguir?"
                                              : "¿Borrar a «{$cliente->nombre}»?" }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Borrar<span class="visually-hidden"> {{ $cliente->nombre }}</span>
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $clientes->links() }}
    @endif
</x-plantilla.gestion>
