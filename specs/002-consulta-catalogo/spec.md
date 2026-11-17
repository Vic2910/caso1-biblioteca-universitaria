# Especificación: Consulta del catálogo y detalle de libros

**Feature Branch**: `feature/consulta-catalogo`

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Consulta del catálogo y detalle de libros — consultar los libros del catálogo en un listado paginado con imagen predeterminada para libros sin portada, y abrir la ficha de un libro para ver sus datos y su categoría. Fase 1; entrada: Constitución del Caso 1 - Biblioteca Universitaria, versión 1.0. (Borrador funcional completo recibido como descripción de la funcionalidad.)"

## Clarifications

### Session 2026-10-26

- Q: Al listar los libros en el catálogo paginado, ¿qué datos debe mostrar cada libro en su fila o tarjeta además de la portada? → A: Portada, título, autor, año de publicación y categoría.
- Q: Cuando dos libros comparten el mismo título, ¿qué campo debe definir el orden en que aparecen en el listado? → A: Por autor de la A a la Z (y, si coincide también, por ISBN).
- Q: Cuando se pide un libro inexistente o una página fuera de rango, ¿qué respuesta HTTP debe devolver la aplicación junto a la página de error? → A: HTTP 404 con vista de error personalizada y enlace para volver al catálogo.
- Q: ¿Qué campos exactos debe mostrar la ficha de detalle de un libro cuando se abre? → A: Todos los campos de la entidad Libro: portada, título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles y categoría.
- Q: ¿Cómo debe ordenarse el listado del catálogo cuando los títulos contienen letras con tilde o mayúsculas y minúsculas? → A: Orden alfabético en español que ignora tildes y diferencias de mayúsculas/minúsculas ("Álgebra" se ordena como "Algebra").
- Q: ¿Cómo llega el usuario desde el listado a la ficha de detalle de un libro? → A: Toda la tarjeta o fila del libro es un enlace clicable al detalle.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el catálogo (Priority: P1)

Como estudiante, docente o integrante del personal administrativo, quiero consultar los libros en un listado paginado para conocer el catálogo.

**Why this priority**: La consulta del catálogo es el resultado principal de la Fase 1 y la puerta de entrada de los tres perfiles de usuario al sistema.

**Independent Test**: Con los datos de prueba cargados, abrir el catálogo y navegar entre sus páginas hasta localizar libros de distintas categorías.

**Acceptance Scenarios**:

1. **Given** un catálogo con libros, **When** se abre el listado, **Then** se muestran los registros agrupados por páginas y se puede llegar a todos ellos.
2. **Given** un libro sin portada, **When** aparece en el listado, **Then** se muestra la imagen predeterminada en lugar de un espacio vacío.
3. **Given** un catálogo cuyos libros superan una página, **When** el usuario pasa a la página siguiente o vuelve a la anterior, **Then** el listado muestra los libros de esa página y se conserva el acceso al resto del catálogo.
4. **Given** un catálogo sin libros, **When** se abre el listado, **Then** se muestra el mensaje amigable «No hay libros registrados aún».
5. **Given** una página solicitada fuera del rango válido, **When** se pide esa página, **Then** se muestra una página de error (HTTP 404) con un enlace para volver al catálogo.

---

### User Story 2 - Consultar la ficha de un libro (Priority: P1)

Como usuario, quiero ver la ficha de un libro para conocer sus datos completos y su categoría.

**Why this priority**: Permite consultar la información completa de un registro; junto con la Historia 1 conforma el alcance de consulta de la Fase 1.

**Independent Test**: Seleccionar un libro conocido en el listado y contrastar su ficha con los datos de prueba cargados.

**Acceptance Scenarios**:

1. **Given** un libro existente, **When** se abre su detalle, **Then** se muestran todos sus campos (título, autor, editorial, año de publicación, ISBN, ejemplares total y disponibles) y su categoría relacionada.
2. **Given** un libro sin portada, **When** se abre su detalle, **Then** se muestra la imagen predeterminada, igual que en el listado.
3. **Given** un identificador de libro inexistente, **When** se pide su detalle, **Then** se muestra una página de error (HTTP 404) con un enlace para volver al catálogo.

---

### Edge Cases

- Catálogo vacío: al abrir el listado sin libros se muestra el mensaje amigable «No hay libros registrados aún», presentado en la zona del listado.
- Libro inexistente o página fuera de rango: se devuelve HTTP 404 con una vista de error personalizada con un mensaje claro (p. ej., «Libro no encontrado» o «Página no válida») y un enlace para volver al catálogo.
- Tamaño de página y orden del listado: 10 libros por página, en orden alfabético por título de la A a la Z ignorando tildes y mayúsculas/minúsculas, con desempate por autor y después por ISBN.
- Última página incompleta: cuando la última página contiene menos libros que el tamaño de página, se muestran solo los libros existentes, sin posiciones vacías.
- Portada no disponible: si la portada registrada de un libro no puede mostrarse, se aplica la misma imagen predeterminada que a un libro sin portada.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder consultar un listado paginado de libros y desplazarse entre páginas; cada libro se muestra con su portada (o la imagen predeterminada), título, autor, año de publicación y categoría, y toda su tarjeta o fila es un enlace clicable que abre el detalle del libro.
- **FR-002**: El usuario debe poder abrir el detalle de un libro y consultar todos sus campos: portada (o imagen predeterminada), título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles y categoría.
- **FR-003**: El listado y el detalle deben mostrar una imagen predeterminada común cuando el libro no tenga portada o esta no esté disponible.
- **FR-004**: Las vistas de listado y detalle deben compartir una estructura visual común (cabecera, navegación y pie) y presentar una interfaz clara y coherente para estudiantes, docentes y personal administrativo.
- **FR-005**: El listado debe agrupar los libros en páginas de 10 libros, en orden alfabético por título de la A a la Z ignorando tildes y diferencias de mayúsculas/minúsculas («Álgebra» se ordena como «Algebra»), con desempate por autor y, en último lugar, por ISBN, de modo que el orden sea siempre determinista.

### Key Entities

- **Libro**: registro bibliográfico con ISBN, título, autor, editorial, año de publicación, cantidades de ejemplares (total y disponibles), categoría asociada y portada opcional.
- **Categoría**: clasificación que agrupa libros; cada libro pertenece a una categoría y una categoría puede contener varios libros.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En una prueba con más de 10 libros cargados, que superan una página, se puede acceder a todos los libros del catálogo mediante la paginación.
- **SC-002**: El detalle de cada libro probado muestra sus datos y su categoría correspondientes, coincidentes con los datos de prueba cargados.
- **SC-003**: El 100 % de los libros probados sin portada muestran la imagen predeterminada, tanto en el listado como en el detalle.
- **SC-004**: Al abrir el listado y el detalle, ambos presentan la misma estructura visual (cabecera, navegación y pie), reconocible como una única aplicación.

## Assumptions

- Estudiantes, docentes y personal administrativo son públicos de consulta; no se presuponen permisos diferenciados y la consulta no exige inicio de sesión en esta fase.
- La funcionalidad depende de los datos y relaciones preparados en la Fase 1 (estructura de datos, modelos, relaciones y datos de prueba con al menos 15 libros y al menos tres categorías).
- La búsqueda y el filtro del catálogo se abordan en la Fase 2 y quedan fuera del alcance de esta funcionalidad.
- La imagen predeterminada es una única imagen común incluida por el proyecto para todos los libros sin portada, tal como requiere la constitución.
- El listado ofrece controles estándar de paginación (página anterior, página siguiente y números de página) como valor por defecto.
- La ficha muestra la totalidad de los datos bibliográficos del libro y su categoría; las reglas de disponibilidad y préstamos no forman parte de esta funcionalidad.
- Esta funcionalidad solo presenta los datos del catálogo; su validación y captura corresponden a la gestión de libros.
- No se definen objetivos de rendimiento adicionales más allá de la respuesta estándar de una aplicación web local.
