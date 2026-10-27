# Quickstart: validación de 008-interfaz-validacion-seguridad

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local).
- Funcionalidades `002`–`007` implementadas (vistas, formularios y listados que `008` homogeneiza y protege).
- Navegador con DevTools para verificar tamaños de pantalla y contraste.

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (001 contracts/environment-variables.md)
                           # SESSION_LIFETIME=120 (2 h de inactividad, D-038)
php artisan key:generate
php artisan migrate --seed  # esquema (incluida users), catálogo y UserSeeder (D-037)
php artisan test            # suite completa, incluidas las 9 suites transversales de 008
php artisan serve           # → http://127.0.0.1:8000 (redirige a /login)
```

## Escenarios de validación

### V-001 — Acceso obligatorio sin sesión (FR-009, SC-005)

1. Con la sesión cerrada, abrir `http://127.0.0.1:8000/catalogo`, `/libros`, `/prestamos` y `/panel`.
2. **Esperado**: todas redirigen a `http://127.0.0.1:8000/login`; ninguna operación se ejecuta ni se muestran datos.
3. Intentar enviar (con DevTools o `curl`) un formulario de creación sin sesión: **Esperado**: redirección a `/login`, sin registros creados.
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §2.

### V-002 — Inicio de sesión y cierre (FR-009)

1. Abrir `/login`; iniciar sesión con una cuenta de `UserSeeder`.
2. **Esperado**: redirección a la vista previa/catálogo, bloque de sesión visible con «Cerrar sesión».
3. Pulsar «Cerrar sesión» (con teclado, `Tab` + `Enter`).
4. **Esperado**: sesión cerrada y `/login` visible; volver a `/catalogo` redirige a `/login` (V-001).

### V-003 — Login fallido genérico, repetido, sin bloqueo (FR-010, SC-006)

1. En `/login`, enviar credenciales incorrectas **5 veces seguidas** (y con un correo inexistente).
2. **Esperado**: siempre el mismo texto «Correo o contraseña incorrectos.», en español, junto a los campos; el correo se conserva; sin bloqueo, sin espera perceptible y sin indicar si la cuenta existe.
3. Inmediatamente después, iniciar sesión con credenciales válidas. **Esperado**: acceso normal (ningún bloqueo persistió).
   Contrato: [contracts/user-messages.md](contracts/user-messages.md) §2.

### V-004 — Sesión caducada a las 2 horas (FR-009, SC-005)

1. Autenticado, avanzar el reloj 2 h + 1 min (`travel(121)->minutes()` en el test; manualmente, dejar pasar la inactividad o vaciar la sesión).
2. Ejecutar cualquier operación (abrir una vista protegida o enviar un formulario).
3. **Esperado**: la operación **no** se procesa; se solicita iniciar sesión de nuevo («Su sesión expiró. Inicie sesión de nuevo.» en `/login`).
   Referencia: [data-model.md](data-model.md) §Sesión.

### V-005 — Errores en español junto al campo con valores conservados (FR-004, SC-002)

1. Con sesión iniciada, enviar el formulario de creación de libro **vacío** y luego con varios campos inválidos a la vez (ISBN de 12 dígitos, año 1800).
2. **Esperado**: el servidor rechaza; **todos** los campos inválidos muestran su error en español inmediatamente junto al campo; los valores válidos se conservan; operar con teclado es posible.
3. Corregir un solo campo y reenviar: **Esperado**: los demás conservan sus valores mientras siga habiendo errores.
   Estructura: [contracts/ui-structure.md](contracts/ui-structure.md) §3.

### V-006 — Fallo de protección CSRF (FR-005, SC-003 — edge case)

1. En un formulario abierto, quitar el campo `_token` (DevTools) y enviar.
2. **Esperado**: **nada** se guarda; aparece junto al formulario «Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.» con los valores introducidos conservados.
3. Repetir con un token manipulado/caducado. **Esperado**: mismo resultado.
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §3.

### V-007 — Texto de usuario escapado (FR-005, SC-003)

1. Crear un libro con título `<script>alert('x')</script>` y otro con `' OR 1=1 --` en sus campos de texto.
2. Consultar listado y ficha. **Esperado**: el texto aparece **literal, visible y no ejecutado** (sin alertas); el catálogo tiene exactamente los mismos registros que antes, ninguno alterado, expuesto ni eliminado.
3. Ejecutar `php artisan test --filter=EscapadoSalidaTest`. **Esperado**: auditoría de vistas en verde (sin `{!! !!}` con datos).

### V-008 — Eliminaciones solo por `DELETE` con confirmación (FR-007, SC-004)

1. En los listados, comprobar que «Eliminar» abre una confirmación en español y que el formulario contiene `@csrf` y `@method('DELETE')`.
2. Pedir por `GET` la URL de una eliminación (repetidas veces). **Esperado**: **405** en todos los intentos y los registros intactos.
3. Confirmar una eliminación válida: se elimina una única vez; provocar un fallo (p. ej. categoría con libros): **Esperado**: mensaje en español y registro intacto.
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §4.

### V-009 — Estados vacíos con acción (FR-012, SC-008)

1. Base sin datos de gestión (p. ej. `php artisan migrate:fresh` sin sembrar) → abrir los listados de libros, categorías y préstamos.
2. **Esperado**: cada uno muestra «No hay registros» junto a su acción («Añadir libro», «Nueva categoría», «Registrar préstamo»); nunca un área vacía sin explicación.
   Contrato: [contracts/ui-structure.md](contracts/ui-structure.md) §4.

### V-010 — Accesibilidad WCAG 2.1 AA (FR-011, SC-007)

1. Recorrer con teclado (Tab/Shift+Tab/Enter) la navegación, un formulario y una confirmación de eliminación.
2. **Esperado**: todo operable, foco visible y orden lógico.
3. Comprobar en las vistas principales que cada campo tiene etiqueta visible asociada.
4. Medir contraste de texto normal con una herramienta de DevTools. **Esperado**: ≥ 4,5:1.
5. Ejecutar `php artisan test --filter=AccesibilidadUiTest`. **Esperado**: en verde (labels, `aria`, estructura común).

### V-011 — Adaptación a tres tamaños (FR-002, SC-001)

1. Con DevTools, revisar las vistas de la Fase 2 (catálogo, formularios, listados, login, panel) en:
   - teléfono **360 × 640 px**,
   - tableta **768 × 1024 px** en vertical,
   - escritorio **≥ 1024 px**.
2. **Esperado**: en los tres tamaños, sin recortes, sin desbordamientos horizontales ni contenido superpuesto; formularios usables.
   Contrato: [contracts/ui-structure.md](contracts/ui-structure.md) §6.

### V-012 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: las suites `AuthAccesoTest`, `AuthLoginTest`, `SesionCaducadaTest`, `CsrfProteccionTest`, `EscapadoSalidaTest`, `EliminacionDeleteTest`, `ValidacionTransversalTest`, `AccesibilidadUiTest` y `EstadosVaciosTest` en verde (D-047), junto con las de `001`–`007` (ninguna regresión).

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-011, V-012 (estructura común auditada) |
| SC-002 | V-005 |
| SC-003 | V-006, V-007 |
| SC-004 | V-008 |
| SC-005 | V-001, V-004 |
| SC-006 | V-003 |
| SC-007 | V-010 |
| SC-008 | V-009 |
