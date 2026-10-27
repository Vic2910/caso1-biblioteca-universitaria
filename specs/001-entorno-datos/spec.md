# Feature Specification: Entorno y base de datos

**Feature Branch**: `feature/entorno-datos` (propuesta)

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "Especificación funcional: Entorno y base de datos (Fase 1): iniciar la aplicación local siguiendo el README y sin servicios de pago obligatorios; disponer de las entidades Libro, Categoría y Préstamo y de sus relaciones mediante un esquema creado con cambios de estructura versionados (claves foráneas y unicidad cuando corresponda); cargar datos de prueba con al menos 15 libros y tres registros por catálogo; usar las tecnologías fijadas por la constitución del proyecto y documentar la conducta de eliminación de datos relacionados. (Primera de las dos especificaciones entregadas en la invocación, seleccionada por el usuario.)"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Preparar el entorno y los datos (Priority: P1)

Como integrante del equipo, quiero iniciar la aplicación y preparar una base de datos con datos de prueba para comprobar las funcionalidades del proyecto.

**Why this priority**: Es la base necesaria para consultar y gestionar información; ninguna otra funcionalidad puede probarse sin un entorno ejecutable y datos iniciales consistentes.

**Independent Test**: Seguir el README en un entorno preparado y comprobar la conexión y los datos iniciales.

**Acceptance Scenarios**:

1. **Given** un entorno con los requisitos instalados, **When** se siguen las instrucciones de instalación, **Then** la aplicación local se inicia y conecta con la base de datos elegida.
2. **Given** una base de datos preparada con el esquema versionado, **When** se cargan los datos de prueba, **Then** existen al menos 15 libros y tres registros por catálogo.
3. **Given** las entidades de la biblioteca, **When** se inspeccionan sus relaciones, **Then** cada libro se relaciona con una categoría y cada préstamo con un libro.
4. **Given** credenciales incorrectas o la base de datos no disponible, **When** se intenta iniciar la aplicación, **Then** no se permite continuar y se muestra un aviso visible en español que explica el fallo y cómo corregir la configuración, sin exponer credenciales.

---

### User Story 2 - Documentar la conducta de eliminación (Priority: P2)

Como integrante del equipo, quiero que la conducta de eliminación de registros relacionados esté decidida y documentada en el README para saber qué ocurre al eliminar una categoría con libros o un libro con préstamos.

**Why this priority**: La constitución del proyecto exige que el comportamiento de eliminación esté documentado; no bloquea la puesta en marcha, pero debe quedar resuelto antes de implementar la gestión de registros.

**Independent Test**: Revisar el README para localizar la conducta documentada y comprobarla con un intento de eliminación controlado sobre datos de prueba.

**Acceptance Scenarios**:

1. **Given** una categoría con libros o un libro con préstamos, **When** se intenta eliminar el registro, **Then** la eliminación se bloquea con un aviso que indica que existen registros relacionados, tal y como describe el README.
2. **Given** una categoría sin libros o un libro sin préstamos, **When** se elimina el registro, **Then** la eliminación se realiza con éxito.
3. **Given** la conducta decidida, **When** se revisa el README, **Then** la restricción de eliminación de registros relacionados aparece explicada en español.

---

### Edge Cases

- La conexión falla o las credenciales son incorrectas: se impide continuar hasta corregir la configuración; se muestra un aviso visible en español que describe el fallo, indica que hay que revisar la configuración de conexión y no revela credenciales.
- Eliminación de una categoría con libros o de un libro con préstamos: se restringe; la eliminación se bloquea mientras existan registros relacionados y se muestra un aviso con el motivo. La conducta debe documentarse en el README.
- La base de datos existe pero el esquema todavía no está creado: no hay datos que consultar; primero se crea el esquema y después se cargan los datos de prueba.
- Se vuelve a cargar los datos de prueba sobre una base ya poblada: la carga se define sobre una base creada o refrescada; recargar sobre datos modificados manualmente no forma parte de esta fase.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El equipo debe poder ejecutar la aplicación localmente siguiendo los pasos del README, sin servicios de pago obligatorios y mediante un único comando de inicio documentado.
- **FR-002**: El sistema debe disponer de los datos de libros, categorías y préstamos y de sus relaciones: una categoría agrupa muchos libros, cada libro pertenece a una categoría y cada préstamo corresponde a un libro.
- **FR-003**: El esquema debe crearse exclusivamente mediante migraciones, es decir, cambios de estructura versionados; debe incluir claves foráneas y restricciones de unicidad cuando corresponda (por ejemplo, ISBN único).
- **FR-004**: El equipo debe poder cargar como datos de prueba al menos 15 libros y tres registros por catálogo (categorías y préstamos).
- **FR-005**: La aplicación debe funcionar con las tecnologías fijadas por la constitución del proyecto y con el motor de base de datos elegido entre los que ella permite; la elección debe documentarse en el README.
- **FR-006**: La eliminación de una categoría con libros o de un libro con préstamos debe restringirse: no se permite mientras existan registros relacionados, se informa del motivo al usuario y la conducta debe documentarse en el README.
- **FR-007**: Las credenciales de conexión deben permanecer fuera del repositorio y poder cambiar sin editar archivos versionados.
- **FR-008**: Ante una conexión fallida o credenciales incorrectas, la aplicación debe impedir continuar y mostrar un aviso visible, en español, que describa el fallo e indique cómo corregir la configuración, sin exponer credenciales.

### Key Entities *(include if feature involves data)*

- **Libro**: obra con ISBN, título, autor, editorial, año de publicación, ejemplares totales y disponibles, categoría y portada; se relaciona con una categoría y puede tener muchos préstamos.
- **Categoría**: clasificación que agrupa libros; se relaciona con muchos libros.
- **Préstamo**: relación de un libro con el nombre y el carné del estudiante, con las fechas de préstamo, devolución prevista y devolución real, y el estado (activo, devuelto o vencido); corresponde a un libro.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En una instalación preparada, las tres entidades están presentes con sus relaciones: el 100 % de los libros tiene una categoría asignada y el 100 % de los préstamos corresponde a un libro existente.
- **SC-002**: La carga inicial genera al menos 15 libros y tres registros por catálogo.
- **SC-003**: Un integrante del equipo completa la instalación y la puesta en marcha en una sola verificación siguiendo únicamente los pasos documentados en el README.
- **SC-004**: Ante una configuración de conexión incorrecta, la aplicación bloquea el inicio con un aviso visible en el 100 % de los intentos y sin revelar credenciales.
- **SC-005**: El 100 % de los intentos de eliminar una categoría con libros o un libro con préstamos quedan bloqueados y la restricción aparece documentada en el README.

## Assumptions

- El stack tecnológico exacto (lenguaje, framework, gestor de base de datos) y el comando de ejecución local están fijados por la constitución del proyecto (Versión 1.0) y documentados en el README; esta especificación los referencia sin repetirlos.
- Ante credenciales incorrectas o una conexión fallida se muestra, como valor por defecto, un aviso en español que explica el fallo, indica cómo corregir la configuración y no expone credenciales.
- Los datos de prueba se cargan sobre una base de datos recién creada o refrescada; recargarlos sobre datos modificados manualmente no forma parte de esta fase.
- Alcance: esta especificación cubre únicamente el entorno y los datos; la consulta del catálogo y su detalle se especifican por separado, y la búsqueda, los filtros y la gestión de registros pertenecen a fases posteriores.
- La especificación de la consulta del catálogo depende de los datos y relaciones preparados aquí.
- El equipo dispone de un entorno compatible y de un motor de base de datos permitido.
- Las migraciones y los datos de prueba son una dependencia de las pruebas de consulta y gestión.
- Las credenciales permanecen fuera del repositorio; no se presupone un servicio de pago.
