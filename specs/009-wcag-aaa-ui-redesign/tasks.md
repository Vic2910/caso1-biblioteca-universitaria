---

description: "Task list for 009-wcag-aaa-ui-redesign (WCAG 2.1 AAA aplicable)"
---

# Tasks: Rediseño de interfaz a estándares mundiales (WCAG 2.1 AAA aplicable)

**Input**: Design documents from `/specs/009-wcag-aaa-ui-redesign/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Los contratos y quickstart referencian suites concretas (`SesionAvisoTest`, `AccionPrincipalTest`, V-001…V-014), por lo que se incluyen tareas de prueba por historia; se ejecutan con `php artisan test`.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- Layout/estilos/JS: `resources/views/`, `resources/css/`, `resources/js/`
- Backend: `app/Http/Controllers/`, `routes/web.php`
- Tests: `tests/Feature/`
- Documentación: `README.md`, `specs/009-wcag-aaa-ui-redesign/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Cablear la cadena de build front-end (Tailwind 4 + Vite, ya instalados pero sin usar — D-048)

- [X] T001 Cablear el layout a Vite: sustituir `<link asset('css/app.css')>` y `<script asset('js/app.js')>` por `@vite(['resources/css/app.css', 'resources/js/app.js'])` en `resources/views/layouts/app.blade.php` (D-048)
- [X] T002 [P] Migrar la confirmación de borrado (`data-confirm`) desde `public/js/app.js` al bundle de entrada en `resources/js/app.js` (D-048)
- [X] T003 [P] Integrar `public/js/busqueda-catalogo.js` en la cadena de Vite (importar desde `resources/js/app.js` o mantenerlo como script de `resources/views/catalogo/index.blade.php` apuntando al bundle) sin romper el debounce de `006`
- [X] T004 Ejecutar `npm run build` y verificar que el layout sirve el bundle; retirar `asset('css/app.css')` y `asset('js/app.js')` del layout y dejar de servir `public/css/app.css` y `public/js/app.js` (D-048)

**Checkpoint**: Build único con Vite operativo; ninguna vista enlaza CSS/JS estático de `public/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Sistema de diseño base, layout común y parciales transversales que TODAS las historias necesitan

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T005 Definir los tokens del sistema de diseño en `@theme` de `resources/css/app.css`: color (superficie, texto, borde, acento, éxito, aviso, error — cada par texto/fondo verificado a ≥ 7:1), tipografía (rem 12/14/16/18/20/24/30/36, línea 1,5), espaciado (escala 4 px: 4-8-12-16-24-32-48-64), radios, sombras, `--focus-ring-width: 3px`, `--focus-ring-offset: 2px` y breakpoints `sm: 640px`, `md: 768px`, `lg: 1024px` (D-049)
- [ ] T006 Crear los componentes reutilizables en `resources/css/app.css` (`@utility`/clases): botón primario/secundario/seguro, campo con etiqueta y error, tarjeta, tabla, alerta, menú, estado vacío, toast, estado de carga — con estados `normal`, `hover`, `focus-visible`, `active`, `error`, `disabled` definidos una sola vez (contracts/ui-structure.md §1)
- [X] T007 Reestructurar `resources/views/layouts/app.blade.php`: skip link «Saltar al contenido principal» como primer elemento enfocable hacia `#contenido`, `<main id="contenido">`, regiones cabecera/navegación/pie idénticas en todas las vistas, `lang="es"`, un único `<h1>` por vista y `@yield('title')` (contracts/ui-structure.md §2)
- [X] T008 [P] Regla global `:focus-visible` con `outline: 3px solid var(--color-foco)` + `outline-offset: 2px` (nunca `outline: none` sin sustituto) y utilidades de estado no cromático en `resources/css/app.css` (D-057)
- [X] T009 [P] Crear `resources/views/partials/toast.blade.php`: contenedor fijo `role="status"` `aria-live="polite"` con mensaje en español, botón «Cerrar mensaje» y auto-ocultado a 6 s (D-052, contracts/ui-structure.md §6)
- [X] T010 [P] Crear `resources/views/partials/carga.blade.php`: `role="status"` `aria-busy="true"` con texto «Cargando…» para la región de resultados del catálogo (D-051)
- [X] T011 [P] Crear `resources/js/toast.js`: auto-ocultado del toast a los 6 segundos con pausa al recibir foco o puntero; importarlo desde `resources/js/app.js` (D-052)

**Checkpoint**: Foundation ready — tokens, layout, foco, toast y estado de carga disponibles para todas las historias

---

## Phase 3: User Story 1 - Navegar con teclado, con y sin JavaScript (Priority: P1)

**Goal**: Menús desplegables y colapsables operables con teclado, anunciados, con hamburguesa en móvil, funcionales sin JavaScript y con foco siempre visible.

**Independent Test**: Recorrer la navegación solo con teclado (Tab, Shift+Tab, Enter, Espacio, flechas, Escape) en 360×640, 768×1024 y ≥1024 px, con y sin JavaScript; cada sección alcanzable, estado anunciado y foco visible.

### Tests for User Story 1

- [ ] T012 [P] [US1] Crear `tests/Feature/NavegacionTecladoTest.php`: menús/colapsables exponen `aria-expanded`/`aria-controls`, patrón `<details>` presente, `aria-current="page"` en la página activa, skip link primero y sin trampas de foco (FR-007, contracts/ui-structure.md §3)
- [X] T013 [P] [US1] Crear `tests/Feature/NavegacionSinJsTest.php`: todas las secciones alcanzables con semántica nativa (`<details>`/`<summary>`, enlaces `<a>`), hamburguesa presente en el markup y formularios operables sin JS (FR-007/FR-008, D-061)
- [ ] T014 [P] [US1] Crear `tests/Feature/BreakpointsUiTest.php`: markup de navegación coherente en 360, 768 y 1024 px sin desbordamiento horizontal (FR-009)

### Implementation for User Story 1

- [X] T015 [P] [US1] Crear `resources/js/menus.js`: mejora ARIA de menús y colapsables (`aria-expanded` sincronizado, `Escape` cierra y devuelve el foco al disparador, clic fuera cierra, un solo menú abierto a la vez); importar desde `resources/js/app.js` (D-050)
- [X] T016 [US1] Rediseñar la navegación de `resources/views/layouts/app.blade.php` con menú desplegable «Gestión» (`<details>`/`<summary>` → Libros, Categorías, Préstamos) y menú hamburguesa `<details>` visible por debajo de `md` (768 px), navegación horizontal por encima; enlaces primer nivel con `aria-current="page"` **y** refuerzo no cromático (D-050, contracts/ui-structure.md §3)
- [ ] T017 [P] [US1] Aplicar el patrón de secciones colapsables `<details>` a los bloques del home (`resources/views/welcome.blade.php`) y del panel (`resources/views/panel/index.blade.php`)
- [X] T018 [US1] Ajustar `resources/css/app.css` a los breakpoints de aceptación: 360×640 una columna con hamburguesa, 768×1024 navegación horizontal legible, ≥1024 diseño de referencia; sin recortes ni scroll horizontal (contracts/ui-structure.md §3)
- [ ] T019 [US1] Ampliar `tests/Feature/AccesibilidadUiTest.php` (008): indicador de foco visible siempre, no oculto bajo cabecera (2.4.7/2.4.11) y objetivos táctiles ≥ 44 px (FR-004)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Encontrar las acciones principales en cada pantalla (Priority: P1) 🎯 MVP

**Goal**: Home con accesos reales al sistema (público) y acción principal visible en cada listado, incluido el registro de préstamos hoy inalcanzable.

**Independent Test**: Abrir home, catálogo, libros, categorías, préstamos, panel, login y errores (401/404/conexión); identificar y ejecutar la acción principal de cada uno; registro de préstamos alcanzable en ≤ 2 pasos desde el home.

### Tests for User Story 2

- [X] T020 [P] [US2] Crear `tests/Feature/AccionPrincipalTest.php`: ≥ 1 botón primario visible en `/catalogo`, `/libros`, `/categorias`, `/prestamos` (con datos y vacíos), home con accesos reales y recorrido home → registrar préstamo en ≤ 2 pasos (FR-011/FR-012, SC-004, SC-009)
- [ ] T021 [P] [US2] Crear `tests/Feature/EstadosCargaTest.php`: estado «Cargando…» con `role="status"` anunciado en la región de resultados del catálogo (FR-010, D-051)
- [ ] T022 [P] [US2] Crear `tests/Feature/ToastExitoTest.php`: mensaje de éxito con `role="status"`, resultado visible en la página tras la operación y errores no auto-ocultos (FR-022, D-052)

### Implementation for User Story 2

- [X] T023 [P] [US2] Crear `resources/views/partials/accion-principal.blade.php`: botón primario siempre visible en la cabecera de la zona de contenido y junto al estado vacío (D-056)
- [X] T024 [US2] Reescribir `resources/views/welcome.blade.php` como home público con accesos reales (Catálogo, Libros, Categorías, Préstamos, Panel), estadísticas (`$totalLibros`, `$totalCategorias`, `$totalPrestamos`) como indicadores con etiqueta textual, acción primaria «Registrar préstamo» (con sesión) / «Iniciar sesión» (sin sesión) y sin aviso de conexión; sin sesión cada acceso conduce a `GET /login` sin ejecutar operación (D-055, contracts/screens.md §1)
- [X] T025 [P] [US2] Añadir acción principal «Registrar préstamo» + bloque de búsqueda heredado a `resources/views/catalogo/index.blade.php` y renderizar `partials/carga.blade.php` en la región de resultados (D-051/D-056)
- [X] T026 [P] [US2] Añadir acción principal «Añadir libro» a `resources/views/libros/index.blade.php` y «Nueva categoría» a `resources/views/categorias/index.blade.php` (D-056)
- [ ] T027 [US2] Añadir acción principal «Registrar préstamo» a `resources/views/prestamos/index.blade.php` (hoy inalcanzable; abre `GET /prestamos/crear`) y «Ir al catálogo» a `resources/views/panel/index.blade.php` (depende de T023)
- [X] T028 [P] [US2] Añadir acción clara a `resources/views/auth/login.blade.php` («Iniciar sesión») y a las vistas de error `resources/views/errors/401.blade.php`, `404.blade.php` y de conexión («Iniciar sesión» / «Volver al catálogo» / «Reintentar»), con mensajes en español de contracts/screens.md §8
- [ ] T029 [US2] Integrar la acción principal en `resources/views/partials/estado-vacio.blade.php` conservando los textos heredados («No hay libros registrados aún», «No hay préstamos registrados aún», etc.) — D-060, contracts/user-messages.md §1
- [X] T030 [US2] Integrar `partials/toast.blade.php` en `resources/views/layouts/app.blade.php` y asegurar que los `session('success')` existentes lo alimentan sin cambiar sus textos (D-052, D-060)

**Checkpoint**: At this point, User Story 1 AND 2 should both work independently — MVP

---

## Phase 5: User Story 3 - Cumplir los criterios AAA aplicables y proteger la sesión (Priority: P2)

**Goal**: Contraste ≥ 7:1 en todo el texto, información sin color como único canal, y aviso con 5 minutos de antelación + extensión de sesión que conserva los datos.

**Independent Test**: Auditar contraste real en cada pantalla, verificar estados en escala de grises, recorrer tareas con teclado y simular inactividad con caducidad acortada: aviso, extensión sin pérdida de datos y caducidad controlada.

### Tests for User Story 3

- [X] T031 [P] [US3] Crear `tests/Feature/ContrasteAaaTest.php`: verifica que los pares de color declarados en `@theme` (`resources/css/app.css`) alcanzan ≥ 7:1 (FR-002, D-049)
- [X] T032 [US3] Crear `tests/Feature/SesionAvisoTest.php`: aviso visible con 5 minutos restantes, `POST /sesion/extender` autenticado con `@csrf` conserva los datos de la sesión y la caducidad sin respuesta se comporta como en `008` (FR-005, contracts/session.md §5)

### Implementation for User Story 3

- [X] T033 [US3] Comunicar toda la información por canal no cromático: estados de préstamo «activo/devuelto/vencido» como **texto + icono + color** en `resources/views/prestamos/index.blade.php`, disponibilidad en `resources/views/libros/index.blade.php` y `resources/views/catalogo/index.blade.php`, errores y éxito con texto (FR-003, D-057)
- [X] T034 [P] [US3] Crear `app/Http/Controllers/SesionController.php` con método `extender` (renueva la sesión conservando sus datos, responde con «Sesión extendida.») y registrar `POST /sesion/extender` dentro del grupo `auth` en `routes/web.php` (D-053, G4: `@csrf` obligatorio)
- [X] T035 [US3] Crear `resources/js/sesion.js`: lee `data-sesion-expira`, muestra diálogo `role="alertdialog"` `aria-modal="true"` con «Quedan {n} minutos antes de que finalice su sesión.», botones «Extender sesión» (`POST /sesion/extender`) y «Cerrar sesión»; `Escape` no lo descarta; importar desde `resources/js/app.js` (D-053, contracts/session.md)
- [X] T036 [US3] Renderizar `data-sesion-expira="{{ now()->addMinutes(config('session.lifetime'))->timestamp }}"` en `<body>` de `resources/views/layouts/app.blade.php` solo con sesión iniciada (D-053)
- [X] T037 [US3] Revisar cada pantalla con la calculadora de contraste y ajustar tokens de `resources/css/app.css` hasta ≥ 7:1 en texto normal, estados, badges y botones (FR-002, D-049/D-057)

**Checkpoint**: At this point, User Story 3 should be fully functional and testable independently

---

## Phase 6: User Story 4 - Subir una portada sin romper la página (Priority: P2)

**Goal**: Pre-validación en cliente de formato/tamaño (JPG·JPEG·PNG·WebP ≤ 2 MB) con rechazo elegante y validación del servidor intacta como fuente de verdad.

**Independent Test**: Subir en el formulario de libro archivos de > 2 MB, formato no permitido, corruptos y válidos, con y sin JavaScript; en todos los casos el formulario sigue utilizándose.

### Tests for User Story 4

- [X] T038 [P] [US4] Crear `tests/Feature/PortadaPrevalidacionTest.php`: rechazo en servidor con mensaje en español junto al campo y `old()` conservado; archivo válido se almacena; el formulario nunca queda inoperable (FR-017/FR-018, contracts/upload-portada.md §6)

### Implementation for User Story 4

- [X] T039 [P] [US4] Crear `resources/js/portada.js`: valida al elegir el archivo `file.size <= 2 * 1024 * 1024` y tipo/extensión ∈ {JPG, JPEG, PNG, WebP} y archivo ≠ 0 bytes; al rechazar limpia el `input`, muestra «El archivo debe ser una imagen JPG, PNG o WebP de hasta 2 MB.» con `role="alert"` y `aria-invalid="true"` y bloquea el envío; al aceptar muestra nombre, tamaño y vista previa; importar desde `resources/js/app.js` (D-054, contracts/upload-portada.md §2)
- [X] T040 [US4] Cablear la pre-validación en los formularios `resources/views/libros/create.blade.php` y `resources/views/libros/edit.blade.php`: contenedor de error junto al campo de portada, `aria-describedby` y estado de envío (depende de T039)
- [X] T041 [US4] Verificar el rechazo del servidor en `app/Http/Controllers/LibroController.php`/Form Requests de `005`/`003` (sin cambios de regla): error en español junto al campo con `old()` y el resto del formulario operable — sin `{!! !!}` (FR-018, G2, contracts/upload-portada.md §3)
- [X] T042 [US4] Confirmar la imagen predeterminada `images/libro-sin-portada.svg` con `alt` descriptivo en `resources/views/partials/portada.blade.php` ante ausencia, borrado o fallo de carga (FR-019)

**Checkpoint**: At this point, User Story 4 should be fully functional and testable independently

---

## Phase 7: User Story 5 - Contar con un sistema de diseño coherente y documentado (Priority: P3)

**Goal**: Misma tipografía, espaciado, color, radios, sombras, botones, campos y estados en todas las pantallas, con el sistema declarado en el README.

**Independent Test**: Comparar listado de libros y formulario de préstamo: comparten tokens y componentes; revisar el README por la sección «Sistema de diseño» con Tailwind 4 + Vite.

### Tests for User Story 5

- [ ] T043 [P] [US5] Ampliar `tests/Feature/AccesibilidadUiTest.php`: dos pantallas distintas comparten clases de token (tipografía, espaciado, botón, campo) y alto contraste del SO conserva bordes y estados (FR-015, US5)

### Implementation for User Story 5

- [ ] T044 [US5] Pasada de coherencia sobre `resources/views/catalogo/`, `resources/views/libros/`, `resources/views/categorias/`, `resources/views/prestamos/`, `resources/views/panel/`, `resources/views/auth/` y `resources/views/errors/`: sustituir estilos sueltos por componentes y tokens de `resources/css/app.css` sin cambiar rutas, controladores ni textos heredados (D-049, D-060)
- [X] T045 [US5] Añadir la sección **«Sistema de diseño»** a `README.md`: herramienta (Tailwind 4 + Vite, `npm run build`/`npm run dev`), tabla de tokens (color con contraste verificado, tipografía, espaciado, radios, sombras), catálogo de componentes y estados, patrones de navegación accesible y mención WCAG 2.1 AAA aplicable con enlace a la matriz (FR-016, D-059)
- [X] T046 [US5] Verificar la legibilidad con alto contraste del sistema operativo y `prefers-reduced-motion` en `resources/css/app.css`: contenido legible, bordes conservados y sin animaciones esenciales (contracts/ui-structure.md §4)

**Checkpoint**: At this point, User Story 5 should be fully functional and testable independently

---

## Phase 8: User Story 6 - Declarar qué contratos de interfaz reemplaza esta especificación (Priority: P3)

**Goal**: Declaración explícita de que 009 reemplaza los contratos de interfaz de 002–008 sin tocar reglas de negocio, datos ni seguridad.

**Independent Test**: Revisar la sección «Contratos de interfaz reemplazados» de la spec: lista 002–008 con su alcance; confirmar que reglas como el límite de tres préstamos activos no se modifican.

### Implementation for User Story 6

- [X] T047 [P] [US6] Verificar y completar la sección «Contratos de interfaz reemplazados» de `specs/009-wcag-aaa-ui-redesign/spec.md`: 002, 003, 004, 005, 006, 007 y 008 con el alcance de interfaz que asumen y qué permanece vigente en cada caso (FR-020)
- [X] T048 [US6] Confirmar en el código que ninguna regla de negocio cambió: `app/Services/PrestamoService.php`, Form Requests y migraciones intactos; `git status database/migrations` vacío (FR-021, G1/G3)
- [X] T049 [US6] Confirmar que los textos heredados de 002–008 siguen literales según `specs/009-wcag-aaa-ui-redesign/contracts/user-messages.md` (D-060)

**Checkpoint**: All user stories should now be independently functional

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Validación integral, auditoría AAA, documentación final y limpieza

- [ ] T050 Ejecutar la validación completa de `specs/009-wcag-aaa-ui-redesign/quickstart.md` (V-001…V-014) y registrar resultados
- [ ] T051 [P] Auditoría AAA (FR-023, D-058): herramienta automatizada (axe o equivalente) sobre las 8 pantallas × 3 tamaños + revisión manual (teclado, foco, sin JS, aviso/extensión de sesión, estados sin color, zoom 200 %, `prefers-reduced-motion`); tabular el cumplimiento en `README.md`
- [ ] T052 [P] Capturar las 8 pantallas en 360 × 640, 768 × 1024 y ≥ 1024 px y añadirlas a `README.md` junto a la sección del sistema de diseño (FR-016)
- [X] T053 Ejecutar `php artisan test` completo: las 60 suites existentes + las nuevas siguen en verde (regresión de 002–008, D-062)
- [X] T054 Ejecutar `vendor/bin/pint --dirty --format agent` sobre PHP tocado (T034, T038, T041)
- [ ] T055 Ejecutar `npm run build` final y `git status` sin artefactos pendientes; commits Conventional (`feat(ui):`, `docs(readme):`, `test(a11y):`) en `feature/interfaz-seguridad` (D-062, G9)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - US1 (P1) → US2 (P1) → US3 (P2) → US4 (P2) → US5 (P3) → US6 (P3) en orden de prioridad
  - US3/US4 requieren pantallas de US2 para su prueba independiente; US5/US6 son transversales y pueden ejecutarse en paralelo con US3/US4
- **Polish (Final Phase)**: Depends on all user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - Independiente de US1 (usa el layout base)
- **User Story 3 (P2)**: Depende de US2 (pantallas donde auditar contraste y mostrar estados) y del layout de Foundational
- **User Story 4 (P2)**: Depende de US2 (formularios de libro rediseñados) — el JS de portada es independiente
- **User Story 5 (P3)**: Depende de US1–US4 para auditar coherencia sobre las pantallas ya rediseñadas
- **User Story 6 (P3)**: Independiente (documentación/gobernanza); sin dependencias de código

### Within Each User Story

- Tests (si se incluyen) antes o en paralelo a la implementación
- Parciales/JS antes de las vistas que los consumen
- Vistas independientes en paralelo; integración al final
- Historia completa antes de pasar a la siguiente prioridad

### Parallel Opportunities

- T002 + T003 + T004 (archivos distintos en Setup)
- T008 + T009 + T010 + T011 (parciales y CSS independientes)
- T012 + T013 + T014 (suites de US1)
- T015 + T017 (JS de menús y bloques colapsables)
- T020 + T021 + T022 (suites de US2) y T023 + T025 + T026 + T028 (vistas distintas)
- T031 + T032 (suites de US3) y T034 (controlador) en paralelo con T033/T037
- T038 (suite) en paralelo con T039 (JS de portada)
- T047 + T049 (documentación de US6) y T051 + T052 (Polish)

---

## Parallel Example: User Story 2

```bash
# Lanzar juntas las suites de US2 (archivos distintos):
Task: "Crear tests/Feature/AccionPrincipalTest.php"
Task: "Crear tests/Feature/EstadosCargaTest.php"
Task: "Crear tests/Feature/ToastExitoTest.php"

# Lanzar juntas las vistas independientes:
Task: "Añadir acción principal a resources/views/catalogo/index.blade.php"
Task: "Añadir acción principal a resources/views/libros/index.blade.php"
Task: "Añadir acción principal a resources/views/categorias/index.blade.php"
Task: "Añadir acción clara a resources/views/errors/*.blade.php"

# Integración final:
Task: "Integrar acción en resources/views/partials/estado-vacio.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 + User Story 2, ambas P1)

1. Complete Phase 1: Setup (cadena Vite/Tailwind)
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (navegación accesible)
4. Complete Phase 4: User Story 2 (acciones principales + home)
5. **STOP and VALIDATE**: `php artisan test --filter=NavegacionTecladoTest --filter=NavegacionSinJsTest --filter=AccionPrincipalTest` y recorrido manual V-001…V-005
6. Demo: navegación completa por teclado en los 3 tamaños y registro de préstamos alcanzable

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 → test independiente → demo (navegación con/sin JS)
3. US2 → test independiente → demo (MVP completo: home + acciones)
4. US3 → test independiente → demo (AAA contraste + sesión)
5. US4 → test independiente → demo (portadas seguras)
6. US5 + US6 → test independiente → demo (sistema de diseño documentado + gobernanza)
7. Polish → quickstart V-001…V-014 + auditoría AAA → entrega final

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (navegación)
   - Developer B: User Story 2 (acciones y home)
   - Developer C: User Story 6 (documentación de gobernanza, sin dependencias)
3. Después: US3 + US4 en paralelo; US5 al cierre

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable
- **Sin cambios de esquema**: ninguna tarea crea migraciones ni entidades (FR-021, G1)
- Reglas de negocio, validación en servidor, CSRF, `{{ }}` y DELETE con confirmación intactos (G2–G4)
- Textos heredados de 002–008 se conservan literales (D-060, contracts/user-messages.md)
- Commit tras cada tarea o grupo lógico con Conventional Commits en `feature/interfaz-seguridad`
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
