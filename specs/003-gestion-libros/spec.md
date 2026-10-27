# Especificación: Gestión de libros y categorías

**Feature Branch**: `feature/gestion-libros`

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Gestión de libros y categorías — Registrar, editar y eliminar libros del catálogo de la Biblioteca Universitaria, con validación en el servidor, confirmación de eliminación y relación con categorías y préstamos. Fase 2; amplía la consulta de la Fase 1."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar y editar libros (Priority: P1)

Como usuario encargado de la gestión, quiero registrar y actualizar libros para mantener el catálogo al día.

**Why this priority**: Los libros son la entidad principal de la aplicación; sin esta capacidad no existe gestión que completar.

**Independent Test**: Registrar un libro válido, abrir su detalle, editarlo y verificar que los datos reflejan lo enviado.

**Acceptance Scenarios**:

1. **Given** una categoría disponible, **When** se registra un libro con ISBN de 13 dígitos único y año entre 1900 y el año actual, **Then** el libro se guarda y puede consultarse con sus datos.
2. **Given** un libro existente, **When** se envían cambios válidos desde el formulario de edición, **Then** el detalle del libro muestra los datos actualizados.
3. **Given** un ISBN repetido o con longitud distinta de 13 dígitos, o un año anterior a 1900 o posterior al año actual, **When** se intenta guardar el formulario, **Then** el sistema rechaza el dato, muestra un error en español junto al campo correspondiente y conserva los valores introducidos por el usuario.
4. **Given** el formulario de registro vacío o incompleto, **When** se intenta guardar sin completar los campos obligatorios, **Then** el sistema muestra errores en español junto a cada campo faltante y no crea el libro.

---

### User Story 2 - Eliminar un libro (Priority: P2)

Como usuario encargado de la gestión, quiero confirmar la eliminación de un libro para evitar eliminarlo accidentalmente.

**Why this priority**: Completa la gestión del catálogo, pero depende de una decisión sobre registros relacionados (préstamos).

**Independent Test**: Solicitar y confirmar la eliminación de un libro sin préstamos asociados y comprobar que desaparece del listado.

**Acceptance Scenarios**:

1. **Given** un libro elegible para eliminación sin préstamos asociados, **When** el usuario confirma la operación de borrado, **Then** el libro se elimina mediante una solicitud `DELETE` y ya no aparece en el listado.
2. **Given** un libro existente, **When** el usuario solicita eliminarlo pero no confirma la operación, **Then** el libro permanece intacto y sin cambios.
3. **Given** un libro con préstamos registrados, **When** el usuario intenta eliminarlo, **Then** el sistema bloquea la operación, muestra un mensaje en español indicando que el libro tiene préstamos y el libro permanece en el catálogo.

---

### User Story 3 - Gestionar categorías (Priority: P3)

Como usuario encargado de la gestión, quiero crear, editar y eliminar categorías para mantener la clasificación del catálogo al día.

**Why this priority**: Es una capacidad de mantenimiento que apoya a la historia 1 (toda libro requiere una categoría), pero el catálogo ya funciona con las categorías existentes de la Fase 1.

**Independent Test**: Crear una categoría, verificarla en el listado, editarla y comprobar que la categoría eliminada (sin libros asociados) desaparece del selector de libros.

**Acceptance Scenarios**:

1. **Given** el listado de categorías, **When** el usuario envía el nombre de una categoría nueva válida y no repetida, **Then** la categoría se guarda y aparece en el listado y en el selector de libros.
2. **Given** una categoría existente, **When** el usuario envía un nombre actualizado, **Then** el cambio se refleja en el listado y en los libros que la usan.
3. **Given** una categoría sin libros asociados, **When** el usuario confirma su eliminación, **Then** la categoría se elimina mediante una solicitud `DELETE` y desaparece de todos los selectores.
4. **Given** una categoría asociada a uno o más libros, **When** el usuario intenta eliminarla, **Then** el sistema bloquea la operación, muestra un mensaje en español y la categoría permanece disponible.

---

### Edge Cases

- ISBN con ceros iniciales: se trata como una secuencia de 13 dígitos, conservando los ceros; no se aceptan separadores (guiones, espacios) ni ningún carácter no numérico [asumido, ver Supuestos].
- Año 1900 y año actual: son límites válidos e incluidos; cualquier año fuera del rango (1899 o posterior al año en curso) se rechaza.
- ISBN duplicado concurrente: si dos registros intentan usar el mismo ISBN, solo se acepta el primero y el segundo recibe error junto al campo.
- Cantidad total o disponibilidad incoherentes: se rechaza cualquier guardado donde el total sea menor que 1, la disponibilidad quede fuera del rango 0–total, o el total intentado sea inferior al número de préstamos activos del libro.
- Libro con préstamos asociados: la eliminación se bloquea y se muestra un mensaje en español; el libro permanece en el catálogo.
- Eliminación interrumpida: si la operación de borrado falla, el usuario recibe un mensaje en español y el libro permanece en el catálogo.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder crear, listar, consultar, editar y eliminar libros del catálogo.
- **FR-002**: El sistema debe registrar los atributos de Libro definidos en la constitución (ISBN, título, autor, editorial, año de publicación, número de ejemplares, ejemplares disponibles, categoría y portada) y asociar una categoría a cada libro.
- **FR-003**: El sistema debe validar en el servidor que el ISBN sea único y tenga exactamente 13 dígitos, y que el año de publicación esté entre 1900 y el año actual inclusive.
- **FR-004**: El sistema debe mostrar los errores en español junto al campo correspondiente y conservar los valores ingresados por el usuario tras un error.
- **FR-005**: El sistema debe solicitar confirmación al usuario antes de eliminar y realizar la eliminación mediante una solicitud `DELETE`; nunca debe eliminar mediante GET.
- **FR-006**: Son campos obligatorios: ISBN, título, autor, editorial, año de publicación, categoría y número de ejemplares; la portada es opcional. El título y el autor deben tener al menos 2 caracteres y no más de 255; las cantidades deben ser números enteros.
- **FR-007**: El sistema debe validar en el servidor que el número de ejemplares total sea un entero mayor o igual que 1, que los ejemplares disponibles sean un entero entre 0 y el total inclusive, y que el total no pueda editarse a un valor inferior al número de préstamos activos del libro.
- **FR-008**: El usuario debe poder crear, editar y eliminar categorías; una categoría solo puede eliminarse si no tiene libros asociados y, en caso contrario, el sistema debe bloquear la operación con un mensaje en español.
- **FR-009**: El sistema debe bloquear la eliminación de un libro que tenga préstamos registrados, indicándolo al usuario con un mensaje en español antes de confirmar.

### Key Entities

- **Libro**: registro bibliográfico con ISBN único, datos de publicación, categoría asociada, cantidades de ejemplares (total y disponibles) y portada.
- **Categoría**: clasificación que puede asociarse a uno o varios libros.
- **Préstamo**: registro relacionado con un libro y un estudiante que puede afectar la disponibilidad y la elegibilidad para eliminación de un libro.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Las cinco operaciones de gestión de libros (crear, listar, consultar, editar, eliminar) se completan correctamente en el 100 % de los casos válidos probados.
- **SC-002**: El 100 % de los casos probados con ISBN duplicado, formato de ISBN inválido o año fuera del intervalo 1900–año actual se rechazan con un error visible junto al campo correspondiente.
- **SC-003**: Ninguna eliminación probada se ejecuta sin confirmación previa del usuario ni mediante una solicitud GET.
- **SC-004**: El 100 % de los formularios con errores conservan los valores introducidos por el usuario y muestran mensajes en español.
- **SC-005**: Tras una edición válida, el 100 % de los detalles consultados muestran exactamente los datos actualizados.
- **SC-006**: El 100 % de los guardados con cantidades incoherentes (total menor que 1, disponibilidad fuera de 0–total o total inferior a préstamos activos) se rechazan con error junto al campo.
- **SC-007**: Las operaciones de categoría se completan en el 100 % de los casos válidos probados, y ninguna categoría con libros asociados se elimina.

## Assumptions

- «Usuario encargado» identifica a quien realiza la tarea; no implica un rol ni un sistema de permisos definido en esta funcionalidad.
- La gestión de portadas (subida, validación y eliminación de imágenes) se especifica por separado; aquí solo se asocia una portada opcional al libro.
- El ISBN se acepta únicamente como secuencia de 13 dígitos sin separadores ni caracteres no numéricos; los ceros iniciales se conservan.
- Los campos obligatorios y longitudes mínimas de FR-006 son un valor por defecto razonable derivado de los atributos de la constitución; pueden ajustarse en la fase de clarificación.
- La eliminación de un libro se bloquea si tiene préstamos registrados (respuesta Q1, opción A); la edición de cantidades sigue la regla de FR-007 (respuesta Q3, opción A).
- El mantenimiento de categorías incluye crear, editar y eliminar (respuesta Q2, opción B), sin distinción de roles: cualquier usuario encargado de la gestión puede realizarlo.
- El año actual se calcula con respecto al momento en que se realiza la operación.
- La consulta y el listado de libros ya existentes proceden de la Fase 1; esta funcionalidad los amplía con creación, edición y eliminación.
- No se definen objetivos de rendimiento adicionales más allá de la respuesta estándar de una aplicación web local.
