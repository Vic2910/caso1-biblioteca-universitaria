/*
 * Confirmaciones previas a las eliminaciones (008, FR-007): se enlazan los
 * formularios con `data-confirm`. Los formularios que ya llevan `onsubmit`
 * (contratos heredados de 003/005) muestran su propia confirmación nativa.
 */
document.addEventListener('submit', function (evento) {
    const formulario = evento.target.closest('form[data-confirm]');

    if (formulario && !formulario.hasAttribute('onsubmit') && !window.confirm(formulario.dataset.confirm)) {
        evento.preventDefault();
    }
});
