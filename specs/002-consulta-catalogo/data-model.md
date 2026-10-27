# Data Model: 002-consulta-catalogo (Consulta del catálogo y detalle de libros)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-005, sección *Key Entities* de la especificación y Principio I de la constitución (Versión 1.0). Esta funcionalidad es de **solo lectura**: no crea entidades ni reglas de validación nuevas; reutiliza el esquema de `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)) y añade **una única migración de ajuste de colación** (D-012).

---

## Relaciones (heredadas de 001)

```text
categorias (1) ──────< (N) libros
                            │
                            └── portada (opcional, ruta en Storage público)
```

- Categoría agrupa muchos libros; cada libro pertenece a exactamente una categoría (`libros.categoria_id` → `categorias.id`, `RESTRICT`).
- Eloquent: `Libro::categoria()` → `belongsTo(Categoria)`; `Categoria::libros()` → `hasMany(Libro)` (definidas en `001`).

---

## Entidad de lectura: `Libro`

Campos almacenados (sin cambios respecto a `001`):

| Campo | Tipo | Restricciones | Uso en esta funcionalidad |
|---|---|---|---|
| `id` | bigint unsigned, PK | — | Identificador de la ficha (`/catalogo/{id}`) |
| `isbn` | char(13) | NOT NULL, UNIQUE, solo dígitos | Desempate final del orden (FR-005); se muestra en el detalle |
| `titulo` | varchar(255) | NOT NULL, **colación `utf8mb4_0900_ai_ci`** (migración de `002`) | Tarjeta + detalle; clave de ordenación |
| `autor` | varchar(255) | NOT NULL, **colación `utf8mb4_0900_ai_ci`** (migración de `002`) | Tarjeta + detalle; primer desempate |
| `editorial` | varchar(255) | NOT NULL | Solo detalle |
| `anio_publicacion` | smallint | NOT NULL, 1900–año actual | Tarjeta + detalle |
| `ejemplares_totales` | int unsigned | NOT NULL, ≥ 1 | Solo detalle (solo lectura) |
| `ejemplares_disponibles` | int unsigned | NOT NULL, ≤ `ejemplares_totales` | Solo detalle (solo lectura) |
| `categoria_id` | bigint unsigned, FK → `categorias.id` | NOT NULL, `restrictOnDelete()` | Tarjeta (nombre) + detalle (nombre) |
| `portada` | varchar(255), nullable | `NULL` = imagen predeterminada | Tarjeta + detalle vía parcial `partials/portada` (D-014) |

### Proyección a interfaz

| Dónde | Campos mostrados (en este orden) | Origen |
|---|---|---|
| Tarjeta del listado | portada (o predeterminada), título, autor, año de publicación, categoría | Clarificación 2026-10-26, FR-001 |
| Ficha de detalle | portada (o predeterminada), título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles, categoría | Clarificación 2026-10-26, FR-002 |

### Relación de lectura

- `Libro` → `belongsTo(Categoria)`; el listado usa **carga ansiosa** `with('categoria')` (D-017) para mostrar el nombre de categoría sin N+1.
- No hay relación nueva ni cambios de cardinalidad.

---

## Entidad de lectura: `Categoría`

| Campo | Tipo | Restricciones | Uso en esta funcionalidad |
|---|---|---|---|
| `id` | bigint unsigned, PK | — | — |
| `nombre` | varchar(100) | NOT NULL, UNIQUE | Se muestra en tarjeta y detalle del libro |
| `created_at` / `updated_at` | timestamps | — | — |

- No se listan categorías por sí mismas en esta funcionalidad; solo se presentan como nombre asociado a cada libro.

---

## Regla de ordenación (FR-005, determinista)

```text
ORDER BY titulo ASC  →  autor ASC  →  isbn ASC
```

- **`titulo` y `autor`**: comparación con la colación `utf8mb4_0900_ai_ci` fijada en la migración de `002` (`*_alter_libros_orden_collation.php`): ignora tildes y mayúsculas/minúsculas («Álgebra» se ordena como «Algebra»), en orden alfabético español (D-012).
- **`isbn`**: desempate final; al ser único de 13 dígitos garantiza un orden total y estable.
- La migración de `002` debe ser **posterior** a la creación de `libros` de `001` (orden cronológico de migraciones).

---

## Reglas de paginación

| Regla | Valor | Origen |
|---|---|---|
| Tamaño de página | 10 libros | FR-005 / edge cases |
| Página por defecto | 1 (parámetro `page` ausente o vacío) | Contrato [../contracts/http-endpoints.md](../contracts/http-endpoints.md) |
| Página válida | entero ≥ 1 y ≤ última página del resultado | D-013 |
| Página fuera de rango / no numérica | HTTP 404 con vista propia y enlace al catálogo | Historias 1 y 2, criterio 5 / 3 |
| Última página incompleta | se muestran solo los libros existentes, sin posiciones vacías | Edge cases |
| Catálogo vacío (total = 0) | página 1 válida → mensaje «No hay libros registrados aún» sin controles de paginación | Edge cases |
| Estado vacío + `page` ≥ 2 | HTTP 404 (fuera de rango) | D-013 |

---

## Cambios de esquema en esta funcionalidad

| Migración | Cambio | Motivo |
|---|---|---|
| `database/migrations/*_alter_libros_orden_collation.php` | `libros.titulo` y `libros.autor` → colación `utf8mb4_0900_ai_ci` (`Schema::table(...)->change()`) | Hacer verificable en el esquema el orden alfabético en español que ignora tildes y mayúsculas (FR-005, D-012) |

- Es el **único** cambio de esquema; no se crean ni eliminan entidades, columnas, índices o claves.
- Sin variante MariaDB: sustituir por `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci` (ver D-012).

---

## Validación y transiciones de estado

- **Validación**: esta funcionalidad no recibe entradas que modifiquen datos (solo lectura). La única entrada es el parámetro de consulta `page`, validado en el servidor según la tabla de paginación (D-013). Las reglas de negocio de formularios (ISBN, año, etc.) siguen en fases posteriores (Principio II).
- **Transiciones de estado**: ninguna. `Libro` y `Categoría` no cambian de estado en esta funcionalidad; el estado de los préstamos (`activo`/`devuelto`/`vencido`) no interviene en el catálogo y corresponde a `004-gestion-prestamos`.

---

## Dependencia de datos de prueba (heredada de 001)

| Requisito | Uso en `002` |
|---|---|
| ≥ 15 libros sembrados | Superan una página de 10 → SC-001 y recorrido de paginación |
| ≥ 3 categorías | Categoría distinta visible en tarjetas y fichas (SC-002) |
| `portada = NULL` en todos los sembrados | 100 % muestra la imagen predeterminada (SC-003) |
| Catálogo sin sembrar (solo migraciones) | Estado vacío «No hay libros registrados aún» |
