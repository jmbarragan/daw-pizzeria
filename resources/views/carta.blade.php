{{-- Carta pública (P-02, RF-01): por categorías, con precio, descripción y alérgenos de cada producto. --}}
<x-plantilla.publica titulo="Carta" descripcion="Carta de pizzas, focaccias, entrantes, postres y bebidas para recoger en el local, con los alérgenos de cada plato.">
    <h1 class="h2">Carta</h1>

    @if ($categorias->isEmpty())
        <p>Estamos preparando la carta. Vuelve en un rato.</p>
    @else
        <nav aria-label="Secciones de la carta" class="mb-4">
            <ul class="nav nav-pills">
                @foreach ($categorias as $categoria)
                    <li class="nav-item"><a class="nav-link" href="#categoria-{{ $categoria->id }}">{{ $categoria->nombre }}</a></li>
                @endforeach
            </ul>
        </nav>

        @foreach ($categorias as $categoria)
            <section id="categoria-{{ $categoria->id }}" class="mb-5" aria-labelledby="titulo-categoria-{{ $categoria->id }}">
                <h2 id="titulo-categoria-{{ $categoria->id }}" class="h3 border-bottom pb-2">{{ $categoria->nombre }}</h2>

                <ul class="list-unstyled">
                    @foreach ($categoria->productos as $producto)
                        <li id="producto-{{ $producto->id }}" class="producto-carta py-3 border-bottom">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <h3 class="h5 mb-1">{{ $producto->nombre }}</h3>
                                <span class="fw-bold text-nowrap">{{ number_format((float) $producto->precio, 2, ',', '.') }} €</span>
                            </div>

                            @if ($producto->descripcion)
                                <p class="mb-1">{{ $producto->descripcion }}</p>
                            @endif

                            <p class="small mb-2">
                                @if ($producto->alergenos->isEmpty())
                                    Sin alérgenos declarados.
                                @else
                                    Alérgenos: {{ $producto->alergenos->sortBy('nombre')->pluck('nombre')->implode(', ') }}.
                                @endif
                            </p>

                            @if ($producto->disponible)
                                <form method="POST" action="{{ route('cesta.anadir') }}">
                                    @csrf
                                    <input type="hidden" name="producto" value="{{ $producto->id }}">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Añadir<span class="visually-hidden"> {{ $producto->nombre }} a la cesta</span>
                                    </button>
                                </form>
                            @else
                                <p class="mb-0"><span class="badge text-bg-secondary">Agotado</span>
                                    <span class="visually-hidden">: hoy no se puede pedir.</span></p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($alergenos->isNotEmpty())
            <section aria-labelledby="titulo-alergenos" class="mb-4">
                <h2 id="titulo-alergenos" class="h4">Qué incluye cada alérgeno</h2>
                <dl class="small">
                    @foreach ($alergenos as $alergeno)
                        <dt>{{ $alergeno->nombre }}</dt>
                        <dd>{{ $alergeno->descripcion }}</dd>
                    @endforeach
                </dl>
                <p class="small">Si tienes una alergia o una intolerancia, dínoslo en las observaciones del pedido o llámanos antes de pedir.</p>
            </section>
        @endif
    @endif
</x-plantilla.publica>
