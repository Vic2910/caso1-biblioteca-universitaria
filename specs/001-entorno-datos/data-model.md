# Data Model: 001-entorno-datos (Entorno y base de datos)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-002 a FR-006, sección *Key Entities* de la especificación y Principio I + *Restricciones* de la constitución (Versión 1.0). El esquema se crea **exclusivamente con migraciones** versionadas en `database/migrations/` (D-003).

---

## Diagrama entidad-relación

```text
categorias (1) ──────< (N) libros (1) ──────< (N) prestamos
   nombre UNIQUE           isbn UNIQUE            estado: activo|devuelto|vencido
                                                   FK libro_id → libros (RESTRICT)
                        FK categoria_id → categorias (RESTRICT)
```

- Categoría agrupa muchos libros; cada libro pertenece a exactamente una categoría; cada préstamo corresponde a exactamente un libro (FR-002, SC-001).

---

## Entidad: `categorias`

| Campo | Tipo | Restricciones | Origen |
|---|---|---|---|
| `id` | bigint unsigned, PK, autoincremento | — | Laravel default |
| `nombre` | varchar(100) | **NOT NULL, UNIQUE** (`categorias_nombre_unique`) | FR-003, clarificación 2026-10-26 |
| `created_at` / `updated_at` | timestamps | — | Laravel default |

**Reglas de validación** (Form Requests en fases posteriores; hoy, migración + factory):
- `nombre`: obligatorio, único, no vacío.

**Eliminación**: bloqueada mientras existan libros asociados (`RESTRICT`, D-004 / FR-006).

---

## Entidad: `libros`

| Campo | Tipo | Restricciones | Origen |
|---|---|---|---|
| `id` | bigint unsigned, PK, autoincremento | — | Laravel default |
| `isbn` | char(13) | **NOT NULL, UNIQUE** (`libros_isbn_unique`), solo dígitos (validación `regex:/^[0-9]{13}$/`) | FR-003, constitución "ISBN único de 13 dígitos" |
| `titulo` | varchar(255) | NOT NULL | Key Entities |
| `autor` | varchar(255) | NOT NULL | Key Entities |
| `editorial` | varchar(255) | NOT NULL | Key Entities |
| `anio_publicacion` | smallint | NOT NULL, entre 1900 y el año actual (validación de servidor) | Constitución "año entre 1900 y el año actual" |
| `ejemplares_totales` | int unsigned | NOT NULL, >= 1 | Key Entities |
| `ejemplares_disponibles` | int unsigned | NOT NULL, `<= ejemplares_totales` | FR-004, reglas de disponibilidad |
| `categoria_id` | bigint unsigned, FK → `categorias.id` | NOT NULL, **`restrictOnDelete()`** | FR-002, FR-006 |
| `portada` | varchar(255), nullable | NULL = usar imagen predeterminada (Laravel Storage guardará la ruta cuando exista portada) | Clarificación: datos de prueba sin portada real; constitución *Imágenes* |
| `created_at` / `updated_at` | timestamps | — | Laravel default |

**Invariante de disponibilidad** (validado en datos de prueba, FR-004 / SC-002):

```text
ejemplares_disponibles = ejemplares_totales − (SUMA de ejemplares prestados en préstamos con estado 'activo')
```

**Relaciones (Eloquent)**: `categoria()` → `belongsTo(Categoria)`; `prestamos()` → `hasMany(Prestamo)`.

**Eliminación**: bloqueada mientras existan préstamos asociados (`RESTRICT`, D-004 / FR-006).

---

## Entidad: `prestamos`

| Campo | Tipo | Restricciones | Origen |
|---|---|---|---|
| `id` | bigint unsigned, PK, autoincremento | — | Laravel default |
| `libro_id` | bigint unsigned, FK → `libros.id` | NOT NULL, **`restrictOnDelete()`** | FR-002, FR-006 |
| `nombre_estudiante` | varchar(255) | NOT NULL | Key Entities |
| `carnet_estudiante` | varchar(50) | NOT NULL | Key Entities |
| `fecha_prestamo` | date | NOT NULL | Key Entities |
| `fecha_devolucion_prevista` | date | NOT NULL, **> `fecha_prestamo`** (validación de servidor) | Constitución "devolución prevista posterior al préstamo" |
| `fecha_devolucion_real` | date, nullable | NOT NULL solo cuando `estado = 'devuelto'` | Key Entities |
| `estado` | enum/varchar: `activo`, `devuelto`, `vencido` | NOT NULL, uno de los tres valores | Constitución: estado activo, devuelto o vencido |
| `created_at` / `updated_at` | timestamps | — | Laravel default |

**Relaciones (Eloquent)**: `libro()` → `belongsTo(Libro)`.

**Índices**: índice sobre (`libro_id`) — automático con la FK en MySQL; índice sobre (`estado`) para el listado de vencidos de fases posteriores.

### Transiciones de estado

```text
(nuevo) ──→ activo ──→ devuelto   [se registra fecha_devolucion_real]
              │
              ├──→ vencido        [fecha_devolucion_prevista < hoy y sin devolución]
              │
vencido ──→ devuelto              [se registra fecha_devolucion_real]
```

- El préstamo de prueba `activo`: sin `fecha_devolucion_real`, prevista futura.
- El préstamo `devuelto`: con `fecha_devolucion_real` no nula.
- El préstamo `vencido`: sin `fecha_devolucion_real`, prevista pasada (o marcado como vencido en el seeder).
- Los tres estados están representados en los datos de prueba (clarificación 2026-10-26 / FR-004).

**Eliminación**: la constitución exige `DELETE` con confirmación en fases con interfaz; el borrador de un libro con préstamos queda bloqueado por `RESTRICT`.

---

## Datos de prueba (semilla mínima, FR-004 / SC-002)

| Catálogo | Cantidad | Detalle |
|---|---|---|
| Categorías | ≥ 3 | nombres únicos |
| Libros | ≥ 15 | ISBN únicos de 13 dígitos, `portada = NULL`, asignados a categorías existentes |
| Préstamos | 3 | 1 `activo`, 1 `devuelto`, 1 `vencido`, sobre libros existentes |

Comprobaciones exigidas a la carga:
1. 100 % de los libros tiene `categoria_id` válido (SC-001).
2. 100 % de los préstamos tiene `libro_id` válido (SC-001).
3. 100 % de los libros cumple el invariante de disponibilidad (SC-002).
4. Los tres estados de préstamo aparecen representados (SC-002).

---

## Reglas de eliminación documentadas (FR-006)

| Intento | Resultado | Aviso |
|---|---|---|
| Eliminar categoría con libros | Bloqueado (RESTRICT en BD + comprobación previa en app) | Aviso en español: existen libros relacionados |
| Eliminar categoría sin libros | Eliminación correcta | — |
| Eliminar libro con préstamos | Bloqueado (RESTRICT en BD + comprobación previa en app) | Aviso en español: existen préstamos relacionados |
| Eliminar libro sin préstamos | Eliminación correcta | — |

Ambas conductas se documentan en el README (SC-005). Contrato de mensajes: [contracts/user-messages.md](contracts/user-messages.md).
