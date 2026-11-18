{{--
    Plantilla de la zona de gestión (pantallas G del diseño). Menú lateral con
    las opciones del rol: el personal ve pedidos, carta, clientes, horario e
    informes; el administrador además usuarios y ajustes (RF-21). La página
    activa se marca con aria-current para los lectores de pantalla. Las
    pantallas de acceso y de contraseña pasan :con-menu="false".
--}}
@props(['titulo', 'usuario' => null, 'activa' => null, 'conMenu' => true])

@php
    $usuario ??= auth('personal')->user();
    $opciones = [
        'pedidos' => 'Pedidos',
        'carta' => 'Carta',
        'clientes' => 'Clientes',
        'horario' => 'Horario',
        'informes' => 'Informes',
    ];
    if ($usuario?->esAdministrador()) {
        $opciones += ['usuarios' => 'Usuarios', 'ajustes' => 'Ajustes'];
    }
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $titulo }} · Gestión · La Focaccia di Trevi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#contenido" class="saltar-al-contenido">Saltar al contenido</a>

    @if (! $conMenu)
        <main id="contenido" class="container py-5" style="max-width: 28rem">
            <p class="fw-bold mb-1">La Focaccia di Trevi · Gestión</p>
            <h1 class="h3 mb-4">{{ $titulo }}</h1>
            {{ $slot }}
        </main>
    @else
    <div class="d-md-flex min-vh-100">
        <nav class="menu-gestion p-3" aria-label="Menú de gestión">
            <p class="text-white fw-bold mb-3">La Focaccia di Trevi</p>
            <ul class="nav flex-column">
                @foreach ($opciones as $ruta => $nombre)
                    <li class="nav-item">
                        <a href="{{ url('/gestion/'.$ruta) }}" @class(['nav-link', 'active' => $activa === $ruta])
                           @if ($activa === $ruta) aria-current="page" @endif>{{ $nombre }}</a>
                    </li>
                @endforeach
            </ul>
            @if ($usuario)
                <p class="text-white small mt-4 mb-1">{{ $usuario->nombre }}</p>
                <form method="POST" action="{{ route('gestion.salir') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                </form>
            @endif
        </nav>

        <main id="contenido" class="flex-grow-1 p-4">
            <h1 class="h3 mb-4">{{ $titulo }}</h1>
            {{ $slot }}
        </main>
    </div>
    @endif
</body>
</html>
