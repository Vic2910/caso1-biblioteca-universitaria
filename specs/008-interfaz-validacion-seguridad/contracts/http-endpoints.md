# Contract: Endpoints de autenticación y reglas transversales de petición (FR-005, FR-007, FR-009, FR-010)

**Spec**: [../spec.md](../spec.md) FR-005, FR-007, FR-009, FR-010, SC-003…SC-006 | **Research**: [../research.md](../research.md) D-037, D-038, D-039, D-040, D-043 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. Este contrato define el **acceso** a las vistas y formularios existentes (`002`–`007`) y las respuestas transversales de seguridad. Textos visibles: [user-messages.md](user-messages.md).

## 1. Rutas de autenticación (nuevas)

| # | Método y ruta | Nombre | Acción | Éxito | Fallo |
|---|---|---|---|---|---|
| 1 | `GET /login` | `login` | `AuthController@showLogin` | `200 OK` con el formulario; si ya hay sesión → `302` a `/catalogo` | — |
| 2 | `POST /login` | `login.store` | `AuthController@login` (`LoginRequest` + `Auth::attempt`) | `302` a la URL previa o `/catalogo`, sesión iniciada | Credenciales inválidas → `302` a `/login` con el mensaje genérico (jamás `401`/`404`, jamás distingue si la cuenta existe) |
| 3 | `POST /logout` | `logout` | `AuthController@logout` | `302` a `/login` con sesión cerrada | Sin sesión → `302` a `/login` |

- Validación del `POST /login`: `email` obligatorio con formato de correo y `password` obligatorio (reglas en servidor, errores junto al campo) — [../data-model.md](../data-model.md) §Inventario.
- No hay ruta de registro ni de recuperación de contraseña (las cuentas las crea el equipo — supuesto de la spec).

## 2. Control de acceso a todas las rutas de negocio (FR-009, SC-005)

| Situación | Respuesta del servidor |
|---|---|
| Petición autenticada a cualquier ruta (`/catalogo`, `/libros`, `/categorias`, `/prestamos`, `/panel`, …) | Se procesa con normalidad |
| Petición **sin sesión** (o con sesión caducada) a cualquier ruta protegida | `302` a `/login`; la operación **no se ejecuta** ni modifica registros |
| Formulario enviado **sin sesión** | `302` a `/login`; no se procesa ningún dato |
| Sesión caducada tras **2 horas de inactividad** (`SESSION_LIFETIME=120`) | Como sin sesión: `302` a `/login` antes de procesar cualquier operación |

- Protegidas: **todas** las rutas salvo `GET /login`, `POST /login` y los recursos estáticos (grupo `auth` en `routes/web.php`, D-037).
- Sin permisos diferenciados: una vez autenticado, cualquier usuario accede a las mismas rutas (supuesto de la spec).

## 3. Respuesta ante fallo de protección CSRF (FR-005, SC-003)

| Elemento | Contrato |
|---|---|
| **Disparador** | `POST`/`PUT`/`PATCH`/`DELETE` sin token, con token caducado o manipulado |
| **Datos guardados** | **Ninguno**: la operación se aborta antes de tocar la base de datos |
| **Respuesta** | `302` de vuelta a la vista de origen con los errores y **los valores introducidos conservados** (`withInput()`) |
| **Mensaje** | El texto exacto de [user-messages.md](user-messages.md) §1, en español, mostrado **junto al formulario** |
| **Excepciones al CSRF** | Ninguna: no hay entradas en `$except` (D-040) |

## 4. Eliminaciones (FR-007, SC-004)

| Método sobre la URL de una eliminación | Respuesta | Efecto sobre los datos |
|---|---|---|
| `GET /libros/{id}`, `GET /categorias/{id}`, etc. | **405 Method Not Allowed** | **Ninguno** — la eliminación no existe por GET; repetir el `GET` sigue dando 405 sin cambios (D-043) |
| `POST /…` sin `@method('DELETE')` | Respuesta de validación/ruta de Laravel, sin eliminación | Ninguno |
| `DELETE /libros/{id}` con token CSRF válido **y** confirmación previa del usuario | `302` al listado con mensaje de éxito si procede; `409`/`back()` con mensaje en español si la regla de negocio la rechaza | Elimina exactamente el registro confirmado, o **ninguno** si falla (el registro permanece intacto) |

- El `<form>` de eliminación incluye `@csrf`, `@method('DELETE')` y confirmación JavaScript en español previa al envío (D-043, [ui-structure.md](ui-structure.md) §3).
- El sistema **no ofrece ninguna ruta de eliminación por GET** (supuesto de la spec).

## 5. Restricciones transversales del contrato

- Toda escritura pasa por Form Request con `$request->validated()` (FR-003); los mensajes de validación en español y sus atributos personalizados son los de `003` D-024 (`lang/es/validation.php`).
- Toda salida escapada con `{{ }}` (FR-005); consultas Eloquent/Query Builder sin SQL concatenado (FR-006).
- Ninguna respuesta expone trazas SQL, credenciales, tokens CSRF ni detalles técnicos (Principio IV; coherente con [../../002-consulta-catalogo/contracts/user-messages.md](../../002-consulta-catalogo/contracts/user-messages.md)).
- Las rutas, parámetros y códigos propios de cada módulo (catálogo, CRUD, préstamos, portadas, búsqueda, panel) no cambian: solo se les antepone el requisito de sesión de §2.
