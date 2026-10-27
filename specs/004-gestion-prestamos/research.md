# Research: 004-gestion-prestamos (Registro, consulta y devolución de préstamos)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa la de `001-entorno-datos` (D-001…D-009) y `002-consulta-catalogo` (D-010…D-017)**; aquí se añaden D-018…D-027. Las decisiones de stack heredadas se reutilizan sin cambios.

---

## D-018: Rutas y controlador de préstamos (operaciones explícitas, inmutables)

- **Decision**: Cinco rutas sin autenticación en `routes/web.php`, todas bajo el prefijo `prestamos`:

  | Método y ruta | Nombre | Acción del controlador |
  |---|---|---|
  | `GET /prestamos?page=N` | `prestamos.index` | `PrestamoController@index` (listado paginado) |
  | `GET /prestamos/crear` | `prestamos.create` | `@create` (formulario de registro) |
  | `POST /prestamos` | `prestamos.store` | `@store` (valida y registra) |
  | `GET /prestamos/{id}/devolver` | `prestamos.form-devolucion` | `@devolver` (formulario de devolución) |
  | `POST /prestamos/{id}/devolver` | `prestamos.devolver` | `@registrarDevolucion` (valida y devuelve) |

  - **No existen rutas PUT/PATCH/DELETE de préstamos**: el historial es inmutable (FR-007); las únicas operaciones son registrar, consultar y devolver.
  - La navegación del layout común incorpora el enlace «Préstamos» → `route('prestamos.index')`.
  - `{id}` se resuelve con `Prestamo::find($id)`; si es `null` → `abort(404, 'Préstamo no encontrado')` (mismo patrón controlado que D-013).
  - La devolución tiene **página propia** con el resumen del préstamo y el campo de fecha real (la spec exige mostrar errores «junto al campo» y conservar lo ingresado).
- **Rationale**: El Principio IV exige operaciones explícitas y la clarificación de sesión prohíbe editar o eliminar; no declarar esas rutas es la garantía más fuerte (no hay endpoint que invocar). El formulario de devolución propio permite el requisito de UX de FR-003/FR-006 (error junto al campo, `old()`) mejor que un botón instantáneo sin campo. `POST /prestamos/{id}/devolver` es idempotente en efecto: la segunda llamada es rechazada con «Este préstamo ya fue devuelto.»
- **Alternatives considered**: Rutas REST completas con `update`/`destroy` y validación en el controlador (rechazado: la spec prohíbe editar/eliminar, FR-007); devolución como botón POST sin formulario (rechazado: la fecha real es obligatoria y sus errores deben mostrarse junto al campo); rutas API JSON + SPA (rechazado: la constitución exige Blade con layout común); `DELETE /prestamos/{id}` como «anulación» (rechazado: sería una eliminación encubierta, la spec la descarta).

## D-019: Centralización de las reglas de préstamo en `PrestamoService`

- **Decision**: Un único servicio `app/Services/Prestamos/PrestamoService.php` es el **único lugar** donde viven las reglas de préstamo (Principio III, FR-006). API:

  ```text
  registrar(array $datosValidados): Prestamo   # transacción: valida disponibilidad y límite,
                                               # crea el préstamo 'activo', descuenta 1 ejemplar
  devolver(Prestamo $p, string $fechaReal): Prestamo  # transacción: rechaza si ya devuelto,
                                                      # marca 'devuelto', suma 1 ejemplar
  sincronizarVencidos(): void                  # 'activo' con prevista < hoy → 'vencido' (D-020)
  libroDisponible(Libro $libro): bool          # ejemplares_disponibles >= 1
  prestamosNoDevueltos(string $carnet): int    # cuenta 'activo' + 'vencido' del carné normalizado
  ```

  - Los Form Requests (D-021) y el controlador **delegan** en el servicio; ninguna vista ni controlador reimplementa disponibilidad, límite, fechas ni vencimiento.
  - El descuento/suma de `ejemplares_disponibles` ocurre **dentro de la misma transacción** que crea/actualiza el préstamo (atomicidad: SC-001).
- **Rationale**: El Principio III exige que la lógica de préstamo exista en un solo lugar para que ningún controlador o vista la contradiga. Un servicio (patrón de servicios de Laravel, admitido explícitamente por la constitución: «servicios o modelos») mantiene los controladores delgados y hace que las reglas sean unitariamente verificables. La coherencia con `003-gestion-libros` (FR-007: total no editable por debajo de los préstamos activos) se da porque los préstamos activos se consultan con el mismo criterio (estado no devuelto).
- **Alternatives considered**: Reglas en los Form Requests solamente (rechazado como única vía: `devolver()` y `sincronizarVencidos()` también las necesitan y quedarían duplicadas); reglas en el modelo `Prestamo` como scopes/estáticos (viable, pero la manipulación transaccional de dos modelos —`Prestamo` y `Libro`— encaja mejor en un servicio; los modelos conservan solo helpers de presentación); lógica en el controlador (rechazado: se duplicaría entre `store`, `registrarDevolucion` e `index`); librería externa de dominio (rechazado: dependencia innecesaria para app local).

## D-020: Estado «vencido» calculado y sincronizado en los tres puntos de evaluación

- **Decision**: El estado se **persiste** en `prestamos.estado` (columna ya existente, D-003) y se **recalcula automáticamente** en el único punto de verdad `PrestamoService::sincronizarVencidos()`:

  ```sql
  UPDATE prestamos SET estado = 'vencido'
  WHERE estado = 'activo' AND fecha_devolucion_real IS NULL AND fecha_devolucion_prevista < CURDATE()
  ```

  (expresado con Eloquent: `Prestamo::where('estado', 'activo')->whereDate('fecha_devolucion_prevista', '<', today())->update([...])`).

  - «Vencido desde el **día siguiente** a la devolución prevista» se modela como estrictamente `<` hoy: un préstamo con prevista = hoy sigue `activo`; con prevista = ayer pasa a `vencido` al primer evaluado.
  - `sincronizarVencidos()` se invoca en los tres puntos que exige la clarificación de sesión: al **registrar** (`store`), al **devolver** (`registrarDevolucion`) y al **listar** (`index`), siempre dentro del mismo proceso de la operación.
  - `devuelto` es un estado final: la sincronización solo convierte `activo → vencido` y jamás toca filas `devuelto`.
- **Rationale**: La clarificación exige que el estado se evalúe «al registrar, devolver o listar». Persistir el resultado hace el estado consultable e informable (el panel de `007` consultará «préstamos vencidos» directamente con `where('estado', 'vencido')`), y el recálculo centralizado evita que la columna se desincronice: toda entrada/salida de la funcionalidad pasa por los tres puntos. El borde «día siguiente» es literal de la spec y se prueba con fechas contiguas.
- **Alternatives considered**: Estado derivado al vuelo sin persistir (rechazado: `001` ya define la columna con los tres valores y el seeder crea un préstamo `vencido`; además rompería las consultas del panel de `007`); job/programador diario (`schedule:daily`) que marque vencidos (rechazado: introduce un proceso asíncrono que la app local no garantiza; un listado nocturno no sincronizado mostraría estados falsos); recalcular en un accessor de Eloquent (rechazado: oculta escrituras en una lectura y no deja el estado consultable para reportes); comparar fechas solo en la vista (rechazado: la spec exige evaluación en el servidor y la constitución prohíbe que la vista sea la fuente de reglas).

## D-021: Form Requests con validación de campo + delegación de reglas de negocio

- **Decision**: Dos Form Requests:

  - **`RegistrarPrestamoRequest`** (`authorize()` → `true`):
    - `libro_id`: `required`, `integer`, `exists:libros,id`.
    - `nombre_estudiante`: `required`, `string`, `min:2`, `max:255`.
    - `carnet_estudiante`: `required`, `string`, `max:50`; en `prepareForValidation()` se aplica `trim()` (D-023).
    - `fecha_prestamo`: `required`, `date`.
    - `fecha_devolucion_prevista`: `required`, `date`, `after:fecha_prestamo`.
    - En `withValidator()` se añaden las reglas de **negocio** que dependen del estado de la base de datos, delegando en `PrestamoService` (D-019): disponibilidad del libro (`libro_id` → «No hay ejemplares disponibles de este libro.») y límite de tres préstamos no devueltos del carné (`carnet_estudiante` → mensaje de límite, [contracts/user-messages.md](contracts/user-messages.md)).
  - **`DevolverPrestamoRequest`**:
    - `fecha_devolucion_real`: `required`, `date`, `on_or_after:fecha_prestamo` del préstamo en curso (comparación en `withValidator()` con el modelo, porque la regla cruza entrada y registro existente).
  - Ambas redirigen de vuelta con `withErrors()` y `old()` preserva lo ingresado (Principio II). El controlador usa `$request->validated()` y pasa los datos al servicio; el servicio es quien aplica los rechazos transaccionales (devolución repetida, última carrera del último ejemplar) que no pueden evaluarse antes de la transacción.
- **Rationale**: Principio II («El servidor es la fuente de verdad… mediante Form Requests y `$request->validated()`») y FR-003/FR-006 (errores en español junto al campo, conservando lo ingresado). Las reglas de negocio que dependen de datos viven en el servicio y se consultan desde la validación para que el error aparezca en el campo correcto sin duplicar lógica. Los mensajes por defecto de Laravel están en inglés: cada regla lleva `messages()` (o `attributes()`) en español.
- **Alternatives considered**: Validar en el controlador con `validate()` (rechazado: la constitución exige Form Requests; el patrón no escala a dos formularios); reglas de negocio solo en el servicio con excepciones capturadas en el controlador (rechazado como única vía: el mensaje quedaría como flash genérico, no junto al campo, violando FR-006/SC-004); validación en JavaScript (rechazado: la spec dice que la restricción de interfaz es complementaria, no sustitutiva).

## D-022: Disponibilidad ante concurrencia — transacción, bloqueo pesimista y decremento condicional

- **Decision**: `PrestamoService::registrar()` y `devolver()` envuelven cada operación en `DB::transaction()`:

  - **Registrar**: dentro de la transacción, `Libro::whereKey($id)->lockForUpdate()->first()` (bloqueo de fila), comprobación `ejemplares_disponibles >= 1`, descuento con `decrement('ejemplares_disponibles')` (o `increment(...)` en la devolución) y creación del préstamo, todo sobre el estado ya verificado bajo bloqueo.
  - El modelo `Libro` añade un método de actualización **condicional** para el descuento: `decrement` solo si `ejemplares_disponibles > 0` (cláusula `WHERE` en el `UPDATE`), de modo que la disponibilidad **nunca baja de cero** aunque un camino futuro omitiera el bloqueo.
  - `devolver()` re-verifica dentro de la transacción que el préstamo siga sin devolver (`lockForUpdate()` sobre el préstamo); una devolución repetida rechaza sin tocar disponibilidad.
- **Rationale**: El edge case «dos solicitudes simultáneas para el último ejemplar» exige que solo una consuma el ejemplar y que la rechazada reciba «No hay ejemplares disponibles de este libro.». En MySQL (InnoDB, D-002) `SELECT … FOR UPDATE` serializa las dos transacciones sobre la misma fila: la segunda lee la disponibilidad ya descontada y es rechazada. El decremento condicional es defensa en profundidad para SC-001/SC-002 («la disponibilidad no baja de cero»).
- **Alternatives considered**: Comprobar disponibilidad sin transacción y luego descontar (rechazado: carrera clásica de doble gasto del último ejemplar); `UPDATE … SET disponibilidad = disponibilidad - 1 WHERE disponibilidad > 0` sin lectura previa (rechazado solo: no distingue «sin disponibilidad» del éxito para el mensaje y no serializa el límite por estudiante); bloqueo optimista con columna `version` (rechazado: añade columna y reintentos sin necesidad para una app local de baja concurrencia); `SELECT … FOR UPDATE` sobre la tabla `prestamos` del carné para el límite (rechazado: el límite se re-verifica con un conteo dentro de la misma transacción; la serialización efectiva la aporta el bloqueo de la fila del libro compartido).

## D-023: Identidad del estudiante — carné con `trim()` exclusivo

- **Decision**: El carné se normaliza **solo** eliminando espacios sobrantes al inicio y al final: `RegistrarPrestamoRequest::prepareForValidation()` aplica `trim()`, de modo que el valor almacenado ya está recortado. El límite de tres se cuenta con `where('carnet_estudiante', $carnetNormalizado)` sobre `estado IN ('activo', 'vencido')`. La comparación de carné usa la colación de la columna (case-insensitive por defecto en MySQL 8 con `utf8mb4`); la spec solo pide equivalencia por espacios sobrantes y es lo que se aplica.
- **Rationale**: La spec es literal: «se compara entre registros eliminando **únicamente** espacios sobrantes al inicio y al final». Normalizar al guardar garantiza que la consulta de conteo sea una igualdad simple sin SQL crudo y que «dos registros con el mismo carné (con o sin espacios sobrantes) se consideren el mismo estudiante» (edge case). `trim()` de PHP cubre espacios normales; no se aplica `strtolower`, acentos ni puntuación porque la spec los excluye.
- **Alternatives considered**: `whereRaw('TRIM(carnet) = ?')` (rechazado: introduce SQL crudo cuando la normalización al guardar logra lo mismo con Eloquent puro); normalización agresiva (minúsculas, sin acentos, sin guiones; rechazada: la spec dice «únicamente» espacios sobrantes; además dos carnés distintos en mayúsculas podrían colisionar); entidad `Estudiante` con clave única (rechazado: la spec asume explícitamente que no existe registro independiente de estudiantes; el estudiante vive en el préstamo).

## D-024: Listado paginado de préstamos (10 por página, orden determinista, estados visibles)

- **Decision**: `GET /prestamos` muestra **todos** los préstamos (sin filtros: la búsqueda/filtro es de `006`) paginados a **10 por página** con vista propia `pagination/prestamos.blade.php` (análoga a `pagination/catalogo` de D-011, con «Anterior»/«Siguiente»/números y CSS propio). Orden determinista: `fecha_prestamo DESC → id DESC` (los más recientes primero). Consulta: `Prestamo::with('libro')->orderByDesc('fecha_prestamo')->orderByDesc('id')->paginate(10)->withQueryString()`. Cada fila muestra libro (título + ISBN), nombre y carné del estudiante, las tres fechas, el **estado actual** (tras `sincronizarVencidos()`) como badge de color, y —solo para filas `activo` o `vencido`— el botón «Registrar devolución» hacia `prestamos.form-devolucion`. Estado vacío: «No hay préstamos registrados aún» sin controles de paginación.
- **Rationale**: FR-001 pide consultar los préstamos «y sus estados» para identificar pendientes y devueltos; la paginación de 10 reutiliza el patrón ya entregado y validado en `002` (consistencia visual, Principio V). El orden por fecha descendente es el más útil para supervisar pendientes y es determinista (desempate por `id`). `with('libro')` evita N+1 (Principio IV / D-017). El botón de devolución solo aparece donde la operación tiene sentido, evitando acciones inválidas en filas devueltas.
- **Alternatives considered**: Sin paginación con tabla completa (rechazado: rompe el patrón de 10 por página ya establecido y degrada con el tiempo); reutilizar `pagination/catalogo` por nombre (rechazado: nombre semánticamente falso; un refactor a `pagination/default` sería churn de `002` sin ganancia funcional); orden por estado (vencidos primero; rechazado: no lo pide la spec y mezclaría criterios; el badge ya identifica cada estado); solo préstamos no devueltos por defecto (rechazado: FR-003 pide ver pendientes **y** devueltos; un filtro sería de `006`).

## D-025: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` sobre la base de datos de prueba (D-008), comando `php artisan test`. Cinco suites de característica:

  | Suite | Cubre |
  |---|---|
  | `PrestamoRegistroTest` | Registro válido (estado `activo`, disponibilidad −1), fechas previstas inválidas junto al campo con `old()`, libro inexistente, formulario vacío |
  | `PrestamoReglasTest` | Rechazo sin disponibilidad; límite de 3 contando `activo` + `vencido`; mismo carné con espacios sobrantes; disponibilidad nunca < 0 |
  | `PrestamoDevolucionTest` | Devolución de `activo` y de `vencido` (disponibilidad +1 exactamente una vez), devolución repetida, fecha real ausente o anterior al préstamo |
  | `PrestamoListadoTest` | Datos y estados en el listado, 10 por página, orden determinista, estado vacío |
  | `PrestamoVencimientoTest` | `vencido` desde el día siguiente a la prevista; evaluación al registrar, devolver y listar; `devuelto` inmutable |

  - La carrera del último ejemplar se prueba de forma **determinista**: solicitar el último ejemplar dos veces de forma consecutiva (la segunda, tras el commit de la primera) y verificar rechazo + disponibilidad = 0; la garantía de aislamiento real la aporta el bloqueo de D-022.
- **Rationale**: SC-001…SC-004 exigen evidencia reproducible; las factories de `001` (`PrestamoFactory` con estados `activo`/`devuelto`/`vencido`) permiten montar cada caso sin datos frágiles. Separar por flujo localiza los fallos.
- **Alternatives considered**: Solo verificación manual (rechazado: no es reproducible); tests de navegador/E2E (rechazado: dependencia innecesaria para app Blade; la validación de interfaz es complementaria); un único fichero de tests (rechazado: poco mantenible con ~20+ escenarios); Pest (viable sobre la misma base, se adoptaría si el equipo lo prefiere).

## D-026: Límite de tres préstamos no devueltos por estudiante (carné)

- **Decision**: El límite se evalúa en el servidor dentro de la transacción de registro (D-022) y en la validación de campo (D-021): `prestamosNoDevueltos($carnet)` cuenta los préstamos con `carnet` normalizado y `estado IN ('activo', 'vencido')`; si la cuenta es **3 o más**, el registro se rechaza con el mensaje de límite junto al campo del carné y sin modificar disponibilidad. Los vencidos **cuentan** (clarificación de sesión). Devolver un préstamo libera la plaza de inmediato (su estado pasa a `devuelto` y deja de contar).
- **Rationale**: Clarificación de sesión 2026-10-26 y FR-004 («tres préstamos no devueltos (activos o vencidos) por estudiante»). Contar por carné normalizado aplica el edge case de identidad (D-023). La comprobación bajo la transacción garantiza que dos registros simultáneos no superen el límite si comparten libro (el bloqueo de la fila del libro serializa ambos); con libros distintos, el conteo en la transacción es la mejor aproximación sin tabla de reservas (la app local no modela concurrencia multi-libro extrema).
- **Alternatives considered**: Contar solo `activo` (rechazado: contradice la clarificación — un vencido seguiría ocupando plaza); límite en la interfaz (rechazado: la spec lo declara complementario, no sustitutivo); bloqueo pesimista por carné con tabla `estudiantes` (rechazado: la spec descarta la entidad estudiante; sobre-complicado); trigger/procedimiento almacenado (rechazado: la regla debe vivir en el código centralizado, Principio III).

## D-027: Formulario de registro — selector de libro y fechas

- **Decision**: `GET /prestamos/crear` muestra un formulario con:

  | Campo | Control | Reglas (resumen; detalle en [contracts/ui-structure.md](contracts/ui-structure.md)) |
  |---|---|---|
  | Libro | `<select>` con todos los libros ordenados por título, etiqueta «{título} ({ISBN}) — disponibles: {n}» | `required`, `exists:libros,id`; se muestran también los agotados (su rechazo llega del servidor junto al campo) |
  | Nombre del estudiante | `<input type="text">` | `required`, 2–255 |
  | Carné | `<input type="text">` | `required`, máx. 50 |
  | Fecha de préstamo | `<input type="date">`, valor por defecto **hoy** | `required` |
  | Fecha de devolución prevista | `<input type="date">` | `required`, posterior a la de préstamo |

  - Botón «Registrar préstamo» (`@csrf` incluido); errores junto a cada campo con `old()`; tras éxito, redirección al listado con mensaje flash.
  - El formulario de devolución (`GET /prestamos/{id}/devolver`) muestra el resumen del préstamo (libro, estudiante, carné, fechas, estado) y el campo «Fecha de devolución real» (`required`; no anterior a la fecha de préstamo).
- **Rationale**: La spec no prescribe el control; el `<select>` de libros evita el edge case «libro inexistente» en el uso normal mientras la validación `exists` lo sigue cubriendo en el servidor (el rechazo de libro inexistente es un requisito del servidor, no de la UI). Mostrar la disponibilidad en cada opción informa al usuario sin obligar a ir al catálogo; los agotados se muestran porque su rechazo es un mensaje explícito que la spec exige («No hay ejemplares disponibles de este libro.»). La fecha de préstamo por defecto hoy refleja el caso de uso típico sin restringir fechas pasadas (la spec no lo limita).
- **Alternatives considered**: Campo numérico de `libro_id` (rechazado: propicia el edge case de libro inexistente en el flujo normal y es peor UX); autocomplete con JS (rechazado: dependencia innecesaria, la app es SSR con CSS propio); ocultar los libros agotados del selector (rechazado: la spec exige que el intento sobre libro sin disponibilidad muestre un mensaje junto al campo, y la carrera del último ejemplar puede ocurrir aunque estuviera oculto); `<input type="date">` nativo en lugar de datepicker JS (rechazado el datepicker: nativo, accesible y sin dependencias).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, PHPUnit (heredado D-001/D-008/D-011) |
| Storage | MySQL 8.x local (heredado D-002); esquema de `prestamos` ya creado en `001`; sin migraciones nuevas (D-018…D-027) |
| Testing | PHPUnit + `RefreshDatabase` sobre MySQL de prueba (heredado D-008; suites de `004`: D-025) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de filas; listado paginado + eager loading y sincronización de vencidos por operación (D-020, D-024) |
| Constraints | Sin servicios de pago; credenciales fuera del repo (heredado D-007); mensajes en español junto al campo con `old()` (D-021); préstamos inmutables (D-018); disponibilidad ≥ 0 ante concurrencia (D-022) |
| Scale/Scope | 5 rutas, 1 controlador, 2 Form Requests, 1 servicio, 3 vistas, ~5 suites; sin cambios de esquema (D-018…D-027); CRUD de libros (`003`), portadas (`005`), búsqueda (`006`) y panel (`007`) fuera de alcance |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
