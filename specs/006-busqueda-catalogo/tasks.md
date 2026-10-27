# Tasks: Búsqueda o filtro del catálogo (006-busqueda-catalogo)

**Input**: Design documents from `/specs/006-busqueda-catalogo/`
**Prerequisites**: plan.md, spec.md, research.md (D-028…D-035), data-model.md, contracts/ (http-endpoints.md, ui-structure.md, user-messages.md), quickstart.md

**Tests**: Solicitados explícitamente en el plan (D-035: 5 suites de característica). Las suites se escriben ANTES de la implementación y deben fallar antes de ella.

**Organization**: Tareas agrupadas por user story (una sola story, US1, P1) para implementación y validación independientes. Monolito Laravel 13/Blade existente (heredado de `001`/`002`): no hay rutas nuevas ni entidades; se amplía `GET /catalogo`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: User story a la que pertenece (US1)

## Path Conventions

Rutas del monolito Laravel en la raíz del repositorio (según plan.md de `006`): `app/`, `database/`, `routes/`, `resources/views/`, `public/`, `tests/`. El CSS propio del proyecto es la hoja definida en `001` (ruta del proyecto, p. ej. `public/css/app.css` — usar la existente en la rama base, no crear una nueva).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirmar la base heredada de `001`/`002` sobre la que se construye `006`

- [ ] T001 Checkout/verificar la rama base con el código de `001-entorno-datos` y `002-consulta-catalogo` (CatalogoController, vista catalogo/index, paginación, portada, layout, seed de ≥ 15 libros y ≥ 3 categorías); crear la rama `feature/busqueda-catalogo` desde ella en la raíz del repositorio
- [ ] T002 Ejecutar `php artisan migrate --seed` y `php artisan test` en la raíz del repositorio para confirmar el estado verde de las suites de `001` y `002` antes de empezar (base: database/seeders/, tests/Feature/)

**Checkpoint**: Entorno de `001`/`002` funcionando y en verde; rama de feature creada.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema garantizado para la coincidencia insensible a acentos en `editorial` — BLOQUEA la búsqueda de US1

**⚠️ CRITICAL**: Ninguna tarea de US1 puede completarse (coincidencia con acentos sobre `editorial`) hasta que esta fase termine

- [ ] T003 Crear migración `database/migrations/YYYY_MM_DD_HHMMSS_alter_libros_editorial_search_collation.php` (posterior a la migración de colación de `002` sobre `titulo`/`autor`) que fija la colación `utf8mb4_0900_ai_ci` sobre `libros.editorial` mediante `Schema::table('libros', fn (Blueprint $t) => $t->string('editorial')->collation('utf8mb4_0900_ai_ci')->change())` (D-030, data-model.md §Cambios de esquema; sin variante MariaDB salvo que la BD lo sea: `utf8mb4_uca1400_ai_ci` o `utf8mb4_unicode_520_ci`)
- [ ] T004 Ejecutar `php artisan migrate` y verificar con `SHOW FULL COLUMNS FROM libros WHERE Field = 'editorial'` que `Collation = utf8mb4_0900_ai_ci` (quickstart.md §Puesta en marcha)

**Checkpoint**: Esquema listo — la coincidencia sobre los cuatro campos es insensible a mayúsculas y acentos; puede comenzar US1.

---

## Phase 3: User Story 1 - Localizar libros mediante búsqueda o filtro (Priority: P1) 🎯 MVP

**Goal**: Un único campo de texto (contra título, autor, editorial e ISBN) + desplegable de categoría exacta, combinados con regla Y, aplicados automáticamente (debounce 300 ms / cambio de categoría), con estado vacío diferenciado, «Limpiar» y paginación de 10 sobre el conjunto filtrado.

**Independent Test**: Con los libros de prueba de Fase 1 (≥ 15), aplicar texto/categoría en `GET /catalogo?q=&categoria=` y comprobar que el listado muestra solo registros coincidentes; sin coincidencias aparece el mensaje exacto «No se encontraron libros que coincidan con la búsqueda» con criterios visibles; «Limpiar» restaura el catálogo completo; los escenarios V-001…V-009 de quickstart.md pasan manualmente.

### Tests for User Story 1 (Escribir PRIMERO — deben FALLAR antes de implementar) ⚠️

> **NOTE**: PHPUnit + `RefreshDatabase` sobre la BD de prueba MySQL; usar factories/seed de `001` (D-008/D-035). Ejecutar con `php artisan test`.

- [ ] T005 [P] [US1] Crear tests/Feature/BusquedaCatalogoTest.php: coincidencia parcial en título, autor, editorial e ISBN (V-001); texto presente en varios campos; insensibilidad a mayúsculas/acentos (`algebra` ↔ «Álgebra lineal»); ISBN parcial con ceros iniciales conservados (V-002); ningún libro ajeno al criterio en la respuesta (SC-001/SC-002/SC-003)
- [ ] T006 [P] [US1] Crear tests/Feature/BusquedaCombinacionTest.php: texto + categoría con regla Y (solo cumplen ambos, V-003); filtro de categoría exacta; `categoria` inválida o inexistente → se ignora y catálogo completo (D-031); `q` vacío y `q` de solo espacios en blanco → catálogo completo sin residuos (V-005; FR-003/FR-005, SC-005)
- [ ] T007 [P] [US1] Crear tests/Feature/BusquedaVacioYLimpiezaTest.php: con criterio y total = 0 → HTTP 200, mensaje exacto «No se encontraron libros que coincidan con la búsqueda», sin tarjetas, sin paginación, `q` rebotado en el campo y categoría marcada (V-004); sin criterio y total = 0 → «No hay libros registrados aún» (V-009); enlace «Limpiar» presente solo con criterio y visible en el HTML (V-006; FR-004/FR-006, SC-002/SC-004)
- [ ] T008 [P] [US1] Crear tests/Feature/BusquedaPaginacionTest.php: `paginate(10)` sobre el conjunto filtrado (10 tarjetas por página con > 10 resultados); enlaces de paginación conservan `q` y `categoria` (`withQueryString()`); 404 «Página no válida» con `page` fuera de rango o no numérica bajo filtro (evaluado sobre el total filtrado); total = 0 con criterio → página 1 válida y `page ≥ 2` → 404 (V-008; FR-005, D-034)
- [ ] T009 [P] [US1] Crear tests/Feature/BusquedaInterfazTest.php: formulario `GET` hacia `catalogo.index` presente; campo `q` con etiqueta «Buscar», placeholder «Buscar por título, autor, editorial o ISBN» y valor rebotado escapado; desplegable con opción «Todas las categorías» y una opción por categoría de la BD ordenada alfabéticamente, valor = id, activa marcada; botón de envío existe pero SIN clase/atributo visible (oculto); enlace «Limpiar» a `catalogo.index` sin parámetros; script `busqueda-catalogo.js` referenciado en el HTML; colación de `editorial` = `utf8mb4_0900_ai_ci` vía `DB::select("SHOW FULL COLUMNS FROM libros WHERE Field = 'editorial'")` solo en el test (V-007/V-010; D-035)

**Checkpoint (tests)**: Las 5 suites compilan y FALLAN (funcionalidad aún no implementada).

### Implementation for User Story 1

- [ ] T010 [US1] Ampliar `index` de app/Http/Controllers/CatalogoController.php: normalizar `q` con `trim()` (vacío tras trim = sin criterio) y escapar comodines `%`, `_`, `\` con `addcslashes()` envolviendo el patrón como `%'texto'%`; aplicar grupo OR anidado `where(function ($query) { … where('titulo','like',$p) … orWhere('autor',…) … orWhere('editorial',…) … orWhere('isbn',…); })` sobre los cuatro campos (D-029; bindings parametrizados, sin `whereRaw` ni concatenación — Principio IV)
- [ ] T011 [US1] En el mismo `index` de app/Http/Controllers/CatalogoController.php: validar `categoria` (`^\d+$`, entero ≥ 1 y `Categoria::whereKey($id)->exists()`); válida → `where('categoria_id', $id)` como condición Y sucesiva del grupo OR; inválida/inexistente → se ignora (D-031); mantener `with('categoria')`, `paginate(10)`, `withQueryString()` y la comprobación de `page` fuera de rango → 404 «Página no válida» evaluada sobre el total **filtrado** (D-013/D-034); sin ningún criterio la consulta queda exactamente la de `002`
- [ ] T012 [US1] Pasar a la vista resources/views/catalogo/index.blade.php desde el controlador: `$q` (texto normalizado para rebotar), `$categoria` (id válido aplicado o null), `$categorias` (`Categoria::orderBy('nombre')->get()`) y los indicadores de criterio activo y total usados por la vista para estados vacíos/enlace «Limpiar»
- [ ] T013 [P] [US1] Añadir reglas CSS propias (en la hoja CSS existente del proyecto definida en `001`, p. ej. public/css/app.css — sin frameworks CSS) para la barra de búsqueda: campo, desplegable, enlace «Limpiar» y botón de envío oculto (D-032, ui-structure.md §1)
- [ ] T014 [US1] Añadir en resources/views/catalogo/index.blade.php la barra de controles bajo el título «Catálogo de libros» y sobre el listado: formulario `GET` hacia `route('catalogo.index')` con `id="busqueda"`, etiqueta «Buscar» + campo `q` con placeholder y `value="{{ $q }}"` escapado, etiqueta «Categoría» + desplegable `categoria` (opción «Todas las categorías» valor vacío + opciones id/nombre ordenadas por nombre, activa marcada desde servidor), botón de envío visualmente oculto (sin `hidden` para conservar semántica accesible; accesible vía CSS) y enlace «Limpiar» a `route('catalogo.index')` visible **solo** con al menos un criterio activo (FR-001/FR-006, D-032/D-033, user-messages.md §3)
- [ ] T015 [US1] Implementar en resources/views/catalogo/index.blade.php la prioridad de estados vacíos (data-model.md §Prioridad): criterio activo y total = 0 → mensaje exacto «No se encontraron libros que coincidan con la búsqueda» sin tarjetas ni paginación y con criterios visibles; sin criterio y total = 0 → «No hay libros registrados aún» (sin cambios); criterio con total ≥ 1 → tarjetas + paginación filtrada sin mensaje; los controles de paginación (vista `pagination/catalogo.blade.php` de `002`) se aplican al conjunto filtrado y conservan `q`/`categoria` (FR-004/FR-005, D-033/D-034)
- [ ] T016 [P] [US1] Crear public/js/busqueda-catalogo.js (JavaScript nativo, sin dependencias ni build, D-032): evento `input` sobre `q` con debounce de cola de 300 ms (cada pulsación reinicia el temporizador; al expirar se envía el formulario del catálogo — una sola petición en escritura rápida y el último texto determina los resultados); evento `change` sobre el desplegable `categoria` → envío inmediato; el formulario no envía `page` (reinicio a página 1, D-034); tras el render, si `q` tiene valor, devolver el foco al campo con el cursor al final
- [ ] T017 [US1] Referenciar el script en resources/views/catalogo/index.blade.php (p. ej. `<script src="{{ asset('js/busqueda-catalogo.js') }}" defer></script>`) sin tocar resources/views/layouts/app.blade.php; verificar que sin JavaScript «Enter» envía el formulario (botón oculto) y el desplegable funciona nativamente (funcionalidad degradada, D-032)
- [ ] T018 [US1] Ejecutar `php artisan test` y dejar en verde las 5 suites de `006` junto con las de `001`/`002`; corregir implementación o tests según fallos (V-010)

**Checkpoint**: US1 completa y funcional de forma independiente — búsqueda/filtro operativo en `/catalogo` con estados, limpieza y paginación filtrada.

---

## Phase 4: Polish & Cross-Cutting Concerns

**Purpose**: Documentación y validación final transversales

- [ ] T019 [P] Documentar la búsqueda/filtro en el README de la rama (uso de `q`/`categoria`, filtrado automático con debounce, enlace «Limpiar», estados vacíos) y actualizar quickstart.md si la implementación difiere de algún escenario (plan.md §Constitution Check G9 — README como documentación oficial)
- [ ] T020 Ejecutar la validación manual completa de quickstart.md (escenarios V-001 a V-009 en `http://127.0.0.1:8000/catalogo`, incluida la comprobación de red del debounce en V-007) y registrar el resultado
- [ ] T021 [P] Limpieza final: sin SQL crudo con entradas del usuario, sin métodos destructivos, valor de `q` siempre escapado con `{{ }}`, mensajes exactos en español sin detalles técnicos (Principio IV; user-messages.md §4); commitear con Conventional Commits por tarea o grupo lógico

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sin dependencias — arranca inmediatamente
- **Foundational (Phase 2)**: Depende de Setup — BLOQUEA la búsqueda de US1 (la coincidencia sin acentos sobre `editorial` exige la colación)
- **User Story 1 (Phase 3)**: Depende de Foundational — tests (T005–T009) primero, luego implementación (T010–T018)
- **Polish (Phase 4)**: Depende de US1 completa

### User Story Dependencies

- **User Story 1 (P1)**: Comienza tras Foundational (Phase 2); única story — sin dependencias entre historias

### Within Each User Story

- Tests (T005–T009) escritos y FALLANDO antes de la implementación
- Controlador (T010–T012) antes de la vista (T014–T015)
- Vista de controles/estados (T014–T015) antes de verificar integración JS (T016–T017)
- T018 (suite en verde) al final de la story

### Parallel Opportunities

- T005, T006, T007, T008, T009 en paralelo (5 archivos de test distintos)
- T013 (CSS) en paralelo con T010–T012 (controlador) — archivos distintos
- T016 (JS) en paralelo con T013/T014 — archivo distinto (public/js/busqueda-catalogo.js)
- T019 en paralelo con T020 (README vs. validación manual), T021 al final

---

## Parallel Example: User Story 1

```bash
# Lanzar las 5 suites de test juntas (distintos archivos, sin dependencias):
Task: "tests/Feature/BusquedaCatalogoTest.php"
Task: "tests/Feature/BusquedaCombinacionTest.php"
Task: "tests/Feature/BusquedaVacioYLimpiezaTest.php"
Task: "tests/Feature/BusquedaPaginacionTest.php"
Task: "tests/Feature/BusquedaInterfazTest.php"

# Tras Fallar los tests, en paralelo (archivos distintos):
Task: "Controlador CatalogoController@index (T010–T012)"
Task: "CSS de la barra de búsqueda (T013)"
Task: "public/js/busqueda-catalogo.js (T016)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only — es toda la feature)

1. Completar Phase 1: Setup (baseline verde de `001`/`002`)
2. Completar Phase 2: Foundational (migración de colación — CRÍTICA)
3. Completar Phase 3: US1 (tests → controlador → vista → JS)
4. **PARAR y VALIDAR**: `php artisan test` en verde + escenarios V-001…V-009 manuales
5. Deploy/demo si está listo

### Incremental Delivery

1. Setup + Foundational → esquema garantizado
2. US1 completa → validar independientemente → demo (MVP, fin de la feature)
3. Polish → README + validación quickstart + limpieza

### Notes

- [P] = archivos distintos, sin dependencias
- Sin escrituras: toda la funcionalidad es de solo lectura sobre `GET /catalogo`
- Sin rutas nuevas, sin entidades nuevas, sin cambios en `layouts/app.blade.php` ni en `partials/portada.blade.php`
- Commits con Conventional Commits; parar en el checkpoint de US1 para validar de forma independiente
