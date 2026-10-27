# Contract: Endpoints HTTP de la búsqueda o filtro (FR-001, FR-002, FR-003, FR-005)

**Spec**: [../spec.md](../spec.md) FR-001…FR-005, SC-001…SC-005 | **Research**: [../research.md](../research.md) D-028, D-029, D-031, D-034 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. **No hay rutas nuevas**: se amplía el contrato del listado de `002` ([../../002-consulta-catalogo/contracts/http-endpoints.md](../../002-consulta-catalogo/contracts/http-endpoints.md)) con dos parámetros de consulta (D-028). Respuesta **HTML** (navegador), sin autenticación. Mensajes visibles: [user-messages.md](user-messages.md).

## Rutas

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 1 | `GET /catalogo?q=&categoria=&page=N` | `catalogo.index` | `CatalogoController@index` | `200 OK` con listado (posiblemente filtrado) y paginado |
| 2 | `GET /catalogo/{id}` | `catalogo.show` | `CatalogoController@show` | `200 OK` con ficha de detalle (**sin cambios**, `002`) |

- Las rutas de `002` se mantienen idénticas; solo `catalogo.index` gana los parámetros `q` y `categoria`.
- El enlace «Catálogo» de la navegación común apunta a `catalogo.index` sin parámetros (listado completo).

## Parámetros de consulta

| Parámetro | Obligatorio | Formato válido | Comportamiento si es inválido |
|---|---|---|---|
| `q` | No (sin criterio por defecto) | Cualquier cadena; se aplica `trim()` en el servidor | Tras `trim()` vacío (incluido solo espacios) → **sin criterio de texto**; comodines `%`/`_`/`\` se tratan literalmente (D-029) |
| `categoria` | No (sin criterio por defecto) | `^\d+$` con entero ≥ 1 **y** existente en `categorias` | Valor no numérico, &lt; 1 o inexistente → **se ignora** (catálogo completo sin ese criterio; D-031) |
| `page` | No (por defecto 1) | Cadena de dígitos `^\d+$` entre `1` y la última página **del resultado filtrado** | HTTP **404** con vista de error (D-013, sin cambios; evaluada sobre el total filtrado) |

- Ausente o cadena vacía en cualquier parámetro → se comporta como ausente.
- Los tres parámetros se conservan en los enlaces de paginación (`withQueryString()`), de modo que la página siguiente mantiene `q` y `categoria` (D-011/D-034).

## Combinación de criterios (FR-003, FR-005)

- `q` → coincidencia que contiene contra **título, autor, editorial e ISBN** (coincide si el texto aparece en cualquiera de los cuatro), insensible a mayúsculas y acentos (D-030), ISBN parcial sobre el valor almacenado (ceros iniciales intactos).
- `categoria` → pertenencia a la categoría con **coincidencia exacta** (`categoria_id`).
- Varios criterios → **regla Y**: un libro se muestra solo si cumple todos los criterios activos simultáneamente.
- Sin criterios → respuesta idéntica al listado original de `002` (catálogo completo, 10 por página, orden `titulo → autor → isbn`).

## Respuesta `200` — listado (`GET /catalogo`)

- Contenido: tarjetas de los libros de la página solicitada que cumplen todos los criterios, con los campos y en el orden definidos en [../../002-consulta-catalogo/contracts/ui-structure.md](../../002-consulta-catalogo/contracts/ui-structure.md) §2 y los controles descritos en [ui-structure.md](ui-structure.md) §2.
- Orden garantizado: `titulo` → `autor` → `isbn` con colación `utf8mb4_0900_ai_ci` (heredado de `002`/D-012).
- Paginación: 10 libros por página **sobre el conjunto filtrado**; controles anteriores/siguientes/números conservan `q` y `categoria`.
- Estados vacíos según la prioridad de [../data-model.md](../data-model.md) §Prioridad de estados vacíos:
  - criterio activo y total = 0 → mensaje «No se encontraron libros que coincidan con la búsqueda», criterios visibles, sin paginación;
  - sin criterio y total = 0 → «No hay libros registrados aún», sin paginación.

## Respuesta `404`

| Caso | Disparador | Código | Cuerpo |
|---|---|---|---|
| Página fuera de rango | `page` no numérico, &lt; 1, o &gt; última página **del resultado filtrado** | `404 Not Found` | Vista `errors/404.blade.php` con «Página no válida» + enlace «Volver al catálogo» |
| Libro inexistente | `/catalogo/{id}` sin fila (sin cambios de `002`) | `404 Not Found` | «Libro no encontrado» + enlace de vuelta |

- Un filtro que no coincide con ningún libro **no es un 404**: responde `200` con el mensaje de resultados vacíos (FR-004).
- Un `categoria` inválido **no es un 404** (D-031): se ignora y responde `200` con el catálogo completo.
- La vista de error conserva el layout común — FR-004/SC-004. Contrato de textos: [user-messages.md](user-messages.md) §2.

## Restricciones del contrato

- Solo lectura: sin cuerpos de petición, sin métodos distintos de `GET`, sin operaciones de escritura (sin formularios de datos, sin CSRF aplicable en esta fase).
- Consultas exclusivamente vía Eloquent con bindings parametrizados y carga ansiosa de `categoria`; sin SQL concatenado con entradas del usuario (Principio IV; D-029).
- No expone datos de préstamos ni reglas de disponibilidad (fuera de alcance: `004-gestion-prestamos`); no modifica portadas (`005-gestion-portadas`).
