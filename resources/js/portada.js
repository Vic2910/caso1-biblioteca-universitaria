/**
 * Pre-validación de portadas en el cliente (009, D-054, contracts/upload-portada.md).
 * El servidor sigue siendo la única fuente de verdad: esto solo evita el viaje inútil.
 */
const TAMANO_MAXIMO = 2 * 1024 * 1024;
const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];
const MENSAJE = 'El archivo debe ser una imagen JPG, PNG o WebP de hasta 2 MB.';

document.querySelectorAll('input[type="file"][data-portada]').forEach((campo) => {
    const contenedor = campo.closest('.campo') ?? campo.parentElement;
    const error = document.createElement('p');
    const resumen = document.createElement('p');

    error.className = 'error-portada';
    error.setAttribute('role', 'alert');
    error.hidden = true;
    resumen.className = 'resumen-portada';
    resumen.hidden = true;
    contenedor.append(error, resumen);

    campo.addEventListener('change', () => {
        const archivo = campo.files?.[0];

        error.hidden = true;
        campo.removeAttribute('aria-invalid');
        resumen.hidden = true;

        if (!archivo) {
            return;
        }

        const valido = TIPOS_PERMITIDOS.includes(archivo.type) && archivo.size > 0 && archivo.size <= TAMANO_MAXIMO;

        if (!valido) {
            campo.value = '';
            error.textContent = MENSAJE;
            error.hidden = false;
            campo.setAttribute('aria-invalid', 'true');

            return;
        }

        const kilobytes = (archivo.size / 1024).toFixed(0);

        resumen.textContent = `Archivo listo para enviar: ${archivo.name} (${kilobytes} KB).`;
        resumen.hidden = false;
    });

    campo.form?.addEventListener('submit', (evento) => {
        if (campo.getAttribute('aria-invalid') === 'true') {
            evento.preventDefault();
            campo.focus();
        }
    });
});
