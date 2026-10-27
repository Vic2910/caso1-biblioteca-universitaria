# Data Model: 006-busqueda-catalogo (Búsqueda o filtro del catálogo)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-006, la sección *Key Entities* de la especificación y Principio I de la constitución (Versión 1.0). Esta funcionalidad es de **solo lectura**: no crea entidades, columnas ni relaciones; reutiliza el esquema de `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)) y la colación de `titulo`/`autor` de `002` ([../002-consulta-catalogo/data-model.md](../002-consulta-catalogo/data-model.md)), y añade **una única migración de ajuste de colación sobre `editorial`** (D-030).

---

## Relaciones (heredadas de 001)

```text
categorias (1) ──────< (N) libros
   ▲                        │
   └── filtro exacto ───────┘  (libros.categoria_id)
```

- Categoría agrupa muchos libros; cada libro pertenece a exactamente una categoría (`libros.categoria_id` → `categorias.id`, `RESTRICT`).
- Eloquent: `Libro::categoria()` → `belongsTo(Categoria)`; `Categoria::libros()` → `hasMany(Libro)` (definidas en `001`; sin cambios).

---

## Campos que intervienen en la búsqueda (FR-003)

| Campo | Tipo | Colación / restricción | Papel en la búsqueda |
|---|---|---|---|
| `libros.titulo` | varchar(255) NOT NULL | `utf8mb4_0900_ai_ci` (fijada por `002`, D-012) | Texto: coincidencia que contiene, sin distinguir mayúsculas ni acentos |
| `libros.autor` | varchar(255) NOT NULL | `utf8mb4_0900_ai_ci` (fijada por `002`, D-012) | Texto: ídem |
| `libros.editorial` | varchar(255) NOT NULL | `utf8mb4_0900_ai_ci` (**fijada por `006`, D-030**) | Texto: ídem |
| `libros.isbn` | char(13) NOT NULL, UNIQUE, solo dígitos | sin ajuste (solo dígitos: no aplica caso ni acento) | Texto: coincidencia que contiene **sobre el valor almacenado**, conservando los ceros iniciales |
| `libros.categoria_id` | bigint unsigned, FK → `categorias.id` NOT NULL | `restrictOnDelete()` | Filtro de **coincidencia exacta** |
| `categorias.nombre` | varchar(100) NOT NULL, UNIQUE | — | Solo para poblar y mostrar las opciones del desplegable |

- **Proyección**: no hay campos nuevos que mostrar; las tarjetas siguen mostrando portada, título, autor, año y categoría ([../002-consulta-catalogo/contracts/ui-structure.md](../002-consulta-catalogo/contracts/ui-structure.md) §2). Los campos de este cuadro son los que la consulta **filtra**, no los que la vista agrega.

---

## Cambios de esquema en esta funcionalidad

| Migración | Cambio | Motivo |
|---|---|---|
| `database/migrations/*_alter_libros_editorial_search_collation.php` | `libros.editorial` → colación `utf8mb4_0900_ai_ci` (`Schema::table(...)->change()`) | Garantizar en el esquema que la coincidencia sobre `editorial` ignore tildes y mayúsculas (FR-003, D-030) |

- Es el **único** cambio de esquema; no se crean ni eliminan entidades, columnas, índices o claves.
- La migración debe ser **posterior** a la de colación de `002` sobre `titulo`/`autor` (orden cronológico de migraciones).
- Sin variante MariaDB: sustituir por `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci` (ver D-030 y el precedente D-012).
- Verificación: `SHOW FULL COLUMNS FROM libros WHERE Field = 'editorial'` → `Collation = utf8mb4_0900_ai_ci` (también afirmada en `BusquedaInterfazTest`).

---

## Semántica de los criterios (FR-003, FR-005)

### Parámetros de consulta

| Parámetro | Normalización en servidor | Criterio activo cuando… | Condición resultante |
|---|---|---|---|
| `q` | `trim()`; comodines `%`, `_`, `\` escapados (D-029) | tras `trim()` no está vacío | grupo OR: `titulo LIKE '%q%'` **OR** `autor LIKE '%q%'` **OR** `editorial LIKE '%q%'` **OR** `isbn LIKE '%q%'` |
| `categoria` | `^\d+$` y ≥ 1 y existente en `categorias` (D-031) | valor válido | `categoria_id = <id>` (exacta) |
| `page` | heredado de `002` (D-013) | — | paginación sobre el total **filtrado** |

### Reglas de combinación

```text
WHERE  (titulo LIKE ? OR autor LIKE ? OR editorial LIKE ? OR isbn LIKE ?)   -- q
  AND  categoria_id = ?                                                     -- categoria (si está activo)
ORDER BY titulo ASC → autor ASC → isbn ASC                                   -- D-012, sin cambios
LIMIT 10 OFFSET …                                                            -- paginate(10)
```

- **Regla Y** (FR-005): cada criterio activo añade una condición `AND` sucesiva; el texto es un grupo `OR` interno anidado (D-029).
- **Sin criterio** (`q` vacío o solo espacios, y `categoria` ausente/inválido) → la consulta queda **exactamente** la de `002`: catálogo completo con paginación habitual.
- **Coincidencia que contiene** (`LIKE` con `%…%`): parcial en cualquier posición; en ISBN también parcial y sobre el texto almacenado (ceros iniciales intactos).
- **Sensibilidad**: la comparación usa la colación de cada columna → insensible a mayúsculas y acentos en los tres campos de texto (D-030); para `isbn` (dígitos) la distinción no aplica.
- Los valores viajan como **bindings** del Query Builder; jamás se concatenan al SQL (Principio IV).

---

## Reglas de paginación sobre resultados filtrados (FR-005, D-034)

| Regla | Valor | Origen |
|---|---|---|
| Tamaño de página | 10 libros (sin cambios de `002`) | D-011 |
| Base de conteo | conjunto **filtrado** (`total()` del resultado filtrado) | FR-005, edge case «multipágina» |
| Criterios en enlaces de página | conservados vía `withQueryString()` (`q`, `categoria`, `page`) | D-011, http-endpoints §Parámetros |
| Cambio de criterio | el formulario no envía `page` → vuelve a la página 1 | D-032, D-034 |
| `page` fuera de rango / no numérica (bajo filtro) | HTTP **404** «Página no válida» evaluado sobre el total filtrado | D-013, D-034 |
| Filtro con total = 0 | página 1 válida → mensaje de resultados vacíos, sin controles de paginación; `page ≥ 2` → 404 | D-033, D-034 |
| Última página incompleta | solo libros existentes, sin posiciones vacías | heredado de `002` |

### Prioridad de estados vacíos (D-033)

| Condición | Estado mostrado |
|---|---|
| Criterio activo y total filtrado = 0 | «No se encontraron libros que coincidan con la búsqueda» + criterios visibles + sin tarjetas ni paginación |
| Sin criterio y total = 0 | «No hay libros registrados aún» (contrato de `001`/`002`, sin cambios) |
| Criterio activo y total filtrado ≥ 1 | tarjetas + paginación filtrada (sin mensaje) |

---

## Validación y transiciones de estado

- **Validación**: sin Form Requests ni escrituras (Principio II satisfecho en su vertiente de solo lectura): las tres entradas de consulta se normalizan en el servidor según la tabla *Parámetros de consulta* (D-029, D-031, D-013). Ningún error de validación produce mensajes de formulario; el único error HTTP posible sigue siendo el 404 de página heredado de `002`.
- **Transiciones de estado**: ninguna. `Libro` y `Categoría` no cambian; la búsqueda no modifica datos, no escribe en Storage ni toca préstamos/disponibilidad (fuera de alcance: `004`, `005`).

---

## Dependencia de datos de prueba (heredada de 001)

| Requisito | Uso en `006` |
|---|---|
| ≥ 15 libros sembrados | Base sobre la que un criterio reduce el listado de forma verificable (edge case «catálogo pequeño», SC-003) |
| ≥ 3 categorías | Desplegable con varias opciones y filtro exacto verificable (SC-005) |
| ISBN únicos de 13 dígitos con ceros iniciales | Búsqueda parcial por ISBN conservando ceros (SC-003) |
| Datos con tildes/mayúsculas | Verificación de la coincidencia insensible (SC-001); se crean además en los tests con factories |
