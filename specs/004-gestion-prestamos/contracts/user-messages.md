# Contract: Mensajes visibles al usuario (FR-003, FR-006 + edge cases)

**Spec**: [../spec.md](../spec.md) FR-003, FR-006, edge cases, SC-002, SC-004 | **Research**: [../research.md](../research.md) D-021, D-026 | **Structure**: [ui-structure.md](ui-structure.md) | **Endpoints**: [http-endpoints.md](http-endpoints.md)

Todos los mensajes son **en español**, se muestran **junto al campo o acción** que los originan y conservan lo ingresado por el usuario. Coherentes con el contrato de `001` ([../../001-entorno-datos/contracts/user-messages.md](../../001-entorno-datos/contracts/user-messages.md)) y de `002` ([../../002-consulta-catalogo/contracts/user-messages.md](../../002-consulta-catalogo/contracts/user-messages.md)).

## 1. Mensajes fijados por la especificación (textos exactos)

| # | Disparador | Texto requerido (literal) | Dónde se muestra |
|---|---|---|---|
| M1 | Registrar un préstamo de un libro sin disponibilidad (incluye la carrera por el último ejemplar) | «No hay ejemplares disponibles de este libro.» | Junto al campo **Libro** del formulario de registro |
| M2 | Registrar la devolución de un préstamo ya devuelto (doble envío o navegación directa) | «Este préstamo ya fue devuelto.» | Junto a la acción/devolución; sin cambiar la disponibilidad |

## 2. Errores de validación del registro (junto a cada campo)

| Campo | Disparador | Texto |
|---|---|---|
| Libro | No seleccionado | «El campo libro es obligatorio.» |
| Libro | Sin fila en `libros` (libro inexistente) | «El libro seleccionado no existe.» |
| Libro | Sin disponibilidad (M1) | «No hay ejemplares disponibles de este libro.» |
| Nombre del estudiante | Vacío / menor de 2 / mayor de 255 | «El campo nombre del estudiante es obligatorio.» / «El nombre del estudiante debe tener al menos 2 caracteres.» / «El nombre del estudiante no puede superar los 255 caracteres.» |
| Carné | Vacío / mayor de 50 | «El campo carné es obligatorio.» / «El carné no puede superar los 50 caracteres.» |
| Carné | Estudiante con 3 préstamos no devueltos (activo o vencido) | «El estudiante ya tiene el máximo de tres préstamos sin devolver.» |
| Fecha de préstamo | Vacía o inválida | «La fecha de préstamo es obligatoria.» / «La fecha de préstamo no es válida.» |
| Fecha de devolución prevista | Vacía o inválida | «La devolución prevista es obligatoria.» / «La devolución prevista no es válida.» |
| Fecha de devolución prevista | Igual o anterior a la fecha de préstamo | «La devolución prevista debe ser posterior a la fecha de préstamo.» |

## 3. Errores de validación de la devolución (junto al campo)

| Campo | Disparador | Texto |
|---|---|---|
| Fecha de devolución real | Vacía o inválida | «La fecha de devolución real es obligatoria.» / «La fecha de devolución real no es válida.» |
| Fecha de devolución real | Anterior a la fecha de préstamo (incluye préstamos vencidos) | «La fecha de devolución real no puede ser anterior a la fecha de préstamo.» |
| (acción) | Préstamo ya devuelto (M2) | «Este préstamo ya fue devuelto.» |

## 4. Estados de éxito (flash en el listado)

| Acción | Texto |
|---|---|
| Préstamo registrado | «Préstamo registrado correctamente.» |
| Devolución registrada | «Devolución registrada correctamente.» |

## 5. Listado y estructura

| Elemento | Texto |
|---|---|
| Estado vacío del listado | «No hay préstamos registrados aún» |
| Badges de estado | «Activo», «Vencido», «Devuelto» |
| Devolución real ausente en la tabla | «—» |
| Título del listado | «Préstamos registrados» |
| Título del formulario de registro | «Registrar préstamo» |
| Título del formulario de devolución | «Registrar devolución» |
| Enlace de navegación | «Préstamos» |

## 6. Error 404

| Caso | Texto | Elementos adicionales |
|---|---|---|
| Préstamo inexistente (`/prestamos/{id}/devolver` sin fila) | «Préstamo no encontrado» | Enlace «Volver a préstamos» hacia `prestamos.index` |

## 7. Restricciones del contrato

- Ningún mensaje expone trazas SQL, credenciales, rutas internas ni detalles técnicos (Principio IV).
- Todo rechazo conserva los valores ingresados (`old()`) y se muestra junto al campo o acción correspondiente (SC-004).
- Los textos de los campos son fijos en el marcado salvo datos (título/ISBN del libro, nombre y carné del estudiante, fechas y estado).
