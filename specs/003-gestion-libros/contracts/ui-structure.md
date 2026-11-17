# Contract: Estructura de interfaz de gestión (FR-001, FR-004, FR-006)

**Spec**: [../spec.md](../spec.md) FR-001, FR-004, FR-006, SC-003, SC-004 | **Research**: [../research.md](../research.md) D-015, D-018, D-021, D-022 | **Data model**: [../data-model.md](../data-model.md)

Todas las vistas de gestión **extienden `layouts/app.blade.php`** (cabecera, navegación y pie idénticos a las vistas de la Fase 1 — Principio V, FR-001) y usan CSS propio del proyecto. Los textos visibles están en [user-messages.md](user-messages.md).

## 1. Navegación común (ajuste del layout)

- La navegación del layout incorpora, junto al enlace «Catálogo» (`catalogo.index`): «Categorías» (`categorias.index`).
- «Nuevo libro» **no** va en la navegación: es un botón del propio listado del catálogo (FR-001: la gestión se ofrece desde las vistas de la Fase 1).

## 2. Listado del catálogo ampliado (`catalogo/index.blade.php`)

- Se conserva todo lo de `002` (tarjetas, paginación, estado vacío) y se añade un botón **«Nuevo libro»** → `libros.create`, visible también en el estado vacío (para poder crear el primer libro).

## 3. Ficha de detalle ampliada (`catalogo/show.blade.php`)

- Se conserva la ficha completa de `002` y se añade una zona de gestión con:
  - Botón **«Editar»** → `libros.edit`.
  - Formulario de eliminación: `<form method="post" action="libros.destroy">` con `@csrf`, `@method('DELETE')` y botón **«Eliminar»**; al enviarse pregunta con `confirm('¿Eliminar el libro «{título}»? Esta acción no se puede deshacer.')` (D-022).
  - Si el libro tiene préstamos registrados: junto al botón Eliminar se muestra un **aviso en español** («Este libro tiene préstamos registrados y no puede eliminarse.») — FR-009, informar *antes* de confirmar.
- La ficha conserva la imagen de portada vía `partials/portada` (D-014).

## 4. Formularios de libro (`libros/create.blade.php`, `libros/edit.blade.php`, parcial `libros/_form.blade.php`)

- Estructura común (parcial `_form`): campos en este orden — ISBN, título, autor, editorial, año de publicación, número de ejemplares, categoría (selector), portada — más el par de botones «Guardar» / «Cancelar» (volver al catálogo o a la ficha).
- Cada campo: etiqueta en español, `<input>`/`<select>`, y bloque `@error` **inmediatamente debajo del campo** (FR-004/SC-004).
- `old()` conserva los valores introducidos tras un error; el ISBN usa `type="text" inputmode="numeric"` para no perder ceros iniciales.
- El selector de **categoría** lista las categorías existentes (nombre visible); nunca está vacío con los datos sembrados.
- **No existe campo de ejemplares disponibles** en ningún formulario (clarificación Q1).
- **Portada**:
  - Alta: `<input type="file" name="portada" accept="image/jpeg,image/png,image/webp">` opcional.
  - Edición: se muestra la portada actual (o la imagen predeterminada) con el texto de ayuda de que el reemplazo de archivos llega con la funcionalidad `005`; sin input de archivo en esta fase (D-021).
- Ante un error de validación, el formulario se re-renderiza vía redirect/back con los mensajes junto a cada campo y los valores introducidos intactos.

## 5. Vista de categorías (`categorias/index.blade.php`, `create`, `edit`)

- **Listado**: tabla con las categorías (nombre, cantidad de libros asociados como referencia) y, por fila, los botones «Editar» y «Eliminar»; cabecera con botón **«Nueva categoría»** → `categorias.create`.
- Eliminar: formulario `@csrf` + `@method('DELETE')` con `confirm('¿Eliminar la categoría «{nombre}»? Esta acción no se puede deshacer.')` (D-022); si tiene libros, el servidor la conserva y muestra el error junto al listado (§user-messages).
- **Alta/edición**: formulario con un único campo «Nombre» (≤ 100 caracteres), etiqueta, `@error` debajo y botones «Guardar» / «Cancelar» (vuelta al listado).

## 6. Estados y confirmaciones

| Situación | Comportamiento de interfaz |
|---|---|
| Guardado válido | Redirección con mensaje de éxito visible (flash) |
| Error de validación | Redirect back: mensajes en español junto a cada campo + `old()` conservado (SC-004) |
| Eliminación cancelada | `confirm()` falso → sin petición, sin cambios |
| Eliminación bloqueada (libro con préstamos / categoría con libros) | Nada se elimina; error visible en la vista de destino (listado o catálogo) |
| Eliminación interrumpida | Nada se elimina; error genérico en español visible |
| Libro/categoría inexistente | HTTP 404 con vista `errors/404.blade.php` de `002` (layout común) |

## 7. Accesibilidad y consistencia

- Cada control de gestión tiene etiqueta o texto visible en español; los formularios declaran `method`/`action` correctos.
- Salida escapada con `{{ }}` en todos los textos dinámicos (títulos, nombres, mensajes) — Principio IV.
- Sin frameworks CSS: formularios, tablas y avisos reutilizan/estilan el CSS propio del proyecto.
