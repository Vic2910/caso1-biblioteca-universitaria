/**
 * Mejora ARIA de menús y secciones colapsables (009, D-050). Sin JavaScript,
 * los <details>/<summary> nativos siguen funcionando.
 */
const desplegables = () => document.querySelectorAll('details[data-menu]');

function sincronizarEstado(detalles) {
    const disparador = detalles.querySelector('summary');

    if (!disparador) {
        return;
    }

    disparador.setAttribute('aria-expanded', String(detalles.open));
}

document.querySelectorAll('details').forEach((detalles, indice) => {
    const disparador = detalles.querySelector('summary');

    if (!disparador) {
        return;
    }

    const contenido = detalles.querySelector(':scope > ul, :scope > div');

    if (contenido && !contenido.id) {
        contenido.id = `seccion-desplegable-${indice}`;
        disparador.setAttribute('aria-controls', contenido.id);
    }

    sincronizarEstado(detalles);
    detalles.addEventListener('toggle', () => sincronizarEstado(detalles));
});

document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') {
        return;
    }

    desplegables().forEach((detalles) => {
        if (detalles.open) {
            detalles.open = false;
            detalles.querySelector('summary')?.focus();
        }
    });
});

document.addEventListener('click', (evento) => {
    desplegables().forEach((detalles) => {
        if (detalles.open && !detalles.contains(evento.target)) {
            detalles.open = false;
        }
    });
});

desplegables().forEach((detalles) => {
    detalles.addEventListener('toggle', () => {
        if (!detalles.open) {
            return;
        }

        desplegables().forEach((otro) => {
            if (otro !== detalles && otro.open) {
                otro.open = false;
            }
        });
    });
});
