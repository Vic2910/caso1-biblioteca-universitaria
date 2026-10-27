# Data Model: 005-gestion-portadas (Gestión de portadas de libros)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-008, sección *Key Entities* y edge cases de la especificación, y las Restricciones adicionales → *Imágenes* de la constitución (Versión 1.0). Esta funcionalidad **no crea entidades nuevas ni cambia el esquema**: usa la columna `libros.portada` creada en `001-entorno-datos` ([../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md)) y define el ciclo de vida del archivo asociado (D-018…D-022).

---

## Relaciones (heredadas, sin cambios)

```text
categorias (1) ──────< (N) libros
                            │
                            └── portada (0..1 archivo en Storage disco "public")
                                    directorio: portadas/{libro_id}/
```

- Cada libro tiene **como máximo una portada propia**; no existen portadas compartidas (supuesto y edge cases de la spec).
- `Portada` es una entidad **lógico-física**: en la BD solo existe el campo `libros.portada` (ruta); el archivo vive en Storage (D-018). No hay tabla ni modelo `Portada`.
- Eloquent: se conserva `Libro::categoria()` → `belongsTo(Categoria)`; el modelo `Libro` gana el hook de borrado descrito en §Transiciones (D-022).

---

## Entidad: `Libro` (campos relevantes para esta funcionalidad)

| Campo | Tipo | Restricciones | Uso en esta funcionalidad |
|---|---|---|---|
| `id` | bigint unsigned, PK | — | Identifica el directorio `portadas/{id}/` y la ruta `GET /portadas/{id}` |
| `titulo` | varchar(255) | NOT NULL | Valor del `alt` de la portada mostrada (FR-008) |
| `portada` | varchar(255), nullable | `NULL` = sin portada → imagen predeterminada | Ruta **relativa** al disco `public` (p. ej. `portadas/7/9f3c….webp`), D-018 |

- El resto de campos (`isbn`, `autor`, `editorial`, `anio_publicacion`, `ejemplares_*`, `categoria_id`) **no cambian**; al editar otros datos sin archivo nuevo la portada se conserva (FR-001) y los demás valores se actualizan según las reglas de `003`.
- **Sin cambios de esquema**: cero migraciones en esta funcionalidad.

---

## Entidad lógica: `Portada`

| Atributo | Definición | Origen |
|---|---|---|
| Archivo | JPG, PNG o WebP; **≤ 2 MB (2048 KB, inclusivo)**; contenido real debe ser de esos formatos | FR-002, edge cases |
| Ubicación | `storage/app/public/portadas/{libro_id}/{id_unico}.{ext}` (disco `public` de Laravel Storage) | D-018, FR-003 |
| Referencia | `libros.portada` = ruta relativa; `NULL` = sin portada | FR-003, Key Entities |
| Cardinalidad | ≤ 1 archivo por libro; directorio propio por libro | Supuestos, D-018 |
| Acceso | Solo con sesión vía `GET /portadas/{id}`; sin sesión → HTTP 401 | FR-007, D-023 |
| Presentación | Listado y detalle usan `partials/portada`; `alt` = título del libro; predeterminada con `alt` genérico «Sin portada» | FR-008, D-024 |
| Respaldo | Archivo ausente en disco → `onerror` muestra `public/images/libro-sin-portada.svg` (asset público, sin sesión) | D-014 (`002`), D-023 |

---

## Ciclo de vida y transiciones de estado

Estado derivado: **Sin portada** (`portada IS NULL`) ↔ **Con portada** (ruta + archivo).

| # | Transición | Disparador | Efecto (orden exacto) | Fallo → estado final |
|---|---|---|---|---|
| T1 | Sin portada → Con portada | Guardar edición con archivo válido (`hasFile`) | ① guardar archivo nuevo → ② `portada = nuevaRuta` en BD → ③ reconciliar directorio | Fallo en ① o ②: sin cambios (en ② se borra el archivo nuevo como compensación) y mensaje de error junto al campo |
| T2 | Con portada → Con portada (reemplazo) | Guardar edición con archivo nuevo | ① capturar ruta anterior → ② guardar archivo nuevo → ③ actualizar BD → ④ borrar archivo anterior → ⑤ reconciliar | Cualquier fallo: portada y archivo previos intactos + mensaje de error (FR-006) |
| T3 | Con portada → Sin portada | Botón «Eliminar portada» con confirmación (`DELETE /libros/{id}/portada`) | ① borrar archivo → ② `portada = NULL` → ③ reconciliar → redirección con mensaje | Fallo en ①: referencia y archivo intactos + mensaje de error |
| T4 | Edición sin archivo | Guardar edición de otros datos | Sin operación de disco ni cambio de `portada` (FR-001) | — |
| T5 | Con/Sin portada → libro eliminado | `DELETE /libros/{id}` (reglas de `003`) | Hook `deleting` del modelo: ① `deleteDirectory("portadas/{id}")` → ② borro de la fila | Fallo de disco en ①: el libro se elimina igualmente (no se bloquea por Storage) |
| T6 | Reemplazo concurrente (T2 × 2) | Dos usuarios reemplazan a la vez | Predomina la última escritura; la reconciliación final (paso ⑤) elimina todo archivo que no sea la ruta vigente | Siempre queda **≤ 1 archivo** en `portadas/{id}` (SC-005) |

- **Reconciliación** (D-020): al final de T1, T2 y T3, se elimina todo archivo de `portadas/{libro_id}` cuya ruta difiera de `libros.portada` vigente. Es la garantía de ausencia de huérfanos (SC-005).
- **Validación de estado**: `GET /portadas/{id}` con libro inexistente, `portada = NULL` o archivo ausente en disco → HTTP 404 (sin servir imagen).

---

## Reglas de validación (servidor, FR-002)

| Regla | Valor | Comportamiento ante incumplimiento |
|---|---|---|
| Presencia | Opcional (`nullable`): ausente = T4 | — |
| Tipo | `file` + `mimes:jpg,jpeg,png,webp` (análisis del **contenido**, no de la extensión) | Error en español junto al campo; conservar valores con `old()`; portada intacta |
| Tamaño | `max:2048` KB — **2 MB exactos se acepta** (inclusivo) | Igual que arriba |
| Formatos excluidos | GIF, SVG, TIFF, u otros → rechazados | Igual que arriba |
| Extensión válida, contenido no imagen | Rechazado (por `mimes`) | Igual que arriba |
| Fallo de almacenamiento | Mensaje «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo | Portada existente sin cambios (FR-006) |

- Definidas en el Form Request de edición de libros (compartido con `003`, D-019); mensajes personalizados en español.

---

## Cambios de esquema en esta funcionalidad

| Migración | Cambio | Motivo |
|---|---|---|
| — | **Ninguna** | `libros.portada varchar(255) NULL` ya fue creada en `001-entorno-datos`; esta fase solo consume la columna |

---

## Dependencia de datos de prueba

| Requisito | Uso en `005` |
|---|---|
| ≥ 15 libros sembrados con `portada = NULL` | Punto de partida «Sin portada» para T1; verificación de la imagen predeterminada |
| Libros con préstamos registrados (`001`/`004`) | La eliminación del libro (T5) respeta la regla de bloqueo de `003`; la portada se retira solo si el libro llega a eliminarse |
| Categorías sembradas | El formulario de edición (donde viven los controles) las necesita como `003` |
