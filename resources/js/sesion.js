/**
 * Aviso de caducidad de sesión (009, D-053, contracts/session.md). Se activa
 * cuando quedan 5 minutos según data-sesion-expira (timestamp UNIX en segundos).
 */
const cuerpo = document.body;
const expiraEn = Number(cuerpo.dataset.sesionExpira ?? 0);
const AVISO_SEGUNDOS = 5 * 60;

if (expiraEn > 0) {
    const aviso = document.getElementById('aviso-sesion');
    const texto = document.getElementById('aviso-sesion-texto');
    const extender = document.getElementById('aviso-sesion-extender');
    const formularioExtender = document.getElementById('formulario-extender-sesion');

    const restantes = () => Math.max(0, expiraEn - Math.floor(Date.now() / 1000));

    const actualizar = () => {
        const segundos = restantes();

        if (segundos <= AVISO_SEGUNDOS && aviso) {
            const minutos = Math.max(1, Math.ceil(segundos / 60));
            const sufijo = minutos === 1 ? 'Quedan 1 minuto' : `Quedan ${minutos} minutos`;

            texto.textContent = `${sufijo} antes de que finalice su sesión.`;
            aviso.hidden = false;
        }

        if (segundos > 0) {
            window.setTimeout(actualizar, 15000);
        }
    };

    extender?.addEventListener('click', () => formularioExtender?.submit());

    actualizar();
}
