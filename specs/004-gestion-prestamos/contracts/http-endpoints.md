# Contract: Endpoints HTTP de préstamos (FR-001, FR-002, FR-004, FR-005, FR-007)

**Spec**: [../spec.md](../spec.md) FR-001…FR-007, edge cases, SC-001…SC-004 | **Research**: [../research.md](../research.md) D-018, D-021, D-022, D-024 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. Escrituras vía formularios HTML con `@csrf`, respuestas de navegador (sin API JSON), sin autenticación en esta fase. Mensajes visibles: [user-messages.md](user-messages.md).

## Rutas

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 1 | `GET /prestamos?page=N` | `prestamos.index` | `PrestamoController@index` | `200 OK` con listado paginado |
| 2 | `GET /prestamos/crear` | `prestamos.create` | `PrestamoController@create` | `200 OK` con formulario de registro |
| 3 | `POST /prestamos` | `prestamos.store` | `PrestamoController@store` | `302` → `prestamos.index` + mensaje flash de éxito |
| 4 | `GET /prestamos/{id}/devolver` | `prestamos.form-devolucion` | `PrestamoController@devolver` | `200 OK` con formulario de devolución |
| 5 | `POST /prestamos/{id}/devolver` | `prestamos.devolver` | `PrestamoController@registrarDevolucion` | `302` → `prestamos.index` + mensaje flash de éxito |

- `{id}` es el `id` numérico de `prestamos` (entero ≥ 1), resuelto con `find()`; sin fila → **404** (ver abajo).
- **No existen rutas de edición ni eliminación** (FR-007): no hay `PUT`/`PATCH`/`DELETE` de préstamos; el historial es inmutable (D-018).
- La navegación común incorpora el enlace «Préstamos» → `prestamos.index`.

## Cuerpo del formulario de registro (`POST /prestamos`)

| Campo | Obligatorio | Formato válido | Reglas de negocio (servidor) |
|---|---|---|---|
| `libro_id` | Sí | Entero con fila en `libros` (`exists:libros,id`) | `ejemplares_disponibles >= 1` (R1) |
| `nombre_estudiante` | Sí | Texto de 2 a 255 caracteres | — |
| `carnet_estudiante` | Sí | Texto de hasta 50 caracteres; se almacena con `trim()` (D-023) | Menos de 3 préstamos no devueltos (`activo`/`vencido`) con ese carné (R2) |
| `fecha_prestamo` | Sí | Fecha (`date`) | — |
| `fecha_devolucion_prevista` | Sí | Fecha (`date`) | Estrictamente posterior a `fecha_prestamo` (R3) |

## Cuerpo del formulario de devolución (`POST /prestamos/{id}/devolver`)

| Campo | Obligatorio | Formato válido | Reglas de negocio (servidor) |
|---|---|---|---|
| `fecha_devolucion_real` | Sí | Fecha (`date`) | No anterior a `fecha_prestamo` del préstamo (R4); el préstamo debe estar `activo` o `vencido` |

## Respuestas de error

| Caso | Disparador | Respuesta | Cuerpo |
|---|---|---|---|
| Préstamo inexistente | `{id}` sin fila en `prestamos` | `404 Not Found` | Vista `errors/404.blade.php` con «Préstamo no encontrado» + enlace «Volver a préstamos» |
| Regla de campo / negocio incumplida (R1–R6) | Form Request o servicio rechaza | `302` → volver al formulario con `errors` y `old()` | Errores en español **junto al campo** correspondiente; los valores ingresados se conservan |
| Devolución repetida | `POST` sobre préstamo `devuelto` (doble envío o navegación directa) | `302` → volver al formulario/devolución con error | «Este préstamo ya fue devuelto.» junto a la acción; la disponibilidad **no** cambia |

- La vista de error usa el layout común y el mismo `errors/404.blade.php` que el catálogo (FR-004/SC-004), con el texto en español controlado (mismo patrón que D-013 de `002`).
- Los rechazos **nunca** modifican `ejemplares_disponibles` (SC-002).

## Efectos en datos (invariantes del contrato)

| Operación | `prestamos` | `libros.ejemplares_disponibles` |
|---|---|---|
| `store` válido | Crea fila `activo` | −1 (exactamente uno) |
| `registrarDevolucion` válido | `estado` → `devuelto`, `fecha_devolucion_real` fijada | +1 (exactamente uno; una segunda devolución no suma) |
| Cualquier rechazo | Sin cambios | Sin cambios |

- El `estado` no se acepta en ningún cuerpo de petición: se calcula en el servidor (D-020); registrar y devolver evalúan el vencimiento antes de resolver la respuesta.
- Transacciones + bloqueo de fila del libro en ambas escrituras (D-022): ante dos solicitudes por el último ejemplar, solo una lo consume y la otra recibe el rechazo R1.

## Restricciones del contrato

- Escrituras solo con `POST` + token `@csrf`; sin métodos de modificación de préstamos (FR-007).
- Consultas exclusivamente vía Eloquent (`with('libro')`, sin SQL concatenado con entradas del usuario — Principio IV).
- El estado y la disponibilidad no se infieren de la interfaz: todo se revalida en el servidor (supuesto de la spec).
