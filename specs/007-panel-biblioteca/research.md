# Research: 007-panel-biblioteca (Panel y reportes de biblioteca)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa la de `002-consulta-catalogo`** (D-001…D-017); aquí se añaden D-018…D-026. Las decisiones de stack heredadas (D-001…D-017) se reutilizan sin cambios.

---

## D-018: Ruta, controlador y acceso al panel

- **Decision**: Una única ruta GET sin parámetros en `routes/web.php`: `GET /panel` → `PanelController@index`, nombre de ruta `panel.index`. La navegación del layout común añade el enlace «Panel» (`route('panel.index')`). No hay middleware de autenticación en esta funcionalidad: el panel es una vista pública dentro del estado actual del proyecto, igual que el catálogo (`002`). Cuando `008-interfaz-validacion-seguridad` entregue el inicio de sesión global, el panel quedará protegido como el resto de vistas; la clarificación 2026-10-26 («los tres perfiles: estudiante, docente y personal administrativo») se cumple porque `008` no define permisos diferenciados: todos los usuarios autenticados ven las mismas vistas.
- **Rationale**: FR-007 exige los tres indicadores en una sola vista y consultable por los tres perfiles; una ruta única con una acción es la implementación mínima de «una sola vista». El supuesto de la spec («el panel es una vista de solo consulta») y FR-006 (sin modificaciones) excluyen escrituras, formularios y CSRF aplicable. Definir ahora un control de acceso propio duplicaría y contradiría el diseño de `008` (inicio de sesión único, sin roles).
- **Alternatives considered**: `/dashboard` o `/indicadores` (rechazado: la spec y el vocabulario del proyecto hablan de «panel»; `panel.*` sigue el patrón de nombres `catalogo.*` de D-010); incluir el panel como sección de `/catalogo` (rechazado: FR-007 pide una vista propia identificable, y mezclaría consulta de catálogo con reportes); proteger la ruta con un middleware propio de perfiles (rechazado: `008` establece que no habrá permisos diferenciados); API JSON + gráficos JS (rechazado: la constitución exige Blade con layout común y SSR).

## D-019: Ranking de libros más prestados (top 5, historial completo)

- **Decision**: Consulta Eloquent sobre `Libro`:

  ```php
  Libro::has('prestamos')                     // solo libros con ≥ 1 préstamo
      ->withCount('prestamos')                // cuenta del historial completo, sin filtro de fechas
      ->orderByDesc('prestamos_count')        // mayor a menor número de préstamos
      ->orderBy('titulo')                     // desempate alfabético (colación utf8mb4_0900_ai_ci de 002)
      ->orderBy('isbn')                       // desempate final: orden total determinista
      ->limit(5)                              // máximo 5 posiciones
      ->get();
  ```



  Se muestran título, autor y la cifra de préstamos junto a cada libro (clarificación 2026-10-26). Si ningún libro tiene préstamos, el resultado es vacío y se aplica el estado vacío (D-024). `has('prestamos')` excluye los libros de cuenta 0 sin rellenar posiciones (clarificación 2026-10-26): un libro con 1 préstamo ocupa posición antes que uno con 0, y este último nunca entra.
- **Rationale**: FR-004 exige máximo 5 libros, orden de mayor a menor del **historial completo**, desempate por orden alfabético del título y resultado «único, reproducible y verificable con un cálculo manual». `withCount` + `orderBy` resuelven todo en una consulta parametrizada (Principio IV). La colación `utf8mb4_0900_ai_ci` de `libros.titulo` (migración de `002`, D-012) hace que el desempate alfabético ignore tildes y mayúsculas; `isbn` (único, 13 dígitos) aporta el desempate final estable cuando dos libros comparten título, garantizando orden total (misma técnica que D-012). El conteo se hace sobre la tabla `prestamos` completa: cada fila cuenta una vez por préstamo, sin distinguir estado ni ejemplares (supuestos de la spec).
- **Alternatives considered**: Contar en PHP tras traer todos los libros (rechazado: innecesario y carga filas de más); filtrar por `estado` o por un periodo (rechazado: FR-004 fija el historial completo); paginar o puntuar por duración del préstamo (rechazado: los supuestos lo excluyen); `orderBy('titulo')->limit(5)` sin `has()` (rechazado: incluiría libros sin préstamos, prohibido por la clarificación); agrupar desde `Prestamo::groupBy('libro_id')` + join (rechazado: equivalente pero más verboso y sin aportar el título ordenable directamente).

## D-020: Préstamos vencidos — regla por fechas, orden y determinismo

- **Decision**: Un préstamo **está vencido** en el panel cuando se cumple la regla de fechas de FR-005, calculada con la fecha de consulta (D-022):

  ```text
  fecha_devolucion_prevista < fecha de consulta  AND  fecha_devolucion_real IS NULL
  ```

  La consulta se hace con Eloquent sobre `Prestamo`, uniendo `libros` solo para poder ordenar:

  ```php
  Prestamo::query()
      ->with('libro')                                   // eager loading: título en cada fila, sin N+1
      ->whereNull('fecha_devolucion_real')              // pendientes de devolución
      ->where('fecha_devolucion_prevista', '<', $fechaConsulta)
      ->join('libros', 'libros.id', '=', 'prestamos.libro_id')
      ->orderBy('fecha_devolucion_prevista')            // retraso mayor → menor (fecha prevista menor → mayor)
      ->orderBy('libros.titulo')                        // desempate alfabético del título (colación ai_ci de 002)
      ->orderBy('prestamos.id')                         // desempate final determinista
      ->get(['prestamos.*']);
  ```

  De cada fila se muestran: título del libro, nombre del estudiante, carné, fecha de préstamo, fecha de devolución prevista y **días de retraso** = diferencia en días naturales entre la fecha de consulta y la devolución prevista. La lista se presenta completa, sin paginación (el volumen esperado es de decenas de filas). No se filtra por la columna `estado`: la regla del panel es la de FR-005, y `estado` es un valor derivado que `004` evalúa automáticamente al listar, por lo que filtrar por fechas es coherente con `004` y evita depender del momento en que se actualizó el estado.
- **Rationale**: FR-005 y los edge cases exigen: regla estricta («anterior a la fecha de consulta» — el préstamo con prevista **igual** a hoy no es vencido), campos concretos, orden por días de retraso de mayor a menor con desempate por título, y que solo aparezcan préstamos pendientes (los devueltos —incluso con retraso— quedan excluidos por `whereNull(fecha_devolucion_real)`). Ordenar por `fecha_devolucion_prevista` ASC es exactamente ordenar por días de retraso DESC porque la fecha de consulta es única para toda la petición (D-022). El join ordena por el título con la misma colación `ai_ci` del ranking, y `prestamos.id` cierra el orden total.
- **Alternatives considered**: Filtrar por `estado = 'vencido'` (rechazado: puede estar desactualizado respecto a la fecha de consulta y la spec define el vencimiento por fechas, no por el valor almacenado; además un préstamo activo no devuelto con prevista pasada es vencido **en el momento de la consulta** aunque su estado diga `activo`); calcular los días en SQL con `DATEDIFF` (rechazado: la fecha de consulta se aplica igual como binding y calcular en PHP con Carbon es más legible y portable); ordenar por días de retraso con expresión calculada (rechazado: equivalente a ordenar por fecha prevista ASC, más complejo); paginar la lista (rechazado: la spec no lo pide y el volumen es local); excluir por `estado = 'devuelto'` además de la fecha (rechazado: `whereNull(fecha_devolucion_real)` ya lo excluye y es la condición literal de FR-005).

## D-021: Disponibilidad por categoría — colación de orden y agregado

- **Decision**: Dos piezas:
  1. **Migración propia de 007** (`database/migrations/*_alter_categorias_nombre_collation.php`, posterior a la creación de `categorias` de `001`) fija la colación **`utf8mb4_0900_ai_ci`** sobre `categorias.nombre` mediante `Schema::table(...)->change()` — misma técnica y justificación que D-012.
  2. Consulta Eloquent:

     ```php
     Categoria::query()
         ->withCount('libros')                                        // detecta categorías sin libros
         ->withSum('libros as ejemplares_disponibles', 'ejemplares_disponibles') // total disponible por categoría
         ->orderBy('nombre')                                          // alfabético español (colación ai_ci)
         ->having('libros_count', '>', 0)                             // oculta categorías sin libros
         ->get();
     ```

     Se muestra el nombre de la categoría y su cifra de ejemplares disponibles junto a él (clarificación 2026-10-26). Las categorías con libros y **0 disponibles** se muestran siempre (FR-008); las categorías sin libros se ocultan; si ninguna categoría tiene libros, el indicador muestra «No hay datos disponibles» (D-024).
- **Rationale**: FR-003 exige orden alfabético por nombre «en español, sin distinguir tildes ni mayúsculas»; la clarificación de sesión lo confirma. Fijar la colación en la columna (Principio I) hace la regla verificable en el esquema con `SHOW FULL COLUMNS` y mantiene `ORDER BY nombre` como Eloquent plano. `withSum` sobre la relación `libros` suma `ejemplares_disponibles` (el campo que las reglas de `004` mantienen coherente); `having('libros_count', '>', 0)` implementa «ocultar categorías sin libros» en la propia consulta. No se suma `ejemplares_totales`: el indicador es de **disponibles** (FR-003).
- **Alternatives considered**: Ordenar en PHP con `Str::ascii()` (rechazado: innecesario cuando la colación resuelve la regla y rompe la verificación en esquema; mismo rechazo que D-012); depender del default de la base (rechazado: una base creada con otra colación rompería el orden sin avisar); usar la colación ya aplicada a `libros.titulo` (imposible: es otra columna/otra tabla); agrupar desde `Libro::groupBy('categoria_id')` (rechazado: exige join con `categorias` para el nombre y no detecta categorías vacías sin LEFT JOIN explícito); traer todas las categorías y filtrar en PHP (rechazado: `having` lo resuelve en la consulta); incluir categorías sin libros con «0» (rechazado: FR-008 las oculta explícitamente).

## D-022: Fecha de consulta — única por petición

- **Decision**: El controlador calcula **una sola vez** `$fechaConsulta = today()` (Carbon, fecha del servidor en la zona horaria de la aplicación) y la pasa a las consultas de vencidos (D-020) y al cálculo de días de retraso. Los tres indicadores se calculan en la misma petición con esa fecha; no se acepta ninguna entrada de fecha por parte del usuario.
- **Rationale**: El edge case «Fecha de comparación para el vencimiento: es la del día en que se consulta el panel» y FR-006 (cálculo con los registros vigentes **en el momento de la consulta**). Una variable única garantiza coherencia interna: un préstamo no puede ser vencido en el ranking y a tiempo en la lista dentro de la misma página. `today()` truncar horas/minutos, de modo que un préstamo cuya prevista es hoy no cuenta como vencido durante todo el día (regla estricta de FR-005).
- **Alternatives considered**: `now()` con hora (rechazado: la spec compara por **día** de consulta; la hora haría vencer un préstamo a las 00:00 y solo mediría retrasos con precisión de hora); `Carbon::today()` calculado por separado en cada consulta (rechazado: dos evaluaciones podrían diverbir si la petición cruza la medianoche); aceptar un parámetro `?fecha=` (rechazado: FR-006 y los supuestos no definen entrada de fecha; sería un formulario de filtro fuera de alcance — la spec dice «sin realizar búsquedas ni filtros previos»); usar la fecha de creación del registro (rechazado expresamente por el edge case).

## D-023: Acceso a datos — tres consultas agregadas, sin N+1

- **Decision**: `PanelController@index` ejecuta exactamente tres consultas Eloquent (D-019, D-020, D-021), todas parametrizadas, sin concatenación de entradas del usuario (no hay entradas). La lista de vencidos usa `with('libro')` para el título en cada fila (eager loading); el ranking trae filas completas de `Libro` (mostrando título, autor y cifra); el agregado por categoría se resuelve con `withCount`/`withSum`. Los resultados se pasan a la vista ya calculados (cifras, días de retraso); la vista solo presenta.
- **Rationale**: Principio IV (Eloquent/Query Builder sin SQL concatenado) y la herencia de D-017 (eager loading para evitar N+1: cada fila de vencidos evitaría una consulta extra al libro). Tres consultas acotadas por petición cumplen el supuesto de rendimiento («respuesta estándar de una aplicación web local», sin objetivos adicionales). El cálculo vive en el controlador/consultas, no en la vista, lo que hace cada regla testeable en PHPUnit (D-026).
- **Alternatives considered**: Cachear los indicadores (rechazado: FR-006 exige los registros vigentes **del momento de la consulta**; la caché los desactualizaría); resolver todo en una única consulta SQL con subconsultas (rechazado: más difícil de leer, probar y mantener, sin beneficio en este volumen); SQL crudo con `DB::select` (rechazado: sin ventaja y más difícil de auditar frente a Eloquent); crear un modelo `Panel` o tabla de agregados (rechazado: el panel es una proyección de solo consulta, no una entidad — violaría el Principio I al inventar estado persistido).

## D-024: Estados vacíos — «No hay datos disponibles»

- **Decision**: Cada uno de los tres indicadores se renderiza de forma independiente. Cuando su resultado es vacío se muestra **exactamente** el mensaje «No hay datos disponibles» en la zona de ese indicador:
  - Ranking vacío (ningún libro tiene préstamos) → mensaje; no se muestran posiciones.
  - Lista de vencidos vacía (ningún préstamo cumple la regla de fechas) → mensaje; no se muestra la tabla.
  - Ninguna categoría tiene libros → mensaje en el indicador de disponibilidad; con al menos una categoría con libros, se listan todas ellas (incluidas las de 0 disponibles).
- **Rationale**: FR-008 y los edge cases lo exigen textualmente («No hay datos disponibles»), y el caso «libros empatados / menos de 5» se resuelve con el ranking parcial sin relleno (D-019). Un mensaje por indicador —y no global— permite que el panel muestre datos parciales válidos (p. ej. categorías con libros pero sin vencidos).
- **Alternatives considered**: Mensaje único para el panel completo (rechazado: viola FR-008, que lo define «cuando un indicador no tiene datos»); ocultar la sección vacía sin más (rechazado: el usuario no sabría si el indicador existe; la spec exige el mensaje); mostrar «0» en cifras (rechazado: la spec distingue «sin datos» de «cero ejemplares disponibles» — una categoría con 0 disponibles sí muestra su cifra, pero un indicador sin filas muestra el mensaje).

## D-025: Estructura visual del panel (una vista, tres secciones)

- **Decision**: `resources/views/panel/index.blade.php` extiende `resources/views/layouts/app.blade.php` (creado en `001`, navegación ampliada en `002`) y añade el enlace «Panel» a la navegación común. La zona de contenido contiene, en orden, título de página y **tres secciones**, cada una con rótulo en español, su indicador (tabla o lista) y su estado vacío (D-024):
  1. «Libros más prestados» — filas: posición (1–5), título, autor, **número de préstamos** (cifra visible junto al libro).
  2. «Préstamos vencidos» — tabla: libro, estudiante, carné, fecha de préstamo, fecha de devolución prevista, **días de retraso** (cifra visible).
  3. «Ejemplares disponibles por categoría» — filas: categoría, **ejemplares disponibles** (cifra visible junto a la categoría), en orden alfabético.
  El CSS propio del proyecto añade las reglas de las secciones/tablas; sin frameworks CSS. Toda la salida se escapa con `{{ }}`. Etiquetas y textos: [contracts/user-messages.md](contracts/user-messages.md); composición: [contracts/ui-structure.md](contracts/ui-structure.md).
- **Rationale**: FR-007 exige una sola vista, rótulos y valores claros en español y comprensible «sin conocimientos previos del sistema»; SC-003 exige identificar los tres resultados «en una sola visita al panel». Reutilizar el layout (Principio V, D-015) evita duplicación y mantiene una sola aplicación reconocible. Las cifras junto a cada elemento satisfacen la clarificación 2026-10-26.
- **Alternatives considered**: Tres páginas o pestañas (rechazado: FR-007 pide «una sola vista»); dashboard con gráficos JS (rechazado: añade dependencias sin necesidad; la spec pide listas con cifras, no gráficas); tres vistas parciales incluidas desde una vista contenedora (viable como refactor de implementación, pero no cambia el contrato; se permite si el equipo lo prefiere sin alterar la composición aquí definida); Bootstrap/Tailwind para las tarjetas (rechazado como necesario: el CSS propio es la vía por defecto; si se usara, habría que declararlo en el README).

## D-026: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` sobre la base de datos de prueba (D-008), comando `php artisan test`. Tests de característica:
  - `PanelRankingTest` — conteo correcto por libro, top 5, desempate alfabético del título (con tildes/mayúsculas) y por ISBN, exclusiones de libros sin préstamos, historial completo (libros devueltos/vencidos cuentan).
  - `PanelVencidosTest` — regla estricta (prevista = hoy no vence; prevista < hoy sí), exclusión de devueltos (incluso tarde), campos mostrados, orden por retraso y desempate, un libro con varios préstamos pendientes aparece varias veces.
  - `PanelDisponibilidadTest` — suma de disponibles por categoría, categorías con 0 disponibles visibles, categorías sin libros ocultas, orden alfabético español.
  - `PanelEstadosVaciosTest` — «No hay datos disponibles» en cada indicador con base migrada sin sembrar y con datos parciales.
  - `PanelLayoutTest` — layout común, enlace «Panel», una sola vista con las tres secciones, cifras junto a cada elemento, mensajes exactos.
  - Los datos se crean con las factories de `001` (y estados de préstamo de su seeder/factory) para resultados conocidos verificables contra el cálculo manual (SC-002).
- **Rationale**: SC-001…SC-004 exigen evidencia reproducible («coinciden en el 100 % de los casos» con el cálculo manual) y comportamiento correcto en los casos límite («en el 100 % de los casos probados»). Las factories permiten montar los conjuntos de resultados conocidos de los criterios de aceptación (empates, fechas límite, categorías vacías) sin depender de la siembra.
- **Alternatives considered**: Solo verificación manual (rechazado: no es reproducible); tests E2E de navegador (rechazado: dependencia innecesaria para SSR con PHP); Pest (viable sobre la misma base, se adoptaría si el equipo lo prefiere); probar contra la siembra de `001` únicamente (rechazado: la siembra no cubre empates ni fechas límite de forma controlada).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, PHPUnit (heredado D-001/D-008; sin frameworks CSS: D-025) |
| Storage | MySQL 8.x local (heredado D-002); colación `utf8mb4_0900_ai_ci` en `categorias.nombre` por migración (D-021); sin tablas nuevas |
| Testing | PHPUnit + `RefreshDatabase` sobre MySQL de prueba (heredado D-008; suites de `007`: D-026) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009); ruta pública `GET /panel` hasta la entrega de `008` (D-018) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de filas; 3 consultas por petición (D-023) |
| Constraints | Sin servicios de pago; credenciales fuera del repo (heredado D-007); vista de solo consulta sin entradas (FR-006, D-018/D-020); mensajes en español (D-024/D-025); determinismo de orden (D-019/D-020/D-021) |
| Scale/Scope | 1 ruta, 1 controlador, 1 vista + navegación, 1 migración, ~5 suites de test (D-018…D-026); sin CRUD, sin portadas, sin búsqueda, sin autenticación propia |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
