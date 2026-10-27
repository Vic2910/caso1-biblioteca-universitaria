# Contract: Mensajes visibles al usuario (FR-002, FR-006, FR-008)

**Spec**: [../spec.md](../spec.md) FR-002, FR-006, FR-007, FR-008, edge cases, SC-003, SC-006, SC-007 | **Research**: [../research.md](../research.md) D-019, D-021, D-023, D-024 | **Structure**: [ui-structure.md](ui-structure.md)

Todos los mensajes son **en español**, se muestran **junto al campo** cuando se refieren a un campo, y no exponen rutas, trazas ni detalles técnicos (Principio IV).

## 1. Validación de archivo (FR-002, SC-001/SC-003)

| Caso | Texto requerido | Presentación |
|---|---|---|
| Formato no permitido (GIF, SVG, TIFF u otro; o contenido que no corresponde a una imagen) | «La portada debe ser una imagen JPG, PNG o WebP.» | Error junto al campo `portada`; resto de valores con `old()`; portada existente intacta |
| Archivo mayor a 2 MB | «La portada no puede superar los 2 MB.» | Igual que arriba |
| 2 MB exactos con formato válido | *(sin error: se acepta)* | — |

- Los textos son mensajes personalizados del Form Request (D-019); si el equipo mantiene los textos por defecto de Laravel, deben traducirse a un equivalente en español con el mismo significado y quedar fijados aquí.

## 2. Fallo de almacenamiento (FR-006, edge cases, SC-003)

| Caso | Texto requerido | Presentación |
|---|---|---|
| Fallo al guardar, reemplazar o eliminar el archivo | **«No se pudo guardar la imagen. Inténtelo de nuevo.»** (texto exacto de la spec) | Junto al campo `portada` en el formulario de edición; la portada existente y el registro del libro quedan sin cambios |

- Cubre también el caso «puerto o red interrumpidos durante la subida»: mismo texto, mismo efecto.

## 3. Confirmación de eliminación (FR-005, SC-004)

| Elemento | Contrato |
|---|---|
| Disparador | Botón «Eliminar portada» del formulario de edición, antes de enviar el `DELETE` |
| Texto de la confirmación | «¿Seguro que desea eliminar la portada de este libro? La imagen dejará de mostrarse.» |
| Mecanismo | Diálogo de confirmación nativo (`confirm()`), operable con teclado; si el usuario cancela, no se envía ninguna petición |

- Ninguna eliminación se ejecuta sin confirmación previa ni por enlace GET (SC-004).

## 4. Textos de imagen y accesibilidad (FR-008, SC-007)

| Elemento | Texto |
|---|---|
| `alt` de portada registrada (listado y detalle) | **Título del libro** (valor literal del campo `titulo`) |
| `alt` de imagen predeterminada | «Sin portada» (texto genérico, heredado del contrato de `002`) |
| Leyenda del campo en el formulario | «JPG, PNG o WebP, hasta 2 MB. Al guardar un archivo nuevo se reemplaza la portada actual.» |

## 5. Acceso sin sesión a una imagen (FR-007, SC-006)

| Caso | Texto requerido | Presentación |
|---|---|---|
| `GET /portadas/{id}` sin sesión | «Debe iniciar sesión para ver las imágenes de portadas.» | Vista `errors/401` con layout común; HTTP 401; **nunca** se sirven los bytes de la imagen; enlace «Iniciar sesión» si existe la ruta `login` |

## 6. Operaciones con éxito y 404

| Caso | Texto requerido | Presentación |
|---|---|---|
| Portada guardada/reemplazada | «Portada actualizada correctamente.» | Mensaje junto al formulario de edición tras la redirección |
| Portada eliminada | «Portada eliminada correctamente.» | Igual que arriba |
| `DELETE` de portada a un libro sin portada | «Portada no encontrada» | Vista `errors/404` |
| Portada de libro inexistente | «Libro no encontrado» | Vista `errors/404` (texto heredado de `002`) |

## 7. Restricciones del contrato

- Ningún mensaje expone trazas SQL, credenciales, rutas internas de Storage ni detalles técnicos (coherente con [../../001-entorno-datos/contracts/user-messages.md](../../001-entorno-datos/contracts/user-messages.md)).
- Los mensajes de §1 y §2 aparecen **junto al campo** `portada`, no solo como banner general.
