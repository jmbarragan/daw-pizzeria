{{--
    Ayuda de la gestión por perfiles (RF-09, CU-21). Reúne lo que explica la
    ayuda desplegable de cada pantalla, en el orden del trabajo de un día. La
    parte del administrador solo se ve con ese rol, igual que sus opciones del
    menú.
--}}
@php
    $administrador = auth('personal')->user()?->esAdministrador();
@endphp

<x-plantilla.gestion titulo="Ayuda" activa="ayuda">
    <p>Cada pantalla tiene también su ayuda: el botón «Ayuda» de arriba la abre.</p>

    <h2 class="h4 mt-4">Para todo el personal</h2>

    <details class="ayuda mb-3" open>
        <summary>Atender los pedidos</summary>
        <div class="ayuda-texto">
            <ol class="mb-0">
                <li>En <a href="{{ route('gestion.pedidos') }}">Pedidos</a>, la pestaña «Pendientes» tiene los que esperan,
                    del más antiguo al más nuevo. Ábrelos por orden.</li>
                <li>En el detalle están el cliente, las líneas, el total, los alérgenos y las observaciones. Para aceptarlo,
                    elige una hora de recogida de la lista; para rechazarlo, escribe el motivo, que le llega al cliente.</li>
                <li>Cuando esté hecho, pulsa «Listo para recoger». Cuando pase a por él y pague, «Recogido y pagado»; si no
                    viene, «No recogido».</li>
            </ol>
            <p class="mt-2 mb-0">Si el cliente cancela mientras lo estás mirando, al decidir se te avisa y no se aplica nada.
                Los pendientes que se quedan sin hora en su día se rechazan solos, con un motivo para el cliente.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>Apuntar un pedido por teléfono</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En Pedidos, «Pedido telefónico»: nombre, teléfono, el correo si lo da, las unidades y la hora. Nace
                aceptado. Aquí no hay máximo de pizzas: los pedidos grandes llegan precisamente por teléfono.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>La carta y los alérgenos</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En <a href="{{ route('gestion.carta') }}">Carta</a> están los productos, las categorías y los
                alérgenos. Si algo se acaba, desmarca «Disponible» y en la web saldrá agotado. Retirar un producto lo quita de
                la carta sin perder los pedidos que lo llevan.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>El horario</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En <a href="{{ route('gestion.horario') }}">Horario</a> se abren los turnos de cada día. Un día sin
                turnos está cerrado. De los turnos salen el aviso de abierto o cerrado de la web y las horas de recogida.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>Los clientes</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En <a href="{{ route('gestion.clientes') }}">Clientes</a> se busca por nombre o teléfono. Un cliente
                con pedidos no se borra: se le quitan el teléfono, el correo y la cuenta, y se conserva el nombre.</p>
        </div>
    </details>

    <details class="ayuda mb-3">
        <summary>Los informes</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En <a href="{{ route('gestion.informes') }}">Informes</a>: ventas cobradas, productos más vendidos y
                pedidos por estado entre dos fechas, por defecto el mes en curso. «Descargar PDF» da el mismo informe en PDF.</p>
        </div>
    </details>

    @if ($administrador)
        <h2 class="h4 mt-4">Solo para el administrador</h2>

        <details class="ayuda mb-3">
            <summary>Las cuentas del personal</summary>
            <div class="ayuda-texto">
                <p class="mb-0">En <a href="{{ route('gestion.usuarios') }}">Usuarios</a> se dan de alta las cuentas: a la persona
                    le llega un correo para elegir su contraseña. Las cuentas no se borran, se desactivan. Siempre queda al
                    menos un administrador activo, y nadie puede desactivar la suya.</p>
            </div>
        </details>

        <details class="ayuda mb-3">
            <summary>Los ajustes y las IP bloqueadas</summary>
            <div class="ayuda-texto">
                <p class="mb-0">En <a href="{{ route('gestion.ajustes') }}">Ajustes</a> se cambian el teléfono, la dirección y la
                    política de privacidad, y el servidor del correo. Allí mismo se levantan los bloqueos de las conexiones
                    con diez accesos fallidos.</p>
            </div>
        </details>
    @endif

    <details class="ayuda mb-3">
        <summary>Si olvidas la contraseña</summary>
        <div class="ayuda-texto">
            <p class="mb-0">En la pantalla de acceso, «¿Has olvidado la contraseña?»: te llega un enlace que sirve una hora.
                Tras diez intentos fallidos desde la misma conexión, el acceso se bloquea 24 horas; un administrador puede
                levantarlo antes.</p>
        </div>
    </details>
</x-plantilla.gestion>
