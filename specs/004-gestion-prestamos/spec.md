# Feature Specification: Registro, consulta y devolución de préstamos

**Feature Branch**: `feature/prestamos` (propuesta)

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "Especificación funcional: Registro, consulta y devolución de préstamos — Biblioteca Universitaria, Fase 2 (entrada: Constitución del Caso 1 - Biblioteca Universitaria, versión 1.0)"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar un préstamo (Priority: P1)

Como usuario encargado, quiero registrar el préstamo de un libro a un estudiante para controlar sus ejemplares.

**Why this priority**: Es la operación central del control de préstamos y disponibilidad; sin ella no existe la funcionalidad principal.

**Independent Test**: Con un libro disponible y un estudiante sin préstamos activos, registrar un préstamo y comprobar el registro y la disponibilidad.

**Acceptance Scenarios**:

1. **Dado** un libro con un ejemplar disponible y un estudiante con menos de tres préstamos activos, **cuando** se registra un préstamo con devolución prevista posterior a la fecha de préstamo, **entonces** se registra el préstamo y la disponibilidad disminuye en uno.
2. **Dado** un libro sin ejemplares disponibles, **cuando** se intenta prestar, **entonces** se rechaza la operación y la disponibilidad permanece igual.
3. **Dado** un estudiante con tres préstamos activos, **cuando** se intenta registrar otro, **entonces** se rechaza y no cambia la disponibilidad.
4. **Dada** una devolución prevista igual o anterior a la fecha de préstamo, **cuando** se intenta guardar, **entonces** se rechaza y se muestra el error junto al campo.

---

### User Story 2 - Registrar una devolución (Priority: P1)

Como usuario encargado, quiero registrar la devolución para actualizar el préstamo y recuperar la disponibilidad del ejemplar.

**Why this priority**: Mantiene coherentes los préstamos y las existencias; es la contrapartida directa del registro de préstamo.

**Independent Test**: Devolver un préstamo activo conocido y comprobar el estado, la fecha real y la disponibilidad.

**Acceptance Scenarios**:

1. **Dado** un préstamo activo, **cuando** se registra su devolución, **entonces** se registra la fecha real, el estado pasa a devuelto y la disponibilidad aumenta en uno.
2. **Dado** un préstamo ya devuelto, **cuando** se intenta repetir la devolución, **entonces** no se suma otra vez un ejemplar y se muestra el mensaje "Este préstamo ya fue devuelto." sin modificar la disponibilidad.

---

### User Story 3 - Consultar préstamos (Priority: P2)

Como usuario encargado, quiero consultar los préstamos registrados y sus estados para identificar los pendientes y devueltos.

**Why this priority**: Permite supervisar la información registrada; es complementaria al registro.

**Independent Test**: Registrar datos de prueba de préstamos y revisar el listado.

**Acceptance Scenarios**:

1. **Dados** préstamos registrados, **cuando** se consulta el listado, **entonces** se muestran sus datos (libro, estudiante, fechas y estado) y los estados correspondientes.

---

### Edge Cases

- Estado "vencido": [NEEDS CLARIFICATION: ¿cómo y cuándo se determina el estado vencido, incluida la fecha exacta de vencimiento?]
- Préstamos vencidos y límite por estudiante: [NEEDS CLARIFICATION: ¿los préstamos vencidos cuentan para el límite de tres préstamos activos por estudiante?]
- Edición o eliminación de préstamos: [NEEDS CLARIFICATION: ¿se permite editar o eliminar préstamos y cómo afectan esas operaciones a las existencias y al historial?]
- Mismo estudiante identificado por carné: dos registros con el mismo carné (con o sin espacios sobrantes) se consideran el mismo estudiante para el límite de tres préstamos.
- Devolución de un préstamo vencido: se permite; la fecha real es obligatoria y no puede ser anterior a la fecha de préstamo.
- Dos solicitudes simultáneas para el último ejemplar: la regla de disponibilidad se mantiene y solo una puede consumir el ejemplar; la solicitud rechazada recibe el mensaje "No hay ejemplares disponibles de este libro." y la disponibilidad no baja de cero.
- Intento de préstamo de un libro inexistente o no registrado: se rechaza la operación.
- Fecha real de devolución ausente o anterior a la fecha de préstamo: se rechaza y se muestra el error junto al campo, conservando lo ingresado.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder registrar y listar préstamos vinculados a un libro y al nombre y carné de un estudiante.
- **FR-002**: El sistema debe registrar las fechas de préstamo, devolución prevista y devolución real, y manejar los estados activo, devuelto y vencido.
- **FR-003**: La devolución prevista debe ser posterior a la fecha de préstamo; el sistema debe rechazar incumplimientos en el servidor y mostrar el error junto al campo correspondiente.
- **FR-004**: El sistema debe impedir préstamos sin disponibilidad o que superen tres préstamos activos por estudiante, rechazando la operación sin modificar la disponibilidad.
- **FR-005**: El sistema debe disminuir en uno los ejemplares disponibles al prestar y aumentarlos en uno al devolver; una misma devolución no debe incrementar la cantidad dos veces.
- **FR-006**: El sistema debe centralizar las reglas de préstamo en un solo lugar y mostrar los errores de validación en español junto a los campos pertinentes, conservando lo ingresado por el usuario.
- **FR-007**: [NEEDS CLARIFICATION: ¿se permite editar o eliminar préstamos y, si es así, cómo afectan esas operaciones a las existencias y al historial?]

### Key Entities

- **Préstamo**: vínculo entre un libro y un estudiante identificado por nombre y carné; incluye fecha de préstamo, devolución prevista, devolución real y estado (activo, devuelto o vencido).
- **Libro**: obra prestada, con su cantidad total de ejemplares y sus ejemplares disponibles; debe existir y tener disponibilidad antes de registrar un préstamo.
- **Estudiante**: identificado por su nombre y carné dentro del propio préstamo; no se asume un registro independiente de estudiantes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En los casos válidos probados, cada préstamo reduce la disponibilidad exactamente en uno y cada devolución la incrementa exactamente en uno.
- **SC-002**: El 100 % de intentos probados sin disponibilidad, con tres préstamos activos o con fechas previstas inválidas se rechazan sin modificar la disponibilidad.
- **SC-003**: Todos los préstamos de prueba aparecen en el listado con los datos y estados registrados.
- **SC-004**: El 100 % de los rechazos muestran un mensaje en español junto al campo o formulario correspondiente, sin perder los datos ya ingresados por el usuario.

## Assumptions

- Los préstamos se registran para estudiantes; la constitución no establece préstamos para docentes o personal.
- Los libros y su disponibilidad deben existir antes de registrar un préstamo.
- No se presupone que exista un registro independiente de estudiantes ni autenticación; el estudiante se identifica por el carné ingresado en el préstamo.
- El carné es obligatorio y se compara entre registros eliminando únicamente espacios sobrantes al inicio y al final; el mismo carné identifica al mismo estudiante para aplicar el límite de préstamos.
- Se permite devolver un préstamo vencido; la fecha real de devolución es obligatoria y no puede ser anterior a la fecha de préstamo.
- Los mensajes de rechazo por devolución repetida ("Este préstamo ya fue devuelto.") y por falta de disponibilidad ("No hay ejemplares disponibles de este libro.") se muestran en español junto a la acción que los originó.
- Las operaciones se validan en el servidor; cualquier restricción del lado de la interfaz es complementaria, no sustitutiva.
