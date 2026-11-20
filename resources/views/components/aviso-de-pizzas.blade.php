{{--
    Ventana que pide llamar al local cuando un pedido pasaría del máximo de
    pizzas (RF-03). La abre app.js al cargar la página; sin JavaScript, el
    mismo texto sale en el aviso de error de la plantilla.
--}}
@props(['maximo', 'telefono' => null])

<dialog class="aviso-pizzas" data-abrir-al-cargar aria-labelledby="titulo-aviso-pizzas">
    <h2 id="titulo-aviso-pizzas" class="h5">Para más de {{ $maximo }} pizzas, llámanos</h2>
    <p>Los pedidos grandes los preparamos por encargo, para tenerlos a tiempo.
        @if ($telefono)
            Llámanos al <a href="tel:{{ $telefono }}">{{ $telefono }}</a> y lo organizamos.
        @else
            Llámanos al local y lo organizamos.
        @endif
    </p>
    <form method="dialog">
        <button type="submit" class="btn btn-primary">Entendido</button>
    </form>
</dialog>
