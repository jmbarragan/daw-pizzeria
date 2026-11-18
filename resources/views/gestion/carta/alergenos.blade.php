{{-- Pestaña Alérgenos de la carta (G-04, RF-17): el catálogo legal, con cuántos productos declara cada uno. --}}
<x-plantilla.gestion titulo="Carta" activa="carta">
    <x-gestion.pestanas-carta activa="alergenos" />
    <x-gestion.ayuda>
        <p>Es el catálogo de los 14 alérgenos que el Reglamento (UE) 1169/2011 obliga a indicar. La columna «Productos»
            dice cuántos lo declaran.</p>
        <p class="mb-0">Un alérgeno que declara algún producto no se puede borrar: quítalo antes de esos productos.</p>
    </x-gestion.ayuda>

    <p>
        <a href="{{ route('gestion.carta.alergenos.nuevo') }}" class="btn btn-primary">Nuevo alérgeno</a>
    </p>

    @if ($alergenos->isEmpty())
        <p>El catálogo está vacío. La instalación carga los 14 alérgenos del Reglamento (UE) 1169/2011.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Catálogo de alérgenos</caption>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Lo que ve el cliente</th>
                        <th scope="col">Productos</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alergenos as $alergeno)
                        <tr>
                            <td>{{ $alergeno->nombre }}</td>
                            <td class="small">{{ $alergeno->descripcion }}</td>
                            <td>{{ $alergeno->productos_count }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('gestion.carta.alergenos.editar', $alergeno) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar<span class="visually-hidden"> {{ $alergeno->nombre }}</span>
                                </a>
                                <form method="POST" action="{{ route('gestion.carta.alergenos.borrar', $alergeno) }}" class="d-inline"
                                      data-confirmar="¿Borrar «{{ $alergeno->nombre }}» del catálogo?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Borrar<span class="visually-hidden"> {{ $alergeno->nombre }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $alergenos->links() }}
    @endif
</x-plantilla.gestion>
