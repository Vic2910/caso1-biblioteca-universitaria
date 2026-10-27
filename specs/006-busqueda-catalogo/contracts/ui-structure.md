# Contract: Estructura de interfaz de la búsqueda o filtro (FR-001, FR-004, FR-006)

**Spec**: [../spec.md](../spec.md) FR-001, FR-004, FR-006, edge cases, SC-002, SC-004 | **Research**: [../research.md](../research.md) D-032, D-033, D-034 | **Data model**: [../data-model.md](../data-model.md)

Contrato de composición visual de los controles de búsqueda sobre el listado `/catalogo`. Hereda el layout y la tarjeta de [../../002-consulta-catalogo/contracts/ui-structure.md](../../002-consulta-catalogo/contracts/ui-structure.md). Textos visibles: [user-messages.md](user-messages.md).

## 1. Ubicación y layout común (SC-004)

- La vista `catalogo/index` **sigue extendiendo** `resources/views/layouts/app.blade.php`: cabecera, navegación y pie idénticos al resto de vistas (sin cambios del contrato de `002` §1).
- La barra de búsqueda se sitúa **debajo del título de la sección** («Catálogo de libros») y **encima** del estado vacío o de la rejilla de tarjetas, de modo que los criterios son lo primero visible y permanecen en pantalla en todos los estados (FR-004).
- Estilos con CSS propio del proyecto (Principio V); sin frameworks CSS/JS ni pasos de build.

## 2. Controles (FR-001, FR-003, FR-006)

Zona de controles, en orden, dentro de un formulario `GET` hacia `catalogo.index`:

| # | Elemento | Regla |
|---|---|---|
| 1 | Campo de texto `q` | Etiqueta visible «Buscar» + `placeholder` de ayuda; un **único** campo comparado contra título, autor, editorial e ISBN (clarificación 2026-10-26); valor rebotado desde el servidor con `{{ $q }}` (escapado) |
| 2 | Desplegable `categoria` | Opción por defecto «Todas las categorías» (valor vacío = sin criterio); el resto de opciones, una por categoría de la BD ordenada alfabéticamente, valor = `id`; la opción activa se marca desde el servidor |
| 3 | Enlace «Limpiar» | Visible **solo** cuando hay al menos un criterio activo; enlace a `catalogo.index` sin parámetros (funciona sin JavaScript) — FR-006 |
| 4 | Botón de envío **visualmente oculto** | Existe para que «Enter» envíe el formulario y para la semántica accesible; **no es** el «botón de envío visible» que la clarificación descarta (FR-001) |

- No hay botón de envío visible: el filtrado se dispara solo (D-032).

## 3. Comportamiento de filtrado automático (FR-001, edge case «escritura rápida»)

| Disparador | Comportamiento |
|---|---|
| Escritura en `q` | Debounce de **300 ms** (cola): cada pulsación reinicia el temporizador; al expirar se envía el formulario. Se envía **una sola petición** aunque el usuario escriba rápido, y el **último texto** determina los resultados |
| Cambio de `categoria` | Envío **inmediato** (sin debounce) |
| Envío del formulario | El formulario **no incluye** `page` → el listado vuelve a la página 1 con los nuevos criterios (D-034) |
| Tras el render | Si `q` tiene valor, el script devuelve el foco al campo (cursor al final) para continuar escribiendo sin interrupciones |
| Sin JavaScript | «Enter» envía el formulario (botón oculto) y el desplegable funciona con el envío nativo: la funcionalidad degrada, no se bloquea |

- Implementación: script propio `public/js/busqueda-catalogo.js` (JS nativo, D-032); el servidor aplica los criterios en cada petición (Principio II).

## 4. Estados del listado (FR-004, SC-002, SC-004)

| Estado | Condición | Composición |
|---|---|---|
| **Resultados con criterio** | criterio activo y total ≥ 1 | barra de controles + rejilla de tarjetas (sin cambios) + paginación filtrada |
| **Sin coincidencias** | criterio activo y total = 0 | barra de controles **con los criterios aún visibles** + mensaje «No se encontraron libros que coincidan con la búsqueda» + **sin** tarjetas ni paginación |
| **Catálogo vacío** | sin criterio y total = 0 | barra de controles + mensaje «No hay libros registrados aún» (contrato de `002`, sin cambios) + sin tarjetas ni paginación |

- Los criterios «permanecen visibles» en todos los estados: el campo conserva el texto y el desplegable marca la categoría **desde el servidor**, no desde el estado del cliente (FR-004).
- La prioridad exacta entre estados está en [../data-model.md](../data-model.md) §Prioridad de estados vacíos.

## 5. Paginación sobre resultados filtrados (FR-005)

- Mismos controles propios «Anterior»/«Siguiente»/números de `002` (vista `pagination/catalogo.blade.php`), aplicados al conjunto filtrado.
- Cada enlace conserva `q` y `categoria` (`withQueryString()`): cambiar de página no pierde la búsqueda (D-034).
- Sin resultados filtrados → no se renderizan controles de paginación (solo el mensaje de estado vacío).

## 6. Accesibilidad y seguridad de la vista

- Etiquetas `<label>` asociadas al campo y al desplegable; el formulario tiene nombre identificable (p. ej. `id="busqueda"`).
- Todo texto de datos —incluido el valor de `q` rebotado— se renderiza con `{{ }}` escapado (Principio IV).
- El mensaje de resultados vacíos es un texto fijo del marcado en español (no depende de la BD).
- Las tarjetas, la imagen predeterminada y la ficha de detalle no cambian respecto a `002` (parcial `partials/portada`, portada con `onerror`).
