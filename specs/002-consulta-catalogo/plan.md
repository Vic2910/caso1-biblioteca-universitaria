# Implementation Plan: Consulta del catálogo y detalle de libros (002-consulta-catalogo)

**Branch**: `002-consulta-catalogo` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-consulta-catalogo/spec.md`

## Summary

Implementar la consulta del catálogo (Fase 1): un listado paginado en `GET /catalogo` con 10 libros por página ordenados de A a la Z por título **ignorando tildes y mayúsculas** (colación `utf8mb4_0900_ai_ci` fijada por migración, desempate por autor e ISBN), tarjetas completas y clicable con portada o imagen predeterminada, estado vacío «No hay libros registrados aún», y la ficha de detalle `GET /catalogo/{id}` con todos los campos de `Libro` y su categoría; libro inexistente o página fuera de rango responden **HTTP 404** con vista personalizada en español y enlace de vuelta al catálogo; ambas vistas —y la de error— comparten el layout común (cabecera, navegación, pie) creado en `001-entorno-datos`.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS: la paginación usa vista propia, D-011), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `002` añade una única migración de colación (D-012); portadas en Storage público cuando existan (D-014)

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; 5 suites de característica (D-016)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), sin autenticación en esta fase

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de filas; una consulta paginada + eager loading `with('categoria')` por petición (D-017)

**Constraints**: Sin servicios de pago; mensajes en español y 404 controlados sin exponer detalles técnicos (D-013); credenciales fuera del repositorio (heredado D-007); orden determinista FR-005 verificable en el esquema (D-012); búsqueda/filtro fuera de alcance (`006`)

**Scale/Scope**: 2 rutas, 1 controlador (2 acciones), 5 vistas nuevas/ajustadas, 1 imagen predeterminada, 1 migración, ~5 suites de test; alcance solo lectura — sin CRUD, sin formularios, sin reglas de préstamo

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con relaciones | **PASS** | Único cambio: migración de colación `utf8mb4_0900_ai_ci` en `titulo`/`autor` (D-012, [data-model.md](data-model.md) §Cambios de esquema); sin entidades nuevas; relaciones Eloquent heredadas de `001` |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS (fase sin formularios)** | No hay escrituras; la única entrada (`page`) se valida en el servidor y ante inválida devuelve 404 en español (D-013); reglas de formularios siguen en fase posterior |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (solo lectura)** | El detalle muestra `ejemplares_totales/disponibles` como datos de solo lectura; sin descuentos ni lógica de préstamos (fuera de alcance: `004`) |
| G4 | **IV. Seguridad y operaciones explícitas** (Eloquent sin SQL concatenado, escapado `{{ }}`, sin DELETE por GET) | **PASS** | Consultas Eloquent con eager loading (D-017); `page` validada como entero, sin SQL con entradas del usuario; sin formularios ni eliminaciones; mensajes sin detalles técnicos (D-013) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Rutas sobre la app local; layout común reutilizado de `001` (D-015); paginación y tarjetas con CSS propio, sin frameworks (D-011) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; la colación se aplica con `Schema::table(...)->change()` en migración |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida | **PASS** | D-013, D-017; textos renderizados con `{{ }}` (escapado) incluido el mensaje de `$exception` |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, imagen predeterminada cuando no exista portada | **PASS** | Parcial único de portada con imagen predeterminada del proyecto y respaldo `onerror` (D-014, FR-003); uploads son de `005` |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/consulta-catalogo` (spec); el README documenta la navegación del catálogo y la captura de listado/detalle |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/002-consulta-catalogo/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-010…D-017, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — proyección de lectura, orden, paginación, migración de colación
├── quickstart.md        # Phase 1 output — escenarios V-001…V-009
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/002/005: rutas, parámetros, 200/404
│   ├── ui-structure.md        # FR-001/002/004: layout, tarjeta, ficha, paginación, portada
│   └── user-messages.md       # FR-001/003: textos en español (vacío, 404, alt)
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── CatalogoController.php   # index (paginado, D-010/D-011) y show (detalle, D-010)
└── Models/
    └── Libro.php                # (creado en 001) solo se usan sus relaciones; sin cambios

database/
└── migrations/
    └── *_alter_libros_orden_collation.php  # utf8mb4_0900_ai_ci en titulo/autor (D-012)

routes/web.php                   # GET /catalogo, GET /catalogo/{id} (D-010)

resources/views/
├── layouts/app.blade.php        # (001) + enlace «Catálogo» en la navegación (D-015)
├── errors/404.blade.php         # 404 en español con enlace «Volver al catálogo» (D-013)
├── pagination/catalogo.blade.php# anterior/siguiente/números con CSS propio (D-011)
├── partials/portada.blade.php   # <img> con imagen predeterminada + onerror (D-014)
├── catalogo/
│   ├── index.blade.php          # listado: tarjetas + estado vacío + paginación
│   └── show.blade.php           # ficha: todos los campos + categoría + vuelta
└── (welcome.blade.php de 001 sin cambios)

public/images/
└── libro-sin-portada.svg        # imagen predeterminada única del proyecto (D-014)

tests/Feature/
├── CatalogoIndexTest.php            # listado, tarjeta, estado vacío (V-001, V-006)
├── CatalogoDetalleTest.php          # campos completos, portada, 404 (V-003, V-004, V-005)
├── CatalogoOrdenYPaginacionTest.php # 10/página, orden y desempates (V-001, V-002)
├── CatalogoErroresTest.php          # página fuera de rango → 404 (V-007)
└── CatalogoLayoutTest.php           # layout común listado/detalle/404 (V-008)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. La consulta reutiliza el layout, modelos y datos sembrados de `001` y añade un controlador con dos acciones, cinco piezas de vista y una migración de ajuste; no hay frontend/backend separados.

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; la única decisión de esquema (D-012) usa migración, como exige el Principio I |
| Clarificaciones 2026-10-26 | Satisfechas | Campos de tarjeta y ficha (ui-structure §2/§3); desempate autor→ISBN con orden total determinista (data-model §ordenación); 404 con vista y enlace (http-endpoints §404); orden en español sin tildes (D-012); tarjeta entera clicable (ui-structure §2) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
