{{--
    Plantilla de la zona pública (pantallas P del diseño). Cabecera fija con
    Carta, Ayuda, Acceder y la cesta con su número de productos (RF-03).
    Cada página pasa su título y su descripción para los buscadores (RNF-04).
    Las páginas de error sin sesión disponible pasan :con-cesta="false", y las
    de un pedido concreto (recibido y seguimiento), :indexar="false".
--}}
@props(['titulo', 'descripcion' => 'Pizzas al horno de leña para recoger en el local. Pide en la web y pasa a por tu pedido.', 'conCesta' => true, 'indexar' => true])

@php
    $productosEnCesta = $conCesta ? array_sum(session('cesta', [])) : 0;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $descripcion }}">
    @unless ($indexar)
        <meta name="robots" content="noindex">
    @endunless
    <title>{{ $titulo }} · La Focaccia di Trevi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
    <a href="#contenido" class="saltar-al-contenido">Saltar al contenido</a>

    <header class="cabecera-publica sticky-top">
        <nav class="navbar navbar-expand-md navbar-dark container" aria-label="Menú principal">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">La Focaccia di Trevi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-publico"
                    aria-controls="menu-publico" aria-expanded="false" aria-label="Abrir el menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu-publico">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ url('/carta') }}">Carta</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/ayuda') }}">Ayuda</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ url('/acceder') }}">Acceder</a></li>
                    @if ($conCesta)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('/cesta') }}">Cesta ({{ $productosEnCesta }})</a>
                        </li>
                    @endif
                </ul>
            </div>
        </nav>
    </header>

    <main id="contenido" class="container py-4 flex-grow-1">
        @if ($conCesta && session('estado'))
            <div class="alert alert-success" role="status">{{ session('estado') }}</div>
        @endif
        @if ($conCesta && session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if ($conCesta && session('aviso_pizzas'))
            <x-aviso-de-pizzas :maximo="session('aviso_pizzas')"
                               :telefono="app(\App\Servicios\AjustesPublicos::class)->valor('telefono_local')" />
        @endif
        {{ $slot }}
    </main>

    <footer class="border-top py-3 text-center small">
        La Focaccia di Trevi · Pedidos para recoger · Solo cookies técnicas
    </footer>
</body>
</html>
