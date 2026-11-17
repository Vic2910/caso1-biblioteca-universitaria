{{--
    Mensaje de éxito auto-ocultable (009, D-052). Recibe: $mensaje.
    Sin JavaScript permanece visible hasta la siguiente navegación.
--}}
<div class="toast mensaje-exito" role="status" aria-live="polite" data-toast>
    <p>{{ $mensaje }}</p>
    <button type="button" class="toast-cerrar" data-toast-cerrar>Cerrar mensaje</button>
</div>
