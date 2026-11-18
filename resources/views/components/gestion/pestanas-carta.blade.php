{{--
    Pestañas de la carta en la gestión (boceto G-04): Productos, Categorías y
    Alérgenos comparten la entrada «Carta» del menú. La activa lleva
    aria-current para los lectores de pantalla.
--}}
@props(['activa'])

@php
    $pestanas = [
        'productos' => ['Productos', url('/gestion/carta')],
        'categorias' => ['Categorías', url('/gestion/carta/categorias')],
        'alergenos' => ['Alérgenos', url('/gestion/carta/alergenos')],
    ];
@endphp

<ul class="nav nav-tabs mb-4">
    @foreach ($pestanas as $clave => [$nombre, $enlace])
        <li class="nav-item">
            <a href="{{ $enlace }}" @class(['nav-link', 'active' => $activa === $clave])
               @if ($activa === $clave) aria-current="page" @endif>{{ $nombre }}</a>
        </li>
    @endforeach
</ul>
