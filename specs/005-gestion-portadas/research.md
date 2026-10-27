# Research: 005-gestion-portadas (Gestión de portadas de libros)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa la de `001` (D-001…D-009) y `002` (D-010…D-017)**; aquí se añaden D-018…D-026. Las decisiones de stack heredadas (PHP 8.3+, Laravel 13, MySQL 8, PHPUnit, layout, imagen predeterminada) se reutilizan sin cambios.

---

## D-018: Almacenamiento de los archivos de portada

- **Decision**: Disco **`public`** de Laravel Storage, con un directorio propio por libro y un nombre único por archivo:
  - Ruta física: `storage/app/public/portadas/{libro_id}/{identificador_unico}.{jpg|png|webp}`.
  - Ruta guardada en `libros.portada` (columna `varchar(255) NULL` creada en `001`): valor **relativo al disco**, p. ej. `portadas/7/9f3c….webp`.
  - Las imágenes **no se exponen mediante el enlace simbólico `/storage`**: se sirven con `GET /portadas/{id}` a través del controlador (D-023), que responde con `Storage::disk('public')->response($ruta)`. Por tanto `php artisan storage:link` no es requisito de esta funcionalidad.
  - El directorio `portadas/` se crea bajo demanda (`Storage::makeDirectory`) y queda fuera del versionado (el `.gitignore` de `storage/` heredado de `001` lo cubre).
- **Rationale**: FR-003 y la constitución (Restricciones → *Imágenes*) exigen Laravel Storage con la ruta en la base de datos. Un directorio por libro hace que el borrado del libro retire **todo** su contenido de una sola vez (FR-004, D-022) y que la reconciliación de huérfanos sea local (D-020). El nombre único por archivo permite que «el archivo anterior deje de existir» literalmente (SC-002) y que dos escrituras concurrentes no colisionen en la misma ruta. Servir por controlador en lugar de `/storage/…` es lo que hace posible exigir sesión (FR-007) sin MIDDLEWARE extra sobre ficheros estáticos.
- **Alternatives considered**: Enlace simbólico + ficheros públicos bajo `public/storage` (rechazado: cualquier persona con la URL vería la imagen sin sesión, viola FR-007); disco `local` privado (rechazado: la constitución pide Storage y la columna ya está pensada para la ruta, y el disco `public` es el convencional para imágenes de la app); nombre determinista `portadas/{id}/portada.{ext}` (rechazado: la extensión cambia entre formatos y «el archivo anterior» seguiría existiendo como ruta, en tensión con el escenario de aceptación 2 de la Historia 1; además un reemplazo concurrente pisaría sin dejar constancia del archivo a borrar); guardar la imagen en base de datos (rechazado: la constitución exige Storage).

## D-019: Validación del archivo en el servidor (Form Request)

- **Decision**: Las reglas de portada viven en el **Form Request de edición de libros** (entregado por `003-gestion-libros`), que `005` amplía con:
  - `'portada' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']`.
  - `mimes` de Laravel **analiza el contenido real** del archivo (adivina la extensión a partir del MIME detectado), no la extensión del nombre: un `.jpg` cuyo contenido no sea imagen válida se rechaza (edge case de la spec).
  - `max:2048` es **inclusivo** en KB (2048 KB = 2 MB exactos): un archivo de exactamente 2 MB se acepta (edge case / supuesto de la spec).
  - Mensajes personalizados **en español** en el propio Form Request, mostrados junto al campo `portada`; los demás campos conservan sus valores con `old()` (Principio II).
  - Solo se procesa el archivo si `$request->hasFile('portada')`: guardar otros datos del libro sin elegir archivo no toca la portada (FR-001, clarificación de sesión).
- **Rationale**: FR-002 y Principio II de la constitución exigen validación en el servidor con errores en español junto al campo. Centralizar las reglas en el Form Request compartido con `003` evita duplicarlas entre registro y edición (el registro de `003` ya valida portada con las mismas reglas básicas).
- **Alternatives considered**: Validación en JavaScript/`accept` del input (rechazada como única vía: es evitable; se mantiene solo como ayuda); reglas en el controlador con `$request->validate()` (rechazado: la constitución y el patrón del proyecto usan Form Requests); MIME por extensión de nombre con `mimetypes:` en frío (rechazado: `mimes` ya hace sniffing del contenido y da el mensaje amigable); intervención de un servicio de imágenes (rechazado: la constitución no pide recorte/redimensión — supuesto de la spec).

## D-020: Reemplazo de portada y ausencia de huérfanos (incluida concurrencia)

- **Decision**: El reemplazo es una secuencia fija en `LibroController@update` (ampliado por `005`), ejecutada **solo** si llega un archivo nuevo:
  1. Capturar la ruta anterior (`$libro->portada`).
  2. **Guardar primero** el archivo nuevo (si falla → mensaje de error y se aborta: la portada existente y el registro quedan intactos, FR-006).
  3. Actualizar `libros.portada` con la nueva ruta (si falla → se elimina el archivo recién guardado como compensación y se muestra el error; el libro queda como estaba).
  4. **Borrar el archivo anterior** capturado.
  5. **Reconciliar**: eliminar todo archivo del directorio `portadas/{id}` que no coincida con la ruta vigente en la base de datos.
  - Concurrencia (clarificación: «predomina la última escritura; el archivo desplazado se elimina siempre»): como cada operación termina con la reconciliación del paso 5, la operación cuyo `UPDATE` es el último deja su archivo como único; las reconciliaciones posteriores —incluida la del propio último escritor— borran cualquier archivo que no sea el vigente. Cada libro sigue teniendo como máximo un archivo en disco (SC-005).
  - La eliminación explícita (D-021) sigue el mismo patrón: borrar archivo → retirar la referencia en BD → reconciliar.
- **Rationale**: El orden «guardar → persistir BD → borrar el anterior» garantiza que ningún fallo deja al libro apuntando a un archivo inexistente, y que un rechazo jamás altera la portada previa (FR-006, SC-003). La reconciliación es lo que convierte la última escritura en «sin huérfanos» incluso con escrituras superpuestas (SC-005), sin necesidad de bloqueos o transacciones distribuidas entre disco y BD.
- **Alternatives considered**: Borrar antes de guardar (rechazado: un fallo de escritura dejaría al libro sin portada, viola FR-006); transacción de BD que cubra el disco (imposible: el disco no participa en transacciones de MySQL); bloqueo pesimista con `lockForUpdate` sobre el fila (rechazado: no protege el disco frente a procesos fuera de la petición y complica la app sin eliminar la necesidad de reconciliar); dejar la reconciliación a una tarea programada (rechazado: introduce cron y deja huérfanos visibles entre ejecuciones, la spec exige que no queden).

## D-021: Eliminación de portada — ruta `DELETE` y confirmación

- **Decision**:
  - Ruta **`DELETE /libros/{id}/portada`** → `PortadaController@destroy`, nombre `libros.portadas.destroy`.
  - Formulario (solo en la vista de edición, ver D-024) con `@csrf`, `@method('DELETE')` y un botón «Eliminar portada» que dispara `confirm()` con el texto de [contracts/user-messages.md](contracts/user-messages.md) §3 antes de enviar; el botón solo se renderiza si el libro tiene portada.
  - Acción: borra el archivo, retira `portada` del libro (`$libro->portada = null; $libro->save();`), reconcilia el directorio y redirige al formulario de edición con mensaje de éxito; sin portada previa → HTTP 404 (vista `errors/404` heredada de `002`).
  - Fallo de borrado en disco → mensaje «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo y **la referencia y el archivo permanecen como estaban** (edge case de fallo de almacenamiento).
  - El catálogo y el detalle **no** reciben ningún control de portada (clarificación de sesión: la vista de detalle queda excluida).
- **Rationale**: FR-005 y Principio IV exigen `DELETE` (nunca GET) con confirmación; el método de formulario es la vía estándar de Laravel para DELETE sobre HTML. Limitar los controles a la edición cumple la clarificación y evita duplicar superficie de escritura.
- **Alternatives considered**: `GET /libros/{id}/portada/delete` (rechazado: lo prohíbe la constitución y FR-005); enlace con `onclick` que envía fetch sin formulario (rechazado: pierde el patrón de formularios con `@csrf` del proyecto); checkbox «Eliminar portada» dentro del submit de edición (rechazado: mezcla dos operaciones y complica garantizar que editar otros datos no toque la portada); diálogo nativo `window.confirm` sin `DELETE` (rechazado: la confirmación sin el método correcto sigue violando FR-005).

## D-022: Eliminación de la portada al eliminar el libro

- **Decision**: Hook en el modelo — `Libro` define un evento `deleting` (método `booted()` → `static::deleting`) que elimina el directorio `portadas/{id}` completo (`Storage::disk('public')->deleteDirectory("portadas/{$libro->id}")`) antes de que se borre la fila. El hook es tolerante a fallos de disco (un fallo no debe impedir eliminar el libro según el edge case de almacenamiento; el error se registra y la reconciliación futura del directorio no aplica porque ya no hay fila).
- **Rationale**: FR-004/SC-005 exigen que eliminar el libro elimine también su archivo. El hook en el modelo cubre **toda** vía de borrado (controlador, tinker, tests, seeders) sin acoplarse a `LibroController@destroy` de `003`, y mantiene la regla en un solo lugar (estilo Principio III).
- **Alternatives considered**: Borrado solo en `LibroController@destroy` (rechazado: cualquier otro camino dejaría huérfanos, incumpliendo SC-005); política `ON DELETE CASCADE` de BD sobre archivos (no existe: los archivos no están en MySQL); job en cola tras el borrado (rechazado: introduce colas/redis sin necesidad y permite huérfanos transitorios visibles).

## D-023: Acceso a las imágenes solo con sesión (FR-007)

- **Decision**: Ruta **`GET /portadas/{id}`** → `PortadaController@show`:
  - **Sin sesión**: responde **HTTP 401** con la vista `resources/views/errors/401.blade.php` (extiende el layout común) y el mensaje en español «Debe iniciar sesión para ver las imágenes de portadas»; la imagen **no se sirve**. Si la ruta `login` ya existe (entregada por `008-interfaz-validacion-seguridad`), la vista muestra un enlace hacia ella.
  - **Con sesión**: si el libro no existe o no tiene `portada` registrada → HTTP 404 (vista `errors/404` de `002`); si la ruta registrada no existe en disco → HTTP 404; si existe → `Storage::disk('public')->response($ruta)` con el MIME derivado del archivo.
  - El parcial `partials/portada.blade.php` (D-014 de `002`) se amplía: cuando hay portada, el `src` pasa de `Storage::url()` a `route('portadas.show', $libro)`; se conserva el `onerror` que sustituye por la imagen predeterminada — así, para un visitante sin sesión el `<img>` recibe el 401 y muestra la imagen predeterminada (asset público) en lugar de un icono roto.
  - La imagen predeterminada sigue siendo `public/images/libro-sin-portada.svg` accesible sin sesión (es un asset genérico del sistema, no una portada).
- **Rationale**: La clarificación de sesión y SC-006 exigen que la URL directa de una portada sin sesión no sirva la imagen y solicite el inicio de sesión. Un **401 con vista en español** satisface literalmente «redirigen **o** solicitan el inicio de sesión» y es determinista: no depende de que exista la ruta `login` de `008` (funcionalidad aún no implementada), de modo que `005` puede probarse de forma independiente con `assertStatus(401)`. Servir por controlador (no `/storage`) es lo que permite aplicar la comprobación de sesión.
- **Alternatives considered**: Middleware `auth` con redirección a `route('login')` (rechazado como única vía: antes de `008` la ruta no existe y Laravel respondería 500; además una redirección servida dentro de un `<img>` aterrizaría en HTML del login — el `onerror` lo cubriría, pero el resultado es indeterminado para las pruebas); proteger la ruta simbólica `/storage` con un middleware global (rechazado: `php artisan serve` y el servidor de desarrollo no aplican middleware de Laravel a ficheros estáticos servidos desde `public/`); exigir sesión en todo el catálogo como sustituto (rechazado: `002` es de Fase 1 sin autenticación y FR-007 habla específicamente de las imágenes); *basic auth* o tokens en la URL (rechazado: la spec pide sesión y el login llega con `008`).

## D-024: Textos alternativos y ubicación de los controles

- **Decision**:
  - `partials/portada.blade.php` emite `alt` = **título del libro** cuando hay portada y `alt` = texto genérico **«Sin portada»** cuando se muestra la imagen predeterminada (texto fijado en [contracts/user-messages.md](contracts/user-messages.md) §4; se mantiene el contrato de `002` y se cumple ahora con el criterio de clarificación «el del título del libro»).
  - Los controles de reemplazo (input `file` con la leyenda del campo) y de eliminación (botón con confirmación) viven **únicamente** en `resources/views/libros/edit.blade.php`; `libros/show` y el listado no los incluyen.
- **Rationale**: FR-008 y SC-007 (alt = título; genérico en la predeterminada) y la clarificación que excluye a la vista de detalle de controles de gestión.
- **Alternatives considered**: `alt` = «Portada de {título}» (rechazado: la clarificación dice que el valor **es** el título del libro); `alt` vacío (rechazado: viola SC-007 y WCAG 2.1 AA de `008`); controles en la ficha de detalle (rechazado explícitamente por la clarificación de sesión).

## D-025: Manejo de fallos de almacenamiento

- **Decision**: Toda operación de disco (guardar, borrar archivo, borrar directorio) se envuelve en manejo de excepciones en el controlador:
  - **Guardar/reemplazar**: fallo → mensaje «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo `portada`, sin cambios en la BD ni en el archivo previo; si el fallo ocurre al persistir la BD tras guardar el archivo nuevo, el archivo nuevo se elimina (compensación) y se muestra el mismo mensaje.
  - **Eliminar portada**: fallo → mismo mensaje junto al campo, la referencia de BD y el archivo permanecen intactos.
  - **Actualizar otros datos del libro con fallo de portada no relacionado**: no aplica — sin `hasFile()` no hay operación de disco.
  - Los mensajes no exponen rutas, trazas ni detalles técnicos (Principio IV).
- **Rationale**: FR-006, el edge case «fallo del almacenamiento» y SC-003 exigen el mensaje exacto en español junto al campo y la portada existente intacta; el edge case de «puerto o red interrumpidos» se cubre igual porque el fallo ocurre antes de tocar la BD.
- **Alternatives considered**: Dejar que la excepción burbujea a la página 500 (rechazado: mensaje técnico y posibles estados intermedios); registrar el error y borrar igualmente la referencia (rechazado: dejaría el libro sin portada por un fallo transitorio); reintentos automáticos (rechazado: complejidad injustificada; el mensaje invita al usuario a reintentar).

## D-026: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` (heredado D-008), usando **`Storage::fake('public')`** en todas las suites de portadas (aisla el disco real y permite asertar archivos con `Storage::assertExists` / `assertMissing` / `assertDirectoryEmpty`). Suites:
  - `PortadaSubidaTest` — asociar portada a libro sin portada, reemplazarla, los 3 formatos aceptados, 2 MB exactos aceptado, >2 MB rechazado, GIF/SVG/TIFF rechazados, contenido no imagen con extensión válida rechazado, error junto al campo y valores conservados, edición sin archivo no toca la portada.
  - `PortadaEliminacionTest` — `DELETE` elimina archivo y referencia y muestra la predeterminada; intento de eliminación por GET no elimina nada; portada inexistente → 404; eliminación del libro retira el directorio.
  - `PortadaAccesoTest` — sin sesión: 401 y cuerpo con el mensaje en español (nunca los bytes de la imagen); con sesión: 200 y contenido correcto; URL de portada de libro sin portada → 404; `alt` = título en listado y detalle; `alt` genérico en la predeterminada.
  - `PortadaFalloAlmacenamientoTest` — con `Storage` mockeado para fallar (`Storage::shouldReceive('putFileAs')->andThrow(...)` sobre la fachada, o disco parcial): guardar/borrar fallidos → mensaje exacto, portada previa intacta, libro intacto.
  - `PortadaConcurrenciaTest` — dos reemplazos secuenciales superpuestos (el segundo captura la ruta antigua antes del primer `UPDATE`): solo queda el último archivo; tras cada operación, `portadas/{id}` contiene exactamente un archivo (SC-005).
  - Casos de red interrumpida se prueban como fallo de escritura (mismo camino de código).
- **Rationale**: SC-001…SC-007 exigen evidencia reproducible; `Storage::fake` es el mecanismo oficial de Laravel para probar Storage sin tocar disco real; el mock de fallo es la única forma práctica de provocar un fallo de almacenamiento determinista.
- **Alternatives considered**: Tests contra el disco real (rechazado: deja archivos y depende del entorno); solo pruebas manuales (rechazado: no reproducible para SC-001/SC-005/SC-006); Pest (viable, se adoptaría si el equipo lo prefiere, misma base); tests E2E de navegador (rechazado: dependencia innecesaria; los 401/401 y formularios se prueban con peticiones de prueba).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, Laravel Storage, PHPUnit (heredado D-001/D-008; D-018, D-026) |
| Storage | MySQL 8.x sin migraciones nuevas (`libros.portada` ya existe, D-003) + disco `public` de Storage en `portadas/{id}/` (D-018) |
| Testing | PHPUnit + `RefreshDatabase` + `Storage::fake` y mocks de fallo (heredado D-008; D-026) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009); imágenes con sesión (D-023) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de libros; una E/S de archivo por operación; sin lecturas de disco por fila en el listado (D-018, D-020) |
| Constraints | 2 MB inclusivo y 3 formatos validados en servidor (D-019); mensajes en español junto al campo e integridad ante fallos (D-025); DELETE con confirmación (D-021); sin servicios de pago (heredado) |
| Scale/Scope | 2 rutas nuevas + `update` ampliado, 1 controlador nuevo, 2 vistas ampliadas + 1 vista 401, 0 migraciones, 5 suites (D-018…D-026); CRUD completo en `003`, login en `008` |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
