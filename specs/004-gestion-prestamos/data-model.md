# Data Model: 004-gestion-prestamos (Registro, consulta y devolución de préstamos)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-007, sección *Key Entities* de la especificación, clarificaciones de sesión 2026-10-26 y Principio I + Principio III de la constitución (Versión 1.0). **No hay cambios de esquema**: la tabla `prestamos` y su FK `restrictOnDelete()` se crearon en `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)); `004` implementa las **reglas de negocio** sobre ese esquema (D-018…D-027).

---

## Relaciones (heredadas de 001)

```text
categorias (1) ──────< (N) libros (1) ──────< (N) prestamos
                            │                      │
                            ├─ ejemplares_totales  ├─ estado: activo | devuelto | vencido
                            └─ ejemplares_disponibles  └─ FK libro_id → libros (RESTRICT)
```

- Cada préstamo corresponde a exactamente un libro (`prestamos.libro_id` → `libros.id`, `RESTRICT`): no se puede eliminar un libro con préstamos (bloqueo heredado de `001`, también exigido por `003` FR-009).
- Eloquent: `Prestamo::libro()` → `belongsTo(Libro)`; `Libro::prestamos()` → `hasMany(Prestamo)` (definidas en `001`).

---

## Entidad de escritura: `Prestamo`

Campos almacenados (sin cambios de columnas respecto a `001`; se detalla el uso en `004`):

| Campo | Tipo | Restricciones | Uso en esta funcionalidad |
|---|---|---|---|
| `id` | bigint unsigned, PK | — | Identificador del préstamo (`/prestamos/{id}/devolver`) |
| `libro_id` | bigint unsigned, FK → `libros.id` | NOT NULL, `restrictOnDelete()` | Origen del préstamo; se valida `exists:libros,id` (D-021) |
| `nombre_estudiante` | varchar(255) | NOT NULL | Datos del préstamo; 2–255 caracteres (D-021) |
| `carnet_estudiante` | varchar(50) | NOT NULL, **almacenado con `trim()`** (D-023) | Clave de identidad del estudiante para el límite de 3 |
| `fecha_prestamo` | date | NOT NULL | Inicio del préstamo |
| `fecha_devolucion_prevista` | date | NOT NULL, **> `fecha_prestamo`** (FR-003) | Umbral del estado `vencido` |
| `fecha_devolucion_real` | date, nullable | NOT NULL cuando se registra la devolución; **≥ `fecha_prestamo`** (FR-006) | Se fija solo al devolver |
| `estado` | enum/varchar: `activo`, `devuelto`, `vencido` | NOT NULL | **Calculado/sincronizado** en el servidor (D-020); nunca se captura en formularios |
| `created_at` / `updated_at` | timestamps | — | Laravel default |

**Inmutabilidad (FR-007)**: no existe ninguna operación de edición ni eliminación de préstamos. Los campos anteriores solo se crean (`store`) y se actualizan en el punto exacto de la devolución (`devolver`: `estado` → `devuelto` y `fecha_devolucion_real`). No se expone ninguna ruta PUT/PATCH/DELETE (D-018).

**Relación**: `libro()` → `belongsTo(Libro)`. El listado usa **carga ansiosa** `with('libro')` (D-024) sin N+1.

---

## Entidad afectada: `Libro` (solo disponibilidad)

| Campo | Uso en `004` |
|---|---|
| `ejemplares_disponibles` | Se **descuenta 1** dentro de la transacción al registrar un préstamo; se **suma 1** exactamente una vez al devolver (FR-005). Actualización condicional: nunca baja de cero (D-022) |
| `ejemplares_totales` | Solo lectura aquí; lo edita el CRUD de `003` con la regla «total ≥ préstamos activos» (003 FR-007) |
| `titulo`, `isbn` | Se muestran en el `<select>` del formulario y en el listado (etiqueta del libro) |

**Invariante de disponibilidad** (heredado de `001`, ahora activo):

```text
ejemplares_disponibles = ejemplares_totales − (SUMA de ejemplares prestados en préstamos 'activo' o 'vencido')
```

Cada operación de `004` lo preserva exactamente: −1 al registrar, +1 al devolver, 0 en cualquier rechazo (SC-001, SC-002).

---

## Transiciones de estado (FR-002, clarificación 2026-10-26)

```text
(nuevo) ──→ activo ──→ devuelto        [devolución: se registra fecha_devolucion_real]
              │
              ├──→ vencido             [cálculo automático: prevista < hoy, sin devolver]
              │
devuelto      └──→ (vencido imposible) [estado final: jamás se recalcula desde 'devuelto']
```

| Transición | Disparador | Efecto |
|---|---|---|
| `(nuevo) → activo` | `PrestamoService::registrar()` | Crea el préstamo; disponibilidad −1 |
| `activo → vencido` | `PrestamoService::sincronizarVencidos()`, invocado al **registrar**, **devolver** y **listar** (D-020) | Solo si `fecha_devolucion_prevista < hoy` (estricto: vencido **desde el día siguiente**) y `fecha_devolucion_real IS NULL`; disponibilidad sin cambio |
| `activo → devuelto` | `PrestamoService::devolver()` | `fecha_devolucion_real` obligatoria ≥ `fecha_prestamo`; disponibilidad +1 |
| `vencido → devuelto` | `PrestamoService::devolver()` | Permitido (spec); misma regla de fecha; disponibilidad +1 |
| `devuelto → *` | Ninguno | Estado final; un nuevo intento de devolución se rechaza con «Este préstamo ya fue devuelto.» sin tocar disponibilidad |

---

## Reglas de negocio (Principio III — centralizadas en `PrestamoService`, D-019)

| # | Regla | Evaluación | Rechazo |
|---|---|---|---|
| R1 | Disponibilidad | `ejemplares_disponibles >= 1` antes de descontar; descuento condicional a la fila (D-022) | Campo `libro_id`: «No hay ejemplares disponibles de este libro.» |
| R2 | Límite por estudiante | Conteo de préstamos con `carnet` normalizado y `estado IN ('activo','vencido')`; si ≥ 3, no registrar (D-026). Los **vencidos cuentan** (clarificación) | Campo `carnet_estudiante`: mensaje de límite ([contracts/user-messages.md](contracts/user-messages.md)) |
| R3 | Fechas del préstamo | `fecha_devolucion_prevista` estrictamente posterior a `fecha_prestamo` (FR-003) | Campo `fecha_devolucion_prevista`: «La devolución prevista debe ser posterior a la fecha de préstamo.» |
| R4 | Fecha real de devolución | Obligatoria; no anterior a `fecha_prestamo` (FR-006); se permite en préstamos vencidos | Campo `fecha_devolucion_real`, junto al campo |
| R5 | Identidad del estudiante | Carné comparado tras `trim()` exclusivo (D-023); mismo carné = mismo estudiante para R2 | — (normalización, no rechazo) |
| R6 | Libro existente | `exists:libros,id` (FR-007 edge «libro inexistente») | Campo `libro_id`: «El libro seleccionado no existe.» |
| R7 | Inmutabilidad | Sin rutas ni operaciones de edición/eliminación (FR-007) | 404/ruta inexistente por diseño |
| R8 | Atómicidad | Registro y descuento en **una** transacción; devolución y suma en **una** transacción; la suma ocurre solo si el préstamo no estaba devuelto (SC-001: nunca +2) | — |

Toda regla se evalúa en el servidor; la interfaz es complementaria (supuesto de la spec, Principio II).

---

## Datos de prueba (heredados de 001)

| Elemento | Uso en `004` |
|---|---|
| ≥ 15 libros con disponibilidad coherente | Opciones del `<select>`; casos de disponibilidad |
| 3 préstamos sembrados: 1 `activo`, 1 `devuelto`, 1 `vencido` | Listado con los tres estados; base para devolución y límite |
| Invariante de disponibilidad de los sembrados | Verificado en tests antes/después de cada operación (SC-001) |

---

## Índices y rendimiento

- `prestamos(libro_id)`: automático con la FK en MySQL (heredado de `001`).
- `prestamos(estado)`: creado en `001` para el listado de vencidos (el panel de `007` lo aprovechará).
- Conteo del límite R2: filtro por `carnet_estudiante` + `estado` sobre decenas–cientos de filas; sin necesidad de índice adicional en esta escala.
- `sincronizarVencidos()` es un único `UPDATE` acotado (`estado = 'activo' AND prevista < hoy`) por operación, sin bloqueos relevantes a esta escala.
