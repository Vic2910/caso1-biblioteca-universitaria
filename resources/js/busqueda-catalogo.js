/**
 * Búsqueda con debounce del catálogo (specs/006-busqueda-catalogo, D-032):
 * JavaScript nativo, sin frameworks ni build. El texto se envía 300 ms
 * después de la última pulsación; el desplegable de categoría envía en
 * cuanto cambia. Ningún envío lleva `page`: cada criterio reinicia la
 * paginación (D-034) y el foco se restaura tras la recarga para seguir
 * escribiendo (FR-004).
 */
document.addEventListener('DOMContentLoaded', function () {
    var formulario = document.getElementById('busqueda');

    if (!formulario) {
        return;
    }

    var campo = document.getElementById('q');
    var desplegable = formulario.querySelector('select[name="categoria"]');
    var temporizador = null;
    var RETARDO_MS = 300;

    function cancelarEnvioPendiente() {
        if (temporizador !== null) {
            window.clearTimeout(temporizador);
            temporizador = null;
        }
    }

    function prepararEnvio() {
        cancelarEnvioPendiente();

        var estadoCarga = document.getElementById('estado-carga');

        if (estadoCarga !== null) {
            estadoCarga.textContent = 'Cargando…';
        }

        // Cada criterio parte de la página 1: el formulario nunca envía `page`.
        var pagina = formulario.querySelector('input[name="page"]');

        if (pagina !== null) {
            pagina.remove();
        }

        var foco = document.activeElement;

        sessionStorage.setItem(
            'busqueda-foco',
            foco && foco.id ? foco.id : 'q'
        );
    }

    function restaurarFoco() {
        var guardado = sessionStorage.getItem('busqueda-foco');

        if (guardado === null) {
            return;
        }

        sessionStorage.removeItem('busqueda-foco');

        var destino = document.getElementById(guardado);

        if (destino === null) {
            return;
        }

        destino.focus();

        if (typeof destino.setSelectionRange === 'function' && destino.type === 'text') {
            var final = destino.value.length;
            destino.setSelectionRange(final, final);
        }
    }

    formulario.addEventListener('submit', prepararEnvio);

    campo.addEventListener('input', function () {
        cancelarEnvioPendiente();

        temporizador = window.setTimeout(function () {
            temporizador = null;
            prepararEnvio();
            // submit() nativo: no dispara el evento submit, así que no hay
            // bucle con el listener anterior.
            formulario.submit();
        }, RETARDO_MS);
    });

    // Enter en el campo dispara el submit del formulario; si queda un
    // debounce en vuelo se cancela para no navegar dos veces.
    campo.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter') {
            cancelarEnvioPendiente();
        }
    });

    if (desplegable !== null) {
        desplegable.addEventListener('change', function () {
            prepararEnvio();
            formulario.submit();
        });
    }

    restaurarFoco();
});
