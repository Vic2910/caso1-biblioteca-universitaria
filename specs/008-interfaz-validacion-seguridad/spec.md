# Feature Specification: Interfaz, validación y seguridad transversales

**Feature Branch**: `feature/interfaz-seguridad` (propuesta)

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Interfaz, validación y seguridad transversales — interfaz coherente con errores en español junto a los campos, validación en el servidor, seguridad transversal (CSRF, escapado de salida, eliminaciones con DELETE) y diseño adaptable; estructura común en Fase 1, formularios, validaciones y adaptación completos en Fase 2. Entrada: Constitución del Caso 1 - Biblioteca Universitaria, versión 1.0."

## Clarifications

### Session 2026-10-26

- Q: ¿Qué debe ocurrir cuando un usuario introduce mal su inicio de sesión varias veces seguidas? → A: Mostrar un mensaje de error genérico en español, sin bloquear ni esperar.
- Q: ¿Cuánto tiempo de inactividad debe pasar antes de que la sesión caduque y el sistema pida iniciar sesión de nuevo? → A: 2 horas de inactividad.
- Q: ¿Qué requisitos mínimos de accesibilidad debe cumplir la interfaz para considerarla clara y usable? → A: Cumplimiento completo WCAG 2.1 nivel AA.
- Q: ¿Qué debe ver el usuario cuando una lista (libros, categorías o préstamos) no tiene registros que mostrar? → A: Mensaje en español tipo "No hay registros" junto a la acción disponible (p. ej., "Añadir libro").
- Q: ¿Confirmas tal cual los dos valores por defecto que la especificación dejó abiertos a la clarificación (el mensaje de fallo CSRF y los tamaños de verificación de adaptación)? → A: Confirmar ambos valores tal como están.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Navegar y corregir datos (Priority: P1)

Como usuario, quiero una interfaz coherente que explique los errores de cada campo para poder completar mis operaciones.

**Why this priority**: La comprensión y corrección de datos afectan las funciones de gestión: sin una estructura visual compartida ni errores claros junto a los campos, el usuario no puede completar ni corregir las operaciones de la biblioteca.

**Independent Test**: Abrir las vistas principales y enviar un formulario con un dato inválido, comprobando que las vistas comparten estructura y que el error aparece junto al campo con los valores conservados.

**Acceptance Scenarios**:

1. **Dadas** las vistas del sistema, **cuando** el usuario navega entre ellas, **entonces** comparten una estructura visual común.
2. **Dado** un formulario con datos inválidos, **cuando** se envía, **entonces** el servidor rechaza la operación, presenta errores en español junto a los campos y conserva los valores introducidos.
3. **Dada** una pantalla móvil, **cuando** se consultan las vistas de la Fase 2, **entonces** la interfaz se adapta a su tamaño sin recortes ni desbordamientos.

---

### User Story 2 - Proteger las operaciones (Priority: P1)

Como usuario, quiero que las operaciones y los datos se procesen de forma segura para reducir riesgos de acciones no deseadas.

**Why this priority**: La constitución exige protección y seguridad para todas las funcionalidades aplicables; sin estas salvaguardas cualquier módulo del sistema queda expuesto a acciones no deseadas.

**Independent Test**: Verificar formularios, datos mostrados, solicitudes de eliminación y el acceso sin sesión iniciada con casos controlados (envío de formulario, texto con etiquetas o instrucciones SQL, solicitud GET de eliminación y operación sin sesión).

**Acceptance Scenarios**:

1. **Dado** un formulario, **cuando** se envía, **entonces** cuenta con protección CSRF, que ayuda a impedir solicitudes no autorizadas desde otro sitio.
2. **Dado** texto ingresado por usuarios, **cuando** se muestra, **entonces** se presenta como texto escapado y no se ejecuta como código.
3. **Dada** una operación de eliminación, **cuando** se solicita por GET, **entonces** no elimina registros.
4. **Dado** texto con comillas y órdenes SQL (p. ej., `' OR 1=1 --`) ingresado en un campo de un formulario, **cuando** se envía y se consulta después, **entonces** se trata como texto normal, sin alterar, exponer ni eliminar registros.
5. **Dado** un usuario sin sesión iniciada, **cuando** intenta realizar una operación del sistema, **entonces** se le solicita iniciar sesión y la operación no se ejecuta.

---

### Edge Cases

- Fallo de protección CSRF (token ausente, caducado o manipulado): el servidor rechaza el envío, no se guarda ningún dato y se muestra en español junto al formulario el mensaje "Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.", conservando los valores introducidos.
- Dispositivos y tamaños para verificar la adaptación: teléfono de 360 × 640 px y tableta de 768 × 1024 px en vertical, además del escritorio de 1024 px o más de ancho.
- Autenticación y control de acceso: el sistema exige un inicio de sesión único para todos los usuarios antes de operar; sin sesión iniciada no se ejecuta ninguna operación y se solicita el inicio de sesión. No se definen permisos diferenciados por perfil (ver Supuestos).
- Inicio de sesión fallido (una o varias veces seguidas): se muestra un mensaje genérico en español, sin bloquear el acceso ni añadir esperas, y sin revelar si la cuenta existe.
- Sesión caducada (tras 2 horas de inactividad) mientras el usuario completa un formulario: la operación no se ejecuta y se solicita iniciar sesión de nuevo.
- Texto de usuario con etiquetas o scripts (p. ej., `<script>` en un título): se muestra como texto visible y no se ejecuta.
- Varias solicitudes de eliminación por GET, incluidas repetidas: ninguna modifica ni elimina registros.
- Envío con varios campos inválidos a la vez: se muestran todos los errores en español junto a sus campos, sin perder ningún valor ingresado.
- Usuario que corrige un solo campo tras un rechazo: los demás campos conservan sus valores mientras la operación siga siendo inválida.
- Caracteres especiales del español (á, é, í, ó, ú, ñ, ¿, ¡) en cualquier campo: se muestran correctamente en formularios y listados.
- Fallo de la operación de eliminación después de la confirmación: el usuario recibe un mensaje en español y el registro permanece intacto.
- Lista sin registros (libros, categorías o préstamos): se muestra en español un mensaje tipo "No hay registros" junto a la acción disponible (p. ej., "Añadir libro"), nunca un área vacía sin explicación.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Todas las vistas deben compartir una estructura visual común (layout) con estilos propios; si se utiliza alguna librería de interfaz opcional (Bootstrap o Tailwind), debe declararse en el README.
- **FR-002**: La interfaz debe ser clara y coherente para estudiantes, docentes y personal administrativo; en la Fase 2 debe adaptarse a pantallas móviles.
- **FR-003**: Todo formulario debe validarse en el servidor antes de procesarse; el sistema debe rechazar los datos inválidos y las operaciones deben utilizar únicamente los datos validados.
- **FR-004**: Tras un rechazo, los formularios deben mostrar errores en español junto a cada campo y conservar los valores introducidos por el usuario.
- **FR-005**: Todo formulario debe incorporar protección CSRF y toda la salida debe escaparse, de modo que el texto ingresado por usuarios se muestre como texto y no se ejecute como código.
- **FR-006**: Las consultas deben realizarse sin concatenar datos del usuario en las sentencias, y las credenciales de acceso deben mantenerse fuera del repositorio.
- **FR-007**: Toda eliminación debe requerir confirmación del usuario y realizarse mediante `DELETE`, nunca mediante GET.
- **FR-008**: La estructura visual común es requisito de la Fase 1; los formularios con validación completa, el diseño adaptable a móviles y el inicio de sesión se completan en la Fase 2.
- **FR-009**: El sistema debe exigir el inicio de sesión a todos los usuarios; toda operación debe procesarse únicamente con la sesión iniciada y, sin ella, debe solicitarse el inicio de sesión sin ejecutar la operación. La sesión caduca tras 2 horas de inactividad y, al caducar, se pide iniciar sesión de nuevo antes de procesar cualquier operación.
- **FR-010**: Un inicio de sesión fallido, incluso repetido, debe mostrar un mensaje genérico en español sin bloquear el acceso, sin esperas añadidas y sin indicar si la cuenta existe.
- **FR-011**: La interfaz debe cumplir WCAG 2.1 nivel AA: todo campo visible tiene etiqueta asociada, todas las funciones son operables con teclado y el texto mantiene el contraste mínimo exigido (4,5:1 para texto normal).
- **FR-012**: Toda lista sin registros debe mostrar un mensaje en español que indique que no hay datos, junto a la acción disponible para crear el primer registro; nunca un área vacía sin explicación.

### Key Entities

- **Libro, Categoría y Préstamo**: no aplica una entidad propia en esta funcionalidad; esta especificación protege y presenta los datos que pertenecen a esas funcionalidades, sin añadir nuevos atributos.
- **Cuenta de usuario**: identidad con la que se inicia sesión para operar; solo aporta la identificación del usuario y no define roles ni permisos diferenciados.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Todas las vistas principales revisadas utilizan una estructura común; las vistas de la Fase 2 probadas se adaptan sin recorte ni desbordamiento en los tamaños definidos (360 × 640 px, 768 × 1024 px y escritorio de 1024 px o más de ancho).
- **SC-002**: El 100 % de los campos inválidos probados muestran un error en español junto al campo y conservan los valores introducidos.
- **SC-003**: En las pruebas de formularios y de salida mostrada, el 100 % de las operaciones revisadas cuentan con protección CSRF y presentan el texto de usuario como texto, sin ejecución de código.
- **SC-004**: El 100 % de las solicitudes de eliminación por GET probadas no modifican ni eliminan registros; toda eliminación efectiva exige confirmación previa del usuario.
- **SC-005**: El 100 % de las operaciones probadas sin sesión iniciada —incluidas las intentadas tras 2 horas de inactividad— se redirigen al inicio de sesión y no se ejecutan ni modifican registros.
- **SC-006**: El 100 % de los intentos fallidos de inicio de sesión probados muestran un mensaje genérico en español, sin bloqueo, espera añadida ni revelación de la existencia de la cuenta.
- **SC-007**: En las vistas revisadas, el 100 % de los campos tiene etiqueta visible asociada, las funciones principales (navegación, envío de formularios y confirmación de eliminación) son operables con teclado y el texto alcanza un contraste de al menos 4,5:1.
- **SC-008**: El 100 % de las listas vacías probadas muestra un mensaje en español de "no hay registros" junto a una acción disponible, sin áreas vacías sin explicación.

## Assumptions

- Los públicos objetivo son estudiantes, docentes y personal administrativo; sus permisos diferenciados no están definidos: el inicio de sesión identifica al usuario, pero no establece roles ni privilegios distintos, de modo que todos los usuarios autenticados pueden realizar las mismas operaciones.
- Las cuentas de usuario las crea y administra el equipo; no existe un registro público de cuentas. El inicio de sesión se entrega en la Fase 2 junto con los formularios; en la Fase 1 se establece la estructura visual común.
- La verificación de seguridad complementa las pruebas de cada módulo y depende de las vistas y formularios implementados; los formularios completos corresponden a la Fase 2.
- El mensaje por defecto ante un fallo de protección CSRF es "Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.", mostrado en español junto al formulario y conservando lo ingresado; valor confirmado en la fase de clarificación (2026-10-26).
- La verificación de la adaptación se acuerda en teléfono de 360 × 640 px y tableta de 768 × 1024 px en vertical, más escritorio de 1024 px o más de ancho; valor confirmado en la fase de clarificación (2026-10-26).
- Bootstrap o Tailwind no son obligatorios; si se utilizan, se declaran en el README.
- La eliminación por GET se intenta solo con fines de prueba; el sistema no ofrece ninguna ruta de eliminación por GET.
- La comprobación de que las credenciales quedan fuera del repositorio se realiza revisando el repositorio, no desde la interfaz.
- Esta funcionalidad no añade entidades ni reglas de negocio propias; complementa a las funcionalidades de libros, categorías y préstamos, de las que depende para mostrar contenido que presentar y proteger.
- No se definen objetivos de rendimiento adicionales más allá de la respuesta estándar de una aplicación web local.
