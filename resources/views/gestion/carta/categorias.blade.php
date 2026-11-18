{{-- Pestaña Categorías de la carta (G-04, RF-15): en el orden en que salen en la carta pública. --}}
<x-plantilla.gestion titulo="Carta" activa="carta">
    <x-gestion.pestanas-carta activa="categorias" />
    <x-gestion.ayuda>
        <p>Las categorías son las secciones de la carta, en el orden en que la ve el cliente: el número más bajo sale
            primero. Una categoría oculta no aparece en la carta, ni sus productos con ella.</p>
        <p class="mb-0">Solo se puede borrar una categoría vacía. Si tiene productos, aunque estén retirados, ocúltala.</p>
    </x-gestion.ayuda>

    <p>
        <a href="{{ route('gestion.carta.categorias.nueva') }}" class="btn btn-primary">Nueva categoría</a>
    </p>

    @if ($categorias->isEmpty())
        <p>Todavía no hay categorías. Crea la primera para empezar a montar la carta.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Categorías de la carta, en su orden</caption>
                <thead>
                    <tr>
                        <th scope="col">Orden</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">En la carta</th>
                        <th scope="col">Productos</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categorias as $categoria)
                        <tr>
                            <td>{{ $categoria->orden }}</td>
                            <td>{{ $categoria->nombre }}</td>
                            <td>{{ $categoria->activa ? 'Visible' : 'Oculta' }}</td>
                            <td>{{ $categoria->productos_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('gestion.carta.categorias.editar', $categoria) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar<span class="visually-hidden"> {{ $categoria->nombre }}</span>
                                </a>
                                <form method="POST" action="{{ route('gestion.carta.categorias.borrar', $categoria) }}" class="d-inline"
                                      data-confirmar="¿Borrar la categoría «{{ $categoria->nombre }}»?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Borrar<span class="visually-hidden"> {{ $categoria->nombre }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $categorias->links() }}
    @endif
</x-plantilla.gestion>
