{{-- Pestaña Productos de la carta (G-04, RF-16): búsqueda por nombre, filtro por categoría y 15 filas por página. --}}
<x-plantilla.gestion titulo="Carta" activa="carta">
    <x-gestion.pestanas-carta activa="productos" />
    <x-gestion.ayuda>
        <p>Están todos los productos de la carta, también los retirados, ordenados por categoría. Puedes buscar por una
            parte del nombre y filtrar por categoría.</p>
        <dl class="mb-0">
            <dt>Disponible</dt><dd>Sale en la carta y se puede pedir.</dd>
            <dt>Agotado</dt><dd>Sale en la carta, pero no se puede pedir hasta que vuelvas a marcarlo como disponible.</dd>
            <dt>Retirado</dt><dd>No sale en la carta. Pasa al borrar un producto que ya se ha vendido, para que los pedidos
                antiguos sigan cuadrando; «Volver a la carta» lo recupera.</dd>
        </dl>
    </x-gestion.ayuda>

    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <a href="{{ route('gestion.carta.productos.nuevo') }}" class="btn btn-primary">Nuevo producto</a>

        <form method="GET" action="{{ route('gestion.carta') }}" class="d-flex flex-wrap gap-2 align-items-end ms-md-auto" role="search">
            <div>
                <label for="buscar" class="form-label small mb-1">Buscar por nombre</label>
                <input type="search" id="buscar" name="buscar" value="{{ $buscar }}" class="form-control" maxlength="60">
            </div>
            <div>
                <label for="categoria" class="form-label small mb-1">Categoría</label>
                <select id="categoria" name="categoria" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($categorias as $opcion)
                        <option value="{{ $opcion->id }}" @selected($categoria === $opcion->id)>{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline-secondary">Buscar</button>
        </form>
    </div>

    @if ($productos->isEmpty())
        <p>{{ $buscar !== '' || $categoria > 0 ? 'Ningún producto coincide con la búsqueda.' : 'Todavía no hay productos en la carta.' }}</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Productos de la carta</caption>
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Categoría</th>
                        <th scope="col" class="text-end">Precio</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productos as $producto)
                        <tr>
                            <td>{{ $producto->nombre }}</td>
                            <td>{{ $producto->categoria->nombre }}</td>
                            <td class="text-end text-nowrap">{{ number_format((float) $producto->precio, 2, ',', '.') }} €</td>
                            <td>
                                @if ($producto->retirado_en)
                                    Retirado
                                @else
                                    {{ $producto->disponible ? 'Disponible' : 'Agotado' }}
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('gestion.carta.productos.editar', $producto) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar<span class="visually-hidden"> {{ $producto->nombre }}</span>
                                </a>
                                @if ($producto->retirado_en)
                                    <form method="POST" action="{{ route('gestion.carta.productos.volver', $producto) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Volver a la carta<span class="visually-hidden">: {{ $producto->nombre }}</span>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('gestion.carta.productos.borrar', $producto) }}" class="d-inline"
                                          data-confirmar="¿Borrar «{{ $producto->nombre }}»? Si ya se ha vendido, se retirará de la carta.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Borrar<span class="visually-hidden"> {{ $producto->nombre }}</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $productos->links() }}
    @endif
</x-plantilla.gestion>
