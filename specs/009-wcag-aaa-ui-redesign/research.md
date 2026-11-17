# Research: Rediseño de interfaz a estándares mundiales (WCAG 2.1 AAA aplicable)

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Fecha**: 2026-11-17

Resuelve **todos** los desconocidos del Technical Context (0 `NEEDS CLARIFICATION`). Numeración de decisiones: **D-048…D-062**, continuación de D-036…D-047 de `008-interfaz-validacion-seguridad`.

Contexto verificado en el repositorio antes de decidir:

- `resources/css/app.css` (339 B) ya contiene `@import 'tailwindcss'`, `@source` de paginación y `@theme { --font-sans: Instrument Sans }`, y `vite.config.js` ya cablea `laravel()`, `tailwindcss()` y la fuente Bunny — pero **ninguna vista usa `@vite`**: `layouts/app.blade.php` enlaza `asset('css/app.css')` (13,5 KB de CSS propio) y `asset('js/app.js')` (513 B).
- `resources/js/app.js` está vacío (`//`); la confirmación de borrado vive en `public/js/app.js` y la búsqueda en `public/js/busqueda-catalogo.js` (ambas cargadas con `<script defer>` desde vistas).
- Existen 60 suites de PHPUnit y los parciales `estado-vacio` (008) y `portada` (002/005).
- La navegación actual es una `<ul>` plana de 6 enlaces, sin menús desplegables, sin colapsables y sin hamburguesa.

---

## D-048: Cableado de Tailwind 4 + Vite (cadena de build única)

- **Decision**: `layouts/app.blade.php` sustituye `<link asset('css/app.css')>` y `<script asset('js/app.js')>` por `@vite(['resources/css/app.css', 'resources/js/app.js'])`. `resources/css/app.css` pasa a ser la **única fuente de estilos** (conserva `@import 'tailwindcss'` y amplía `@theme`), y la confirmación de borrado de `public/js/app.js` se migra al bundle. `public/css/app.css` y `public/js/app.js` se dejan de servir desde el layout (los ficheros se retiran al confirmar la migración). Los módulos JS nuevos entran por el mismo bundle; `busqueda-catalogo.js` se importa desde `resources/js/app.js` o se mantiene como script de la vista según convenga probarlo (decisión táctica en tasks).
- **Rationale**: El encargo exige Tailwind 4 + Vite «ya instalados pero sin cablear»; tener dos fuentes de estilos (CSS estático + Tailwind) duplicaría tokens y rompería la coherencia del design system. Un único punto de entrada simplifica el purgado (`@source` ya apunta a `storage/framework/views` y a la paginación de Laravel) y hace que `npm run build` sea reproducible.
- **Alternativas considered**: Declarar Tailwind en el README sin cablearlo (rechazado: la spec exige el sistema de diseño implementado con esas herramientas); añadir Bootstrap (rechazado: dependencia nueva y duplica utilidades); dejar el CSS propio y añadir Tailwind encima (rechazado: dos sistemas de tokens en paralelo, imposible de mantener coherentes).

## D-049: Tokens del sistema de diseño en `@theme`

- **Decision**: Definir en `resources/css/app.css` vía `@theme` la escala completa: **color** (superficie, texto, borde, acento, éxito, aviso, error, cada uno con su par sobre fondo claro/oscuro ya verificado a ≥ 7:1), **tipografía** (escala rem de 12/14/16/18/20/24/30/36 con línea base 1,5), **espaciado** (escala 4 px: 4-8-12-16-24-32-48-64), **radios**, **sombras**, **anchors de foco** (`--focus-ring-width: 3px`, `--focus-ring-offset: 2px`) y **breakpoints** (`sm: 640px`, `md: 768px`, `lg: 1024px`), alineados con los tres tamaños de aceptación. Componentes frecuentes (botón, campo, tarjeta, tabla, alerta, menú) se expresan como clases `@utility`/componentes CSS reutilizados.
- **Rationale**: El FR-015 exige tokens declarados y coherentes; con Tailwind 4 los tokens viven en `@theme` y se consumen como utilidades, de modo que cambiar un color cambia todas las pantallas. Los breakpoints por defecto de Tailwind coinciden exactamente con 768 y 1024 px, y `sm` cubre el chequeo de 360 px.
- **Alternativas considered**: Tokens en JavaScript/JSON compartidos (rechazado: exige build adicional y no alimenta el CSS); variables sueltas por vista (rechazado: duplicación, base del problema actual); paleta heredada de `public/css/app.css` sin verificar (rechazado: no garantiza 7:1, requisito central).

## D-050: Navegación sin JavaScript con `<details>/<summary>` y mejora ARIA

- **Decision**: Menús desplegables (p. ej. agrupación «Gestión» → Libros, Categorías, Préstamos) y secciones colapsables implementados con **`<details>`/`<summary>`**, que abren y cierran nativamente sin JavaScript. El JavaScript (`resources/js/menus.js`) solo **mejora**: añade `aria-expanded`/`aria-controls` al cambiar el estado, cierra con `Escape`, cierra al pulsar fuera y devuelve el foco al disparador, y garantiza un único menú abierto a la vez. El menú hamburguesa en móvil es también un `<details>` con el mismo patrón, visible por debajo de `md` (768 px); por encima, la navegación se muestra horizontal y el resumen queda oculto con CSS sin eliminar el elemento.
- **Rationale**: `<details>` es el único patrón que satisface simultáneamente «operable con teclado», «patrón ARIA» y **«degradable sin JavaScript»** sin duplicar código. La mejora por JS es optativa: si no carga, el menú sigue funcionando con la semántica nativa (que ya es anunciada por los lectores de pantalla como «expandido/colapsado»).
- **Alternativas considered**: Menú construido solo con JS (rechazado: FR-007 exige degradación sin JS); solo `:hover`/`:focus-within` en CSS (rechazado: no se puede cerrar con teclado ni anunciar estado de forma fiable); «checkbox hack» con `<input type=checkbox>` (rechazado: sin semántica de divulgación ni `summary` para anunciar); librería de menús (rechazado: dependencia nueva).

## D-051: Estado de carga anunciado en la zona de resultados

- **Decision**: Parcial `partials/carga.blade.php` con `role="status"`, `aria-busy="true"` y texto **«Cargando…»**, renderizado en la región de resultados de la búsqueda/filtro del catálogo (única actualización parcial de la app, heredada de `006`) mientras se aplican los criterios; con JavaScript desactivado la región se recarga completa y no se muestra estado intermedio (la página entera es el estado). El resto de pantallas son SSR puro: su «estado de carga» es el render completo, sin zonas pendientes.
- **Rationale**: FR-010 exige estados de carga definidos; en una app SSR solo hay una región asíncrona. `role="status"` + texto cumplen 4.1.3 y evitan depender de animaciones (no cegadas por `prefers-reduced-motion`).
- **Alternativas considered**: Esqueleto visual sin texto (rechazado: no anunciado, la clarificación eligió texto); pantalla completa bloqueante (rechazado: impide el teclado y el uso del resto de la página); spinner sin texto en todas las vistas (rechazado: no hay carga asíncrona en ellas).

## D-052: Confirmación de éxito como toast auto-oculto anunciado

- **Decision**: El parcial existente de `session('success')` se convierte en `partials/toast.blade.php`: contenedor fijo con `role="status"` `aria-live="polite"`, mensaje en español, botón de cierre accesible y auto-ocultado a los **6 segundos** mediante `resources/js/toast.js` (con pausa al recibir foco o puntero). **Sin JavaScript el toast permanece visible** hasta la siguiente navegación, y en todos los casos el resultado de la operación sigue visible en la página (el registro aparece en el listado). Los mensajes de **error** siguen siendo persistentes (no se auto-ocultan).
- **Rationale**: La clarificación del 2026-11-17 eligió «toast que desaparece solo»; 4.1.3 exige anunciarlo y la FR-022 exige que la información no se pierda, por lo que el auto-ocultado es una mejora de JS y nunca la única vía de información.
- **Alternativas considered**: Modal que exige cierre (rechazado: opción no elegida y rompe el esfuerzo físico bajo); solo redirección sin mensaje (rechazado: sin confirmación observable, falla SC-012); toast que se oculta con CSS puro (rechazado: desaparece aunque el usuario lo esté leyendo y no se puede pausar).

## D-053: Aviso y extensión de sesión a los 5 minutos

- **Decision**: El layout renderiza `data-sesion-expira="{{ now()->addMinutes(config('session.lifetime'))->timestamp }}"` en `<body>` (la ventana se recalcula en cada petición, ya que Laravel actualiza la actividad de la sesión en cada request). `resources/js/sesion.js` calcula el tiempo restante y, cuando quedan **5 minutos**, muestra un diálogo `role="alertdialog"` con **«Quedan {n} minutos antes de que finalice su sesión.»**, botón **«Extender sesión»** (`POST /sesion/extender`, `@csrf`, grupo `auth`, que renueva la sesión conservando sus datos) y botón **«Cerrar sesión»**; si el usuario no responde, la sesión caduca con el comportamiento ya acordado en `008` (no se ejecuta la operación, se pide iniciar sesión y se conserva lo recuperable con `old()`). Sin JavaScript no hay aviso: se conserva la caducidad por inactividad de 2 horas (degradación documentada en [contracts/session.md](contracts/session.md)).
- **Rationale**: FR-005 + clarificación exigen aviso y extensión previos a la caducidad; el cálculo en el cliente evita sondeos al servidor, y el endpoint es la única forma fiable de «congelar» el reloj de inactividad. 2.2.1/2.2.5 y 1.4.13 se cumplen (tiempo ajustable y contenido que no desaparece al enfocarlo).
- **Alternativas considered**: Aviso solo del servidor en la siguiente petición (rechazado: llega tarde y no permite extender antes); WebSockets/SSE para avisos (rechazado: complejidad y dependencia innecesaria en una app local); alargar la caducidad en lugar de avisar (rechazado: cambia el contrato de 2 h de `008`); aviso automático sin opción de extender (rechazado: incumple la clarificación).

## D-054: Pre-validación de portada en el cliente

- **Decision**: `resources/js/portada.js` se carga en los formularios de libro (crear/editar) y valida al elegir el archivo: `file.size <= 2 * 1024 * 1024` y tipo MIME/extensión ∈ {JPG/JPEG, PNG, WebP}. Si falla: se limpia el `input`, se muestra junto al campo un mensaje en español con `role="alert"` y `aria-invalid="true"`, y se impide el envío. Si pasa: se muestra el nombre, el tamaño y una vista previa. El servidor sigue validando igual que hoy (`005`/`003`): si lo rechaza, `back()->withErrors()->withInput()` pinta el error junto al campo con el resto del formulario intacto. Sin JavaScript, solo actúa la validación del servidor con el mismo texto.
- **Rationale**: FR-017/FR-018 exigen pre-validación y que un archivo fuera de límites **nunca rompa la página**; duplicar los límites de la constitución (JPG/PNG/WebP ≤ 2 MB) en el cliente evita el viaje inútil, mientras el servidor sigue siendo la única fuente de verdad (Principio II).
- **Alternativas considered**: Solo validación del servidor (rechazado: la spec pide pre-validación explícita); librerías de subida (Dropzone/uppy: rechazado por dependencias nuevas); aceptar el archivo y validar en el servidor mostrando un error genérico (rechazado: es el comportamiento actual que hay que mejorar).

## D-055: Home público con accesos reales

- **Decision**: `welcome.blade.php` deja de ser un aviso de conexión y pasa a portada con: encabezado con propuesta de valor, **rejilla de accesos reales** (Catálogo, Libros, Categorías, Préstamos, Panel), bloque de estadísticas ya calculadas (`$totalLibros`, `$totalCategorias`, `$totalPrestamos`, conservando el controlador actual) y acción primaria **«Registrar préstamo»**. El home es **público** (clarificación 2026-11-17): sin sesión, cada acceso conduce a `GET /login` sin ejecutar ninguna operación; con sesión, al destino directo.
- **Rationale**: FR-012 y la clarificación exigen accesos reales; reutilizar las cifras existentes evita cambios de datos o de controlador.
- **Alternativas considered**: Home tras login únicamente (rechazado: opción no elegida; perdería el acceso público al catálogo); home con buscador embebido (rechazado: duplica la búsqueda de `006`); mantener el aviso con accesos añadidos (rechazado: el aviso de conexión no aporta valor al usuario).

## D-056: Acción principal visible por listado

- **Decision**: Parcial `partials/accion-principal.blade.php` que renderiza un botón primario siempre visible en la cabecera de la zona de contenido: **Catálogo → «Registrar préstamo»** (más el bloque de búsqueda heredado), **Libros → «Añadir libro»**, **Categorías → «Nueva categoría»**, **Préstamos → «Registrar préstamo»**. En las listas vacías el mismo parcial aporta la acción junto al mensaje (conservando `estado-vacio`). El panel (solo consulta) y el login no son listados y quedan fuera del requisito, pero ofrecen su propia acción de salida/reintento.
- **Rationale**: FR-011/FR-013 y SC-004 exigen una acción principal visible en cada listado y accesible en ≤ 2 pasos desde el home; el registro de préstamos es hoy inalcanzable.
- **Alternativas considered**: Acción solo en el estado vacío (rechazado: SC-004 la exige siempre visible); acción en la barra de navegación (rechazado: compite con la navegación y no contextualiza la pantalla); FAB flotante en móvil (rechazado: tap target solapado, peor para esfuerzo físico bajo).

## D-057: Foco, estados y canales no cromáticos

- **Decision**: Regla global `:focus-visible` con `outline: 3px solid var(--color-foco)` + `outline-offset: 2px` (nunca `outline: none`), tokens de estado `hover`/`active`/`focus`/`error`/`disabled` definidos una sola vez (D-049) y regla de **información sin color**: todo estado (disponibilidad, `activo`/`devuelto`/`vencido`, errores, éxito) incluye **texto o icono con etiqueta**, verificable en escala de grises. Los badges de estado de préstamos pasan de «color + texto» a «texto + icono + color».
- **Rationale**: FR-002/FR-003/FR-004 y 2.4.7/2.4.11/1.4.1; el outline de 3 px con offset sigue visible sobre cualquier fondo y bajo la cabecera fija.
- **Alternativas considered**: Foco solo con cambio de color de fondo (rechazado: 1.4.1 y no visible sobre fondos oscuros); foco de 1 px (rechazado: 2.4.13 exige ≥ 2 px); animaciones de foco (rechazado: sin valor y molesta con `prefers-reduced-motion`).

## D-058: Verificación AAA (auditoría automatizada + revisión manual)

- **Decision**: Procedimiento fijado en [quickstart.md](quickstart.md) V-012 según FR-023: (1) **auditoría automatizada** con extensión/herramienta de axe o equivalente sobre las 8 pantallas × 3 tamaños (contraste, roles, nombres accesibles, estados ARIA), y (2) **revisión manual** de lo que la herramienta no cubre: operación completa por teclado, visibilidad del foco, degradación sin JavaScript, aviso/extensión de sesión y estados sin color, siguiendo la matriz de [spec.md](spec.md). Resultados: tabla de cumplimiento documentada en el README junto a las capturas.
- **Rationale**: Clarificación 2026-11-17 (opción B) y FR-023; la combinación es el estándar de aceptación de accesibilidad.
- **Alternativas considered**: Solo revisión manual (rechazado: no repetible); solo herramienta automatizada (rechazado: no cubre teclado, foco ni sin-JS); auditoría externa (rechazado: coste y dependencia); muestreo (rechazado: la SC-003 exige cero incumplimientos sobre el conjunto).

## D-059: Declaración del sistema de diseño en el README

- **Decision**: Nueva sección **«Sistema de diseño»** en el README: herramienta (Tailwind 4 + Vite, cómo se ejecuta `npm run build`/`npm run dev`), tabla de tokens (color con su contraste verificado, tipografía, espaciado, radios, sombras), catálogo de componentes y estados, patrones de navegación accesible, y capturas de las tres tamaños. Se añade además la mención de cumplimiento WCAG 2.1 AAA aplicable con enlace a la matriz.
- **Rationale**: FR-016 y la decisión del equipo del 2026-11-17; la constitución exige declarar en el README cualquier framework de estilos usado.
- **Alternativas considered**: Documentación en un archivo aparte sin tocar el README (rechazado: la constitución designa al README como documentación oficial); documentar solo el nombre de la herramienta (rechazado: FR-016 pide tokens, tipografía, espaciado, color, componentes y estados).

## D-060: Conservación de textos y reglas de 002–008

- **Decision**: Los textos acordados en especificaciones previas se conservan literalmente («No hay libros registrados aún», «No hay préstamos registrados aún», «No se encontraron libros que coincidan con la búsqueda», el mensaje de fallo CSRF, el genérico de login, etc.) y se recogen en [contracts/user-messages.md](contracts/user-messages.md); la numeración de mensajes propios de 009 se añade aparte. Las reglas de negocio y de seguridad no se tocan (FR-020).
- **Rationale**: Evita regresiones en suites existentes y contradicciones entre especificaciones.
- **Alternativas considered**: Renombrar todos los textos en el rediseño (rechazado: rompe pruebas sin aporte y contradice «sin cambios en reglas»); mantener textos sin inventario (rechazado: no auditable).

## D-061: Degradación sin JavaScript como requisito transversal

- **Decision**: Regla de diseño: **todo flujo principal es nativo** (enlaces `<a>`, formularios `POST` con `@csrf`, `<details>`, confirmación de borrado con `onsubmit` nativo cuando no hay JS). JavaScript solo añade mejoras: ARIA extra en menús, auto-ocultado del toast, pre-validación de portada, aviso de sesión y debounce de búsqueda (heredado). La matriz de degradación se documenta en [contracts/ui-structure.md](contracts/ui-structure.md) §7 y se prueba en `NavegacionSinJsTest`.
- **Rationale**: FR-007 exige degradación sin JS; en una aplicación local el JS puede no cargar (bloqueo, error de red al servir el bundle).
- **Alternativas considered**: Soporte solo con JS (rechazado: incumple FR-007); duplicar cada vista versión sin JS (rechazado: dos interfaces que divergen); rendimiento degradado sin aviso (rechazado: el aviso de sesión es requisito con JS disponible y está documentado su límite).

## D-062: Rama, convención y alcance de pruebas

- **Decision**: Implementación en la rama `feature/interfaz-seguridad` (activa, ya contiene la spec 009) con **Conventional Commits** (`feat(ui):`, `docs(readme):`, `test(a11y):`); Pint obligatorio sobre PHP tocado (`vendor/bin/pint --dirty`); suites nuevas por archivo temático (lista en [plan.md](plan.md) Project Structure) y **las60 suites existentes deben seguir en verde** como regresión de que no se rompen contratos de `002`–`008`.
- **Rationale**: Constitución → Flujo de desarrollo; aislar por tema permite revisión por commits incrementales.
- **Alternativas considered**: Rama nueva `feature/ui-redesign` (aceptable pero separa la spec ya commiteada de su implementación); una sola suite gigante de UI (rechazado: difícil de diagnosticar); no tocar tests existentes (rechazado: `AccesibilidadUiTest` y `EstadosVaciosTest` deben ampliarse con los nuevos criterios).

---

## Resumen de resolución de desconocidos

| Desconocido del Technical Context | Resuelto en |
|---|---|
| Cableado de Tailwind 4 + Vite | D-048, D-049 |
| Patrón de navegación sin JS + ARIA + hamburguesa | D-050, D-061 |
| Estados de carga anunciados | D-051 |
| Confirmación de éxito (toast) | D-052 |
| Aviso y extensión de sesión | D-053 |
| Pre-validación de portada | D-054 |
| Home con accesos reales y acción por listado | D-055, D-056 |
| Foco, estados y contraste 7:1 | D-049, D-057 |
| Método de verificación AAA | D-058 |
| Declaración en README | D-059 |
| Conservación de textos/reglas previos | D-060 |
| Rama, commits y alcance de pruebas | D-062 |

**NEEDS CLARIFICATION restantes: 0.**
