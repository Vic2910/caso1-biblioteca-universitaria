# Contract: Endpoints HTTP de gestión de libros y categorías (FR-001, FR-003, FR-005, FR-007, FR-008, FR-009)

**Spec**: [../spec.md](../spec.md) FR-001…FR-009, SC-001…SC-007 | **Research**: [../research.md](../research.md) D-018, D-019, D-020, D-022, D-023 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. Todas las rutas responden **HTML** (navegador), sin autenticación en esta fase. Las rutas de consulta de `002` (`GET /catalogo`, `GET /catalogo/{id}`) siguen intactas; aquí se documentan las rutas de escritura nuevas. Mensajes visibles: [user-messages.md](user-messages.md).

## Rutas de libros

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 1 | `GET /libros/crear` | `libros.create` | `LibroController@create` | `200 OK` con formulario de alta |
| 2 | `POST /libros` | `libros.store` | `LibroController@store` | `302` → ficha del libro creado (`libros.show` de `002`) con mensaje de éxito |
| 3 | `GET /libros/{id}/editar` | `libros.edit` | `LibroController@edit` | `200 OK` con formulario precargado |
| 4 | `PUT /libros/{id}` | `libros.update` | `LibroController@update` | `302` → ficha del libro con datos actualizados y mensaje de éxito |
| 5 | `DELETE /libros/{id}` | `libros.destroy` | `LibroController@destroy` | `302` → listado del catálogo con mensaje de éxito |

- `{id}` es el `id` numérico de `libros` (entero ≥ 1). Libro inexistente en `edit`/`update`/`destroy` → **HTTP 404** con vista `errors/404.blade.php` y mensaje «Libro no encontrado» (mecanismo D-013, reutilizado).
- El botón «Nuevo libro» del listado (`catalogo.index`) apunta a `libros.create`; los botones «Editar» y «Eliminar» de la ficha (`catalogo.show`) apuntan a `libros.edit` y envían `DELETE` a `libros.destroy` respectivamente (FR-001).
- Método no soportado (p. ej. `GET /libros/1/delete` o `DELETE /libros/crear`) → **HTTP 405** (solo existen los métodos de la tabla; GET nunca elimina — FR-005/SC-003).

## Rutas de categorías

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 6 | `GET /categorias` | `categorias.index` | `CategoriaController@index` | `200 OK` con listado de categorías |
| 7 | `GET /categorias/crear` | `categorias.create` | `CategoriaController@create` | `200 OK` con formulario de alta |
| 8 | `POST /categorias` | `categorias.store` | `CategoriaController@store` | `302` → listado con mensaje de éxito |
| 9 | `GET /categorias/{id}/editar` | `categorias.edit` | `CategoriaController@edit` | `200 OK` con formulario precargado |
| 10 | `PUT /categorias/{id}` | `categorias.update` | `CategoriaController@update` | `302` → listado con mensaje de éxito |
| 11 | `DELETE /categorias/{id}` | `categorias.destroy` | `CategoriaController@destroy` | `302` → listado con mensaje de éxito |

- `{id}` es el `id` numérico de `categorias` (entero ≥ 1). Categoría inexistente en `edit`/`update`/`destroy` → **HTTP 404** con mensaje «Categoría no encontrada».
- El enlace «Categorías» de la navegación común apunta a `categorias.index` (D-018).

## Cuerpos de formulario

Formularios `application/x-www-form-urlencoded` (o `multipart/form-data` cuando el alta de libro incluye portada). Todos llevan `@csrf` (Principio IV).

### `POST /libros` y `PUT /libros/{id}`

| Campo | Obligatorio | Formato válido | Inválido → |
|---|---|---|---|
| `isbn` | Sí | Cadena de exactamente 13 dígitos `^[0-9]{13}$` (texto; ceros iniciales conservados), única en `libros` (en edición, ignorando el propio libro) | `302` back + error junto al campo |
| `titulo` | Sí | 2–255 caracteres | idem |
| `autor` | Sí | 2–255 caracteres | idem |
| `editorial` | Sí | ≤ 255 caracteres | idem |
| `anio_publicacion` | Sí | Entero entre 1900 y el año actual inclusive (calculado en el momento de la petición) | idem |
| `ejemplares_totales` | Sí | Entero ≥ 1; en edición además ≥ número de préstamos activos del libro | idem |
| `categoria_id` | Sí | Entero existente en `categorias` | idem |
| `portada` | No (solo alta) | Archivo JPG, PNG o WebP de hasta 2 MB (2048 KB); `mimes:jpg,jpeg,png,webp` | idem |
| `ejemplares_disponibles` | — | **No se acepta**: se ignora cualquier valor enviado; lo calcula el servidor ([data-model.md](../data-model.md) §Disponibilidad) | — |

### `POST /categorias` y `PUT /categorias/{id}`

| Campo | Obligatorio | Formato válido | Inválido → |
|---|---|---|---|
| `nombre` | Sí | ≤ 100 caracteres, tras `trim()` de espacios sobrantes; **único ignorando mayúsculas, tildes y espacios** (en edición, ignorando el propio registro) | `302` back + error junto al campo |

### Ante cualquier validación fallida

`302` de redirección a la vista de origen (`back()`) con:
- `withErrors()` → la vista muestra cada mensaje **junto al campo** correspondiente (FR-004).
- `withInput()` → `old()` conserva todos los campos textuales introducidos (FR-004/SC-004); el campo de archivo nunca se conserva ni se reenvía.

## Eliminaciones (FR-005, FR-008, FR-009)

| Caso | Disparador | Resultado |
|---|---|---|
| Confirmación cancelada | `confirm()` devuelve falso en el cliente | **No se envía ninguna petición**; nada cambia (US2 escenario 2) |
| Libro sin préstamos | `DELETE /libros/{id}` con confirmación previa | `302` → catálogo con mensaje de éxito; la ficha responde 404 después |
| Libro con préstamos registrados | `DELETE /libros/{id}` | `302` → catálogo **sin eliminar**, con error «El libro tiene préstamos registrados y no puede eliminarse.» (comprobación previa + respaldo `RESTRICT`, D-022) |
| Categoría sin libros | `DELETE /categorias/{id}` con confirmación previa | `302` → listado con mensaje de éxito; desaparece de los selectores |
| Categoría con libros | `DELETE /categorias/{id}` | `302` → listado **sin eliminar**, con error «La categoría tiene libros asociados y no puede eliminarse.» |
| Fallo inesperado de borrado | `QueryException` (carrera entre comprobación y `DELETE`) | `302` con error genérico en español («No se pudo eliminar…»); registro intacto (edge case «eliminación interrumpida») |

- La ruta solo existe como `DELETE`: un `GET` a la URL de borrado no elimina nada (HTTP 405) — SC-003.
- La ficha del libro muestra el aviso de bloqueo junto al botón Eliminar cuando `libros.prestamos` no está vacío (FR-009, «antes de confirmar»).

## Restricciones del contrato

- Toda escritura pasa por Form Requests y `$request->validated()`; el controlador no usa `$request->all()` (Principio II, D-019).
- Consultas y borrados exclusivamente vía Eloquent; sin SQL concatenado con entradas del usuario (Principio IV).
- No registra ni modifica préstamos (`004-gestion-prestamos`), no busca ni filtra el catálogo (`006-busqueda-catalogo`) y no muestra paneles (`007`); el reemplazo/borrado de archivos de portada es de `005-gestion-portadas`.
