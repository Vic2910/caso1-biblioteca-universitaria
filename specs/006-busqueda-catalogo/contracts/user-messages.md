# Contract: Mensajes visibles al usuario (FR-004, FR-006)

**Spec**: [../spec.md](../spec.md) FR-004, FR-006, edge cases, SC-002, SC-004 | **Research**: [../research.md](../research.md) D-033 | **Structure**: [ui-structure.md](ui-structure.md)

Todos los mensajes son **en español**, visibles para estudiantes, docentes y personal administrativo. Los textos de `001`/`002` ([../../001-entorno-datos/contracts/user-messages.md](../../001-entorno-datos/contracts/user-messages.md), [../../002-consulta-catalogo/contracts/user-messages.md](../../002-consulta-catalogo/contracts/user-messages.md)) se mantienen salvo donde se indique.

## 1. Resultados vacíos de la búsqueda (FR-004, SC-002)

| Elemento | Contrato |
|---|---|
| **Disparador** | `GET /catalogo` con al menos un criterio activo (`q` no vacío tras `trim()` o `categoria` válido) y total filtrado = 0 |
| **Texto requerido** | **«No se encontraron libros que coincidan con la búsqueda»** (texto exacto de la spec, sin variantes) |
| **Presentación** | En la zona del listado: sin tarjetas y sin controles de paginación |
| **Criterios** | Permanecen visibles: el campo conserva el texto buscado y el desplegable marca la categoría seleccionada (ambos rebotados desde el servidor) |

- **Distinguir** del estado «catálogo vacío» de `001`/`002`: «No hay libros registrados aún» solo aparece cuando **no** hay criterio activo (prioridad en [../data-model.md](../data-model.md)).

## 2. Mensajes heredados sin cambios

| Elemento | Texto | Origen |
|---|---|---|
| Catálogo sin libros y sin criterio | «No hay libros registrados aún» | `001`/`002` |
| Página fuera de rango o no numérica | «Página no válida» + enlace «Volver al catálogo» | `002` (D-013) |
| Libro inexistente en el detalle | «Libro no encontrado» + enlace «Volver al catálogo» | `002` (D-013) |

- Un filtro sin coincidencias **nunca** produce 404 (responde `200` con el mensaje de §1); un `categoria` inválido tampoco (se ignora — D-031).

## 3. Textos de los controles (FR-001, FR-006)

| Elemento | Texto |
|---|---|
| Etiqueta del campo de búsqueda | «Buscar» |
| `placeholder` del campo de búsqueda | «Buscar por título, autor, editorial o ISBN» |
| Etiqueta del desplegable | «Categoría» |
| Opción por defecto del desplegable | «Todas las categorías» (valor vacío = sin criterio) |
| Enlace de limpieza | «Limpiar» (solo visible con al menos un criterio activo) |
| Título de la sección (sin cambios) | «Catálogo de libros» |
| Enlace de navegación (sin cambios) | «Catálogo» |

## 4. Restricciones del contrato

- Ningún mensaje expone trazas SQL, credenciales, rutas internas ni detalles técnicos (Principio IV).
- El valor de `q` se renderiza **escapado** con `{{ }}`; no se refleja ningún texto sin escapar proveniente del usuario.
- Textos fijos en el marcado salvo título/autor/categoría, que son datos.
- El script de debounce no genera mensajes visibles propios (no hay estados de error del cliente: cada envío es una petición normal al servidor).
