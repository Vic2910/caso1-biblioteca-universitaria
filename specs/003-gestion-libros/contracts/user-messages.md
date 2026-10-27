# Contract: Mensajes de usuario en español (FR-004, FR-008, FR-009)

**Spec**: [../spec.md](../spec.md) FR-004, FR-008, FR-009, SC-004, SC-007 | **Research**: [../research.md](../research.md) D-019, D-020, D-022, D-024 | **Data model**: [../data-model.md](../data-model.md)

Todos los textos visibles de esta funcionalidad están en **español** y se muestran **junto al campo** (errores de validación) o como aviso/flash (eliminaciones y éxitos). Los mensajes de validación provienen de `lang/es/validation.php` con atributos personalizados (D-024); la tabla fija el texto efectivo esperado por cada regla.

## 1. Errores de validación junto al campo (libro)

| Campo | Condición | Mensaje (texto efectivo) |
|---|---|---|
| `isbn` | ausente | «El campo ISBN es obligatorio.» |
| `isbn` | ≠ 13 dígitos o con caracteres no numéricos | «El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.» |
| `isbn` | duplicado | «El ISBN ya está registrado en otro libro.» |
| `titulo` / `autor` | ausente | «El campo Título/Autor es obligatorio.» |
| `titulo` / `autor` | < 2 caracteres | «El campo Título/Autor debe tener al menos 2 caracteres.» |
| `titulo` / `autor` | > 255 | «El campo Título/Autor no debe superar los 255 caracteres.» |
| `editorial` | ausente / > 255 | «El campo Editorial es obligatorio.» / «…no debe superar los 255 caracteres.» |
| `anio_publicacion` | ausente | «El campo Año de publicación es obligatorio.» |
| `anio_publicacion` | fuera de 1900–año actual | «El año de publicación debe estar entre 1900 y {año actual}.» |
| `ejemplares_totales` | ausente / no entero / < 1 | «El campo Número de ejemplares debe ser un número entero mayor o igual que 1.» |
| `ejemplares_totales` | < préstamos activos (edición) | «El número de ejemplares no puede ser inferior a los préstamos activos ({n}).» |
| `categoria_id` | ausente o inexistente | «Debe seleccionar una categoría válida.» |
| `portada` | formato ≠ JPG/PNG/WebP | «La portada debe ser un archivo JPG, PNG o WebP.» |
| `portada` | > 2 MB | «La portada no debe superar los 2 MB.» |

## 2. Errores de validación junto al campo (categoría)

| Campo | Condición | Mensaje (texto efectivo) |
|---|---|---|
| `nombre` | ausente o vacío tras `trim` | «El campo Nombre es obligatorio.» |
| `nombre` | duplicado (ignorando mayúsculas, tildes y espacios) | «Ese nombre de categoría ya existe.» |

## 3. Eliminaciones (D-022)

| Situación | Mensaje |
|---|---|
| Confirmación de libro | «¿Eliminar el libro «{título}»? Esta acción no se puede deshacer.» |
| Confirmación de categoría | «¿Eliminar la categoría «{nombre}»? Esta acción no se puede deshacer.» |
| Libro con préstamos (aviso en ficha, antes de confirmar) | «Este libro tiene préstamos registrados y no puede eliminarse.» |
| Bloqueo servidor: libro con préstamos | «El libro tiene préstamos registrados y no puede eliminarse.» |
| Bloqueo servidor: categoría con libros | «La categoría tiene libros asociados y no puede eliminarse.» |
| Fallo inesperado de borrado | «No se pudo eliminar el registro. Inténtelo de nuevo.» |

## 4. Éxitos (flash tras redirección)

| Operación | Mensaje |
|---|---|
| Libro creado | «Libro registrado correctamente.» |
| Libro actualizado | «Libro actualizado correctamente.» |
| Libro eliminado | «Libro eliminado correctamente.» |
| Categoría creada | «Categoría creada correctamente.» |
| Categoría actualizada | «Categoría actualizada correctamente.» |
| Categoría eliminada | «Categoría eliminada correctamente.» |

## 5. Errores HTTP (reutilizan la vista de `002`)

| Código | Mensaje |
|---|---|
| 404 libro inexistente | «Libro no encontrado» |
| 404 categoría inexistente | «Categoría no encontrada» |

## 6. Reglas del contrato

- Ningún mensaje expone detalles técnicos (SQL, rutas internas, excepciones) — Principio IV.
- Toda la salida se renderiza con `{{ }}` (escapada), incluidos los nombres de libro/categoría interpolados en confirmaciones y avisos.
- Los mensajes junto al campo aparecen **debajo** del input correspondiente, no solo al inicio del formulario (SC-004).
