# Research: 008-interfaz-validacion-seguridad (Interfaz, validación y seguridad transversales)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. Numeración continuada desde `006-busqueda-catalogo` (D-028…D-035).

---

## D-036: Alcance transversal y reparto por fases

- **Decision**: `008` **no reimplementa** vistas ni reglas de negocio: audita y homogeneiza las vistas y formularios de `002`–`007` (layout común, etiquetas, errores junto al campo, estados vacíos, CSS) y añade la capa de autenticación. La estructura visual común se entrega/verifica en **Fase 1** (FR-008); los formularios con validación completa, el diseño adaptable y el inicio de sesión se completan en **Fase 2** junto con los módulos que los usan.
- **Rationale**: FR-001/FR-008 definen la estructura común como requisito compartido de la Fase 1 y el resto como Fase 2; la spec declara explícitamente que no añade entidades ni reglas propias (Key Entities y supuestos). Homogeneizar en un único sitio evita que cada módulo resuelva por su cuenta los errores, los estados vacíos y la accesibilidad.
- **Alternatives considered**: reescribir todas las vistas desde `008` (rechazado: duplica el trabajo de `002`–`007` y rompe sus contratos); crear una librería de componentes JS (rechazado: añade dependencias y build sin necesidad, frente a Blade + CSS propio).

## D-037: Autenticación propia con el guard estándar de Laravel

- **Decision**: inicio de sesión propio con el guard `auth` de Laravel: rutas `GET /login`, `POST /login` y `POST /logout` en `AuthController`, autenticación con `Auth::attempt()` (verificación con `Hash`), middleware `auth` sobre **todas** las rutas de negocio, tabla `users` creada con migración (o reutilizada del esqueleto si ya existe) y cuentas de prueba en `UserSeeder` **creadas por el equipo** (no hay registro público). El modelo `User` **no define roles ni permisos**: todos los usuarios autenticados pueden hacer las mismas operaciones (supuesto de la spec).
- **Rationale**: FR-009 exige inicio de sesión único para todos los usuarios antes de operar; el Principio V fija Laravel como framework, cuyo guard estándar aporta exactamente esta funcionalidad sin dependencias externas. Las cuentas las crea el equipo (supuesto), por lo que no se necesita flujo de registro ni recuperación de contraseña.
- **Alternatives considered**: Laravel Breeze/UI/Spark (rechazados: añaden scaffolding, npm y paquetes innecesarios para un login de dos campos, y forzarían un pipeline de build ajeno a «CSS propio y sin servicios de pago»); paquete de terceros (rechazado: misma razón y más superficie de mantenimiento); sin persistencia de usuarios o credenciales en `.env` (rechazado: FR-009 y la prohibición de credenciales en el repositorio impiden un login por contraseña fija en config).

## D-038: Caducidad de sesión a las 2 horas de inactividad

- **Decision**: `SESSION_LIFETIME=120` minutos en `.env.example` y `config/session.php` (el valor por defecto del esqueleto se sustituye). Laravel actualiza la actividad en cada petición, de modo que 120 minutos sin peticiones caducan la sesión; la sesión caducada deja de estar autenticada y el middleware `auth` redirige a `/login` **antes** de ejecutar cualquier operación. Se mantiene el driver de sesión por defecto del esqueleto.
- **Rationale**: la clarificación del 2026-10-26 fija «2 horas de inactividad»; FR-009/SC-005 exigen que al caducar se pida iniciar sesión de nuevo antes de procesar cualquier operación. El mecanismo nativo de Laravel ya interpreta `lifetime` como tiempo de inactividad, sin código propio.
- **Alternatives considered**: middleware personalizado de caducidad (rechazado: duplica la lógica nativa y puede divergir); dos horas absolutas desde el login (rechazado: la clarificación habla de inactividad, no de duración total); JavaScript que cierre la sesión en el cliente (rechazado: no es una garantía de servidor).

## D-039: Fallo de inicio de sesión — mensaje genérico, sin bloqueo ni espera

- **Decision**: un **único** mensaje en español —«Correo o contraseña incorrectos.»— idéntico exista o no la cuenta, sin bloqueo de la cuenta ni de la IP, sin esperas añadidas (sin `sleep()` ni rate limiting) y sin revelar la existencia de la cuenta; se muestra junto al formulario de login conservando el correo introducido (`old()`). Implementado con `Auth::attempt()` estándar.
- **Rationale**: clarificación del 2026-10-26 («mensaje genérico en español, sin bloquear ni esperar»), FR-010 y SC-006. Un mensaje diferenciado permitiría enumerar cuentas; un bloqueo o una espera añadida contradice la clarificación.
- **Alternatives considered**: mensajes distintos para usuario inexistente vs. contraseña errónea (rechazado: revela la existencia de la cuenta); rate limiting con `throttle` o bloqueo tras N intentos (rechazado: la clarificación prohíbe bloquear); `sleep()` como mitigación de fuerza bruta (rechazado: espera añadida prohibida); captcha (rechazado: dependencia externa y fricción innecesaria para una app local).

## D-040: Fallo de protección CSRF — mensaje confirmado junto al formulario

- **Decision**: middleware `VerifyCsrfToken` de Laravel activo para **todas** las rutas de escritura, sin entradas en `$except`. Ante un token ausente, caducado o manipulado (`TokenMismatchException`), `bootstrap/app.php` (`withExceptions`) redirige a la vista de origen con el mensaje **exacto** —«Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.»— mostrado **en español junto al formulario**, conservando los valores introducidos (`withInput()`) y **sin guardar ningún dato**.
- **Rationale**: FR-005 y el edge case de la spec fijan el texto y la presentación; la clarificación del 2026-10-26 confirma el mensaje por defecto tal cual. El 419 nativo no cumple: no está en español ni se presenta junto al formulario con los valores conservados.
- **Alternatives considered**: excluir rutas del CSRF (rechazado: viola FR-005); dejar la página 419 por defecto (rechazado: incumple el texto y la ubicación exigidos); validar el token con JS en el cliente (rechazado: la validación debe ser del servidor y el token viaja en la sesión).

## D-041: Patrón transversal de validación y errores junto al campo

- **Decision**: todo formulario del sistema (login, crear/editar libro, crear/editar categoría, registrar préstamo, devolver préstamo, subir portada) usa **Form Request** con `$request->validated()` y redirección `back()->withErrors()->withInput()` ante rechazo; cada error se muestra inmediatamente **después de su campo**, con `@error` + `aria-invalid`/`aria-describedby`, y el valor introducido se conserva con `old()`. Los mensajes y atributos en español siguen en `lang/es/validation.php` (heredado de `003` D-024). La búsqueda de `006` mantiene su patrón sin botón, con etiqueta y contraste exigidos por `008`. Ante varios campos inválidos se muestran **todos** los errores.
- **Rationale**: FR-003/FR-004, Principio II y SC-002. Un patrón único hace verificable el 100 % de campos inválidos con una sola suite transversal (D-047).
- **Alternatives considered**: validación solo en el cliente (rechazado: Principio II, puede eludirse); resumen de errores solo en cabecera de página (rechazado: FR-004 exige el error **junto al campo**); mensajes flash genéricos (rechazado: no identifican el campo); librería de componentes de formularios (rechazado: dependencia innecesaria con Blade).

## D-042: Escapado de salida verificable

- **Decision**: toda salida de datos se renderiza con `{{ }}`; `{!! !!}` queda **prohibido** para datos de usuario y solo se tolera para salidas generadas por Laravel ya escapadas por el framework (p. ej. el HTML del paginador). La regla se hace verificable con `EscapadoSalidaTest`, que audita los archivos de `resources/views` y falla ante cualquier `{!! !!}` distinto de la lista corta de excepciones.
- **Rationale**: FR-005 y Principio IV («las salidas se escapan con `{{ }}`»); los edge cases de `<script>` en títulos y de texto con órdenes SQL exigen que el texto de usuario se muestre como texto y no se ejecute.
- **Alternatives considered**: escapado manual con `e()` en cada punto (rechazado: propenso a olvidos; `{{ }}` ya lo hace por defecto); confiar en la revisión visual (rechazado: no es automatizable para SC-003); sanitizer de HTML en todas las salidas (rechazado: añade dependencia y restringe de más un sistema que no debe permitir HTML de usuario).

## D-043: Eliminaciones solo con `DELETE` y confirmación previa

- **Decision**: toda eliminación se envía desde un `<form>` con `@csrf` y `@method('DELETE')`, tras **confirmación JavaScript** en español (`confirm()`), y se resuelve con Eloquent. **No existen rutas de eliminación por GET**: solicitar por `GET` la URL de una eliminación responde **405 Method Not Allowed** y no modifica ni elimina registros (repetidas incluidas). Ante un fallo de la eliminación se muestra un mensaje en español y el registro permanece intacto.
- **Rationale**: FR-007 y SC-004; Principio IV («nunca mediante GET» y «se solicita confirmación»). El 405 es la respuesta nativa de Laravel ante un método no permitido y es comprobable con tests.
- **Alternatives considered**: eliminación por GET con token en la URL (rechazado: la spec prohíbe el GET, no solo el GET «inseguro»); `DELETE` vía `fetch()` sin formulario (rechazado: añade JS crítico y complica la confirmación accesible; el form con `@method` ya es una petición DELETE); soft deletes (rechazado: fuera de alcance y no los pide la spec).

## D-044: Diseño adaptable con breakpoints propios

- **Decision**: CSS propio (`public/css/`) con media queries en tres rangos verificados: **≤ 640 px** (teléfono 360 × 640), **641–1023 px** (tableta 768 × 1024 en vertical) y **≥ 1024 px** (escritorio). Rejillas flexibles, tablas con desplazamiento propio o apiladas, formulario de una columna en móvil y sin recortes ni desbordamientos horizontales en los tres tamaños.
- **Rationale**: FR-002/FR-008 y SC-001 fijan exactamente esos tres tamaños (confirmados en la clarificación del 2026-10-26). La constitución permite CSS propio sin frameworks, que es la decisión heredada del proyecto (D-011).
- **Alternatives considered**: Bootstrap o Tailwind (rechazado por defecto: la decisión heredada es CSS propio; si el equipo optara por alguno, FR-001 obliga a declararlo en el README); un único diseño responsive implícito sin breakpoints definidos (rechazado: los tamaños de verificación son parte de SC-001); aplicación nativa separada (rechazado: la constitución fija una app web local).

## D-045: Accesibilidad WCAG 2.1 nivel AA

- **Decision**: cumplimiento **completo** WCAG 2.1 AA: toda campo visible tiene `<label for>` asociado (incluidos select y textarea), las funciones son operables con teclado (enlaces y formularios nativos, confirmación de eliminación accesible, orden de tabulación lógico), el texto alcanza contraste **≥ 4,5:1** en la paleta del CSS propio, el foco es visible, los errores se anuncian con `aria-invalid`/`aria-describedby`, el documento declara `lang="es"` y se usan títulos de sección jerárquicos.
- **Rationale**: clarificación del 2026-10-26 («cumplimiento completo WCAG 2.1 nivel AA»), FR-011 y SC-007.
- **Alternatives considered**: solo contraste de color (rechazado: la clarificación exige el nivel AA completo); contraste verificado solo a ojo (rechazado: SC-007 pide evidencia; la paleta se revisa con una herramienta de contraste y se documenta en el quickstart); librería de accesibilidad o axe integrado en PHP (rechazado: requiere navegador; la verificación automatizable por PHPUnit cubre etiquetas/aria/teclado y la revisión visual de contraste queda documentada en `quickstart.md`).

## D-046: Estados vacíos comunes «No hay registros» + acción

- **Decision**: parcial compartido `partials/estado-vacio.blade.php` que muestra un mensaje en español tipo **«No hay registros»** junto a la **acción disponible** para crear el primer registro (p. ej. «Añadir libro»/«Nuevo libro»). Se aplica a los listados de libros, categorías y préstamos; el catálogo de `002` conserva su mensaje «No hay libros registrados aún» (ya indica que no hay datos) y añade la acción cuando corresponda. Nunca se muestra un área vacía sin explicación.
- **Rationale**: FR-012, SC-008 y la clarificación del 2026-10-26 (mensaje tipo «No hay registros» junto a la acción disponible). Un parcial único garantiza el mismo patrón en los tres listados y hace el SC-008 verificable con una suite.
- **Alternatives considered**: mensaje suelto en cada vista (rechazado: permite divergencias y no es auditable de forma conjunta); ilustración sin texto (rechazado: el mensaje en español es obligatorio); ocultar la zona (rechazado: deja un área vacía sin explicación).

## D-047: Estrategia de pruebas transversales

- **Decision**: nueve suites de característica con `RefreshDatabase` sobre la base de datos de prueba MySQL (heredado D-008): `AuthAccesoTest`, `AuthLoginTest`, `SesionCaducadaTest` (viaja en el tiempo con `travel()`), `CsrfProteccionTest`, `EscapadoSalidaTest` (auditoría de plantillas), `EliminacionDeleteTest`, `ValidacionTransversalTest`, `AccesibilidadUiTest` (etiquetas, `aria`, estructura común) y `EstadosVaciosTest`. El contraste exacto y la ausencia de desbordamientos en 360/768/1024 px se verifican manualmente según los escenarios de `quickstart.md`.
- **Rationale**: los SC-001…SC-008 exigen evidencia reproducible (`100 %`); `php artisan test` es el comando único heredado. Lo que PHPUnit no puede medir (viewport real, contraste percibido) se documenta como escenario manual ejecutable.
- **Alternatives considered**: Laravel Dusk con navegador real (rechazado: añade dependencias de navegador y CI pesada para una app local; la verificación visual queda en el quickstart); solo pruebas manuales (rechazado: SC-003/004/005/006 piden evidencia automatizable); tests de solo una funcionalidad (rechazado: `008` es transversal y debe cubrir los formularios de `003`–`005` de forma uniforme).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, guard `auth` nativo (D-001, D-037) |
| Storage | MySQL 8.x local; migración `users` como único esquema nuevo; `SESSION_LIFETIME=120` (D-002, D-037, D-038) |
| Testing | PHPUnit + RefreshDatabase, ~9 suites transversales (D-047) |
| Target Platform | Local, `php artisan serve`, con `auth` en todas las rutas salvo `/login` (D-037) |
| Project Type | Web app Laravel (monolito Blade) (D-001) |
| Performance Goals | Sin requisitos de tráfico; sin esperas añadidas en login (D-039) |
| Constraints | CSRF sin exenciones, escapado `{{ }}`, DELETE con confirmación, errores en español con `old()`, WCAG 2.1 AA (D-040…D-045) |
| Scale/Scope | 3 rutas, 1 controlador, 1 Form Request, 1 migración, 1 seeder, ~9 suites; sin cambios en reglas de negocio (D-036, D-037) |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
