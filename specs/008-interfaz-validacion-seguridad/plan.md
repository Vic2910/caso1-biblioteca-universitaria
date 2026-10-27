# Implementation Plan: Interfaz, validación y seguridad transversales (008-interfaz-validacion-seguridad)

**Branch**: `feature/interfaz-seguridad` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-interfaz-validacion-seguridad/spec.md`

## Summary

Consolidar la interfaz, la validación y la seguridad transversales de toda la aplicación (Fase 2): todas las vistas comparten el layout Blade común con CSS propio y, desde esta funcionalidad, **exigen inicio de sesión** —`Auth::attempt` sobre la tabla `users`, creada con migración y con cuentas sembradas por el equipo—, caducando la sesión a las **2 horas de inactividad** y mostrando ante un fallo un **mensaje genérico en español**, sin bloqueos ni esperas. Todo formulario se valida en el servidor (Form Requests + `validated()`) y, tras un rechazo, presenta los errores **en español junto a cada campo** conservando los valores (`old()`); todo formulario incorpora **protección CSRF** —con el mensaje de fallo confirmado en la clarificación—, toda la salida se **escapa con `{{ }}`**, las eliminaciones se **confirman y ejecutan únicamente con `DELETE`** (nunca por GET) y las consultas siguen sin concatenar datos de usuario, con las credenciales fuera del repositorio. Se completan además el **diseño adaptable** (teléfono 360 × 640 px, tableta 768 × 1024 px, escritorio ≥ 1024 px), la accesibilidad **WCAG 2.1 AA** (etiquetas, teclado, contraste 4,5:1) y los **estados vacíos** «No hay registros» con su acción disponible. Sin entidades de negocio nuevas ni cambios en las reglas de `003`–`007`: esta funcionalidad verifica, homogeneiza y protege lo que otras funcionalidades presentan.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS, heredado D-011), guard `auth` y hashing estándar de Laravel (D-037), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `008` añade como máximo la migración `users` (D-037); sin cambios en `libros`, `categorias` ni `prestamos`; driver de sesión por defecto del esqueleto con `SESSION_LIFETIME=120` (D-038)

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; ~9 suites transversales (D-047)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`); con autenticación global desde `008`: todas las rutas salvo `/login` exigen sesión (D-037/D-038)

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; comprobaciones por petición: CSRF y sesión; sin esperas añadidas ni bloqueos ante fallos de login (D-039)

**Constraints**: Sin servicios de pago; mensajes en español junto al campo conservando lo ingresado (D-041); credenciales fuera del repositorio (heredado D-007); CSRF obligatorio en todo formulario sin rutas exentas (D-040); salida escapada con `{{ }}` (D-042); eliminaciones solo por `DELETE` con confirmación (D-043); WCAG 2.1 AA completo (D-045)

**Scale/Scope**: 3 rutas nuevas (2 de login, 1 de cierre), 1 controlador, 1 Form Request, 1 modelo, 1 migración (`users`), 1 seeder, partials nuevos (`estado-vacio`) + ajustes del layout, reglas CSS propias con breakpoints, ~9 suites de test; sin cambios en las reglas de negocio de `003`–`007`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con `$fillable`, casts y relaciones | **PASS** | Único esquema nuevo: tabla `users` creada **por migración** (D-037, [data-model.md](data-model.md) §Cambios de esquema), exigida por FR-009; sin tocar `libros`, `categorias` ni `prestamos`; `User` sin roles ni permisos (Key Entities de la spec) |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS** | Inventario transversal de todos los formularios con Form Request + `validated()` y patrón `back()->withErrors()->withInput()` (D-041); login validado en el servidor (`LoginRequest`); `lang/es/validation.php` heredado de `003` D-024 |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (sin cambios)** | `008` no implementa ni duplica reglas de disponibilidad, fechas o límite: las centralizadas en `PrestamoService` (`004` D-019) se conservan intactas; la funcionalidad solo homogeneiza presentación y validación (D-036) |
| G4 | **IV. Seguridad y operaciones explícitas** (`@csrf`, `{{ }}`, Eloquent sin concatenar, `DELETE` con confirmación) | **PASS** | CSRF activo sin rutas en `$except` y fallo con mensaje en español junto al formulario (D-040); escapado `{{ }}` auditado por test (D-042); eliminaciones solo por `DELETE` con confirmación y GET → 405 (D-043); inicio de sesión obligatorio con `Auth::attempt` (D-037) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Layout común consolidado y auditado (D-036, heredado D-015); CSS propio con breakpoints 640/768/1024 (D-044); WCAG 2.1 AA completo (D-045); estados vacíos con acción (D-046) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; `users` se crea (si el esqueleto no la incluye) como migración versionada (D-037); sin frameworks CSS ni dependencias nuevas |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida, credenciales fuera del repositorio | **PASS** | D-037/D-041/D-042; contraseñas guardadas con hash (`Hash`, nunca en claro) y credenciales de BD solo en `.env` (heredado D-007); SQL solo parametrizado |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, imagen predeterminada | **PASS (sin cambios)** | `008` no gestiona portadas (`005`); solo exige que ruta y `alt` se rendericen escapados (D-042) |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/interfaz-seguridad` (spec); el README declara que **no** se usan frameworks CSS (o los declara si se usaran), documenta el inicio de sesión, la seguridad transversal y añade capturas de los tres tamaños |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/008-interfaz-validacion-seguridad/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-036…D-047, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — users, estados de sesión, inventario de formularios, reglas transversales
├── quickstart.md        # Phase 1 output — escenarios V-001…V-012
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-005/007/009/010: login/logout, middleware auth, fallo CSRF, GET → 405
│   ├── ui-structure.md        # FR-001/002/004/011/012: layout, campo con error, estados vacíos, breakpoints, a11y
│   └── user-messages.md       # FR-004/010/012 + clarificaciones: textos en español exactos
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── AuthController.php        # NEW: showLogin, login (D-037/D-039), logout (D-037)
├── Http/Requests/
│   └── LoginRequest.php          # NEW: email y contraseña validados en el servidor (D-041)
└── Models/
    └── User.php                  # NEW: cuentas de usuario sin roles ni permisos (D-037)

database/
├── migrations/
│   └── *_create_users_table.php  # NEW si el esqueleto no la incluye: users sin roles (D-037)
└── seeders/
    └── UserSeeder.php            # NEW: cuentas creadas por el equipo, contraseña con hash (D-037)

lang/es/
├── validation.php                # (003 D-024) mensajes y atributos en español
└── auth.php                      # NEW: mensaje genérico de fallo de login (D-039)

bootstrap/app.php                 # + render en español del fallo CSRF junto al formulario (D-040)

.env.example / config/session.php # SESSION_LIFETIME=120 → 2 h de inactividad (D-038)

routes/web.php                    # + GET /login, POST /login, POST /logout; grupo `auth` sobre todas las rutas de negocio (D-037)

resources/views/
├── layouts/app.blade.php         # (001/002) + bloque de sesión («Cerrar sesión») y mensajes junto al formulario (D-036/D-040)
├── auth/login.blade.php          # NEW: formulario de inicio de sesión (D-037/D-041)
└── partials/
    └── estado-vacio.blade.php     # NEW: «No hay registros» + acción disponible (D-046)

resources/views/{catalogo,libros,categorias,prestamos,panel}/   # (002/003/004/007) + ajustes transversales:
                                                                # etiquetas/aria, errores junto al campo,
                                                                # estados vacíos y bloque de sesión (D-041/D-045/D-046)

public/css/                       # CSS propio: breakpoints 640/768/1024, foco visible, contraste ≥ 4,5:1 (D-044/D-045)

tests/Feature/
├── AuthAccesoTest.php            # sin sesión → /login y la operación no se ejecuta (D-037)
├── AuthLoginTest.php             # fallo genérico repetido, sin bloqueo ni espera; cierre de sesión (D-039)
├── SesionCaducadaTest.php        # 2 h de inactividad → pedir login de nuevo antes de procesar (D-038)
├── CsrfProteccionTest.php        # envío sin token → mensaje exacto, sin escritura (D-040)
├── EscapadoSalidaTest.php        # auditoría de vistas: `{{ }}` obligatorio, sin `{!! !!}` con datos (D-042)
├── EliminacionDeleteTest.php     # GET → 405 sin borrar; DELETE exige confirmación en la vista (D-043)
├── ValidacionTransversalTest.php # errores en español junto al campo + valores conservados (D-041)
├── AccesibilidadUiTest.php       # etiquetas asociadas, aria, estructura común, operable con teclado (D-045)
└── EstadosVaciosTest.php         # «No hay registros» + acción en libros, categorías y préstamos (D-046)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. `008` es **transversal**: no crea vistas de contenido propias salvo el login, sino que audita y homogeneiza las vistas y formularios de `002`–`007` —layout común, etiquetas y errores junto al campo, estados vacíos, CSS con breakpoints— y añade la capa de autenticación (modelo `User`, controlador, Form Request, migración y seeder) que protege todas las rutas con el middleware `auth`. No hay frontend/backend separados.

## Dependencias entre funcionalidades

| Feature | Qué aporta a `008` |
|---|---|
| `001-entorno-datos` | Layout base, CSS propio, migraciones, seeders y estrategia de pruebas (D-015, D-008) |
| `002-consulta-catalogo` | Layout con navegación, vista de error 404 y parcial de portada; estado vacío del catálogo |
| `003-gestion-libros` | Formularios de libros y categorías con Form Requests y `lang/es/validation.php` (D-024): `008` homogeneiza etiquetas, errores junto al campo y estados vacíos |
| `004-gestion-prestamos` | Formularios de registro y devolución de préstamos; `008` no toca `PrestamoService` ni sus reglas |
| `005-gestion-portadas` | Formulario de portada; `008` exige su validación en servidor y escapado de la ruta mostrada |
| `006-busqueda-catalogo` | Controles de búsqueda sin botón (JS nativo); `008` conserva ese patrón y exige etiquetas/contraste en ellos |
| `007-panel-biblioteca` | Vista de solo consulta; queda bajo el grupo `auth` junto con el resto (sin roles diferenciados) |

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; el único cambio de esquema (tabla `users`, D-037) usa migración, como exige el Principio I |
| Clarificaciones 2026-10-26 | Satisfechas | Fallo de login genérico sin bloqueo ni espera (D-039, [contracts/user-messages.md](contracts/user-messages.md) §2); sesión a las 2 h de inactividad (D-038, [data-model.md](data-model.md) §Sesión); WCAG 2.1 AA completo (D-045, [contracts/ui-structure.md](contracts/ui-structure.md) §5); «No hay registros» con acción (D-046, [contracts/ui-structure.md](contracts/ui-structure.md) §4); mensaje CSRF y tamaños 360 × 640 / 768 × 1024 / ≥ 1024 confirmados tal cual (D-040, D-044) |
| Casos límite | Cubiertos | Token CSRF ausente/caducado/manipulado, sesión caducada, login repetido, varios campos inválidos, caracteres especiales del español, texto con etiquetas u órdenes SQL, GET de eliminación repetido y listas vacías se especifican en [data-model.md](data-model.md) §Casos límite y se validan en [quickstart.md](quickstart.md) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
