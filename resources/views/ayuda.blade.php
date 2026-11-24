{{--
    Ayuda del cliente (P-07, RF-09, CU-21): preguntas frecuentes desplegables,
    enlazadas desde la cabecera de todas las páginas públicas. Cada respuesta es
    un <details>, así que se abre con el ratón, el teclado o el lector de
    pantalla y funciona sin JavaScript. El teléfono, la dirección y el máximo de
    pizzas salen de los ajustes del local.
--}}
@php
    $ajustes = app(\App\Servicios\AjustesPublicos::class);
    $telefono = $ajustes->valor('telefono_local');
    $direccion = $ajustes->valor('direccion_local');
    $maximo = (int) $ajustes->valor('maximo_pizzas', '20');
@endphp

<x-plantilla.publica titulo="Ayuda" descripcion="Cómo pedir para recoger en La Focaccia di Trevi, cuándo recogerlo, cómo cancelar y los alérgenos.">
    <h1 class="h2">Ayuda</h1>
    <p>Las dudas más habituales. Si no encuentras la tuya, llámanos
        @if ($telefono)
            al <a href="tel:{{ $telefono }}">{{ $telefono }}</a>
        @endif
        y te ayudamos.</p>

    <details class="ayuda mb-3">
        <summary>¿Cómo hago un pedido?</summary>
        <div class="ayuda-texto">
            <p>Elige en la <a href="{{ route('carta') }}">carta</a> lo que quieras y pulsa «Añadir»; en la cesta puedes cambiar
                las unidades. Después pulsa «Tramitar pedido», deja tu nombre, tu teléfono y tu correo, acepta la política de
                privacidad y confirma.</p>
            <p class="mb-0">No hace falta registrarse. Al confirmar verás el código del pedido y un enlace para seguirlo, que
                también te llega por correo.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Cómo sé cuándo recogerlo?</summary>
        <div class="ayuda-texto">
            <p>Revisamos cada pedido y, al aceptarlo, te escribimos con la hora de recogida{{ $direccion ? " en {$direccion}" : '' }}.
                Puedes verla también en el enlace de seguimiento, que te dice en todo momento cómo va: recibido, aceptado,
                listo o recogido.</p>
            <p class="mb-0">Si el local está cerrado puedes pedir igual: lo revisaremos al abrir. Si no podemos prepararlo, te
                escribimos con el motivo.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Puedo cancelar mi pedido?</summary>
        <div class="ayuda-texto">
            <p class="mb-0">Sí, mientras esté pendiente: abre el enlace de seguimiento y pulsa «Cancelar pedido». Si ya lo
                hemos aceptado, ya estamos con él; llámanos
                @if ($telefono)
                    al <a href="tel:{{ $telefono }}">{{ $telefono }}</a>
                @endif
                y lo vemos.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Cómo pago?</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En el local, al recogerlo. En la web no se paga nada.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Cuántas pizzas puedo pedir?</summary>
        <div class="ayuda-texto">
            <p class="mb-0">Por la web, hasta {{ $maximo }} pizzas por pedido y hasta 20 unidades de cada producto. Para un
                pedido más grande, llámanos y te lo apuntamos por teléfono.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>Alérgenos e intolerancias</summary>
        <div class="ayuda-texto">
            <p>Cada producto de la carta lleva sus alérgenos, y antes de confirmar el pedido verás todos los de tu cesta
                juntos.</p>
            <p class="mb-0">Si tienes una alergia, apúntala en «Observaciones» al hacer el pedido. En nuestra cocina se
                trabaja con harina de trigo y otros alérgenos, así que no podemos garantizar que no haya trazas.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Para qué sirve crear una cuenta?</summary>
        <div class="ayuda-texto">
            <p class="mb-0">Es opcional. Al acabar un pedido puedes crearla con solo elegir una contraseña: así ves tus pedidos
                en «Mis pedidos» y puedes repetir uno con un clic. Antes de entrar tienes que confirmar el correo con el
                enlace que te mandamos.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>¿Qué hacéis con mis datos?</summary>
        <div class="ayuda-texto">
            <p class="mb-0">Solo los usamos para tu pedido. Si pides sin cuenta, borramos tu teléfono y tu correo 30 días
                después de tu último pedido. Lo tienes todo en la <a href="{{ route('privacidad') }}">política de
                privacidad</a>.</p>
        </div>
    </details>
</x-plantilla.publica>
