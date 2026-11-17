# Implementation Plan: Rediseño de interfaz a estándares mundiales (WCAG 2.1 AAA aplicable) (009-wcag-aaa-ui-redesign)

**Branch**: `feature/interfaz-seguridad` (rama activa; rama `feature/` por convención constitucional) | **Date**: 2026-11-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/009-wcag-aaa-ui-redesign/spec.md`

## Summary

Rediseñar las ocho pantallas del sistema —home, catálogo, libros, categorías, préstamos, panel, login y errores (401, 404, conexión)— para pasar de una plantilla genérica a un producto profesional, cumpliendo **WCAG 2.1 nivel AAA en los criterios aplicables** según la matriz documentada en la spec (contraste **7:1**, operación completa por teclado, foco visible, información no dependiente del color, aviso con 5 minutos de antelación y extensión de la sesión), aplicando los **siete principios universales del diseño** y una navegación con menús desplegables y secciones colapsables con patrones ARIA, operable por teclado, **degradable sin JavaScript** y con menú hamburguesa en móvil, manteniendo los tres breakpoints verificados (360 × 640, 768 × 1024, ≥ 1024 px). El sistema de diseño (tokens, tipografía, espaciado, color, componentes y estados) se implementa **cableando Tailwind 4 + Vite, ya instalados pero sin conectar**, se declara en el README, y cada listado gana una acción principal visible —incluido el registro de préstamos, hoy inalcanzable— con un home de accesos reales en lugar del aviso de conexión. La subida de portadas gana pre-validación de formato y tamaño en el cliente con manejo elegante del rechazo del servidor. **Sin cambios de esquema**: los contratos de interfaz de las specs 002–008 quedan reemplazados por 009; sus reglas de negocio, datos y seguridad permanecen vigentes.

## Technical Context

**Language/Version**: PHP 8.4 (composer `^8.3`), JavaScript ES2020 nativo sin framework

**Primary Dependencies**: Laravel 13.38 + Blade, Tailwind CSS 4.3.5 y Vite 8.3.8 con `@tailwindcss/vite` y `laravel-vite-plugin` (instalados, sin cablear — D-048), fuente Instrument Bunny Fonts ya configurada en `vite.config.js`, PHPUnit 12.5 y Pint 1.32

**Storage**: MySQL 8.x local; **sin ningún cambio de esquema** (FR-021: no se tocan `libros`, `categorias`, `prestamos` ni `users`); portadas en Laravel Storage con ruta en BD (reglas de `005` intactas)

**Testing**: PHPUnit con `RefreshDatabase` (`php artisan test`, suites nuevas por archivo), más auditoría de accesibilidad (herramienta automatizada + revisión manual, FR-023) documentada en `quickstart.md`

**Target Platform**: Aplicación web local (`php artisan serve` + `npm run dev`/`npm run build`), navegadores modernos **con y sin JavaScript**, en 360 × 640 px, 768 × 1024 px y ≥ 1024 px

**Project Type**: Web application (monolito Laravel/Blade, único repositorio, sin frontend/backend separados)

**Performance Goals**: Sin requisitos de tráfico; el aviso de sesión se calcula en el cliente a partir de la caducidad renderizada, sin peticiones de sondeo; sin esperas añadidas

**Constraints**: WCAG 2.1 AAA aplicable según la matriz de la spec (contraste 7:1, teclado, foco, sin color como único canal, aviso/extensión de sesión); Tailwind 4 + Vite declarados en el README (constitución → Presentación); mensajes en español; CSRF en todo formulario nuevo; sin dependencias nuevas de pago; sin cambios de esquema

**Scale/Scope**: 8 pantallas rediseñadas, 1 layout, ~6 parciales nuevos/reacondicionados, 4 módulos JS (menús, sesión, portada, toast), 1 ruta nueva (`POST /sesion/extender`), 1 sección nueva en el README, ~10 suites nuevas; ninguna entidad ni regla de negocio nuevas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas; modelos con `$fillable`, casts y relaciones | **PASS** | **Cero migraciones y cero cambios de entidades** (FR-021); [data-model.md](data-model.md) §Cambios de esquema = tabla vacía; solo cambia la presentación de Libro, Categoría y Préstamo |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS** | La pre-validación de portada en cliente (D-054) es **aditiva**: el servidor sigue siendo la única fuente de verdad (`PortadaRequest`/`LibroRequest` de `005`/`003` intactos) y el rechazo del servidor conserva `old()` junto al campo |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (sin cambios)** | `009` no toca `PrestamoService` ni sus reglas; solo añade el **acceso visible** al formulario de registro (FR-011) |
| G4 | **IV. Seguridad y operaciones explícitas** (`@csrf`, `{{ }}`, Eloquent sin concatenar, `DELETE` con confirmación) | **PASS** | La única ruta nueva (`POST /sesion/extender`) usa `@csrf` y grupo `auth`; el toast y el aviso de sesión renderizan datos con `{{ }}`; el JS de confirmación de eliminaciones se conserva; `{!! !!}` sigue prohibido (`EscapadoSalidaTest`) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, layout Blade común, CSS propio) | **PASS** | Layout común conservado y ampliado (D-048/D-050); Tailwind 4 cableado y **declarado en el README** (la constitución lo declara opcional y exigible de declarar); contraste 7:1 y AAA aplicable (D-056/D-058) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Sin dependencias nuevas de runtime; Tailwind/Vite ya estaban en `package.json` (devDependencies) y solo se cablean; ninguna tabla nueva |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado, credenciales fuera del repositorio | **PASS** | Sin SQL nuevo; sin credenciales nuevas; endpoint de extensión de sesión autenticado con CSRF |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, JPG/PNG/WebP ≤ 2 MB, imagen predeterminada | **PASS** | D-054 replica **exactamente** esos límites en el cliente; el almacenamiento, reemplazo y borrado de `005` no cambian; se conserva la imagen predeterminada (FR-019) |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/interfaz-seguridad`; commits `feat`/`docs`/`fix`; el README gana la sección del sistema de diseño (FR-016) y las capturas de los tres tamaños |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/009-wcag-aaa-ui-redesign/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-048…D-062, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — sin cambios de esquema; estados de sesión, portada, listado y toast; inventarios
├── quickstart.md        # Phase 1 output — escenarios V-001…V-014 de validación (incluye auditoría AAA)
├── contracts/           # Phase 1 output
│   ├── ui-structure.md       # FR-001…FR-006, FR-015, FR-023: tokens, layout, patrones ARIA, foco, breakpoints, carga
│   ├── screens.md            # FR-010…FR-014: estructura de las 8 pantallas con su acción principal
│   ├── session.md            # FR-005: aviso a los 5 min, extensión, caducidad y degradación sin JS
│   ├── upload-portada.md     # FR-017…FR-019: pre-validación cliente y rechazo del servidor
│   └── user-messages.md      # textos exactos en español (conserva los acordados en 002–008)
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
resources/
├── css/
│   └── app.css                  # AMPLIAR: @import 'tailwindcss' + @theme (tokens D-049) y estilos de componentes
├── js/
│   ├── app.js                   # AMPLIAR: punto de entrada Vite (confirmación de borrado migrada desde public/js/app.js)
│   ├── menus.js                 # NEW: mejora ARIA de menús/colapsables/hamburguesa (Escape, foco, aria-expanded)
│   ├── sesion.js                # NEW: aviso a los 5 min y extensión de sesión (D-053)
│   ├── portada.js               # NEW: pre-validación de formato/tamaño en el formulario de libro (D-054)
│   └── toast.js                 # NEW: auto-ocultado del mensaje de éxito (D-052)
└── views/
    ├── layouts/app.blade.php    # AMPLIAR: @vite (D-048), navegación con <details> + hamburguesa (D-050),
    │                             #   atributos de caducidad de sesión (D-053), contenedor del toast
    ├── welcome.blade.php        # REEMPLAZAR: home con accesos reales + estadísticas (D-055)
    ├── partials/
    │   ├── estado-vacio.blade.php   # CONSERVAR (008 D-046) + acción principal
    │   ├── portada.blade.php        # CONSERVAR (002/005)
    │   ├── toast.blade.php          # NEW: mensaje de éxito role="status" auto-ocultable (D-052)
    │   ├── carga.blade.php          # NEW: estado «Cargando…» role="status" (D-051)
    │   └── accion-principal.blade.php # NEW: botón primario visible de cada listado (D-056)
    └── {catalogo,libros,categorias,prestamos,panel,auth,errors}/  # AJUSTES: acción principal, jerarquía,
                                  #   estados, ARIA y contraste 7:1; sin cambiar rutas ni controladores

public/css/app.css               # RETIRAR del layout tras migrar a resources/css/app.css (D-048)
public/js/app.js                 # RETIRAR del layout: se migra al bundle de Vite (D-048)

app/Http/Controllers/SesionController.php   # NEW: extender sesión (POST /sesion/extender, @csrf, auth) (D-053)
routes/web.php                   # + POST /sesion/extender dentro del grupo auth (D-053)

README.md                        # + sección «Sistema de diseño»: Tailwind 4 + Vite, tokens, tipografía,
                                  #   espaciado, color, componentes, estados y capturas (FR-016, D-059)

tests/Feature/
├── ContrasteAaaTest.php         # contraste ≥ 7:1 de la paleta declarada (FR-002)
├── NavegacionTecladoTest.php    # menús/colapsables operables con teclado y estados ARIA (FR-007)
├── NavegacionSinJsTest.php      # secciones alcanzables sin JS (FR-007) + hamburguesa presente (FR-008)
├── AccionPrincipalTest.php      # ≥1 acción visible en cada listado + home con accesos (FR-011/FR-012)
├── EstadosCargaTest.php        # estado «Cargando…» anunciado (FR-010)
├── ToastExitoTest.php           # confirmación anunciada y resultado visible (FR-022)
├── SesionAvisoTest.php          # aviso a los 5 min, extensión conservando datos, caducidad (FR-005)
├── PortadaPrevalidacionTest.php # rechazo cliente + rechazo servidor sin romper el formulario (FR-017/FR-018)
├── BreakpointsUiTest.php        # sin recortes ni desbordamiento en 360/768/1024 (FR-009)
└── AccesibilidadUiTest.php      # AMPLIAR (008): foco visible, sin color como único canal, foco no obscurecido
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar), heredado de `001-entorno-datos`. `009` es **transversal de presentación**: no crea contenido nuevo ni toca controladores de negocio —salvo el pequeño `SesionController@extender`— y re-especifica las vistas de `002`–`008`. La novedad estructural es el **cableado de la cadena de build front-end** (`resources/css/app.css` + `resources/js/app.js` vía Vite/Tailwind) sustituyendo los ficheros estáticos de `public/`, manteniendo la degradación sin JavaScript. No hay frontend/backend separados.

## Dependencias entre funcionalidades

| Feature | Qué conserva/aporta `009` |
|---|---|
| `001-entorno-datos` | Layout base, migraciones y seeders; `009` no toca el esquema |
| `002-consulta-catalogo` | Listado paginado (10/página), orden y 404; `009` cambia su **presentación** y añade acción principal |
| `003-gestion-libros` | CRUD, Form Requests y `lang/es/validation.php`; `009` rediseña formularios y añade pre-validación de portada |
| `004-gestion-prestamos` | `PrestamoService` y sus reglas intactos; `009` hace **visible** el registro y la devolución |
| `005-gestion-portadas` | Almacenamiento, reemplazo, borrado e imagen predeterminada intactos; `009` añade la capa de cliente |
| `006-busqueda-catalogo` | Búsqueda con debounce y estados; `009` conserva el patrón y añade el estado de carga anunciado |
| `007-panel-biblioteca` | Métricas de solo consulta; `009` reespecifica su estructura y jerarquía |
| `008-interfaz-validacion-seguridad` | Validación, CSRF, escapado, DELETE, sesión de 2 h y estados vacíos **intactos**; `009` eleva AA → AAA aplicable y reemplaza sus contratos de interfaz (FR-020) |

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 no introducen migraciones, entidades ni reglas nuevas: [data-model.md](data-model.md) §Cambios de esquema queda vacío y el único controlador nuevo (`SesionController@extender`) no toca datos, solo renueva la sesión |
| Clarificaciones 2026-11-17 | Satisfechas | Home público con accesos que piden login ([contracts/screens.md](contracts/screens.md) §1); estado de carga con texto anunciado (D-051, [contracts/ui-structure.md](contracts/ui-structure.md) §6); toast de éxito auto-oculto con resultado visible (D-052, [contracts/user-messages.md](contracts/user-messages.md) §4); solo 401/ 404/ conexión (D-055, [contracts/screens.md](contracts/screens.md) §7); auditoría automatizada + manual (D-058, [quickstart.md](quickstart.md) V-012) |
| Casos límite | Cubiertos | Archivo fuera de límites (cliente y servidor), sesión caducada con formulario abierto, sin JS, sin color, listas vacías, títulos largos y foco bajo cabecera en [data-model.md](data-model.md) §Casos límite |
| Contratos reemplazados | Declarados | [spec.md](spec.md) §Contratos de interfaz reemplazados (specs 002–008) + FR-020; los contratos vigentes de 009 están en `contracts/` |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
