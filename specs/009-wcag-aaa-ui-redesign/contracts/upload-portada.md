# Contract: Subida de portadas (FR-017, FR-018, FR-019)

**Spec**: [../spec.md](../spec.md) FR-017…FR-019, SC-007 | **Research**: [../research.md](../research.md) D-054 | **Data model**: [../data-model.md](../data-model.md) §Estados de un archivo de portada

Reemplaza la capa de **interacción** de `005-gestion-portadas`; el almacenamiento, el reemplazo, el borrado y la validación en servidor de `005` **no cambian**.

## 1. Límites (idénticos en cliente y servidor)

- Formato: **JPG, JPEG, PNG o WebP**.
- Tamaño: **≤ 2 MB** (2 097 152 bytes).
- Campo opcional; si no se elige archivo, se conserva la portada actual (o la predeterminada).

## 2. Validación en el cliente (`resources/js/portada.js`, solo con JS)

| Comprobación | Resultado |
|---|---|
| `file.size > 2 MB` | Rechazo previo |
| Tipo/extensión fuera de JPG·JPEG·PNG·WebP | Rechazo previo |
| Archivo vacío (0 bytes) | Rechazo previo |

**Al rechazar en el cliente**:

1. El `input[type=file]` se limpia (no queda seleccionado ningún archivo).
2. Se muestra junto al campo, en español: **«El archivo debe ser una imagen JPG, PNG o WebP de hasta 2 MB.»** con `role="alert"` y `aria-invalid="true"` en el campo.
3. Se impide el envío mientras el campo esté inválido.
4. **El formulario permanece intacto**: ningún otro campo pierde su valor ni la página se recarga.

**Al aceptar**: se muestra junto al campo el nombre del archivo, el tamaño formateado y una vista previa de la imagen; el envío continúa con normalidad.

## 3. Validación en el servidor (intacta, es la fuente de verdad)

- El servidor valida igual que hoy (`005`/`003`); si rechaza, responde con `back()->withErrors()->withInput()`:
  - El error en español aparece **junto al campo de portada**.
  - El resto del formulario conserva sus valores.
  - La página sigue siendo plenamente operable (no hay pantalla rota ni error fatal).
- Si el almacenamiento falla tras una validación correcta, se muestra el mensaje de error existente de `005` junto al campo y el libro queda sin portada (predeterminada).

## 4. Degradación sin JavaScript

- No hay pre-validación: el archivo viaja al servidor y este responde con el mismo texto de rechazo junto al campo, conservando el resto del formulario (FR-018 cubierto en ambos caminos).

## 5. Imagen predeterminada (FR-019)

- Portada ausente, borrada o fallo de carga → `images/libro-sin-portada.svg` con `alt` descriptivo (regla heredada de `002`/`005`, sin cambios).

## 6. Verificación

- `PortadaPrevalidacionTest`: archivo > 2 MB y formato no permitido (cliente y servidor) → mensaje claro y formulario operable; archivo válido → almacenamiento correcto.
- Revisión manual en [../quickstart.md](../quickstart.md) V-007 y V-008.
