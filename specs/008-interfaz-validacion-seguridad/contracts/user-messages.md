# Contract: Mensajes visibles al usuario (FR-004, FR-009, FR-010, FR-012)

**Spec**: [../spec.md](../spec.md) FR-004, FR-009, FR-010, FR-012, edge cases, SC-002, SC-006, SC-008 | **Research**: [../research.md](../research.md) D-039, D-040, D-041, D-046 | **Structure**: [ui-structure.md](ui-structure.md)

Todos los mensajes son **en español**, visibles para estudiantes, docentes y personal administrativo. Los mensajes de validación de campo proceden de `lang/es/validation.php` (heredado de `003` D-024).

## 1. Fallo de protección CSRF (FR-005, edge case — texto confirmado en clarificación 2026-10-26)

| Elemento | Contrato |
|---|---|
| **Disparador** | Envío de formulario con token ausente, caducado o manipulado |
| **Texto requerido** (exacto) | «Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.» |
| **Presentación** | En español, **junto al formulario**, conservando los valores introducidos; nada se guarda |

## 2. Inicio de sesión (FR-010, SC-006)

| Situación | Texto requerido | Presentación |
|---|---|---|
| Credenciales incorrectas (una o varias veces seguidas; cuenta existente o no) | «Correo o contraseña incorrectos.» | Dentro del formulario de login, junto a los campos; el correo se conserva con `old()` |
| Sesión caducada o inexistente al intentar operar | «Inicie sesión para continuar.» | Redirigido a `/login`; la operación no se ejecuta |
| Sesión caducada tras 2 h de inactividad | «Su sesión expiró. Inicie sesión de nuevo.» | En `/login` al ser redirigido; ninguna operación se procesa |
| Cierre de sesión | (sin mensaje; redirección a `/login`) | El formulario de login queda visible |

- Ningún mensaje indica si la cuenta existe, no indica el fallo concreto ni añade esperas ni bloqueos (FR-010).
- Ningún mensaje incluye trazas, tokens, credenciales ni detalles técnicos.

## 3. Errores de validación junto al campo (FR-004, SC-002)

| Elemento | Contrato |
|---|---|
| **Disparador** | Envío de un formulario con datos inválidos (rechazo del servidor) |
| **Texto** | Mensaje en español del campo concreto (p. ej. «El campo título es obligatorio.»), con los atributos personalizados de `lang/es/validation.php` |
| **Presentación** | Inmediatamente después del campo (`aria-invalid` + `aria-describedby`), con **todos** los campos inválidos marcados y **todos** los valores introducidos conservados (`old()`) |

- Corregir un campo mantiene los demás valores mientras la operación siga inválida (edge cases).
- Textos de campos y validaciones concretas: contratos de `003`, `004` y `005` (no se duplican aquí).

## 4. Listas sin registros (FR-012, SC-008 — clarificación 2026-10-26)

| Listado | Mensaje | Acción junto al mensaje |
|---|---|---|
| Libros (gestión) | «No hay registros» | «Añadir libro» / «Nuevo libro» |
| Categorías | «No hay registros» | «Añadir categoría» / «Nueva categoría» |
| Préstamos | «No hay registros» | «Registrar préstamo» |
| Catálogo (`002`) | «No hay libros registrados aún» (mensaje propio, ya indica la ausencia de datos) | Enlace a la acción disponible cuando aplique |
| Panel (`007`) | «No hay datos disponibles» (texto propio de `007`, sin acción: vista de solo consulta) | — |

- Nunca se muestra un área vacía sin explicación.

## 5. Confirmación y fallo de eliminación (FR-007, edge cases)

| Situación | Texto |
|---|---|
| Confirmación previa | Diálogo en español, p. ej. «¿Está seguro de que desea eliminar este registro?» |
| Eliminación correcta | Mensaje de éxito en español junto al listado |
| Fallo de la eliminación (regla de negocio) | Mensaje en español indicando el motivo (p. ej. registros relacionados); **el registro permanece intacto** |
| Solicitud de eliminación por GET | Sin mensaje de eliminación: responde 405 y no cambia nada (contrato [http-endpoints.md](http-endpoints.md) §4) |

## 6. Restricciones del contrato

- **Textos fijos confirmados**: los de §1 proceden de la clarificación del 2026-10-26 y no se reformulan; el de fallo de login es único y genérico (D-039).
- Ningún mensaje expone trazas SQL, credenciales, tokens CSRF, rutas internas ni detalles técnicos (Principio IV; coherente con [../../002-consulta-catalogo/contracts/user-messages.md](../../002-consulta-catalogo/contracts/user-messages.md)).
- Todo texto visible está en español, incluidos etiquetas, `alt`, estados vacíos y diálogos de confirmación (FR-011).
