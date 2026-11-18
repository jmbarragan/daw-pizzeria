{{--
    Ayuda de una pantalla de gestión (RF-09). Va cerrada para no quitar sitio
    y se abre con el ratón, el teclado o el lector de pantalla; es un
    <details> de HTML, así que funciona sin JavaScript.
--}}
<details class="ayuda mb-4">
    <summary>Ayuda</summary>
    <div class="ayuda-texto">
        {{ $slot }}
    </div>
</details>
