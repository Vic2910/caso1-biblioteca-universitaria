# Especificación: Panel y reportes de biblioteca

**Feature Branch**: `feature/panel-biblioteca` (propuesta)

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Panel y reportes de biblioteca — consultar los libros más prestados, los préstamos vencidos y el total de ejemplares disponibles por categoría para conocer la situación de la biblioteca. Fase 2; entrada: Constitución del Caso 1 - Biblioteca Universitaria v1.0."

## Clarifications

### Session 2026-10-26

- Q: ¿Quién puede abrir el panel de indicadores: cualquier usuario de la biblioteca (estudiante, docente y personal administrativo) o solo el personal administrativo? → A: Los tres perfiles: estudiante, docente y personal administrativo.
- Q: ¿En qué orden debe presentarse la lista de préstamos vencidos en el panel? → A: Por días de retraso, de mayor a menor.
- Q: ¿En qué orden deben aparecer las categorías en el indicador de ejemplares disponibles por categoría? → A: Alfabético por nombre de categoría (español, sin distinguir tildes ni mayúsculas).
- Q: ¿Deben mostrarse las cifras junto a cada elemento de los indicadores (número de préstamos por libro en el ranking y ejemplares disponibles por categoría)? → A: Sí, ambas cifras visibles: préstamos por libro y disponibles por categoría.
- Q: Cuando hay menos de 5 libros con préstamos registrados, ¿deben completarse las 5 posiciones del ranking con libros que aún no tienen ningún préstamo? → A: No, el ranking muestra solo los libros con al menos 1 préstamo (máximo 5).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar indicadores del panel (Priority: P1)

Como estudiante, docente o integrante del personal administrativo, quiero consultar en un panel los libros más prestados, los préstamos vencidos y el total de ejemplares disponibles por categoría, para conocer la situación de la biblioteca de un vistazo.

**Why this priority**: Reúne los tres resultados de consulta exigidos para la entrega final de la Fase 2; sin ellos el panel no cumple su propósito.

**Independent Test**: Preparar un conjunto pequeño de libros, categorías y préstamos con resultados conocidos, abrir el panel y comparar cada uno de los tres indicadores con el cálculo manual.

**Acceptance Scenarios**:

1. **Given** préstamos registrados para varios libros, **When** se consulta el panel, **Then** se identifican los libros más prestados según el conteo de préstamos definido.
2. **Given** préstamos cuya devolución prevista ya venció sin devolución registrada, **When** se consulta el panel, **Then** se presentan como vencidos con libro, estudiante, carné, fecha de préstamo, devolución prevista y días de retraso.
3. **Given** libros repartidos en distintas categorías, **When** se consulta la disponibilidad, **Then** se muestra el total de ejemplares disponibles agrupado por categoría.
4. **Given** el panel consultado con un conjunto de datos de prueba de resultados conocidos, **When** se comparan los tres indicadores con el cálculo manual, **Then** los conteos y totales coinciden en el 100 % de los casos.

---

### Edge Cases

- Sin préstamos registrados: el ranking de libros más prestados y la lista de préstamos vencidos muestran el mensaje «No hay datos disponibles» en lugar de valores vacíos o cifras sin contexto.
- Sin libros en ninguna categoría: el indicador de disponibilidad muestra el mensaje «No hay datos disponibles»; las categorías con al menos un libro se muestran siempre, incluidas las que tienen 0 disponibles, y se ocultan las categorías sin libros.
- Préstamo con devolución prevista igual a la fecha de consulta: se considera aún a tiempo, no vencido, porque la regla exige una fecha prevista estrictamente anterior a la de consulta.
- Préstamo devuelto con retraso: al registrar la devolución real, el préstamo deja de aparecer en la lista de vencidos, que solo contiene préstamos pendientes de devolución.
- Libros empatados en el número de préstamos: el empate se resuelve por orden alfabético del título, de modo que el orden final siempre es único y reproducible.
- Menos de 5 libros con préstamos: el ranking lista solo los libros con al menos 1 préstamo, sin rellenar las posiciones restantes con libros de cuenta 0; si ningún libro tiene préstamos, se muestra «No hay datos disponibles».
- Un libro con varios préstamos registrados: cuenta una vez por cada préstamo en el ranking y puede aparecer más de una vez en la lista de vencidos si tiene varios préstamos pendientes.
- Fecha de comparación para el vencimiento: es la del día en que se consulta el panel, no la del momento en que se creó el registro.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder consultar, en el panel, el ranking de libros más prestados según el número de préstamos registrados.
- **FR-002**: El usuario debe poder consultar, en el panel, la lista de préstamos vencidos.
- **FR-003**: El usuario debe poder consultar, en el panel, el total de ejemplares disponibles agrupado por categoría —mostrando la cifra junto a cada categoría—, presentado en orden alfabético por nombre de categoría en español, sin distinguir tildes ni mayúsculas/minúsculas.
- **FR-004**: El ranking de libros más prestados presenta un máximo de 5 libros: los que tienen al menos 1 préstamo registrado, ordenados de mayor a menor número de préstamos (del historial completo) y mostrando junto a cada libro su cifra de préstamos; si hay menos de 5 libros con préstamos, el ranking solo lista esos. Los empates se resuelven por orden alfabético del título, de modo que el resultado es único, reproducible y verificable con un cálculo manual.
- **FR-005**: Un préstamo está vencido cuando su fecha de devolución prevista es anterior a la fecha de consulta y no tiene registrada devolución real. De cada préstamo vencido se muestran el libro, el nombre del estudiante, el carné, la fecha de préstamo, la fecha de devolución prevista y los días de retraso. La lista se presenta ordenada por días de retraso, de mayor a menor; los empates se resuelven por orden alfabético del título del libro.
- **FR-006**: Los tres indicadores deben calcularse con los registros vigentes en el momento de la consulta, ser de solo consulta —no modifican datos— y poder consultarse sin realizar búsquedas ni filtros previos.
- **FR-007**: El panel debe presentar los tres indicadores en una sola vista, con rótulos y valores claros en español, de forma que un usuario identifique cada resultado sin conocimientos previos del sistema. El panel es consultable por los tres perfiles de usuario: estudiante, docente y personal administrativo.
- **FR-008**: Cuando un indicador no tiene datos que mostrar, debe presentarse el mensaje «No hay datos disponibles»; el indicador por categoría muestra todas las categorías con al menos un libro —incluidas las que tienen 0 disponibles— y oculta las categorías sin libros.

### Key Entities

- **Libro**: obra del catálogo cuyos préstamos se cuentan en el ranking y cuya cantidad de ejemplares disponibles se totaliza por categoría.
- **Categoría**: agrupación de libros usada para calcular y mostrar el total de ejemplares disponibles.
- **Préstamo**: registro que aporta el libro, el estudiante y las fechas de préstamo y devolución —prevista y real—, y que alimenta el conteo del ranking y la detección de vencimientos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Los tres resultados exigidos —libros más prestados, préstamos vencidos y ejemplares disponibles por categoría— están disponibles en el panel en el 100 % de las consultas realizadas con datos válidos.
- **SC-002**: En un conjunto de prueba con resultados conocidos calculado según las reglas definidas en FR-004 y FR-005, los conteos y totales de cada indicador coinciden en el 100 % de los casos con el cálculo manual.
- **SC-003**: Un usuario identifica los libros más prestados y los préstamos vencidos, y localiza la disponibilidad de una categoría, en una sola visita al panel y sin ayuda adicional.
- **SC-004**: En los casos límite definidos —sin préstamos, categorías sin libros, empates y fechas límite—, el panel se comporta conforme a las reglas acordadas en el 100 % de los casos probados.

## Assumptions

- El ranking cuenta cada préstamo registrado como una unidad por libro; no distingue entre ejemplares ni valora la duración del préstamo. El periodo considerado es el historial completo, según FR-004.
- El listado de vencidos solo contiene préstamos pendientes de devolución; los ya devueltos —incluso con retraso— no se muestran en el panel.
- La fecha de consulta para el cálculo de vencimientos y del ranking es la del día en que se abre el panel.
- El panel es una vista de solo consulta; el mantenimiento de libros, la gestión de categorías y el registro de préstamos se especifican por separado (spec 001 y la funcionalidad de préstamos).
- Los indicadores dependen de que existan libros, categorías y préstamos registrados; con los datos de prueba exigidos por la constitución —al menos 15 libros y registros de préstamo— el panel puede validarse.
- La consulta y el listado de libros y préstamos proceden de las funcionalidades previas; este panel los resume sin sustituirlos.
- No se definen objetivos de rendimiento adicionales más allá de la respuesta estándar de una aplicación web local.
