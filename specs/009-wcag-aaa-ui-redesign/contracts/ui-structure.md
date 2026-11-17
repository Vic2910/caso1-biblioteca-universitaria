# Contract: Estructura de interfaz y sistema de diseño (FR-001…FR-006, FR-007…FR-009, FR-015, FR-023)

**Spec**: [../spec.md](../spec.md) | **Research**: [../research.md](../research.md) D-048, D-049, D-050, D-051, D-057, D-061 | **Data model**: [../data-model.md](../data-model.md)

Este contrato **reemplaza** al de `008-interfaz-validacion-seguridad/contracts/ui-structure.md` (FR-020). Textos visibles: [user-messages.md](user-messages.md).

## 1. Sistema de diseño (FR-015, FR-016)

| Elemento | Regla |
|---|---|
| Fuente única | `resources/css/app.css` con `@import 'tailwindcss'`; consumida mediante `@vite` en el layout. No existen otras hojas de estilo servidas (D-048) |
| Tokens | Declarados en `@theme`: color, tipografía, espaciado (escala 4 px), radios, sombras, anillos de foco y breakpoints (D-049) |
| Componentes | Botón (primario/secundario/seguro), campo con etiqueta y error, tarjeta, tabla, alerta, menú, estado vacío, acción principal, toast, estado de carga |
| Estados | `normal`, `hover`, `focus-visible`, `active`, `error`, `disabled` — definidos una sola vez y aplicados igual en todas las pantallas |
| Contraste | Todo texto normal ≥ **7:1** sobre su fondo real (FR-002), incluidos estados y badges |
| Declaración | El README incluye la sección «Sistema de diseño» con herramienta (Tailwind 4 + Vite), tokens, tipografía, espaciado, color, componentes y estados (FR-016) |

## 2. Layout común

Todas las vistas extienden `resources/views/layouts/app.blade.php`:

| Región | Contenido obligatorio |
|---|---|
| **Cabecera** | Identidad de la aplicación + navegación (§3) + bloque de sesión (saludo y «Cerrar sesión», heredado de `008`) |
| **Skip link** | Primer elemento enfocable: «Saltar al contenido principal» hacia `#contenido` (2.4.1) |
| **Zona de contenido** | `<main id="contenido">` con `@yield`; mensaje de éxito (toast, §6) y errores persistentes heredados |
| **Pie** | Pie común idéntico en todas las vistas |

- Cabecera, navegación y pie son idénticos en todas las vistas (auditable por test).
- Un único `<h1>` por vista; jerarquía `h2`/`h3` sin saltos (1.3.1/2.4.6).
- `lang="es"` en `<html>`; título descriptivo por pantalla (`@yield('title')`).

## 3. Navegación accesible (FR-007, FR-008, FR-009)

| Patrón | Implementación contractuada |
|---|---|
| Enlaces de primer nivel | Inicio, Catálogo, Panel visibles siempre; página activa con `aria-current="page"` **y** refuerzo no cromático (subrayado/peso) |
| Menú desplegable «Gestión» | `<details>`/`<summary>` con Libros, Categorías y Préstamos; abre/cierra **sin JavaScript** |
| Secciones colapsables | Mismo patrón `<details>` para bloques de contenido (p. ej. ayuda del home, bloques del panel) |
| Menú hamburguesa | `<details>` visible por debajo de `md` (768 px); expone la misma estructura de enlaces; al superar `md` se muestra la navegación horizontal y el resumen se oculta con CSS sin eliminarlo |
| Mejora con JS | `aria-expanded`/`aria-controls` sincronizados, `Escape` cierra y devuelve el foco al disparador, clic fuera cierra, un solo menú abierto a la vez (D-050) |

- Todo elemento de navegación es operable con `Tab`/`Shift+Tab`, `Enter` y `Espacio`; sin trampas de foco (2.1.1/2.1.2).
- Orden de foco = orden visual (2.4.3).

### Breakpoints de aceptación

| Tamaño | Comportamiento |
|---|---|
| **360 × 640 px** | Una columna; hamburguesa; sin recortes ni desbordamiento horizontal |
| **768 × 1024 px** | Navegación horizontal legible; rejilla intermedia |
| **≥ 1024 px** | Diseño de referencia |

## 4. Foco, estados y canales no cromáticos (FR-002, FR-003, FR-004, D-057)

- `:focus-visible` → `outline: 3px solid` + `outline-offset: 2px`; **nunca** `outline: none` sin sustituto; el foco no queda oculto bajo la cabecera (2.4.7/2.4.11).
- Objetivos táctiles ≥ **44 px** de lado o con separación suficiente (2.5.5).
- Ningún estado se comunica solo con color: disponibilidad, `activo`/`devuelto`/`vencido`, errores y éxito incluyen **texto** (y icono con etiqueta) (1.4.1).
- Movimiento: sin animaciones esenciales; se respeta `prefers-reduced-motion`.

## 5. Campos de formulario

Orden obligatorio por campo (heredado de `008`, sin cambios): etiqueta `<label for>` visible → campo con `name`/`id` coincidentes y `old()` → error en español **inmediatamente después** con `aria-invalid="true"` y `aria-describedby`. Confirmación de eliminación previa con `DELETE` (nunca `GET`).

## 6. Mensajes: éxito, error y carga (FR-010, FR-022)

| Mensaje | Componente | Comportamiento |
|---|---|---|
| Éxito | `partials/toast.blade.php`, `role="status"` `aria-live="polite"` | Auto-oculto a los 6 s con JS (pausable al enfocarlo), **persiste sin JS**; el resultado permanece visible en la página (D-052) |
| Error de validación | Existente, `role="alert"` | Persistente, junto a los campos, conserva `old()` |
| Carga | `partials/carga.blade.php`, `role="status"` `aria-busy="true"`, texto «Cargando…» | Región de búsqueda/filtro; sin JS no hay región asíncrona (D-051) |

## 7. Degradación sin JavaScript (FR-007, D-061)

| Capacidades | Con JS | Sin JS |
|---|---|---|
| Navegación, menús, colapsables, hamburguesa | + ARIA extra, `Escape`, foco devuelto | **Funcional** (semántica nativa) |
| Formularios, borrados, búsqueda con `GET` | + confirmación extra | **Funcional** (nativo + `onsubmit`) |
| Toast de éxito | auto-ocultado a los 6 s | visible hasta navegar |
| Pre-validación de portada | sí | solo validación del servidor (mismo texto) |
| Aviso/extensión de sesión | diálogo a los 5 min | sin aviso; caducidad a las 2 h (límite documentado) |
| Búsqueda con debounce | sí | envío por botón/recarga |

## 8. Verificación (FR-023)

Auditoría automatizada (contraste, roles, nombres accesibles) + revisión manual (teclado, foco, sin JS, sesión, estados sin color) sobre las 8 pantallas × 3 tamaños, según [../quickstart.md](../quickstart.md) V-012 y la matriz de [../spec.md](../spec.md).
