# Especificación: Búsqueda o filtro del catálogo

**Feature Branch**: `feature/busqueda-catalogo`

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Búsqueda o filtro del catálogo — Fase 2. Como estudiante, docente o integrante del personal administrativo, quiero buscar o filtrar el catálogo para encontrar libros relevantes. Incluye escenarios de aceptación, casos límite, requisitos FR-001 a FR-006 y criterios SC-001 a SC-005; los campos, la combinación de criterios y el mensaje de resultados vacíos se definieron en la fase de clarificación."

## Clarifications

### Session 2026-10-26

- Q: ¿Cómo debe activarse la búsqueda o el filtro en el listado de libros: con un botón que envíe el formulario o automáticamente mientras el usuario escribe? → A: Filtrado automático mientras el usuario escribe (con debounce).
- Q: ¿El texto de búsqueda debe escribirse en un único campo que se compara con título, autor, editorial e ISBN a la vez, o debe haber un campo distinto para cada uno de esos cuatro datos? → A: Un único campo de texto comparado contra título, autor, editorial e ISBN (coincide si coincide en cualquiera de ellos).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Localizar libros mediante búsqueda o filtro (Priority: P1)

Como estudiante, docente o integrante del personal administrativo, quiero buscar o filtrar el catálogo para encontrar libros relevantes.

**Why this priority**: Facilita el uso del catálogo cuando aumenta el número de registros y la constitución exige búsqueda o filtro como parte de la Fase 2.

**Independent Test**: Con los libros de prueba conocidos de la Fase 1, aplicar el criterio acordado y comprobar que el listado muestra solo los registros coincidentes y permite localizar los registros esperados.

**Acceptance Scenarios**:

1. **Given** un catálogo con libros, **When** el usuario escribe texto en el campo de búsqueda o selecciona una categoría, **Then** el listado se actualiza automáticamente y presenta únicamente los libros cuyo título, autor, editorial o ISBN contienen el texto, o que pertenecen a la categoría seleccionada.
2. **Given** un catálogo con libros, **When** el usuario aplica una búsqueda sin coincidencias, **Then** no se presenta ningún libro que no coincida con el criterio, se muestra el mensaje «No se encontraron libros que coincidan con la búsqueda» y los criterios aplicados permanecen visibles.
3. **Given** un catálogo con libros, **When** el usuario aplica varios criterios a la vez, **Then** se presentan únicamente los libros que cumplen todos los criterios aplicados simultáneamente.
4. **Given** un criterio aplicado, **When** el usuario lo limpia, **Then** el listado vuelve a mostrar el catálogo completo sin residuos del filtrado anterior.

---

### Edge Cases

- **Sin coincidencias**: la consulta no debe devolver ningún libro y debe mostrarse el mensaje «No se encontraron libros que coincidan con la búsqueda», con los criterios aplicados todavía visibles para que el usuario pueda ajustarlos.
- **Filtro vacío o sin criterio**: se muestra el catálogo completo, con la paginación habitual del listado de la Fase 1.
- **Varios criterios simultáneos**: los criterios se combinan con regla Y lógica; un libro solo aparece si cumple todos los criterios aplicados.
- **Texto compuesto solo por espacios en blanco**: se trata como si no hubiera criterio y se muestra el catálogo completo.
- **Resultados que abarcan varias páginas**: la paginación del listado de la Fase 1 se aplica sobre el conjunto de resultados que cumplen el criterio, no sobre el catálogo completo.
- **Búsqueda sin distinguir mayúsculas ni acentos**: la coincidencia no falla por diferencias de mayúsculas/minúsculas ni de acentuación.
- **ISBN con ceros iniciales**: la búsqueda por ISBN es parcial y compara el texto tal como se almacena, conservando los ceros iniciales.
- **Catálogo pequeño (datos de prueba)**: con al menos 15 libros de prueba, el criterio debe reducir el listado de forma verificable frente a datos conocidos.
- **Escritura rápida**: el filtrado automático no debe lanzar una consulta por cada tecla; un breve retardo (*debounce*) agrupa las pulsaciones y el último texto escrito determina los resultados mostrados.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder aplicar una búsqueda o filtro en el listado principal de libros; el filtrado se aplica automáticamente mientras el usuario escribe el texto (tras un breve retardo, *debounce*) o al cambiar el filtro de categoría, sin necesidad de botón de envío.
- **FR-002**: El sistema debe mostrar los resultados correspondientes al criterio seleccionado, sin incluir libros que no coincidan con él.
- **FR-003**: El usuario debe poder escribir un único texto de búsqueda que se compara mediante coincidencia que contiene contra título, autor, editorial e ISBN (un libro coincide si el texto aparece en cualquiera de esos cuatro campos), y filtrar por categoría exacta; la coincidencia no debe distinguir mayúsculas/minúsculas ni acentuación.
- **FR-004**: Cuando una búsqueda no tiene coincidencias, el sistema no debe presentar ningún libro, debe mostrar el mensaje «No se encontraron libros que coincidan con la búsqueda» en español y debe conservar visibles los criterios aplicados.
- **FR-005**: El sistema debe combinar los varios criterios aplicados con regla Y lógica; un criterio vacío debe mostrar el catálogo completo y la paginación del listado de la Fase 1 debe aplicarse sobre el conjunto de resultados filtrado.
- **FR-006**: El usuario debe poder limpiar el criterio aplicado y recuperar el listado completo del catálogo.

### Key Entities

- **Libro**: registro consultado en el catálogo; aporta los campos bibliográficos definidos en la constitución (ISBN, título, autor, editorial, año de publicación, ejemplares, disponibilidad, categoría y portada) sobre los que recae la búsqueda o el filtro.
- **Categoría**: clasificación relacionada con los libros; se utiliza como filtro del catálogo mediante coincidencia exacta.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una vez definidos los criterios, el 100 % de los casos de prueba acordados muestran solo registros coincidentes y permiten localizar los registros esperados.
- **SC-002**: Una consulta sin coincidencias no devuelve libros ajenos al criterio probado en el 100 % de los casos y presenta el mensaje «No se encontraron libros que coincidan con la búsqueda».
- **SC-003**: Con los datos de prueba de la Fase 1 (al menos 15 libros), el 100 % de los libros de prueba conocidos es localizable mediante el criterio admitido.
- **SC-004**: Tras limpiar el criterio, el listado vuelve a mostrar el catálogo completo en el 100 % de los casos probados, sin restos del filtrado anterior.
- **SC-005**: Con varios criterios aplicados, el 100 % de los libros mostrados cumple todos los criterios simultáneamente (regla Y).

## Assumptions

- Depende del listado paginado y de los datos de prueba de la Fase 1 (al menos 15 libros de prueba conocidos).
- La constitución exige búsqueda o filtro en la Fase 2 sin determinar sus campos ni obligar a combinar filtros; los campos (FR-003), la combinación con Y lógico (FR-005) y el mensaje de resultados vacíos (FR-004) se definieron en la fase de clarificación de esta especificación.
- Un texto de búsqueda compuesto solo por espacios en blanco equivale a no haber criterio.
- La búsqueda por ISBN es parcial y compara el valor almacenado como texto, conservando los ceros iniciales.
- El alcance es el listado principal de libros; el panel y los reportes de la Fase 2 quedan fuera de esta funcionalidad.
- «Estudiante, docente o integrante del personal administrativo» identifica los perfiles usuarios del catálogo; esta funcionalidad no implica un sistema de permisos diferenciado.
- No se definen objetivos de rendimiento adicionales más allá de la respuesta estándar de una aplicación web local.
