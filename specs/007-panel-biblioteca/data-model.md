# Data Model: 007-panel-biblioteca (Panel y reportes de biblioteca)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-008, sección *Key Entities* de la especificación y Principio I de la constitución (Versión 1.0). Esta funcionalidad es de **solo lectura**: no crea entidades ni columnas; reutiliza el esquema de `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)) y la colación de `libros.titulo` de `002` ([../002-consulta-catalogo/data-model.md](../002-consulta-catalogo/data-model.md)), y añade **una única migración de ajuste de colación** sobre `categorias.nombre` (D-021).

---

## Relaciones (heredadas de 001)

```text
categorias (1) ──────< (N) libros (1) ──────< (N) prestamos
   nombre UNIQUE           isbn UNIQUE            estado: activo|devuelto|vencido
                          titulo: colación ai_ci   FK libro_id → libros (RESTRICT)
                         FK categoria_id → categorias (RESTRICT)
```

- Eloquent: `Libro::categoria()` → `belongsTo(Categoria)`; `Libro::prestamos()` → `hasMany(Prestamo)`; `Categoria::libros()` → `hasMany(Libro)`; `Prestamo::libro()` → `belongsTo(Libro)` (definidas en `001`).
- El panel consume las tres entidades sin modificarlas (FR-006: solo consulta).

---

## Indicador 1 — Ranking de libros más prestados (FR-001, FR-004)

**Proyección**: `Libro` con `prestamos_count` (agregado), top 5.

| Campo mostrado | Origen | Regla |
|---|---|---|
| Posición | Calculada (1–5) | Orden de la consulta |
| Título | `libros.titulo` | Texto del campo |
| Autor | `libros.autor` | Texto del campo |
| Número de préstamos | `COUNT(prestamos.id)` por `libro_id` | Cifra visible junto al libro (clarificación 2026-10-26) |

**Regla de cálculo** (D-019):

```text
conjunto  = libros con COUNT(prestamos) ≥ 1, sobre el historial completo (sin filtro de fechas ni estado)
orden     = COUNT(prestamos) DESC → titulo ASC (colación utf8mb4_0900_ai_ci) → isbn ASC
límite    = máximo 5 filas
```

- Cada fila de `prestamos` cuenta **una vez** por préstamo (supuestos de la spec); un libro con varios préstamos suma varias unidades.
- Los libros con 0 préstamos **no** ocupan posiciones (clarificación 2026-10-26): si hay 3 libros con préstamos, el ranking tiene 3 filas.
- Empates: mismo número de préstamos → orden alfabético del título ignorando tildes y mayúsculas (colación de `002`); títulos iguales → ISBN (único → orden total determinista, FR-004).

---

## Indicador 2 — Préstamos vencidos (FR-002, FR-005)

**Proyección**: `Prestamo` (+ `libro.titulo`), lista completa sin paginación.

| Campo mostrado | Origen | Regla |
|---|---|---|
| Libro | `libros.titulo` (vía `Prestamo::libro`) | Eager loading `with('libro')` (D-023) |
| Estudiante | `prestamos.nombre_estudiante` | Texto del campo |
| Carné | `prestamos.carnet_estudiante` | Texto del campo |
| Fecha de préstamo | `prestamos.fecha_prestamo` | Texto del campo |
| Devolución prevista | `prestamos.fecha_devolucion_prevista` | Texto del campo |
| Días de retraso | `fecha_consulta − fecha_devolucion_prevista` (días naturales) | Cifra visible (clarificación 2026-10-26) |

**Regla de vencimiento** (D-020, con `fecha_consulta` según D-022):

```text
vencido ⇔ fecha_devolucion_prevista < fecha_consulta  AND  fecha_devolucion_real IS NULL
```

- Regla **estricta**: un préstamo cuya prevista es **igual** a la fecha de consulta **no** es vencido (edge case).
- Solo préstamos **pendientes de devolución**: los devueltos quedan fuera aunque hayan sido devueltos con retraso (edge case).
- La columna `estado` **no** interviene en el filtro: el vencimiento se calcula con las fechas en cada consulta, coherente con la evaluación automática de `004` (FR-002 de esa spec) y con el edge case «la fecha de comparación es la del día en que se consulta».

**Regla de orden** (FR-005):

```text
orden = fecha_devolucion_prevista ASC  →  libros.titulo ASC (colación ai_ci)  →  prestamos.id ASC
```

- `fecha_devolucion_prevista ASC` equivale a «días de retraso de mayor a menor» porque la fecha de consulta es única por petición (D-022).
- Un libro con **varios** préstamos pendientes aparece **una fila por préstamo** (edge case).

---

## Indicador 3 — Ejemplares disponibles por categoría (FR-003, FR-008)

**Proyección**: `Categoria` con `libros_count` y suma de disponibles.

| Campo mostrado | Origen | Regla |
|---|---|---|
| Categoría | `categorias.nombre` | Orden alfabético español (D-021) |
| Ejemplares disponibles | `SUM(libros.ejemplares_disponibles)` de la categoría | Cifra visible junto a la categoría (clarificación 2026-10-26) |

**Regla de cálculo** (D-021):

```text
conjunto  = categorías con COUNT(libros) ≥ 1        (las categorías SIN libros se ocultan — FR-008)
valor     = SUM(libros.ejemplares_disponibles)      (no ejemplares_totales)
orden     = nombre ASC con colación utf8mb4_0900_ai_ci (migración de 007)
```

- Una categoría con libros pero **0 disponibles** se muestra con su cifra `0` (FR-008: «incluidas las que tienen 0 disponibles»).
- El valor suma el campo `ejemplares_disponibles` que las reglas de `004` mantienen coherente (Principio III); el panel **no** recalcula existencias.

---

## Cambios de esquema en esta funcionalidad

| Migración | Cambio | Motivo |
|---|---|---|
| `database/migrations/*_alter_categorias_nombre_collation.php` | `categorias.nombre` → colación `utf8mb4_0900_ai_ci` (`Schema::table(...)->change()`) | Hacer verificable en el esquema el orden alfabético en español que ignora tildes y mayúsculas (FR-003, D-021); misma técnica que la migración de `002` sobre `libros.titulo` (D-012) |

- Es el **único** cambio de esquema; no se crean ni eliminan entidades, columnas, índices o claves.
- La migración debe ser **posterior** a la creación de `categorias` de `001` (orden cronológico de migraciones).
- Sin variante MariaDB: sustituir por `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci` (ver D-012).

---

## Fecha de consulta (D-022)

| Regla | Valor | Origen |
|---|---|---|
| Fuente | `today()` (fecha del servidor en la zona de la aplicación, sin hora) | D-022 |
| Alcance | Una única evaluación por petición; alimenta el filtro de vencidos y los días de retraso | D-020, D-022 |
| Entrada del usuario | Ninguna; el panel no acepta parámetros de fecha | FR-006, D-018 |

---

## Casos límite y su comportamiento

| Caso (spec) | Comportamiento del modelo |
|---|---|
| Sin préstamos registrados | Ranking vacío y lista de vencidos vacía → «No hay datos disponibles» en ambas secciones (FR-008, D-024) |
| Sin libros en ninguna categoría | Disponibilidad vacía → «No hay datos disponibles»; con libros en ≥ 1 categoría, esas se listan (FR-008) |
| Categoría con libros y 0 disponibles | Se muestra con cifra `0` (FR-008) |
| Categoría sin libros | Oculta (`having libros_count > 0`, FR-008) |
| Préstamo con prevista = fecha de consulta | No vencido (regla estricta `<`, D-020) |
| Préstamo devuelto con retraso | Excluido (`fecha_devolucion_real IS NULL`), edge case |
| Libros empatados en préstamos | Desempate por título (colación ai_ci) y luego ISBN (D-019) |
| Vencidos empatados en días de retraso | Desempate por título del libro y luego `prestamos.id` (D-020) |
| Menos de 5 libros con préstamos | Ranking con solo esos; sin relleno de posiciones (clarificación 2026-10-26) |
| Un libro con varios préstamos | Cuenta varias veces en el ranking; una fila por préstamo pendiente en vencidos |
| Ningún libro con préstamos | Ranking vacío → mensaje (no se muestran libros de cuenta 0) |

---

## Validación y transiciones de estado

- **Validación**: el panel no recibe entradas (solo lectura, `GET /panel` sin parámetros — D-018); no hay Form Requests en esta funcionalidad (Principio II se aplica a fases con formularios). El «criterio de vencimiento» de FR-005 se implementa como filtro en el servidor (D-020), no como validación de entrada.
- **Transiciones de estado**: ninguna. El estado de los préstamos (`activo`/`devuelto`/`vencido`) lo gestiona `004-gestion-prestamos`; el panel solo lee fechas y calcula el vencimiento del día, sin escribir en la base de datos (FR-006).

---

## Dependencia de datos de prueba (heredada de 001 y factories)

| Requisito | Uso en `007` |
|---|---|
| ≥ 15 libros y 3 categorías sembrados | Alimentan ranking y disponibilidad con volumen real (SC-001) |
| 3 préstamos sembrados (activo/devuelto/vencido) | Dan al menos un vencido y contenido de ranking tras `migrate --seed` |
| Factories de `Libro`, `Categoria`, `Prestamo` | Construyen en los tests los conjuntos de resultados conocidos: empates, fechas límite, categorías vacías y 0 disponibles (SC-002, SC-004, D-026) |
| Base migrada sin sembrar | Valida los tres estados vacíos «No hay datos disponibles» (V-006 de [quickstart.md](quickstart.md)) |
