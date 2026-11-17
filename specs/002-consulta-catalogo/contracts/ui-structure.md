# Contract: Estructura de interfaz del catálogo (FR-001, FR-002, FR-004)

**Spec**: [../spec.md](../spec.md) FR-001, FR-002, FR-004, SC-004 | **Research**: [../research.md](../research.md) D-011, D-014, D-015 | **Data model**: [../data-model.md](../data-model.md)

Contrato de composición visual compartido por el listado, la ficha de detalle y la vista de error 404. Textos visibles: [user-messages.md](user-messages.md).

## 1. Layout común (FR-004, SC-004)

Todas las vistas del alcance extienden `resources/views/layouts/app.blade.php` (creado en `001`):

| Región | Contenido obligatorio |
|---|---|
| **Cabecera** | Nombre de la aplicación (Biblioteca Universitaria) |
| **Navegación** | Enlaces de la aplicación, incluido «Catálogo» → `route('catalogo.index')`; la página activa se distingue visualmente |
| **Zona de contenido** | `@yield` con el título y el cuerpo de la vista |
| **Pie** | Pie común idéntico en todas las vistas |

Cabecera, navegación y pie son **idénticos** entre listado, detalle y error 404 (verificable en `CatalogoLayoutTest`).

## 2. Listado paginado (`/catalogo`)

Zona de contenido, en orden:

1. **Título** de la sección (p. ej. «Catálogo de libros»).
2. **Estado vacío** (solo si no hay libros): mensaje «No hay libros registrados aún» en la zona del listado, sin tarjetas ni controles de paginación.
3. **Rejilla/lista de tarjetas** — cada tarjeta muestra, en este orden:

   | # | Elemento | Regla |
   |---|---|---|
   | 1 | Portada | Parcial `partials/portada`: imagen predeterminada si `portada = NULL`; si hay ruta, imagen de Storage con respaldo `onerror` a la predeterminada (D-014) |
   | 2 | Título | Texto del campo `titulo` |
   | 3 | Autor | Texto del campo `autor` |
   | 4 | Año de publicación | `anio_publicacion` |
   | 5 | Categoría | `nombre` de la categoría asociada |

   - **Toda la tarjeta es un enlace clicable** al detalle del libro (`catalogo.show`), no solo un botón o el título (clarificación 2026-10-26, FR-001).
   - Orden de las tarjetas: `titulo` → `autor` → `isbn` con colación `utf8mb4_0900_ai_ci` (FR-005).
4. **Controles de paginación** (solo si hay libros): «Anterior», «Siguiente» y números de página; 10 tarjetas por página; última página sin posiciones vacías. Vista propia `pagination/catalogo.blade.php` con el CSS propio del proyecto (D-011).

## 3. Ficha de detalle (`/catalogo/{id}`)

Zona de contenido, en este orden:

1. **Título** de la sección (p. ej. «Ficha del libro»).
2. **Portada** mediante el mismo parcial `partials/portada` del listado (misma imagen predeterminada — FR-003/SC-003).
3. **Campos**, etiquetados en español:

   | # | Etiqueta | Campo |
   |---|---|---|
   | 1 | Título | `titulo` |
   | 2 | Autor | `autor` |
   | 3 | Editorial | `editorial` |
   | 4 | Año de publicación | `anio_publicacion` |
   | 5 | ISBN | `isbn` |
   | 6 | Total de ejemplares | `ejemplares_totales` |
   | 7 | Ejemplares disponibles | `ejemplares_disponibles` |
   | 8 | Categoría | `nombre` de la categoría |

4. **Enlace de vuelta** al catálogo (p. ej. «Volver al catálogo»).

## 4. Vista de error 404 (`errors/404.blade.php`)

- Mismo layout común (cabecera, navegación, pie).
- Mensaje del error en español (§2 de [user-messages.md](user-messages.md)).
- Enlace «Volver al catálogo» → `catalogo.index`.

## 5. Imagen predeterminada (FR-003, SC-003)

| Situación | Comportamiento |
|---|---|
| `portada = NULL` | `<img src="…/images/libro-sin-portada.svg">` en listado, detalle y (si aplica) error |
| `portada` con ruta válida | Imagen del Storage público; ante fallo de carga (`onerror`) se sustituye por la predeterminada |
| Archivo predeterminado | Único archivo del proyecto: `public/images/libro-sin-portada.svg` |

- `alt` en español según [user-messages.md](user-messages.md) §3.
