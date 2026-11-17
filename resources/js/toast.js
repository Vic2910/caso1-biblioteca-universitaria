/**
 * Auto-ocultado del mensaje de éxito a los 6 s (009, D-052). Se pausa al recibir
 * foco o puntero. El resultado sigue visible en la página, y sin JS el toast
 * permanece hasta la siguiente navegación.
 */
const RETARDO_MS = 6000;

document.querySelectorAll('[data-toast]').forEach((toast) => {
    let temporizador = null;

    const ocultar = () => {
        toast.hidden = true;
    };

    const programar = () => {
        window.clearTimeout(temporizador);
        temporizador = window.setTimeout(ocultar, RETARDO_MS);
    };

    toast.querySelector('[data-toast-cerrar]')?.addEventListener('click', ocultar);

    toast.addEventListener('focusin', () => window.clearTimeout(temporizador));
    toast.addEventListener('focusout', programar);
    toast.addEventListener('mouseenter', () => window.clearTimeout(temporizador));
    toast.addEventListener('mouseleave', programar);

    programar();
});
