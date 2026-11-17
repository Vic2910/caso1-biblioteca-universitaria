# Research: 003-gestion-libros (Gestión de libros y categorías)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento. La numeración de decisiones **continúa la de `001-entorno-datos` (D-001…D-009) y `002-consulta-catalogo` (D-010…D-017)**; aquí se añaden D-018…D-025. Las decisiones de stack heredadas (D-001…D-017) se reutilizan sin cambios.

---

## D-018: Rutas y controladores del CRUD

- **Decision**: Dos controladores nuevos, sin autenticación, en `routes/web.php`:
  - **Libros** (gestión sobre el catálogo de `002`):
    - `GET /libros/crear` → `LibroController@create`, nombre `libros.create`.
    - `POST /libros` → `LibroController@store`, nombre `libros.store`.
    - `GET /libros/{id}/editar` → `LibroController@edit`, nombre `libros.edit`.
    - `PUT /libros/{id}` → `LibroController@update`, nombre `libros.update`.
    - `DELETE /libros/{id}` → `LibroController@destroy`, nombre `libros.destroy`.
    - Las rutas de consulta (`GET /catalogo`, `GET /catalogo/{id}`) y su controlador quedan **sin cambios**; el listado y la ficha añaden en sus vistas los botones «Nuevo libro», «Editar» y «Eliminar» (FR-001).
  - **Categorías** (vista propia, prefijo `/categorias`, nombres `categorias.*`): `index`, `create`, `store`, `edit`, `update`, `destroy` con la misma forma REST.
  - La navegación del layout común incorpora el enlace «Categorías» junto a «Catálogo» (D-015).
- **Rationale**: FR-001 exige gestionar desde las vistas de la Fase 1, por lo que se reutilizan `catalogo/*` y solo se crean las acciones de escritura. `libros.*` ocupa el espacio que `002` (D-010) reservó deliberadamente para el CRUD. Categorías tiene vista propia (clarificación Q7), separada del catálogo para no mezclar la gestión de la clasificación con la de libros.
- **Alternatives considered**: Un único `LibroCategoriaController` (rechazado: dos entidades con flujos distintos, controllers pequeños y cohesivos); rutas anidadas `/catalogo/libros/crear` (rechazado: mezcla rutas de consulta con de escritura y complica los nombres de ruta); controladores API JSON + fetch (rechazado: la constitución exige Blade SSR con layout común).

## D-019: Form Requests y reglas de validación de `Libro`

- **Decision**: `LibroStoreRequest` y `LibroUpdateRequest` con las reglas del servidor (FR-003/006/007); el controlador usa `$request->validated()` y nunca `$request->all()`:
  - `isbn`: `required`, `string`, `regex:/^[0-9]{13}$/`, `unique:libros,isbn` (en update: `Rule::unique('libros','isbn')->ignore($libro->id)`). Se recibe como **texto** (`type="text"`, `inputmode="numeric"`) para conservar ceros iniciales (edge case); la comparación de unicidad usa la colación de la columna.
  - `titulo`, `autor`: `required`, `string`, `min:2`, `max:255`.
  - `editorial`: `required`, `string`, `max:255`.
  - `anio_publicacion`: `required`, `integer`, `min:1900`, `max:<año actual>` calculado en el momento de la petición con `now()->year` (supuesto: el año actual es el del momento de la operación).
  - `ejemplares_totales`: `required`, `integer`, `min:1`; en **update** se añade la regla de negocio de D-023 (`>= préstamos activos`).
  - `categoria_id`: `required`, `integer`, `exists:categorias,id`.
  - `portada`: `nullable`, `file`, `mimes:jpg,jpeg,png,webp`, `max:2048` (D-021).
  - **No existe regla ni campo para `ejemplares_disponibles`**: el formulario no lo captura y `$fillable` lo excluye (clarificación Q1).
  - Ante error: `back()->withErrors()->withInput()` (excepto el archivo, que nunca se reenvía) → la vista muestra los mensajes junto al campo y `old()` conserva lo introducido (FR-004).
- **Rationale**: Principio II (servidor como fuente de verdad con Form Requests y `validated()`); FR-003/006/007 fijan cada regla; separar store/update permite que la única diferencia —la cota inferior de ejemplares— viva en el request de edición, no como `if` en el controlador.
- **Alternatives considered**: Validación en el controlador con `$request->validate()` (rechazado: la constitución pide Form Requests; con 2 entidades hay 4 requests, manageable); reglas compartidas en un trait (rechazado por ahora: solo dos reglas difieren, el trait añade indirección sin ganancia); validación solo en cliente (rechazada: puede eludirse, FR-004 exige el servidor).

## D-020: Unicidad del nombre de categoría normalizada (tildes, mayúsculas, espacios)

- **Decision**: Doble capa, apoyada en la colación que ya fija `002`:
  1. **Esquema**: migración propia `*_alter_categorias_nombre_collation.php` aplica `utf8mb4_0900_ai_ci` a `categorias.nombre` con `Schema::table(...)->change()` (mismo mecanismo que D-012). El índice `categorias_nombre_unique` **existente** pasa a comparar ignorando mayúsculas y tildes en la propia base de datos: «Matemáticas» y «matematicas» chocan en el índice, incluso ante un duplicado concurrente (edge case).
  2. **Aplicación**: `CategoriaStoreRequest`/`CategoriaUpdateRequest` aplican `prepareForValidation` con `trim()` a `nombre` (elimina espacios sobrantes antes de validar), reglas `required|string|max:100` y `Rule::unique('categorias','nombre')->ignore(...)` en update, con mensaje en español junto al campo.
  - MySQL 8 con `_0900_ai_ci` es NO PAD: los espacios **sí** importan en la comparación, por eso el `trim()` en la aplicación es necesario y suficiente junto a la colación; el valor almacenado queda recortado.
- **Rationale**: La clarificación Q6 exige unicidad ignorando tildes, mayúsculas y espacios sobrantes con error en español junto al campo, tanto al crear como al editar. Fijar la colación en la columna hace que la regla viva en el esquema (Principio I) y cierre el hueco de concurrencia; el `trim()` cubre el único caso que la colación no cubre. Reutiliza la migración de patrón ya aceptada en `002`.
- **Alternatives considered**: Columna generada `nombre_normalizado` con índice único (rechazado: añade columna y lógica duplicada cuando colación + trim cubren los tres casos); `str_replace`/`Str::ascii()` + comparación PHP (rechazado: la comprobación quedaría fuera del índice y no cubre la concurrencia); depender solo de `Rule::unique` sin colación (rechazado: con colación general el índice no distingue «Matemáticas» de «matematicas» y la validación lo pasaría); normalizar a minúsculas sin tildes el valor almacenado (rechazado: cambia lo que se muestra al usuario, que debe conservar su forma original).

## D-021: Portada en el formulario de creación (subida básica, sin reemplazo)

- **Decision**: El formulario de **creación** de libro admite portada opcional (`input type="file" accept="image/jpeg,image/png,image/webp"`). En `LibroController@store`, si la validación de D-019 pasa y hay archivo: `$path = $request->file('portada')->store('portadas', 'public')` y se guarda `$path` en `libros.portada`. El directorio `app/public/portadas` queda documentado; sirve portadas reales el comando `php artisan storage:link` (ya previsto en D-014). En la **edición**, el formulario muestra la portada actual (vía parcial `partials/portada` de D-014) con el texto de que el reemplazo llega con `005`; si no se envía archivo, el campo `portada` **no se toca** (se conserva el valor existente) y `$request->validated()` no lo incluye.
- **Rationale**: Clarificación Q2: el alta ya admite portada con las validaciones básicas de la constitución (JPG/PNG/WebP ≤ 2 MB), y el reemplazo/eliminación de archivos al editar es de `005-gestion-portadas`. `store()` en disco público con ruta aleatoria en BD cumple *Imágenes* (Storage + ruta en BD) y el parcial de `002` ya muestra la imagen o la predeterminada.
- **Alternatives considered**: Aceptar archivo también en edición y reemplazarlo (rechazado: explícitamente fuera de alcance, Q2/`005`; además dejaría archivos huérfanos sin la lógica de borrado de `005`); subir a disco privado (rechazado: las portadas deben ser públicas para el `<img>` de listado/detalle); guardar el binario en BD (rechazado: la constitución exige Storage con la ruta en la BD); validación de dimensiones o recorte (rechazado: no lo pide la spec y sería alcance de `005`).

## D-022: Confirmación previa y bloqueo de eliminaciones

- **Decision**: Tres capas para `destroy` (Principio IV):
  1. **Confirmación en el cliente**: cada botón «Eliminar» es un `<form method="post">` con `@csrf`, `@method('DELETE')` y `onsubmit="return confirm('…')"` con mensaje en español (libro: «¿Eliminar el libro «{título}»? Esta acción no se puede deshacer.»; categoría: análogo). Si el usuario cancela, no se envía ninguna petición (US2 escenario 2).
  2. **Comprobación previa en el servidor**: `LibroController@destroy` consulta `prestamos()->exists()` y, si hay préstamos, **no** intenta borrar y redirige al catálogo con error en español (FR-009); `CategoriaController@destroy` hace lo propio con `libros()->exists()` (FR-008). La ficha del libro muestra un aviso en español junto al botón Eliminar cuando el libro tiene préstamos.
  3. **Respaldo en la base de datos**: `restrictOnDelete()` de D-004 bloquea igualmente; el controlador captura `QueryException` y redirige con mensaje en español («eliminación interrumpida», edge case).
  - Éxito: `$libro->delete()` y redirección al catálogo con mensaje de éxito; `GET` nunca elimina (rutas registradas solo como `DELETE`).
- **Rationale**: FR-005 exige confirmación y `DELETE` nunca GET; FR-008/FR-009 exigen bloqueo con mensaje en español; el edge case «eliminación interrumpida» pide mensaje ante fallo. Las tres capas son baratas y cada una cubre un hueco distinto (usuario distraído, carrera entre petición y comprobación, SQL directo).
- **Alternatives considered**: Confirmación con vista intermedia dedicada (rechazada como única vía: `confirm()` es suficiente y la spec habla de «confirmar», no de una página; puede migrarse en `008` si se endurece la interfaz); bloqueo solo con `RESTRICT` (rechazado como única vía: la excepción produciría un 500 sin mensaje en español); soft deletes (rechazado: fuera de alcance, D-004); deshabilitar el botón en la ficha sin aviso (rechazado: FR-009 pide indicarlo con mensaje).

## D-023: `ejemplares_disponibles` calculado en el servidor

- **Decision**: El campo **no se captura** en ningún formulario ni se acepta en `$fillable`. Reglas en el servidor:
  - **Al crear**: tras guardar con `ejemplares_disponibles = ejemplares_totales` (aún sin préstamos).
  - **Al editar**: el total debe ser un entero ≥ `max(1, préstamos activos del libro)`. La cota se calcula en el request de edición con `$libro->prestamos()->where('estado', 'activo')->count()` y se expresa como regla `min` dinámica con mensaje propio en español («El número de ejemplares no puede ser inferior a los préstamos activos (N).»). Tras guardar el total, se recalcula `ejemplares_disponibles = ejemplares_totales − préstamos activos` en el propio `$libro->save()` vía evento `saving` del modelo (o método dedicado `recalcularDisponibilidad()` invocado por el controlador), de modo que ningún camino de escritura de `003` deja el campo incoherente.
  - La lógica vive en el **modelo** `Libro` (método `prestamosActivos(): int` y recálculo), no en el controlador: Principio III pide la lógica centralizada en modelos/servicios.
  - El ajuste automático al **prestar/devolver** corresponde a `004-gestion-prestamos`; `003` solo fija el invariante y lo mantiene en sus propias escrituras.
- **Rationale**: Clarificación Q1/FR-007/SC-006: disponibles es derivado (total − activos), solo lectura en el formulario; el total es editable pero coherente con los préstamos activos. Poner el invariante en el modelo evita que futuras escrituras de `004` lo contradigan.
- **Alternatives considered**: Capturar el campo y validarlo (rechazado: la clarificación lo prohíbe explícitamente); calcular `min` solo en el controlador (rechazado: el Principio III pide modelo; el request ya debe conocer la cota para el mensaje y el modelo puede recalcular); columna generada/computed en MySQL (rechazado: MySQL no materializa columnas con subconsultas agregadas y complica la migración); servicio de dominio dedicado (rechazado: excesivo para dos operaciones; el modelo es el punto único exigido).

## D-024: Localización de los mensajes de validación al español

- **Decision**: Publicar `lang/es/validation.php` (traducción oficial de Laravel al español) con `custom` (mensajes por regla), `attributes` (nombres de campo en español: ISBN, título, autor, editorial, año de publicación, número de ejemplares, categoría, portada, nombre) y mensajes específicos donde la regla genérica no sirva (regex de ISBN, `unique` de ISBN/categoría, `mimes`/`max` de portada, `min` dinámico de ejemplares definido en D-023). Configurar `'locale' => 'es'` en `config/app.php` (o soportar `App::setLocale('es')` en el arranque). Las vistas muestran `@error` junto a cada campo y el controlador usa `withInput()`.
- **Rationale**: Principio II y FR-004/SC-004 exigen mensajes **en español** junto al campo; los mensajes por defecto de Laravel están en inglés. Centralizar en `lang/` evita repetir cadenas en cada regla y hereda la traducción oficial mantenida por el framework.
- **Alternatives considered**: Cadenas literales por regla (`withMessage`) en cada Form Request (rechazado: duplica texto, rompe la traducción estandarized y complica el mantenimiento); JavaScript de traducción en cliente (rechazado: la validación es del servidor); solo mensajes `custom` sin cambiar el locale (rechazado: el resto de reglas quedaría en inglés).

## D-025: Estrategia de pruebas

- **Decision**: PHPUnit con `RefreshDatabase` sobre la base de datos de prueba (D-008), comando `php artisan test`. Seis suites de característica:
  - `LibroCreacionTest` — alta válida; ISBN duplicado y con formato inválido; año fuera de rango (límites 1900/año actual incluidos); obligatorios ausentes; longitudes; categoría inexistente; disponibilidad inicial = total.
  - `LibroEdicionTest` — edición válida actualiza el detalle; conservación de portada existente sin archivo; total inferior a préstamos activos rechazado; total válido recalcula disponibles.
  - `LibroEliminacionTest` — `DELETE` elimina un libro sin préstamos; libro con préstamos bloqueado con mensaje; cancelar la confirmación no envía petición (verificación del atributo `onsubmit`/estructura del formulario); `GET` a la ruta de borrado no elimina (405); libro inexistente → 404.
  - `LibroPortadaTest` — JPG/PNG/WebP ≤ 2 MB se guardan y el detalle la muestra; formato no admitido y tamaño > 2 MB rechazados junto al campo.
  - `CategoriaGestionTest` — crear, editar, eliminar sin libros; eliminación bloqueada con libros asociados; categorías aparecen en el selector de libros y el listado.
  - `CategoriaUnicidadTest` — «Matemáticas» vs «matematicas», «MATEMATICAS », «Matematicas» rechazados al crear y al editar (ignorando el propio registro); espacios recortados al almacenar.
  - `GestionLayoutTest` — layout común en todas las vistas de gestión; formularios con `@csrf`; errores junto al campo con `old()` conservado.
- **Rationale**: SC-001…SC-008 exigen evidencia reproducible de cada regla; las suites se alinean una a una con las historias y los success criteria; las factories de `001` permiten crear libros con préstamos activos para probar FR-007/FR-009 sin tocar `004`.
- **Alternatives considered**: Solo verificación manual (rechazado: no es reproducible); Pest (viable sobre la misma base, se adoptaría si el equipo lo prefiere); tests de navegador/E2E (rechazados: dependencia innecesaria; la confirmación `confirm()` se verifica estructuralmente).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (heredado D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x, Blade + CSS propio, PHPUnit (heredado D-001/D-008) |
| Storage | MySQL 8.x local (heredado D-002); colación en `categorias.nombre` por migración (D-020); portadas en Storage público `portadas/` (D-021) |
| Testing | PHPUnit + `RefreshDatabase` sobre MySQL de prueba (heredado D-008; suites de `003`: D-025) |
| Target Platform | Local, `php artisan serve` (heredado D-001/D-009) |
| Project Type | Web app Laravel monolito Blade (heredado D-001) |
| Performance Goals | Sin requisitos de tráfico; decenas–cientos de filas; un conteo de préstamos activos por guardado (D-023) |
| Constraints | Sin servicios de pago; credenciales fuera del repo (heredado D-007); mensajes en español junto al campo con `old()` (D-024); `DELETE` con confirmación (D-022) |
| Scale/Scope | 2 controladores, 4 Form Requests, ~11 rutas, ~7 vistas, 1 migración, 6–7 suites (D-018…D-025); préstamos/búsqueda/panel fuera de alcance |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
