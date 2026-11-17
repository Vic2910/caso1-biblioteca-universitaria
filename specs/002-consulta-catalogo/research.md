# Research: 002-consulta-catalogo (Consulta del catálogo y detalle de libros)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa la de `001-entorno-datos`** (D-001…D-009); aquí se añaden D-010…D-017. Las decisiones de stack heredadas (D-001…D-009) se reutilizan sin cambios.

---

## D-010: Rutas y controlador de consulta

- **Decision**: Dos rutas GET sin autenticación en `routes/web.php`:
  - `GET /catalogo` → `CatalogoController@index`, nombre de ruta `catalogo.index` (listado paginado).
  - `GET /catalogo/{id}` → `CatalogoController@show`, nombre de ruta `catalogo.show` (ficha de detalle), con búsqueda explícita del libro (ver D-013).
  - La navegación del layout común (D-015) incluye el enlace «Catálogo»; la ruta `/` sigue siendo la página de bienvenida que prueba la conexión definida en `001`.
- **Rationale**: Nombres de ruta alineados con el nombre de la funcionalidad (`catalogo.*`), separados de las rutas CRUD de gestión de libros que corresponden a `003-gestion-libros`. La especificación no exige inicio de sesión (supuesto 2026-10-26), por lo que no hay middleware de autenticación en esta fase.
- **Alternatives considered**: `/libros` como ruta del listado (rechazado: ese espacio lo ocupará el CRUD de gestión de libros en la Fase 2); convertir `/` en el catálogo (rechazado: `001` ya define `/` como página de comprobación de conexión y no se pide cambiarla); rutas API JSON + SPA (rechazado: la constitución exige Blade con layout común, Fase 1 es SSR).

## D-011: Paginación de 10 libros por página

- **Decision**: `Libro::…->paginate(10)` con `->withQueryString()` y vista de paginación **propia** `resources/views/pagination/catalogo.blade.php` invocada con `->links('pagination.catalogo')`, estilada con el CSS propio del proyecto: «Anterior», «Siguiente» y números de página (ventana con primera/última cuando el total es grande). Si `total() === 0` se muestra el mensaje vacío y **no** se renderizan controles de paginación.
- **Rationale**: FR-005 fija 10 libros por página; el supuesto de la especificación pide controles estándar con números de página. Las vistas por defecto de Laravel están maquetadas con clases de Tailwind (o exigen `Paginator::useBootstrapFive()`), incompatibles con el CSS propio que exige la constitución (Principio V). `withQueryString()` conserva parámetros de consulta futuros (la búsqueda/filtro de `006-busqueda-catalogo` llegará como query string).
- **Alternatives considered**: `simplePaginate()` (rechazado: no muestra números de página, exigidos por el supuesto); vista por defecto de Laravel sin Tailwind/Bootstrap (rechazada: clases sin CSS resultan sin diseño); `Paginator::useBootstrapFive()` (rechazado: añade Bootstrap solo para la paginación, la constitución lo hace opcional y declarable); paginar en PHP tras cargar todo el catálogo (rechazado: innecesario y rompe la comprobación de página fuera de rango en el servidor).

## D-012: Orden alfabético en español (tildes y mayúsculas) — colación en la base de datos

- **Decision**: Una **migración propia de 002** (`database/migrations/*_alter_libros_orden_collation.php`, posterior a la creación de `libros` de `001`) fija la colación **`utf8mb4_0900_ai_ci`** sobre las columnas `libros.titulo` y `libros.autor` mediante `Schema::table(...)->change()`. El listado ordena con Eloquent plano: `orderBy('titulo')->orderBy('autor')->orderBy('isbn')` (ASC).
  - `utf8mb4_0900_ai_ci` es la colación por defecto de MySQL 8, basada en UCA 9.0.0, **accent-insensitive y case-insensitive** (los propios nombres `_ai_` y `_ci_` lo declaran): «Álgebra» compara igual que «Algebra» y ordena en su posición alfabética.
  - `isbn` es `char(13)` solo dígitos: el desempate final es estable y determinista; al ser único garantiza un orden total (FR-005).
  - Nota de portabilidad: si el equipo usa la variante **MariaDB** admitida en el README de `001`, la migración debe emplear `utf8mb4_uca1400_ai_ci` (MariaDB 10.11+) o `utf8mb4_unicode_520_ci` (compatible con ambos motores). La base elegida por `001` (D-002) es MySQL 8, donde se aplica `utf8mb4_0900_ai_ci`.
- **Rationale**: FR-005 y la clarificación de sesión exigen orden alfabético en español que ignore tildes y mayúsculas. Fijar la colación **a nivel de columna** hace que la regla viva en el esquema (Principio I: la base de datos es la fuente de verdad), sea verificable con `SHOW FULL COLUMNS`, y que `ORDER BY` siga siendo Eloquent puro. El default del servidor/BD no es fiable: una base creada con otra colación (p. ej. `utf8mb4_general_ci` o `latin1`) rompería el orden sin avisar.
- **Alternatives considered**: Depender del default `utf8mb4_0900_ai_ci` de MySQL 8 sin fijarlo (rechazado: no verificable y depende de cómo se creó la base); `orderByRaw('titulo COLLATE utf8mb4_0900_ai_ci')` (rechazado: introduce SQL crudo cuando la regla es de esquema y arrastra el mismo acoplamiento al motor); ordenar en PHP con `iconv`/`Str::ascii()` (rechazado: incompatible con la paginación por offset y con el límite de 10 por página); `utf8mb4_unicode_ci`/`utf8mb4_general_ci` (rechazados: su insensibilidad de acentos no está garantizada por el nombre ni de forma uniforme entre motores, a diferencia de `_ai_ci`).

## D-013: HTTP 404 para libro inexistente y página fuera de rango

- **Decision**: Un único mecanismo de error 404 con vista propia:
  - **Detalle**: `Libro::with('categoria')->find($id)`; si es `null` → `abort(404, 'Libro no encontrado')`.
  - **Página**: se valida el parámetro crudo `page` (si está presente y no vacío, debe cumplir `^\d+$` y ser ≥ 1; en caso contrario → `abort(404, 'Página no válida')`); después de ejecutar `paginate(10)`, si `currentPage() > lastPage()` → `abort(404, 'Página no válida')`. Con catálogo vacío, la página 1 es válida (muestra el estado vacío) y cualquier otra página es 404.
  - **Vista**: `resources/views/errors/404.blade.php`, que Laravel aplica automáticamente a todo 404; extiende el layout común, muestra `$exception->getMessage()` escapado (con texto por defecto si estuviera vacío) y un enlace «Volver al catálogo» hacia `route('catalogo.index')`.
- **Rationale**: Laravel **no** devuelve 404 cuando `page` supera el total: devuelve un conjunto vacío (comportamiento confirmado en laravel/framework#15857), por lo que la comprobación debe ser explícita en el controlador. `abort(404, mensaje)` + la vista de errores con la variable `$exception` es el mecanismo oficial para páginas de error personalizadas. El binding implícito de rutas produce `ModelNotFoundException` con un mensaje técnico en inglés («No query results for model…»), por lo que se usa `find()` explícito para controlar el texto en español que exige la especificación.
- **Alternatives considered**: `findOrFail()` (rechazado: mensaje por defecto técnico, no el texto en español requerido); dejar la página vacía sin error (rechazado: viola los criterios de aceptación 5 de la Historia 1 y 3 de la Historia 2); redirigir a la última página válida (rechazado: la especificación exige 404, no redirección); código 403/410 (rechazado: no es «no encontrado»).

## D-014: Imagen predeterminada y portadas no disponibles

- **Decision**: Un único archivo incluido por el proyecto: `public/images/libro-sin-portada.svg`. Un parcial Blade `resources/views/partials/portada.blade.php` emite el `<img>` de portada:
  - `portada = NULL` → `src` = `asset('images/libro-sin-portada.svg')`.
  - `portada` con valor → `src` = `Storage::disk('public')->url($portada)` **más** atributo `onerror` que, ante un archivo ausente/corrupto, sustituye la fuente por la imagen predeterminada (sin lógica de servidor por fila).
  - `alt` en español (contrato en [contracts/user-messages.md](contracts/user-messages.md)).
- **Rationale**: FR-003 y el edge case «portada no disponible» exigen la misma imagen predeterminada en listado y detalle; la constitución pide «una única imagen común incluida por el proyecto». SVG: sin dependencias ni licencias, escalable y de peso mínimo. `onerror` cubre el caso de un archivo registrado que ya no existe sin añadir una comprobación `Storage::exists()` por fila.
- **Alternatives considered**: PNG/JPG (viables, pero añaden peso sin aportar; se reservan para las fotos reales de `005`); `Storage::exists()` antes de renderizar (rechazado como única vía: una E/S por fila y no cubre el intervalo entre render y carga); imagen base64 embebida (rechazado: no es un archivo del proyecto reutilizable ni cacheable); romper sin más la etiqueta `<img>` (rechazado: muestra el icono roto del navegador, viola FR-003). Nota: servir portadas reales exige `php artisan storage:link`; con los datos de prueba de `001` todas las portadas son `NULL`, por lo que la funcionalidad visible en esta fase no depende del enlace simbólico (su gestión es de `005-gestion-portadas`).

## D-015: Estructura visual común (cabecera, navegación y pie)

- **Decision**: `catalogo/index.blade.php` y `catalogo/show.blade.php` extienden `resources/views/layouts/app.blade.php` creado en `001` (y `errors/404.blade.php` también). `002` incorpora a la navegación del layout el enlace «Catálogo» (`route('catalogo.index')`) y usa secciones Blade (`@yield`) para título y contenido. Cabecera, navegación y pie son idénticos en las tres vistas.
- **Rationale**: FR-004 y SC-004 exigen una estructura visual compartida reconocible como una única aplicación; el Principio V exige un layout Blade común. Reutilizar el layout de `001` evita duplicación (la dependencia entre features queda declarada en el plan).
- **Alternatives considered**: Layout propio duplicado para el catálogo (rechazado: viola FR-004/SC-004); crear un segundo layout en `002` (rechazado: dos layouts contradicen el layout único de la constitución); CSS de framework (rechazado: el CSS propio es la vía por defecto; frameworks son opcionales y declarables en el README).

## D-016: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` sobre la base de datos de prueba (D-008 de `001`), comando `php artisan test`. Tests de característica:
  - `CatalogoIndexTest` — listado, campos de tarjeta, estado vacío, enlace de tarjeta.
  - `CatalogoDetalleTest` — todos los campos + categoría, portada predeterminada, 404 de libro inexistente.
  - `CatalogoOrdenYPaginacionTest` — 10 por página, recorrido completo, orden con tildes/mayúsculas y desempates por autor e ISBN.
  - `CatalogoErroresTest` — página fuera de rango/no numérica → 404 con vista y enlace de vuelta.
  - `CatalogoLayoutTest` — cabecera/navegación/pie idénticos en listado, detalle y 404.
  - El caso de catálogo vacío se prueba con la base migrada **sin** sembrar (los datos de `001` exigen ≥ 15 libros solo en la siembra).
- **Rationale**: SC-001…SC-004 exigen evidencia reproducible; las factories de `001` permiten crear libros con tildes, mayúsculas y empates controlados.
- **Alternatives considered**: Solo verificación manual (rechazado: no es reproducible); Pest (viable sobre la misma base, se adoptaría si el equipo lo prefiere); tests de navegador/E2E (rechazado: dependencia innecesaria para una app Blade con PHP).

## D-017: Acceso a datos del listado y del detalle

- **Decision**: Consultas Eloquent con carga ansiosa de la relación: `Libro::with('categoria')` en `index` y en `show`; orden y paginación según D-011/D-012; sin concatenación de entradas del usuario en SQL (el único parámetro —`page`— se valida y llega al paginador como entero). Proyección de campos según [data-model.md](data-model.md).
- **Rationale**: Principio IV de la constitución (Eloquent/Query Builder sin SQL concatenado) y evitar N+1 (10 tarjetas × 1 consulta extra por categoría).
- **Alternatives considered**: Query Builder crudo (rechazado: sin beneficio y más difícil de auditar); `categoria` diferida con `deferredLoading` (rechazado: no mejora nada con 10 filas); cargar todos los libros y paginar en memoria (rechazado: innecesario e inestable ante datos crecientes).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, PHPUnit (heredado D-001/D-008; paginación sin frameworks CSS: D-011) |
| Storage | MySQL 8.x local (heredado D-002); colación `utf8mb4_0900_ai_ci` fijada por migración (D-012); portadas en Storage público cuando existan (D-014) |
| Testing | PHPUnit + `RefreshDatabase` sobre MySQL de prueba (heredado D-008; suites de `002`: D-016) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de filas; listado de 10 en 10 con una consulta + eager loading (D-011, D-017) |
| Constraints | Sin servicios de pago; credenciales fuera del repo (heredado D-007); mensajes en español y 404 controlados (D-013); orden determinista FR-005 (D-012) |
| Scale/Scope | 2 rutas, 1 controlador, 5 vistas nuevas/ajustadas, 1 migración, ~5 suites de test (D-010…D-016); búsqueda/filtro fuera de alcance (`006`) |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
