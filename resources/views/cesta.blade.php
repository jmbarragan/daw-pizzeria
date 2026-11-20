{{-- Cesta (P-03, RF-03): unidades de 1 a 20 por producto, importes con el precio de ahora y total. --}}
<x-plantilla.publica titulo="Cesta" descripcion="Revisa tu pedido para recoger en La Focaccia di Trevi antes de tramitarlo.">
    <h1 class="h2">Tu cesta</h1>

    @if (session('quitados_de_la_cesta'))
        <p class="alert alert-warning" role="status">
            Hemos quitado de tu cesta {{ session('quitados_de_la_cesta') === 1 ? 'un producto que ya no está' : session('quitados_de_la_cesta').' productos que ya no están' }} en la carta.
        </p>
    @endif

    @if ($lineas->isEmpty())
        <p>Todavía no has añadido nada.</p>
        <a href="{{ route('carta') }}" class="btn btn-primary">Ver la carta</a>
    @else
        <x-estado-del-local :abierto="$abierto" :siguiente="$siguiente" :telefono="$telefono" />

        @if ($agotados->isNotEmpty())
            <p class="alert alert-danger" role="alert">
                {{ $agotados->count() === 1 ? 'Un producto de tu cesta se ha agotado' : 'Algunos productos de tu cesta se han agotado' }}:
                quítalo{{ $agotados->count() === 1 ? '' : 's' }} para poder tramitar el pedido.
            </p>
        @endif

        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Productos de tu cesta</caption>
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col" class="text-center">Unidades</th>
                        <th scope="col" class="text-end">Importe</th>
                        <th scope="col"><span class="visually-hidden">Quitar</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as ['producto' => $producto, 'unidades' => $unidades, 'importe' => $importe])
                        <tr>
                            <td>
                                {{ $producto->nombre }}
                                @unless ($producto->disponible)
                                    <span class="badge text-bg-secondary">Agotado</span>
                                @endunless
                                <div class="small text-body-secondary">{{ number_format((float) $producto->precio, 2, ',', '.') }} € la unidad</div>
                            </td>
                            <td class="text-center text-nowrap">
                                <form method="POST" action="{{ route('cesta.cambiar', $producto) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="unidades" value="{{ $unidades - 1 }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($unidades <= 1 || ! $producto->disponible)
                                            aria-label="Una unidad menos de {{ $producto->nombre }}">−</button>
                                </form>
                                <span class="mx-2" aria-label="{{ $unidades }} unidades">{{ $unidades }}</span>
                                <form method="POST" action="{{ route('cesta.cambiar', $producto) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="unidades" value="{{ $unidades + 1 }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                            @disabled($unidades >= \App\Servicios\Cesta::MAXIMO_POR_PRODUCTO || ! $producto->disponible)
                                            aria-label="Una unidad más de {{ $producto->nombre }}">+</button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">{{ number_format($importe, 2, ',', '.') }} €</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('cesta.quitar', $producto) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link">
                                        Quitar<span class="visually-hidden"> {{ $producto->nombre }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="2">Total</th>
                        <td class="text-end fw-bold text-nowrap">{{ number_format($total, 2, ',', '.') }} €</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="small">Se paga al recoger. Los precios son los de la carta de ahora y se vuelven a comprobar al confirmar.</p>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('carta') }}" class="btn btn-outline-secondary">Seguir pidiendo</a>
            @if ($sePuedeTramitar)
                <a href="{{ url('/pedido') }}" class="btn btn-primary">Tramitar pedido</a>
            @else
                <button type="button" class="btn btn-primary" disabled>Tramitar pedido</button>
            @endif
        </div>
    @endif
</x-plantilla.publica>
