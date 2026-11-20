// Componentes de Bootstrap con su dependencia Popper incluida (menú plegable, ventanas modales).
import 'bootstrap/dist/js/bootstrap.bundle.js';

// Los formularios con data-confirmar (borrar una categoría, un producto…) preguntan antes de enviarse.
// Sin JavaScript se envían directamente y el servidor sigue comprobando si se puede borrar.
// Las ventanas marcadas con data-abrir-al-cargar (por ejemplo, la de más de 20 pizzas) se abren solas.
document.querySelectorAll('dialog[data-abrir-al-cargar]').forEach((ventana) => ventana.showModal());

document.addEventListener('submit', (evento) => {
    const mensaje = evento.target.dataset?.confirmar;

    if (mensaje && !window.confirm(mensaje)) {
        evento.preventDefault();
    }
});
