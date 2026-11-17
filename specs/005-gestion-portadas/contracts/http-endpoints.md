# Contract: Endpoints de gestión y entrega de portadas (FR-001, FR-005, FR-007)

**Spec**: [../spec.md](../spec.md) FR-001, FR-003, FR-005, FR-006, FR-007 | **Research**: [../research.md](../research.md) D-018, D-020, D-021, D-023 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. Mensajes visibles: [user-messages.md](user-messages.md). Las rutas de edición/eliminación de libros pertenecen al CRUD de `003-gestion-libros`; este contrato documenta **su ampliación por portadas** y las rutas nuevas de `005`.

## Rutas

| # | Método y ruta | Nombre | Acción | Requiere sesión | Éxito |
|---|---|---|---|---|---|
| 1 | `PUT/PATCH /libros/{id}` (multipart) | (la de `003`) | `LibroController@update` — ampliado: si el cuerpo incluye un archivo en `portada`, ejecuta T1/T2 | Sí* | `302` a la edición (o al detalle, según `003`) con mensaje de éxito |
| 2 | `DELETE /libros/{id}/portada` | `libros.portadas.destroy` | `PortadaController@destroy` | Sí* | `302` a la edición con mensaje de éxito |
| 3 | `GET /portadas/{id}` | `portadas.show` | `PortadaController@show` | **Sí (FR-007)** | `200 OK` con los bytes de la imagen y su `Content-Type` |

- `{id}` es el `id` numérico de `libros` (entero ≥ 1).
- \* Las operaciones de escritura heredan la exigencia de sesión de `008-interfaz-validacion-seguridad` (FR-009 de ese spec); `005` no la duplica. La ruta 3 la implementa **este** contrato (D-023).

## 1. Edición con archivo nuevo — `PUT /libros/{id}` (multipart/form-data)

- `enctype="multipart/form-data"`, `@csrf`, método real `PUT` (`@method('PUT')`).
- Campo **opcional** `portada`: archivo JPG/PNG/WebP ≤ 2 MB (validación del Form Request, D-019 / [user-messages.md](user-messages.md) §1).
- **Sin campo `portada` o vacío → T4**: la portada existente no se modifica ni se elimina (FR-001).
- **Con archivo válido → T1/T2**: ver orden y compensaciones en [data-model.md](../data-model.md) §Ciclo de vida.
- **Archivo inválido → HTTP 302 de vuelta** con `errors` en el campo `portada`, mensajes en español junto al campo, resto de campos con `old()`, y portada existente intacta (FR-002, SC-003).
- **Fallo de almacenamiento → 302** con el mensaje de §2 junto al campo y sin cambios (FR-006).

## 2. Eliminación de portada — `DELETE /libros/{id}/portada`

| Caso | Código | Cuerpo / efecto |
|---|---|---|
| Libro con portada, confirmado en el formulario | `302` → edición | Archivo borrado, `portada = NULL`, mensaje de éxito; listado y detalle muestran la predeterminada |
| Libro sin portada | `404` | Vista `errors/404` con «Portada no encontrada» |
| Libro inexistente | `404` | Vista `errors/404` con «Libro no encontrado» |
| Fallo de disco al borrar | `302` → edición | Mensaje «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo; archivo y referencia intactos |

- **Solo `DELETE`** (FR-005): la ruta no existe para GET/POST; un `GET /libros/{id}/portada` (o de sus rutas derivadas) no elimina nada (Principio IV).
- La petición va en un formulario con `@csrf` + `@method('DELETE')` y **confirmación previa** del usuario (SC-004); contratos de texto en [user-messages.md](user-messages.md) §3.

## 3. Entrega de imagen — `GET /portadas/{id}`

| Sesión | Condiciones | Código | Cuerpo |
|---|---|---|---|
| Sin sesión | Cualquier `{id}` | **`401 Unauthorized`** | Vista `errors/401.blade.php` (layout común) con mensaje en español pidiendo iniciar sesión (FR-007, SC-006) — **nunca** los bytes de la imagen |
| Con sesión | Libro inexistente | `404` | Vista `errors/404` con «Libro no encontrado» |
| Con sesión | `portada IS NULL` | `404` | Vista `errors/404` con «Portada no encontrada» |
| Con sesión | Archivo ausente en disco | `404` | Vista `errors/404`; en vistas renderizadas el `onerror` del parcial muestra la predeterminada |
| Con sesión | Archivo presente | `200 OK` | Bytes de la imagen + `Content-Type` derivado (image/jpeg / image/png / image/webp) |

- La comprobación de sesión ocurre **antes** de tocar la BD o el disco (no se filtra existencia de portadas para visitantes).
- Ninguna otra ruta sirve portadas: `/storage/…` no se usa (el enlace simbólico no es requisito, D-018); la imagen predeterminada es el asset público `public/images/libro-sin-portada.svg` vía `asset()` y **no** pasa por este contrato.

## Restricciones del contrato

- Escrituras solo con los datos validados por el Form Request (Principio II); consultas con Eloquent, sin SQL concatenado (Principio IV).
- La ruta de entrega (`GET /portadas/{id}`) es la única lectura GET de este contrato; no expone metadatos del libro más allá de la imagen servida.
- Ningún mensaje expone rutas internas, trazas ni detalles técnicos.
- Alcance: la gestión completa del libro (otros campos, borrar libro) sigue siendo de `003`; el inicio de sesión que habilita la columna «sesión» es de `008`.
