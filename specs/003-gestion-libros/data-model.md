# Data Model: 003-gestion-libros (Gestión de libros y categorías)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-002 a FR-009, sección *Key Entities* de la especificación y Principio I + *Restricciones* de la constitución (Versión 1.0). Esta funcionalidad **no crea entidades nuevas**: reutiliza el esquema de `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)) y la colación de `002` ([../002-consulta-catalogo/data-model.md](../002-consulta-catalogo/data-model.md)); añade **una única migración de ajuste de colación** (D-020). Lo nuevo son las **reglas de escritura** sobre `Libro` y `Categoría`.

---

## Relaciones (heredadas de 001)

```text
categorias (1) ──────< (N) libros (1) ──────< (N) prestamos
                           │
                           └── portada (opcional, ruta en Storage público)
```

- Categoría agrupa muchos libros; cada libro pertenece a exactamente una categoría (`libros.categoria_id` → `categorias.id`, `RESTRICT`); cada préstamo pertenece a un libro (`prestamos.libro_id` → `libros.id`, `RESTRICT`).
- Eloquent (definidas en `001`): `Libro::categoria()` → `belongsTo(Categoria)`; `Libro::prestamos()` → `hasMany(Prestamo)`; `Categoria::libros()` → `hasMany(Libro)`.

---

## Cambios de esquema en esta funcionalidad

| Migración | Cambio | Motivo |
|---|---|---|
| `database/migrations/*_alter_categorias_nombre_collation.php` | `categorias.nombre` → colación `utf8mb4_0900_ai_ci` (`Schema::table(...)->change()`) | Hacer que el índice `categorias_nombre_unique` existente compare ignorando mayúsculas y tildes en la base de datos (FR-008, D-020); el mismo patrón que la migración de `002` sobre `libros.titulo`/`libros.autor` |

- Es el **único** cambio de esquema; no se crean ni eliminan columnas, índices o claves.
- La migración debe ser **posterior** a las de `001` y `002` (orden cronológico).
- Con ella, «Matemáticas» y «matematicas» chocan en el índice único incluso ante concurrencia; los **espacios sobrantes** los recorta la aplicación (`trim` en los Form Requests, D-020), ya que `utf8mb4_0900_ai_ci` es NO PAD.
- Sin variante MariaDB: sustituir por `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci` (ver D-012).

---

## Entidad de escritura: `Libro`

Campos almacenados (sin cambios de columnas respecto a `001`); lo nuevo es **quién los escribe y con qué reglas**:

| Campo | Tipo | Escritura en `003` | Reglas (FR) |
|---|---|---|---|
| `isbn` | char(13), NOT NULL, UNIQUE | Form (texto, conserva ceros iniciales) | `^[0-9]{13}$` + único; error junto al campo (FR-003) |
| `titulo` | varchar(255), NOT NULL | Form | obligatorio, 2–255 (FR-006) |
| `autor` | varchar(255), NOT NULL | Form | obligatorio, 2–255 (FR-006) |
| `editorial` | varchar(255), NOT NULL | Form | obligatorio, ≤ 255 (FR-006) |
| `anio_publicacion` | smallint, NOT NULL | Form | entero 1900–año actual inclusive, límite calculado en el momento de la operación (FR-003) |
| `ejemplares_totales` | int unsigned, NOT NULL | Form | entero ≥ 1; al editar, ≥ número de préstamos activos (FR-007) |
| `ejemplares_disponibles` | int unsigned, NOT NULL | **Servidor únicamente** (excluido de `$fillable` y del formulario) | ver §Disponibilidad |
| `categoria_id` | FK → `categorias.id`, NOT NULL, RESTRICT | Form (selector) | obligatorio, `exists` (FR-002/006) |
| `portada` | varchar(255), nullable | **Servidor únicamente** (ruta de Storage) | ver §Portada |
| `created_at` / `updated_at` | timestamps | Laravel | — |

### `$fillable` y protecciones del modelo

- `Libro::$fillable` = `['isbn','titulo','autor','editorial','anio_publicacion','ejemplares_totales','categoria_id','portada']` — **sin** `ejemplares_disponibles` (clarificación Q1: no editable ni por formulario ni por asignación masiva).
- El recálculo de disponibilidad se ejecuta en el evento `saving` del modelo (`Libro::recalcularDisponibilidad()` invocada desde el listener), de modo que ningún `$libro->save()` deja el invariante roto (D-023, Principio III).

### §Disponibilidad (invariante, FR-007 / SC-006)

```text
ejemplares_disponibles = ejemplares_totales − (SUMA de ejemplares prestados
                        en préstamos con estado 'activo' de este libro)
```

| Operación | Efecto |
|---|---|
| Crear libro | `ejemplares_disponibles = ejemplares_totales` (aún sin préstamos) |
| Editar total | Validar `ejemplares_totales ≥ max(1, préstamos activos)`; recalcular disponibles tras guardar |
| Prestar / devolver | Ajuste automático — **fuera de alcance**, corresponde a `004-gestion-prestamos`; este plan solo define el invariante |

### §Portada (FR-006, clarificación Q2)

- **Alta**: archivo opcional JPG/PNG/WebP ≤ 2 MB → `$request->file('portada')->store('portadas','public')`; la ruta resultante se guarda en `portadas` (columna `portada`).
- **Edición**: si no se envía archivo, el campo **no se modifica**; el reemplazo y la eliminación de archivos son de `005-gestion-portadas`.
- `NULL` → el parcial `partials/portada` de `002` (D-014) muestra la imagen predeterminada.

### §Eliminación (FR-005 / FR-009)

| Intento | Resultado | Aviso |
|---|---|---|
| Eliminar libro **con** préstamos registrados | Bloqueado: comprobación previa `prestamos()->exists()` + respaldo `RESTRICT` | «El libro tiene préstamos registrados y no puede eliminarse.» |
| Eliminar libro **sin** préstamos | Confirmación (`confirm()`) → `DELETE` → eliminado | Confirmación previa obligatoria |

---

## Entidad de escritura: `Categoría`

| Campo | Tipo | Escritura en `003` | Reglas (FR) |
|---|---|---|---|
| `nombre` | varchar(100), NOT NULL, UNIQUE (colación `utf8mb4_0900_ai_ci`) | Form (con `trim` previo) | obligatorio; **único ignorando mayúsculas, tildes y espacios sobrantes**; error en español junto al campo al crear y al editar (FR-008) |
| `created_at` / `updated_at` | timestamps | Laravel | — |

- `Categoria::$fillable` = `['nombre']`.
- Unicidad en dos capas (D-020): índice único con colación `_ai_ci` (tildes/mayúsculas, incluso concurrente) + `trim()` en los Form Requests (espacios sobrantes) + `Rule::unique` con `ignore($id)` en edición, traducido al español (D-024).
- El valor almacenado conserva la forma original recortada («Matemáticas», no «matematicas»).

### §Eliminación (FR-008)

| Intento | Resultado | Aviso |
|---|---|---|
| Eliminar categoría **con** libros | Bloqueado: comprobación previa `libros()->exists()` + respaldo `RESTRICT` | «La categoría tiene libros asociados y no puede eliminarse.» |
| Eliminar categoría **sin** libros | Confirmación (`confirm()`) → `DELETE` → eliminada; desaparece de los selectores | Confirmación previa obligatoria |

---

## Transiciones de estado

- `Libro` y `Categoría` **no tienen estados** en esta funcionalidad; solo campos validados y relaciones.
- Los estados de préstamo (`activo`/`devuelto`/`vencido`, ver `001` §Transiciones) no cambian en `003`; solo se **leen** (`prestamos activos`) para las reglas FR-007 y FR-009.

---

## Datos de prueba (heredados de 001, usados por el CRUD)

| Requisito | Uso en `003` |
|---|---|
| ≥ 15 libros sembrados, con ISBN únicos | Casos válidos de edición/eliminación y catálogo con datos |
| ≥ 3 categorías | Selector de categorías poblado en el formulario; gestión desde vista propia |
| 3 préstamos (1 activo, 1 devuelto, 1 vencido) | Libro con préstamo activo para probar el bloqueo de eliminación (FR-009) y la cota inferior del total al editar (FR-007) |
| Invariante de disponibilidad sembrado | Punto de partida coherente para recálculos de `003` |

Para probar la eliminación válida de un libro y de una categoría se crean registros nuevos con factories (sin préstamos/libros asociados), sin alterar la semilla mínima de `001`.
