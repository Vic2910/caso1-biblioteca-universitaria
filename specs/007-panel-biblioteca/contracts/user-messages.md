# Contract: Mensajes visibles al usuario (FR-007, FR-008)

**Spec**: [../spec.md](../spec.md) FR-007, FR-008, edge cases, SC-003, SC-004 | **Research**: [../research.md](../research.md) D-024, D-025 | **Structure**: [ui-structure.md](ui-structure.md)

Todos los mensajes son **en español**, visibles para estudiantes, docentes y personal administrativo (clarificación 2026-10-26).

## 1. Estados vacíos (FR-008, edge cases)

| Elemento | Contrato |
|---|---|
| **Texto requerido** | «No hay datos disponibles» |
| **Disparador — ranking** | `GET /panel` cuando ningún libro tiene préstamos registrados |
| **Disparador — vencidos** | `GET /panel` cuando ningún préstamo cumple la regla de vencimiento (ninguno con prevista anterior a hoy y sin devolución real) |
| **Disparador — disponibilidad** | `GET /panel` cuando ninguna categoría tiene libros |
| **Presentación** | En la zona de la sección afectada, sin filas ni cifras; las demás secciones se muestran con normalidad |

- El texto es **idéntico** en las tres secciones y **exactamente** «No hay datos disponibles» (FR-008, edge cases).
- No se muestra «0» como sustituto del mensaje: la spec distingue «sin datos» (mensaje) de «0 ejemplares disponibles» (cifra junto a la categoría).

## 2. Rótulos de sección (FR-007)

| Sección | Título |
|---|---|
| Página | «Panel de la biblioteca» |
| Indicador 1 | «Libros más prestados» |
| Indicador 2 | «Préstamos vencidos» |
| Indicador 3 | «Ejemplares disponibles por categoría» |

## 3. Rótulos de columnas (FR-007, clarificación 2026-10-26)

| Sección | Columnas (en este orden) |
|---|---|
| Libros más prestados | «N.º», «Título», «Autor», «Préstamos» |
| Préstamos vencidos | «Libro», «Estudiante», «Carné», «Fecha de préstamo», «Devolución prevista», «Días de retraso» |
| Disponibilidad por categoría | «Categoría», «Ejemplares disponibles» |

- Las cifras de «Préstamos» y «Ejemplares disponibles» deben percibirse **junto a** su libro/categoría (clarificación 2026-10-26).
- «Días de retraso» se muestra como número entero de días (p. ej. `12`); una devolución prevista igual a la fecha de consulta no genera fila, por lo que el mínimo mostrado es `1`.

## 4. Navegación

| Elemento | Texto | Destino |
|---|---|---|
| Enlace de navegación | «Panel» | `route('panel.index')` |

## 5. Restricciones del contrato

- Ningún mensaje expone trazas SQL, credenciales, rutas internas ni detalles técnicos (Principio IV; coherente con los contratos de `001` y `002`).
- Textos fijos en el marcado; los únicos valores provenientes de datos son títulos, autores, nombres de categoría, nombres y carnés de estudiantes y fechas, siempre escapados con `{{ }}` (Principio IV).
- El panel no muestra errores de validación ni confirmaciones: no realiza escrituras (FR-006).
