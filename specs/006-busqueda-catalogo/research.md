# Research: 006-busqueda-catalogo (Búsqueda o filtro del catálogo)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa por encima de la de las features vecinas** (la de `003-gestion-libros` cubre D-018…D-025 y la de `004-gestion-prestamos` D-018…D-027): aquí se usan **D-028…D-035** para evitar colisiones. Las decisiones de stack heredadas (D-001…D-017 de `001` y `002`) se reutilizan sin cambios —en particular D-011 (paginación con `withQueryString()`), D-012 (colación `utf8mb4_0900_ai_ci` en `titulo`/`autor`), D-013 (404 de página), D-014/D-015 (portada y layout) y D-017 (acceso a datos).

---

## D-028: Criterios como query string en la ruta existente `GET /catalogo`

- **Decision**: Ampliar la ruta `catalogo.index` (`GET /catalogo`) de `002` con dos parámetros de consulta, sin rutas ni endpoints nuevos:
  - `q` — texto libre de búsqueda (coincidencia que contiene contra título, autor, editorial e ISBN).
  - `categoria` — identificador entero de la categoría (coincidencia exacta).
  - `page` — se mantiene tal cual lo definió `002` (D-011/D-013).
  - La respuesta sigue siendo la **página HTML completa** del listado (SSR con Blade); los criterios quedan reflejados en la URL, de modo que se pueden compartir, recargar y navegar con «atrás/adelante».
- **Rationale**: La especificación acota el alcance al «listado principal de libros» (supuesto de alcance), que ya existe como `GET /catalogo`; la clarificación pide filtrar *ese* listado, no crear una vista nueva. Los parámetros en la URL son la vía natural para que los criterios «permanezcan visibles» (FR-004) tras cada render y para que `withQueryString()` de D-011 los conserva al paginar —`002` ya preveía explícitamente esta llegada de criterios por query string.
- **Alternatives considered**: Ruta nueva `/catalogo/buscar` (rechazada: duplicaría el listado y su paginación, y la spec filtra el listado existente); endpoint JSON + `fetch` con SPA (rechazado: la constitución exige Blade con layout común y SSR; añadiría dos modos de respuesta); formulario `POST` (rechazado: el filtrado es de solo lectura, debe sobrevivir a recargas y ser enlazable; un `GET` en la misma ruta mantiene el contrato de `002`).

## D-029: Filtrado en servidor con Eloquent, parámetros ligados y comodines escapados

- **Decision**: Toda la normalización y el filtrado ocurren en `CatalogoController@index` (Principio II: el servidor es la fuente de verdad):
  - `q`: se aplica `trim()`; si queda vacío (incluido el texto de solo espacios en blanco) **no existe criterio de texto**. Se escapan los comodines `%`, `_` y `\` del valor con `addcslashes()` y se envuelve como `%'texto'%` → `where('titulo', 'like', $patrón)` combinados con `orWhere` en un **grupo anidado** (`where(function ($q) { … })`) para que el OR interno no contamine la condición Y externa; los cuatro campos son título, autor, editorial e ISBN.
  - `categoria`: ver D-031; se aplica como `where('categoria_id', $id)` — condición **Y** sucesiva respecto al grupo de texto (regla Y de FR-005).
  - Todos los valores llegan como **bindings parametrizados** del Query Builder; no se concatenan en SQL.
  - Después de filtrar, se mantiene `paginate(10)` + `withQueryString()` (D-011) y `with('categoria')` (D-017).
- **Rationale**: FR-005 exige combinación Y; el grupo `OR` anidado es la forma idiomática de Eloquent para `(A OR B OR C OR D) AND E` sin SQL crudo. Escapar `%`/`_` hace que la «coincidencia que contiene» sea **literal**: buscar `100%` o `a_b` no debe actuar como comodín (la spec pide que el texto *aparezca* en el campo, no que sea un patrón). El trim del servidor cumple el edge case «solo espacios en blanco = sin criterio» sin depender del cliente.
- **Alternatives considered**: `whereRaw` con `CONCAT`/`CONVERT` (rechazado: SQL crudo cuando el Builder expresa la consulta, más difícil de auditar — Principio IV); cargar todos los libros y filtrar en PHP (rechazado: rompe la paginación por offset, la comprobación de página fuera de rango y el rendimiento); índices `FULLTEXT` (rechazado: la semántica es por relevancia/ palabras completas, no «contiene» literal, y su tratamiento de acentos depende de la configuración del analizador); filtrado en el cliente sobre la página completa (rechazado: solo ve la página actual, viola FR-002/SC-001).

## D-030: Sensibilidad a mayúsculas y acentos — colación `utf8mb4_0900_ai_ci` fijada en la base de datos

- **Decision**: Una **migración propia de 006** (`database/migrations/*_alter_libros_editorial_search_collation.php`, posterior a la de colación de `002`) fija la colación **`utf8mb4_0900_ai_ci`** sobre la columna `libros.editorial` mediante `Schema::table(...)->change()`. Con eso, los tres campos de texto buscables cubren la regla FR-003:
  - `titulo` y `autor`: ya fijados por `002` (D-012).
  - `editorial`: fijado ahora por `006` (era el único campo de texto buscable sin colación garantizada — el default del servidor no es fiable, ya rechazado en D-012).
  - `isbn`: `char(13)` solo dígitos; mayúsculas/minúsculas y acentos no existen en el valor almacenado, no requiere ajuste. La comparación `LIKE` usa la colación de la **columna** (coercibilidad de columna > literal de conexión), por lo que `WHERE editorial LIKE '%algebra%'` coincide también con «Álgebra…» sin SQL adicional.
  - Nota de portabilidad heredada de D-012: en la variante MariaDB, sustituir por `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci`.
- **Rationale**: FR-003 exige que la coincidencia **no distinga mayúsculas/minúsculas ni acentuación** en los cuatro campos. Fijar la colación en el esquema mantiene la regla verificable (`SHOW FULL COLUMNS FROM libros`), evita SQL crudo por consulta y sigue el Principio I (la base de datos es la fuente de verdad) y el precedente de `002`. La comparación parcial del ISBN conserva los ceros iniciales porque `LIKE` opera sobre el texto almacenado, sin normalizar.
- **Alternatives considered**: `LOWER()` + `LIKE` (rechazado: resuelve solo mayúsculas, no acentos); `Str::ascii()`/`iconv` en PHP y comparar en memoria (rechazado: no puede desacentuar lo almacenado sin cargar todo y rompe paginación); `CONVERT(editorial USING utf8mb4) COLLATE …` en cada consulta (rechazado: SQL crudo por petición en lugar de una regla de esquema); columna generada/trigger de normalización (rechazado: complejidad de esquema desproporcionada para tres columnas); depender del default `utf8mb4_0900_ai_ci` de MySQL 8 (rechazado en D-012: no verificable y depende de cómo se creó la base).

## D-031: Filtro de categoría por `categoria_id` con opciones provenientes de la base de datos

- **Decision**: El desplegable se renderiza en el servidor con `Categoria::orderBy('nombre')->get()`, con opción por defecto «Todas las categorías» (valor vacío = sin criterio) y valor de cada opción = `id` de la categoría. En el servidor:
  - Validación: `categoria` debe ser una cadena de dígitos (`^\d+$`) cuyo entero sea ≥ 1 **y** existir en `categorias` (`Categoria::whereKey($id)->exists()`).
  - Valor inválido o inexistente → **se ignora** (equivalente a ausente; se muestra el catálogo completo sin ese criterio).
  - Valor válido → `where('categoria_id', $id)`, es decir, **coincidencia exacta** (FR-003 y *Key Entities*: «se utiliza como filtro mediante coincidencia exacta»).
- **Rationale**: `categoria_id` es la identidad canónica de la relación (FK en el esquema de `001`); el nombre sería un valor mutable y con acentos que habría que codificar en la URL. Ignorar valores inválidos evita inventar comportamientos no pedidos: el 404 queda reservado para página/detalle (D-013) y mostrar el mensaje «sin coincidencias» con una categoría que ni siquiera aparece en el desplegable rompería el requisito de que «los criterios aplicados permanecen visibles» (FR-004) —si el criterio no puede mostrarse, no está aplicado.
- **Alternatives considered**: `categoria=Nombre` en la URL (rechazado: depende del texto almacenado, hay que escaparlo y cambia si se renombra la categoría); 404 ante `categoria` inválida (rechazado: D-013 acota el 404 a `page` y al identificador de libro, no a un filtro de lista); mensaje de resultados vacíos ante id inexistente (rechazado: el desplegable no puede marcarlo, el usuario no vería ningún criterio y no podría ajustarlo); validación solo en el cliente (rechazado: Principio II).

## D-032: Filtrado automático con debounce en JavaScript nativo (sin dependencias ni build)

- **Decision**: `catalogo/index.blade.php` contiene un formulario `GET` hacia `catalogo.index` con: campo de texto `q`, desplegable `categoria`, enlace visible «Limpiar» (ver D-033) y un **botón de envío visualmente oculto** (garantiza el envío con «Enter» y la semántica accesible del formulario, sin ser el «botón de envío» visible que la clarificación descarta). Un script propio `public/js/busqueda-catalogo.js` (JavaScript nativo, sin bundler ni frameworks):
  - Evento `input` sobre `q` → **debounce de cola de 300 ms**: cada pulsación reinicia el temporizador y, al expirar, se envía el formulario; si el usuario sigue escribiendo antes de expirar, solo se envía cuando deja de escribir — el **último texto** determina los resultados y no hay una consulta por tecla (edge case «escritura rápida»).
  - Evento `change` sobre `categoria` → envío **inmediato** (FR-001: «o al cambiar el filtro de categoría»).
  - El formulario **no incluye** `page`: cada nuevo criterio reinicia la paginación en la página 1 (D-034).
  - Tras cada render, si `q` tiene valor, el script coloca el foco al final del campo, de modo que la escritura continua no se interrumpe por la recarga.
  - Sin JavaScript, «Enter» envía el formulario (botón oculto) y el desplegable funciona con el envío nativo: funcionalidad degradada, no bloqueada.
- **Rationale**: La clarificación de la spec exige filtrado automático *mientras se escribe* con retardo y **sin botón de envío visible**; 300 ms agrupa pulsaciones percibidas como «inmediatas». El envío de formulario (SSR) mantiene **una sola ruta de render** con el servidor como fuente de verdad (Principio II), reutiliza la paginación, el layout y los tests de `002`, y evita el estado duplicado de una capa cliente. JavaScript nativo cumple el Principio V: aplicación local sin dependencias de pago ni pasos de build adicionales (la constitución solo declara frameworks CSS/JS como opcionales en el README; aquí no se usa ninguno).
- **Alternatives considered**: `fetch` + intercambio parcial de `#resultados` + `history.pushState` (rechazado: exige dos modos de respuesta del controlador, interceptar también los enlaces de paginación y mucho más JS difícil de probar; sin aporte real a una app local); enviar el formulario en cada tecla sin debounce (rechazado: viola explícitamente el edge case «escritura rápida»); Livewire/Alpine/htmx/jQuery (rechazados: dependencia externa que habría que declarar y justificar para lo que resuelven diez líneas de JS); input con `onkeyup` que filtra solo la página cargada (rechazado: solo ve 10 registros, viola FR-002/SC-001).

## D-033: Estados vacíos diferenciados, criterios visibles y enlace «Limpiar»

- **Decision**: Prioridad de mensajes en `catalogo/index`:
  1. **Hay criterio activo** (`q` no vacío tras trim o `categoria` válido) **y total = 0** → mensaje exacto **«No se encontraron libros que coincidan con la búsqueda»**, **sin tarjetas**, **con los criterios aún visibles** (el campo conserva `value="{{ $q }}"` escapado y el desplegable marca la categoría seleccionada).
  2. **Sin criterio y total = 0** → mensaje de `002` «No hay libros registrados aún» (sin cambios de contrato).
  - **«Limpiar»** es un **enlace** a `route('catalogo.index')` sin parámetros (FR-006): restaura el catálogo completo «sin residuos» (SC-004) y funciona **sin JavaScript**. Se muestra **solo cuando hay al menos un criterio activo**; con el listado completo no aparece (no hay nada que limpiar).
  - Con criterio y total ≥ 1 no hay mensaje: solo tarjetas y paginación filtrada.
- **Rationale**: FR-004 fija el texto exacto y la visibilidad de los criterios; SC-004 exige recuperar el catálogo completo tras limpiar. Los dos mensajes vacíos distintos evitan que «base de datos vacía» y «búsqueda sin coincidencias» se confundan —los dos contratos ya estaban separados en `001`/`002`. El enlace es más simple y robusto que un botón `reset` (que vaciaría el DOM pero no cambiaría la URL ni los resultados sin JS) y garantiza que no queden residuos del filtrado anterior.
- **Alternatives considered**: Un único mensaje para ambos casos vacíos (rechazado: contradice el contrato de `002` y pierde significado para el usuario); botón `type="reset"` del formulario (rechazado: no navega, la URL y los resultados seguirían filtrados hasta un envío manual); limpiar solo con JS (rechazado: dependencia innecesaria y los criterios deben reflejarse desde el servidor); mostrar «Limpiar» siempre, deshabilitado (rechazado: ruido visual sin criterios que limpiar).

## D-034: Paginación de Fase 1 aplicada al conjunto filtrado

- **Decision**: Se conserva `paginate(10)` + `withQueryString()` (D-011) sobre la consulta **ya filtrada**:
  - `total()`/`lastPage()` reflejan el conjunto filtrado; los enlaces de página conservan `q` y `categoria` (y viceversa: cambiar un criterio reinicia `page` porque el formulario no lo envía).
  - La validación de `page` y el 404 «Página no válida» de `002` (D-013) se mantienen intactos, evaluados ahora sobre el total **filtrado**: con criterio y `page` fuera de rango → 404; con criterio y total = 0, la página 1 es válida (muestra el mensaje de FR-004) y `page ≥ 2` → 404.
- **Rationale**: FR-005 lo pide textualmente («la paginación del listado de la Fase 1 debe aplicarse sobre el conjunto de resultados filtrado») y el edge case «resultados que abarcan varias páginas» lo confirma. `withQueryString()` ya fue diseñado en `002` precisamente para conservar los criterios de `006` al paginar; mantener el 404 unificado evita dos comportamientos de error distintos para el mismo parámetro.
- **Alternatives considered**: Ocultar la paginación cuando hay filtro (rechazado: viola el edge case de resultados multipágina); paginar en memoria tras cargar todo (rechazado: ya rechazado en D-011/D-017); redirigir a la última página válida en vez de 404 (rechazado: `002` fijó 404 como contrato); conservar un campo oculto `page` al filtrar (rechazado: un criterio nuevo debe volver a la página 1).

## D-035: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` sobre la base de datos de prueba (D-008), comando `php artisan test`, usando las factories y el seed de `001` (≥ 15 libros, ≥ 3 categorías). Cinco suites de característica:
  - `BusquedaCatalogoTest` — coincidencia parcial en cada uno de los cuatro campos, texto que aparece en varios campos, mayúsculas/acentos (`Álgebra` ↔ `algebra`), ISBN parcial con ceros iniciales; ningún libro ajeno al criterio (SC-001/SC-002/SC-003).
  - `BusquedaCombinacionTest` — texto + categoría con regla Y, categoría exacta, criterio vacío y de solo espacios → catálogo completo (FR-003/FR-005, SC-005).
  - `BusquedaVacioYLimpiezaTest` — mensaje exacto con criterios visibles, enlace «Limpiar» que restaura el catálogo completo, distinción de los dos estados vacíos (FR-004/FR-006, SC-002/SC-004).
  - `BusquedaPaginacionTest` — paginación de 10 sobre el conjunto filtrado, conservación de `q`/`categoria` en los enlaces, reinicio a página 1 al cambiar criterio, 404 de página fuera de rango bajo filtro (FR-005, D-034).
  - `BusquedaInterfazTest` — formulario sin botón de envío visible (con botón oculto), campo `q` con valor rebotado escapado, desplegable con las categorías de la BD, script de debounce presente, y comprobación de la colación de `editorial` vía `DB::select("SHOW FULL COLUMNS …")` **solo en el test** (la app permanece en Eloquent puro).
  - El debounce (temporización de 300 ms) **no** se automatiza en PHPUnit: se verifica manualmente en el escenario V-007 de [quickstart.md](quickstart.md) con las herramientas de red del navegador.
- **Rationale**: SC-001…SC-005 exigen evidencia reproducible en el 100 % de los casos; las factories de `001` permiten crear datos con acentos, mayúsculas y ceros iniciales controlados; la colación es una decisión de esquema que debe verificarse además de funcionalmente.
- **Alternatives considered**: Solo verificación manual (rechazado: no es reproducible); Pest (viable sobre la misma base, se adoptaría si el equipo lo prefiere); tests de navegador/E2E para todo (rechazado: dependencia innecesaria para una app Blade con PHP; se reserva la comprobación visual manual del debounce).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, JS nativo sin build (D-032), PHPUnit (heredado D-001/D-008) |
| Storage | MySQL 8.x local (heredado D-002); colación `utf8mb4_0900_ai_ci` en `editorial` por migración de `006` (D-030) |
| Testing | PHPUnit + `RefreshDatabase` sobre MySQL de prueba (heredado D-008; suites de `006`: D-035) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de filas; una consulta filtrada paginada por petición; debounce de 300 ms evita una consulta por tecla (D-029, D-032) |
| Constraints | Sin servicios de pago; credenciales fuera del repo (heredado D-007); mensaje exacto de FR-004 y salida escapada (D-033, D-029); sin frameworks CSS/JS (D-032) |
| Scale/Scope | 0 rutas nuevas, 1 controlador modificado, 1 vista ajustada + 1 script + CSS propio, 1 migración, 5 suites (D-028…D-035); CRUD (`003`), préstamos (`004`), portadas (`005`) y panel (`007`) fuera de alcance |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
