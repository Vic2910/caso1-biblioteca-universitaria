# Contract: Estructura de interfaz del panel (FR-007, FR-008)

**Spec**: [../spec.md](../spec.md) FR-004, FR-005, FR-007, FR-008, SC-003 | **Research**: [../research.md](../research.md) D-024, D-025 | **Data model**: [../data-model.md](../data-model.md)

Contrato de composición visual de la vista del panel. Textos visibles: [user-messages.md](user-messages.md).

## 1. Layout común (FR-007, D-025)

`panel/index.blade.php` extiende `resources/views/layouts/app.blade.php` (creado en `001`, navegación ampliada en `002`):

| Región | Contenido obligatorio |
|---|---|
| **Cabecera** | Nombre de la aplicación (Biblioteca Universitaria) — idéntica al catálogo |
| **Navegación** | Enlaces comunes, incluido «Catálogo» y el nuevo «Panel» → `route('panel.index')`; la página activa se distingue visualmente |
| **Zona de contenido** | `@yield` con el título del panel y las tres secciones (§2) |
| **Pie** | Pie común idéntico al del catálogo |

Cabecera, navegación y pie son **idénticos** a los del catálogo y su detalle (verificable en `PanelLayoutTest`).

## 2. Vista del panel (`/panel`) — una sola vista, tres secciones

Zona de contenido, en orden:

1. **Título** de la página (p. ej. «Panel de la biblioteca»).
2. **Sección 1 — «Libros más prestados»** (FR-001, FR-004):

   | # | Columna | Regla |
   |---|---|---|
   | 1 | Posición | Número consecutivo 1–5 |
   | 2 | Título | `titulo` del libro |
   | 3 | Autor | `autor` del libro |
   | 4 | Préstamos | Cifra de préstamos registrados **visiblemente junto al libro** (clarificación 2026-10-26) |

   - Máximo 5 filas; solo libros con ≥ 1 préstamo; sin relleno de posiciones.
   - Orden: mayor a menor cifra; empates por título (tildes/mayúsculas ignoradas) y ISBN ([data-model.md](../data-model.md) §Indicador 1).
   - Sin filas → mensaje vacío (§3).
3. **Sección 2 — «Préstamos vencidos»** (FR-002, FR-005):

   | # | Columna | Regla |
   |---|---|---|
   | 1 | Libro | `titulo` del libro del préstamo |
   | 2 | Estudiante | `nombre_estudiante` |
   | 3 | Carné | `carnet_estudiante` |
   | 4 | Fecha de préstamo | `fecha_prestamo` |
   | 5 | Devolución prevista | `fecha_devolucion_prevista` |
   | 6 | Días de retraso | Cifra **visiblemente visible** en su columna (clarificación 2026-10-26) |

   - Una fila por préstamo pendiente vencido; sin paginación.
   - Orden: días de retraso de mayor a menor; empates por título del libro ([data-model.md](../data-model.md) §Indicador 2).
   - Sin filas → mensaje vacío (§3).
4. **Sección 3 — «Ejemplares disponibles por categoría»** (FR-003, FR-008):

   | # | Columna | Regla |
   |---|---|---|
   | 1 | Categoría | `nombre` de la categoría |
   | 2 | Ejemplares disponibles | Cifra **visiblemente junto a la categoría** (clarificación 2026-10-26) |

   - Orden alfabético español del nombre, sin distinguir tildes ni mayúsculas (colación `utf8mb4_0900_ai_ci`, D-021).
   - Se listan las categorías con ≥ 1 libro —incluidas las de 0 disponibles—; se ocultan las categorías sin libros (FR-008).
   - Sin ninguna categoría con libros → mensaje vacío (§3).

## 3. Estados vacíos (FR-008, D-024)

- Cada sección se renderiza de forma **independiente**.
- Una sección sin filas muestra «No hay datos disponibles» en su propia zona, sin tablas ni filas visibles (texto exacto: [user-messages.md](user-messages.md) §1).
- Los indicadores con datos se muestran aunque otros estén vacíos.

## 4. Presentación general

- Rótulos y valores **en español**, comprensibles sin conocimientos previos del sistema (FR-007).
- Estilos con el **CSS propio** del proyecto (sin frameworks CSS; si se añadieran, deberían declararse en el README).
- Toda la salida —títulos, nombres, cifras y mensajes— se escapa con `{{ }}` (Principio IV, D-025).
- La vista es de **solo consulta**: no contiene formularios, botones de acción ni enlaces de escritura (FR-006).
- El panel no muestra portadas ni imágenes de libros/categorías (fuera de alcance de esta funcionalidad).
