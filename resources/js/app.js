// Componentes de Bootstrap con su dependencia Popper incluida (menú plegable, ventanas modales).
import 'bootstrap/dist/js/bootstrap.bundle.js';

// Los formularios con data-confirmar (borrar una categoría, un producto…) preguntan antes de enviarse.
// Sin JavaScript se envían directamente y el servidor sigue comprobando si se puede borrar.
document.addEventListener('submit', (evento) => {
    const mensaje = evento.target.dataset?.confirmar;

    if (mensaje && !window.confirm(mensaje)) {
        evento.preventDefault();
    }
});
