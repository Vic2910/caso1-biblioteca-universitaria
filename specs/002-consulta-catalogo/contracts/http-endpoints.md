# Contract: Endpoints HTTP del catálogo (FR-001, FR-002, FR-005)

**Spec**: [../spec.md](../spec.md) FR-001, FR-002, FR-005, SC-001…SC-003 | **Research**: [../research.md](../research.md) D-010, D-011, D-013 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. Todas las rutas responden **HTML** (navegador), sin autenticación en esta fase. Mensajes visibles: [user-messages.md](user-messages.md).

## Rutas

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 1 | `GET /catalogo?page=N` | `catalogo.index` | `CatalogoController@index` | `200 OK` con listado paginado |
| 2 | `GET /catalogo/{id}` | `catalogo.show` | `CatalogoController@show` | `200 OK` con ficha de detalle |

- `{id}` es el `id` numérico de `libros` (entero ≥ 1).
- El enlace «Catálogo» de la navegación común apunta a `catalogo.index` (D-015).

## Parámetros de consulta

| Parámetro | Obligatorio | Formato válido | Comportamiento si es inválido |
|---|---|---|---|
| `page` | No (por defecto 1) | Cadena de dígitos `^\d+$` cuyo valor entero esté entre `1` y la última página del resultado | HTTP **404** con vista de error (D-013) |

- Ausente o cadena vacía → página 1.
- `page=abc`, `page=0`, `page=-3`, `page=1.5` → 404.
- `page` mayor que la última página (incluido `page ≥ 2` con catálogo vacío) → 404.
- Otros parámetros de consulta se conservan en los enlaces de paginación (`withQueryString()`), de modo que búsquedas/filtros futuros de `006` no se pierdan al cambiar de página.

## Respuesta `200` — listado (`GET /catalogo`)

- Contenido: tarjetas de los libros de la página solicitada, con los campos y en el orden definidos en [ui-structure.md](ui-structure.md) §2.
- Orden garantizado: `titulo` → `autor` → `isbn`, con colación `utf8mb4_0900_ai_ci` (FR-005, [data-model.md](../data-model.md)).
- Paginación: 10 libros por página; controles de página anterior, siguiente y números de página.
- Catálogo vacío (total = 0 y `page=1`): `200 OK` con el mensaje vacío y **sin** controles de paginación.

## Respuesta `200` — detalle (`GET /catalogo/{id}`)

- Contenido: ficha con la totalidad de campos de `Libro` y su categoría, según [ui-structure.md](ui-structure.md) §3.

## Respuesta `404`

| Caso | Disparador | Código | Cuerpo |
|---|---|---|---|
| Libro inexistente | `{id}` sin fila en `libros` | `404 Not Found` | Vista `errors/404.blade.php` con mensaje «Libro no encontrado» + enlace «Volver al catálogo» |
| Página fuera de rango | `page` no numérico, &lt; 1, o &gt; última página | `404 Not Found` | Vista `errors/404.blade.php` con mensaje «Página no válida» + enlace «Volver al catálogo» |

- La vista de error comparte el layout común (cabecera, navegación y pie) — FR-004/SC-004.
- Contrato de textos: [user-messages.md](user-messages.md) §2.

## Restricciones del contrato

- Solo lectura: no se aceptan cuerpos de petición, ni métodos distintos de `GET`, ni operaciones de escritura (sin formularios, sin CSRF aplicable en esta fase).
- Consultas exclusivamente vía Eloquent con carga ansiosa de `categoria`; sin SQL concatenado con entradas del usuario (Principio IV).
- No expone datos de préstamos ni reglas de disponibilidad (fuera de alcance: `004-gestion-prestamos`).
