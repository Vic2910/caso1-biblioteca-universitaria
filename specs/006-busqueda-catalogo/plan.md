# Implementation Plan: Búsqueda o filtro del catálogo (006-busqueda-catalogo)

**Branch**: `006-busqueda-catalogo` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-busqueda-catalogo/spec.md`

## Summary

Implementar la búsqueda o filtro del catálogo (Fase 2) sobre el listado principal `GET /catalogo` de `002-consulta-catalogo`: un **único campo de texto** comparado por coincidencia que contiene contra **título, autor, editorial e ISBN** y un **desplegable de categoría** con coincidencia exacta, combinados con **regla Y** y aplicados **automáticamente mientras el usuario escribe** (debounce de 300 ms, sin botón de envío visible) o al cambiar la categoría. Los criterios viajan como query string (`q`, `categoria`) en la ruta existente —sin rutas nuevas—, se normalizan en el servidor (texto recortado; solo espacios = sin criterio; comodines `LIKE` escapados; `categoria` inválida se ignora) y la sensibilidad a mayúsculas y acentos se garantiza en la base de datos con una **migración propia** que fija la colación `utf8mb4_0900_ai_ci` sobre `editorial` (`titulo`/`autor` ya la fijó `002`). Sin coincidencias se muestra «No se encontraron libros que coincidan con la búsqueda» con los criterios aún visibles; el enlace «Limpiar» restaura el catálogo completo; la paginación de 10 por página de Fase 1 se aplica sobre el conjunto filtrado conservando los criterios en los enlaces. Sin entidades nuevas, sin escrituras y sin cambios en el layout común.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS), JavaScript nativo sin build ni dependencias (D-032), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `006` añade una única migración de colación sobre `editorial` (D-030); sin nuevas entidades ni columnas

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; 5 suites de característica (D-035)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), sin autenticación en esta fase

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de filas; una consulta paginada + eager loading `with('categoria')` por petición (D-017); el debounce agrupa las pulsaciones y evita una consulta por tecla (edge case «escritura rápida»)

**Constraints**: Sin servicios de pago; mensajes en español con el texto exacto de FR-004 (D-033); credenciales fuera del repositorio (heredado D-007); salida escapada con `{{ }}` incluido el valor de `q` rebotado (Principio IV); solo lectura — sin escrituras ni operaciones destructivas

**Scale/Scope**: 0 rutas nuevas (1 ruta existente ampliada con 2 parámetros), 1 controlador modificado (`index`), 1 vista ajustada (`catalogo/index`) + 1 script JS + reglas CSS propias, 1 migración de colación, ~5 suites de test; alcance solo del listado principal — sin CRUD (`003`), sin préstamos (`004`), sin portadas (`005`), sin panel (`007`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con relaciones | **PASS** | Único cambio: migración de colación `utf8mb4_0900_ai_ci` en `libros.editorial` (D-030, [data-model.md](data-model.md) §Cambios de esquema); sin entidades ni columnas nuevas; relaciones Eloquent heredadas de `001` |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS (fase sin formularios de escritura)** | No hay escrituras: las únicas entradas (`q`, `categoria`, `page`) se normalizan/validan en el servidor (trim, entero ≥ 1, existencia de categoría; D-029/D-031); las reglas de formularios siguen en fases con CRUD (`003`) |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (solo lectura)** | El listado filtrado solo muestra datos de solo lectura de `Libro`; sin descuentos ni lógica de préstamos (fuera de alcance: `004`) |
| G4 | **IV. Seguridad y operaciones explícitas** (Eloquent sin SQL concatenado, escapado `{{ }}`, sin DELETE por GET) | **PASS** | Filtros con `where`/`orWhere` y patrón `LIKE` **ligado** tras escapar `%`/`_`/`\` (D-029); `q` rebotado al campo con `{{ }}`; sin métodos destructivos; mensajes sin detalles técnicos (D-033) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Rutas sobre la app local; la vista reutiliza `layouts/app.blade.php` (D-015); controles y estados con CSS propio; JS nativo sin build ni frameworks (D-032) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; la colación se aplica con `Schema::table(...)->change()` en migración propia de `006` (D-030) |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida | **PASS** | D-029/D-031; sin `whereRaw` con entradas del usuario; textos de datos y el valor de `q` renderizados con `{{ }}` |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, imagen predeterminada | **PASS (sin cambios)** | Las tarjetas siguen usando el parcial `partials/portada` de `002` (D-014); la búsqueda no toca portadas (`005`) |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/busqueda-catalogo` (spec); el README documenta el uso de la búsqueda/filtro y sus capturas |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/006-busqueda-catalogo/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-028…D-035, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — campos de búsqueda, colación, semántica de filtros, paginación filtrada
├── quickstart.md        # Phase 1 output — escenarios V-001…V-010
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/002/003/005: parámetros q/categoria/page, 200/404
│   ├── ui-structure.md        # FR-001/004/006: controles, debounce, estados vacíos, limpieza
│   └── user-messages.md       # FR-004: textos en español (mensaje exacto, etiquetas, 404)
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── CatalogoController.php   # index ampliado: normaliza q/categoria y filtra (D-028…D-031)
└── Models/
    └── Libro.php                # (creado en 001) sin cambios; se leen sus columnas y relación categoria()

database/
└── migrations/
    └── *_alter_libros_editorial_search_collation.php  # utf8mb4_0900_ai_ci en editorial (D-030)

routes/web.php                   # sin rutas nuevas: se reutiliza GET /catalogo con q y categoria (D-028)

resources/views/
├── layouts/app.blade.php        # (001/002) sin cambios de estructura
├── partials/portada.blade.php   # (002) sin cambios
└── catalogo/
    └── index.blade.php          # + controles de búsqueda/categoría, estados vacíos diferenciados, «Limpiar» (D-032/D-033)

public/
├── js/busqueda-catalogo.js      # debounce 300 ms, envío del formulario, foco tras recarga (D-032)
└── images/libro-sin-portada.svg # (002) sin cambios

CSS propio del proyecto (ruta definida en 001)   # nuevas reglas: barra de búsqueda, desplegable, estados

tests/Feature/
├── BusquedaCatalogoTest.php         # coincidencia parcial en título/autor/editorial/ISBN, mayúsculas/acentos, ceros iniciales (V-001, V-002)
├── BusquedaCombinacionTest.php      # regla Y, categoría exacta, criterio vacío/solo espacios (V-003, V-005)
├── BusquedaVacioYLimpiezaTest.php   # mensaje exacto, criterios visibles, «Limpiar», dos estados vacíos (V-004, V-006, V-009)
├── BusquedaPaginacionTest.php       # paginación sobre filtrados, conservación de params, 404 fuera de rango (V-008)
└── BusquedaInterfazTest.php         # formulario sin botón visible, script de debounce, colación de editorial (V-007, V-010)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. `006` no añade rutas, controladores ni entidades: amplía la acción `index` de `CatalogoController` (creada en `002`), ajusta su vista, añade un script JS nativo sin paso de build y una migración de colación; reutiliza layout, parcial de portada, paginación propia y datos de prueba de `001`/`002`. No hay frontend/backend separados.

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; la única decisión de esquema (D-030) usa migración, como exige el Principio I |
| Clarificaciones 2026-10-26 | Satisfechas | Filtrado automático con debounce sin botón (D-032, [contracts/ui-structure.md](contracts/ui-structure.md) §2); único campo contra título/autor/editorial/ISBN con Y lógico (D-029, [contracts/http-endpoints.md](contracts/http-endpoints.md) §Combinación); mensaje exacto con criterios visibles (D-033, [contracts/user-messages.md](contracts/user-messages.md) §1) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
