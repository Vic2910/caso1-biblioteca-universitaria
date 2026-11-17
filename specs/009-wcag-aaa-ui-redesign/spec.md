# Feature Specification: Rediseño de interfaz a estándares mundiales (WCAG 2.1 AAA aplicable)

**Feature Branch**: `feature/wcag-aaa-ui-redesign` (propuesta)

**Created**: 2026-11-17

**Status**: Draft

**Input**: User description: "Rediseño de la interfaz a estándares mundiales y profesionales: pasar de una apariencia de plantilla genérica a una aplicación web de primer nivel. Requisitos centrales: (1) cumplimiento de WCAG 2.1 nivel AAA en los criterios aplicables a una aplicación de texto y datos, con matriz de aplicabilidad documentada (contraste 7:1 en texto normal, operación completa por teclado, foco visible, información no dependiente del color, advertencia y posibilidad de extender la sesión antes de que caduque); (2) aplicación de los principios universales del diseño (equitativo, flexible, simple, perceptible, tolerante, esfuerzo físico bajo, tamaño y espacio adecuados); (3) navegación con menús desplegables y secciones colapsables que sigan patrones ARIA, sean operables con teclado, degradables sin JavaScript e incluyan menú tipo hamburguesa en móvil, manteniendo los tres breakpoints ya verificados (360 × 640, 768 × 1024 y ≥ 1024 px); (4) design system coherente de tokens, tipografía, espaciado, color, componentes y estados, implementado con Tailwind 4 y Vite —ya instalados pero sin cablear— y declarado en el README; (5) re-especificación de la estructura de todas las pantallas (home, catálogo, libros, categorías, préstamos, panel, login y vistas de error) con al menos una acción principal visible en cada listado —incluido el registro de préstamos, hoy inalcanzable desde la interfaz— y un home que ofrezca accesos reales en lugar de un simple aviso de conexión; (6) experiencia de subida de portadas con pre-validación de tamaño y formato en el cliente, mensajes claros de rechazo y manejo elegante del rechazo del servidor, para que un archivo fuera de límites nunca rompa la página; (7) declaración explícita de los contratos de interfaz de las specs 002 a 008 que quedan reemplazados por esta especificación. Sin cambios en el esquema de datos. Entrada: Constitución del Caso 1 - Biblioteca Universitaria, versión 1.0, y las decisiones del equipo del 2026-11-17 (AAA en criterios aplicables, Tailwind 4 + Vite, correcciones de usabilidad plegadas en esta especificación)."

## Clarifications

### Session 2026-11-17

- Q: ¿Qué nivel de WCAG se exige y con qué respaldo? → A: Nivel AAA en los criterios aplicables a una aplicación de texto y datos, con matriz de aplicabilidad documentada para cada criterio.
- Q: ¿Qué herramienta de estilos se usa y cómo se declara? → A: Tailwind 4 y Vite (ya instalados), declarados en el README como parte del sistema de diseño.
- Q: ¿Se modifica el esquema de datos? → A: No; la especificación es de interfaz y no añade ni modifica entidades, tablas ni reglas de negocio.
- Q: ¿La pantalla de home debe ser accesible sin iniciar sesión o solo para usuarios autenticados? → A: Público: el home es accesible sin sesión y sus accesos piden iniciar sesión al usarse.
- Q: ¿Cómo debe indicar la interfaz que una pantalla o listado aún se está cargando? → A: Indicador con texto en el área de contenido (p. ej., "Cargando…"), anunciado y sin bloquear el teclado.
- Q: ¿Cómo debe confirmar la interfaz que una operación se completó correctamente? → A: Mensaje emergente temporal (toast) en español que desaparece solo, sin exigir cierre.
- Q: ¿Qué vistas de error debe cubrir el rediseño? → A: Solo las existentes: 401, 404 y conexión; no se añaden otras (p. ej., 500 genérico o 403).
- Q: ¿Cómo se verificará el cumplimiento de la matriz WCAG en la aceptación? → A: Herramienta automatizada de auditoría + revisión manual de los criterios que la herramienta no cubre.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Navegar con teclado, con y sin JavaScript (Priority: P1)

Como usuario —incluidos quienes no usan el ratón o emplean tecnologías de apoyo—, quiero una navegación con menús desplegables y secciones colapsables operables con teclado, descrita correctamente para lectores de pantalla, con un menú tipo hamburguesa en móvil, para llegar a cualquier sección sin depender del puntero ni de JavaScript.

**Why this priority**: La navegación es el cuello de botella de toda la aplicación: sin ella, ninguna otra pantalla es alcanzable. Es la base sobre la que se apoyan el resto de historias.

**Independent Test**: Recorrer la navegación completa usando solo el teclado (Tab, Shift+Tab, Enter, Espacio, flechas, Escape) en los tres tamaños de pantalla, con y sin JavaScript activado, comprobando que cada sección es alcanzable, que su estado se anuncia y que el foco siempre es visible.

**Acceptance Scenarios**:

1. **Dada** la aplicación en un navegador sin JavaScript, **cuando** el usuario activa enlaces y controles de navegación con el teclado, **entonces** todas las secciones y pantallas siguen siendo alcanzables y legibles.
2. **Dado** un menú desplegable cerrado, **cuando** el usuario llega a él con Tab y pulsa Enter o Espacio, **entonces** se despliega, el foco pasa a sus elementos, la tecnología de apoyo anuncia su estado y Escape lo cierra devolviendo el foco al disparador.
3. **Dada** una sección colapsable cerrada, **cuando** el usuario la activa con teclado, **entonces** se expande o contrae, su estado queda anunciable y el contenido expandido es operable.
4. **Dada** una pantalla de 360 × 640 px, **cuando** el usuario abre el menú hamburguesa, **entonces** aparece la navegación completa, es operable con teclado y no hay contenido recortado ni desbordamiento horizontal.
5. **Dada** una ventana con el foco en un elemento, **cuando** el usuario navega por la página, **entonces** el indicador de foco es siempre visible y no queda oculto bajo cabeceras u otros elementos.

---

### User Story 2 - Encontrar las acciones principales en cada pantalla (Priority: P1)

Como usuario, quiero que el home ofrezca accesos reales al sistema y que cada listado muestre de forma visible al menos una acción principal —incluido el registro de préstamos— para poder trabajar sin adivinar rutas ni recordar direcciones.

**Why this priority**: Hoy el registro de préstamos es inalcanzable desde la interfaz y el home solo muestra un aviso de conexión: sin estas correcciones, parte del sistema funciona pero es inexistente para el usuario.

**Independent Test**: Abrir cada pantalla (home, catálogo, libros, categorías, préstamos, panel, login y errores), identificar y ejecutar su acción principal; verificar que el registro de préstamos es alcanzable en dos pasos o menos desde el home.

**Acceptance Scenarios**:

1. **Dado** el home, **cuando** el usuario lo abre —con o sin sesión iniciada—, **entonces** ve accesos reales a catálogo, libros, categorías, préstamos y panel, no solo un aviso de conexión; si no tiene sesión, el acceso conduce al inicio de sesión sin ejecutar ninguna operación.
2. **Dado** el listado de préstamos, **cuando** el usuario lo consulta, **entonces** ve y puede activar la acción de registrar un préstamo, que abre el formulario de registro.
3. **Dadas** las listas de catálogo, libros, categorías y préstamos, **cuando** una lista está vacía, **entonces** se muestra un mensaje en español indicando que no hay registros junto a la acción principal para crear el primer registro.
4. **Dado** un usuario autenticado, **cuando** navega desde el home hasta registrar un préstamo y confirmarlo, **entonces** completa el recorrido sin escribir ninguna dirección ni volver atrás.
5. **Dadas** las pantallas de login y de error (401, 404 y conexión), **cuando** se muestran, **entonces** explican qué ocurrido en español y ofrecen al menos una acción clara (volver a un destino útil o reintentar).

---

### User Story 3 - Cumplir los criterios AAA aplicables y proteger la sesión (Priority: P2)

Como usuario, quiero que el texto tenga un contraste de 7:1, que la información no dependa solo del color, que el foco sea visible y que el sistema me avise y me permita extender la sesión antes de que caduque, para usar la aplicación con claridad y sin perder mi trabajo por inactividad.

**Why this priority**: Es el requisito central de accesibilidad del rediseño, pero depende de que las pantallas y la navegación existan (historias 1 y 2).

**Independent Test**: Auditar el contraste real del texto en cada pantalla, comprobar estados sin color, recorrer las tareas con teclado y simular la inactividad con un periodo de caducidad acortado.

**Acceptance Scenarios**:

1. **Dado** cualquier texto normal de la interfaz, **cuando** se mide su contraste contra el fondo real, **entonces** alcanza al menos 7:1.
2. **Dado** un elemento cuya información se distingue por color (por ejemplo, el estado de un préstamo: activo, devuelto o vencido), **cuando** se consulta sin distinguir colores o en escala de grises, **entonces** la información sigue siendo comprensible mediante texto, icono con etiqueta u otro recurso no cromático.
3. **Dado** un usuario inactivo cerca del vencimiento de la sesión, **cuando** quedan 5 minutos, **entonces** aparece un aviso visible y anunciado con el tiempo restante y las opciones de extender la sesión o salir.
4. **Dado** el aviso de sesión mostrado, **cuando** el usuario elige extender, **entonces** la sesión se prolonga sin perder los datos introducidos y el aviso desaparece.
5. **Dada** una sesión que caduca mientras el usuario completa un formulario, **cuando** se envía, **entonces** la operación no se ejecuta a medias, se explica en español qué ocurrió y los datos introducidos no se pierden cuando sea posible conservarlos.

---

### User Story 4 - Subir una portada sin romper la página (Priority: P2)

Como usuario, quiero que al elegir un archivo de portada el sistema compruebe de inmediato formato y tamaño, me explique claramente por qué rechaza un archivo y se recupere con elegancia si el servidor lo rechaza, para no perder el formulario ni encontrarme con una página rota.

**Why this priority**: La subida de portadas es frecuente en la gestión de libros; un archivo fuera de límites puede romper la página e impedir continuar cualquier operación.

**Independent Test**: Intentar subir, en el formulario de libro, archivos de más de 2 MB, de formato no permitido, corruptos y válidos, con y sin JavaScript, comprobando en todos los casos que el formulario sigue utilizándose.

**Acceptance Scenarios**:

1. **Dado** un archivo de más de 2 MB o de formato distinto de JPG, PNG o WebP, **cuando** el usuario lo selecciona, **entonces** se rechaza antes de enviarlo, con un mensaje en español que indica el formato permitido y el tamaño máximo, y el formulario permanece intacto.
2. **Dado** un archivo válido, **cuando** el usuario lo selecciona, **entonces** se confirma que es aceptable y el envío continúa con normalidad.
3. **Dado** un archivo que el servidor rechaza por cualquier motivo, **cuando** se recibe la respuesta, **entonces** se muestra un mensaje claro en español junto al campo, no se pierden los demás datos del formulario y la página sigue siendo operable.
4. **Dada** la subida realizada sin JavaScript, **cuando** se envía el formulario, **entonces** el rechazo se produce en el servidor con el mismo mensaje claro y el formulario conserva los datos compatibles con la reentrada del servidor.

---

### User Story 5 - Contar con un sistema de diseño coherente y documentado (Priority: P3)

Como usuario y como integrante del equipo, quiero una interfaz con tokens, tipografía, espaciado, color, componentes y estados coherentes en todas las pantallas, y que el sistema de diseño quede declarado en el README, para que la aplicación se vea y se comporte como un producto profesional y no como una plantilla genérica.

**Why this priority**: Mejora la percepción de calidad y la mantenibilidad, pero es transversal y no bloquea la finalización de tareas.

**Independent Test**: Comparar visual y funcionalmente dos pantallas distintas (por ejemplo, listado de libros y formulario de préstamo) y verificar que comparten tokens, tipografía, espaciado, estados y componentes; revisar el README por la declaración del sistema de diseño.

**Acceptance Scenarios**:

1. **Dadas** dos pantallas distintas, **cuando** se comparan, **entonces** usan las mismas reglas de tipografía, espaciado, color, radios, sombras, botones, campos y estados (normal, hover, foco, activo, deshabilitado, error).
2. **Dado** el README, **cuando** se lee la sección del sistema de diseño, **entonces** declara la herramienta de estilos acordada (Tailwind 4 con Vite) y describe tokens, tipografía, espaciado, color, componentes y estados.
3. **Dada** la interfaz con preferencia de alto contraste del sistema operativo, **cuando** se muestra, **entonces** el contenido sigue siendo legible y los componentes conservan sus bordes y estados.

---

### User Story 6 - Declarar qué contratos de interfaz reemplaza esta especificación (Priority: P3)

Como revisor del proyecto, quiero una declaración explícita de qué contratos de interfaz de las especificaciones 002 a 008 quedan reemplazados por esta especificación, para que no convivan dos especificaciones contradictorias sobre las mismas pantallas.

**Why this priority**: Es una exigencia de gobernanza del propio encargo; no cambia el comportamiento visible, pero evita ambigüedad futura.

**Independent Test**: Revisar que la especificación lista, una por una, las specs 002 a 008 con el alcance de interfaz que asume y qué permanece vigente en cada caso.

**Acceptance Scenarios**:

1. **Dada** la especificación 009, **cuando** se lee la sección de contratos reemplazados, **entonces** aparecen las especificaciones 002, 003, 004, 005, 006, 007 y 008 con el alcance de interfaz que asumen.
2. **Dada** una regla de negocio de cualquiera de esas especificaciones (por ejemplo, el límite de tres préstamos activos), **cuando** se busca en 009, **entonces** se confirma que no se modifica: solo se redefine la presentación y la interacción.

---

### Edge Cases

- Navegación sin JavaScript: todos los enlaces y secciones deben seguir siendo alcanzables mediante un mecanismo que no dependa de scripts.
- Usuario que navega exclusivamente con teclado: ningún control debe quedar inalcanzable, atrapado o sin foco visible; no debe haber trampas de foco.
- Zoom al 200 % y reflow en ventanas estrechas: sin pérdida de contenido ni desbordamiento horizontal en los tamaños acordados.
- Sesión que caduca mientras se rellena un formulario o se sube un archivo: la operación no se ejecuta a medias y se explica en español.
- Aviso de sesión no respondido: la sesión caduca de forma controlada y se solicita iniciar sesión de nuevo.
- Archivo de portada vacío, de 0 bytes, corrupto, con extensión válida pero contenido no válido, o con nombre con caracteres especiales y acentos: se rechaza o se usa correctamente sin romper el formulario.
- Portada inexistente o eliminada: se muestra la imagen predeterminada.
- Listas vacías (catálogo, libros, categorías, préstamos): mensaje en español más acción principal, nunca un área vacía sin explicación.
- Títulos, nombres, ISBN y carné largos: sin desbordamiento ni solapamiento en los tres tamaños.
- Estados de préstamo (activo, devuelto, vencido) y disponibilidad de ejemplares comunicados sin depender del color.
- Errores 401, 404 y de conexión: página útil con acción de salida o reintento y la misma identidad visual.
- Usuario sin sesión que pulsa un acceso del home: se le solicita iniciar sesión, no se ejecuta ninguna operación y el resto del home sigue siendo navegable.
- Usuario con preferencia de movimiento reducido: no hay animaciones esenciales para comprender la interfaz.
- Varios errores de validación a la vez: todos los mensajes aparecen junto a sus campos, en español, con el foco llevado al primer error.
- Retardo al cargar una pantalla o un listado: se muestra en el área de contenido un texto en español (p. ej., "Cargando…"), anunciado a las tecnologías de apoyo y sin bloquear la navegación por teclado.
- Mensaje de éxito que aparece mientras el foco está en otro elemento: se anuncia sin arrebatar el foco ni cerrar el mensaje; si el usuario no lo llega a leer, el resultado sigue visible en la pantalla (listado o detalle actualizado).
- Caracteres del español (á, é, í, ó, ú, ñ, ¿, ¡) en formularios, listados y mensajes: se muestran correctamente.

## Requirements *(mandatory)*

### Functional Requirements

**Accesibilidad y principios universales**

- **FR-001**: La interfaz debe cumplir WCAG 2.1 nivel AAA en los criterios aplicables a una aplicación de texto y datos, según la matriz de aplicabilidad documentada en esta especificación.
- **FR-002**: Todo el texto normal debe alcanzar un contraste mínimo de 7:1 contra su fondo real, incluidos los estados de foco, hover, activo, error y deshabilitado.
- **FR-003**: Ninguna información ni acción debe depender exclusivamente del color; los estados (disponibilidad, estado de préstamo, errores, éxito) deben incluir texto, icono con etiqueta u otro recurso no cromático.
- **FR-004**: Toda función debe ser operable completamente con el teclado, con orden de foco lógico, foco siempre visible y sin trampas de foco.
- **FR-005**: El sistema debe avisar cuando quedan 5 minutos para que caduque la sesión por inactividad (2 horas) y ofrecer una acción de extensión que mantenga los datos introducidos; al caducar, debe explicar qué ocurrió y pedir iniciar sesión de nuevo.
- **FR-006**: La interfaz debe aplicar los siete principios universales del diseño: equitativo (mismo contenido y funciones para todos), flexible (adaptable a distintos métodos de uso y tamaños), simple (lenguaje claro y pasos evidentes), perceptible (información comunicada de forma que la perciban todos los usuarios), tolerante (minimiza errores y ayuda a recuperarse), esfuerzo físico bajo (interacciones cortas y sin precisión excesiva) y tamaño y espacio adecuados (objetivos táctiles y espaciado suficientes).

**Navegación**

- **FR-007**: La navegación debe incluir menús desplegables y secciones colapsables que sigan los patrones ARIA correspondientes (estados anunciados, relación con el contenido, apertura y cierre anunciados), sean operables con teclado y degradables sin JavaScript.
- **FR-008**: En móvil debe existir un menú tipo hamburguesa que exponga la misma estructura de navegación, operable con teclado y con su estado anunciado.
- **FR-009**: Los tres tamaños verificados deben mantenerse sin recortes ni desbordamientos: 360 × 640 px, 768 × 1024 px y pantallas de 1024 px o más de ancho.

**Pantallas**

- **FR-010**: Cada pantalla —home, catálogo, libros, categorías, préstamos, panel, login y vistas de error— debe re-especificar su estructura: cabecera y navegación comunes, contenido principal, acciones, estados vacíos, estados de error y estados de carga.
- **FR-011**: Cada listado (catálogo, libros, categorías, préstamos) debe mostrar al menos una acción principal visible, incluido el registro de préstamos, hoy inalcanzable desde la interfaz.
- **FR-012**: El home debe ofrecer accesos reales a las secciones del sistema en lugar de un simple aviso de conexión; el home es accesible sin iniciar sesión y sus accesos a secciones protegidas conducen al inicio de sesión sin ejecutar ninguna operación.
- **FR-013**: Toda lista vacía debe mostrar un mensaje en español junto a la acción principal disponible; ninguna pantalla debe presentar áreas vacías sin explicación ni acciones.
- **FR-014**: Las vistas de error (401, 404 y conexión) deben explicar lo ocurrido en español y ofrecer al menos una acción de salida o reintento; el rediseño se limita a estas tres vistas y no añade otras (por ejemplo, un error genérico 500 o 403).

**Sistema de diseño**

- **FR-015**: Debe existir un sistema de diseño coherente que defina tokens (color, espaciado, radios, sombras, tamaños), tipografía, componentes (botones, campos, tarjetas, tablas, alertas, menús, estados vacíos) y estados (normal, hover, foco, activo, error, deshabilitado), aplicado de forma consistente en todas las pantallas.
- **FR-016**: El sistema de diseño debe declararse en el README, incluyendo la herramienta de estilos acordada por el equipo (Tailwind 4 con Vite) y la descripción de tokens, tipografía, espaciado, color, componentes y estados.

**Subida de portadas**

- **FR-017**: El formulario de portada debe pre-validar en el cliente el formato (JPG, PNG o WebP) y el tamaño (máximo 2 MB) antes de enviar, con mensajes claros de rechazo en español.
- **FR-018**: Un archivo fuera de límites no debe romper la página: se rechaza en el cliente cuando es posible y, si el servidor lo rechaza, se muestra un mensaje claro junto al campo sin perder el resto del formulario.
- **FR-019**: Debe mostrarse una imagen predeterminada cuando no exista portada o no pueda cargarse.

**Gobernanza y alcance**

- **FR-020**: Esta especificación (009) reemplaza los contratos de interfaz de las especificaciones 002 a 008, según la sección "Contratos de interfaz reemplazados"; sus reglas de negocio, de datos y de seguridad permanecen vigentes sin cambios.
- **FR-021**: No debe realizarse ningún cambio en el esquema de datos: no se añaden, eliminan ni modifican entidades, atributos, tablas ni reglas de negocio.

**Confirmación de operaciones**

- **FR-022**: Toda operación completada con éxito (guardar, eliminar, registrar préstamo, devolver, subir portada) debe mostrar un mensaje emergente temporal (toast) en español que desaparece solo sin exigir cierre, anunciado a las tecnologías de apoyo y sin mover el foco ni bloquear la interacción; el resultado de la operación debe seguir visible en la propia pantalla (p. ej., el registro aparece en el listado), de modo que la información no se pierda cuando el mensaje desaparezca.

**Verificación de accesibilidad**

- **FR-023**: La verificación del cumplimiento debe combinar una auditoría automatizada (contraste, roles y estados) con una revisión manual de los criterios que esa herramienta no cubre (operación por teclado, visibilidad del foco, degradación sin JavaScript, aviso y extensión de sesión, estados sin color), aplicada a las ocho pantallas y a los tres tamaños de aceptación.

### Matriz de aplicabilidad WCAG 2.1

Contraste 7:1, operación completa por teclado, foco visible, información no dependiente del color y aviso con extensión de sesión son requisitos explícitos del encargo y figuran como **Aplicable (requisito central)**. El resto se decide así:

| Criterio | Nivel | Aplicabilidad | Justificación |
| --- | --- | --- | --- |
| 1.1.1 Contenido no textual | A | Aplicable | Texto alternativo en portadas e iconos |
| 1.3.1 Información y relaciones | A | Aplicable | Etiquetas, encabezados y tablas con relación semántica |
| 1.3.2 Orden significativo | A | Aplicable | Orden de lectura coherente |
| 1.4.1 Uso del color | A | Aplicable (requisito central) | Estados sin depender del color |
| 1.4.3 Contraste (mínimo) | AA | Aplicable | Subsumido por 1.4.6 |
| 1.4.4 Redimensionado hasta 200 % | AA | Aplicable | Sin pérdida de contenido con zoom |
| 1.4.5 Imágenes de texto | AA | Aplicable | Texto real, no incrustado en imágenes |
| 1.4.6 Contraste (mejorado) | AAA | **Aplicable (requisito central)** | 7:1 en texto normal |
| 1.4.10 Reflow | AA | Aplicable | Sin desbordamiento horizontal a 360 px |
| 1.4.11 Componentes no textuales | AA | Aplicable | Bordes y estados visibles en campos y botones |
| 1.4.12 Espaciado de texto | AA | Aplicable | Legible con ajustes de espaciado del usuario |
| 1.4.13 Contenido al pasar el puntero o enfocarlo | AAA | Aplicable | El aviso de sesión no desaparece al enfocarlo |
| 2.1.1 Teclado | A | **Aplicable (requisito central)** | Operación completa por teclado |
| 2.1.2 Sin trampa de teclado | A | **Aplicable (requisito central)** | Sin trampas de foco |
| 2.1.4 Atajos con carácter único | AAA | No aplicable | La interfaz no define atajos de este tipo |
| 2.2.1 Ajuste temporal | AAA | **Aplicable (requisito central)** | Aviso y extensión antes de caducar la sesión |
| 2.2.2 Pausar, detener, ocultar | A | Aplicable | Sin carruseles ni movimiento automático esencial |
| 2.2.5 Volver a autenticar tras caducar | AAA | **Aplicable (requisito central)** | Reautenticación explicable y sin pérdida evitable |
| 2.3.1 Destellos o parpadeos | A | Aplicable | Sin destellos peligrosos |
| 2.4.1 Bloques de salto | A | Aplicable | Salto al contenido principal |
| 2.4.2 Título de la página | A | Aplicable | Título descriptivo por pantalla |
| 2.4.3 Orden de foco | A | **Aplicable (requisito central)** | Orden lógico al navegar |
| 2.4.4 Enlace en contexto | A | Aplicable | Enlaces comprensibles |
| 2.4.6 Encabezados y etiquetas | AA | Aplicable | Descriptivos |
| 2.4.7 Foco visible | AA | **Aplicable (requisito central)** | Indicador de foco siempre visible |
| 2.4.9 Propósito del enlace | AAA | Aplicable | Enlaces comprensibles sin contexto |
| 2.4.10 Encabezados por norma | AAA | Aplicable | Estructura de encabezados coherente |
| 2.4.11 Foco no obscurecido | AAA | Aplicable | Foco visible bajo cabeceras fijas |
| 2.4.13 Foco y apuntador | AAA | Aplicable | Pares foco/estilo y apuntador/estilo visibles |
| 2.5.1 Gestos punteros | A | Aplicable | Sin gestos múltiples obligatorios |
| 2.5.3 Etiqueta en el nombre | AAA | Aplicable | Texto visible coincide con el nombre accesible |
| 2.5.4 Activación por movimiento | A | Aplicable | Sin controles por movimiento |
| 2.5.5 Tamaño del objetivo | AAA | Aplicable | Objetivos táctiles de al menos 44 px |
| 3.1.1 Idioma de la página | A | Aplicable | Idioma declarado |
| 3.1.2 Idioma de partes | AA | Aplicable | Términos en otros idiomas marcados |
| 3.1.3 Palabras poco habituales | AAA | Parcialmente aplicable | Siglas (ISBN, carné) explicadas al primer uso |
| 3.1.4 Abreviaturas | AAA | Parcialmente aplicable | Abreviaturas expandidas al primer uso |
| 3.1.5 Nivel de lectura | AAA | No aplicable | Requiere contenido redactado; la interfaz usa textos breves de gestión |
| 3.1.6 Pronunciación | AAA | No aplicable | Sin términos cuya pronunciación sea crítica |
| 3.2.1 Al recibir foco | A | Aplicable | Sin cambios de contexto al enfocar |
| 3.2.2 Al recibir entrada | A | Aplicable | Sin cambios de contexto inesperados |
| 3.2.3 Navegación coherente | AA | Aplicable | Navegación siempre en la misma posición |
| 3.2.4 Identificación coherente | AA | Aplicable | Componentes con el mismo nombre |
| 3.2.5 Cambio de contexto a petición | AAA | Aplicable | Los cambios ocurren solo por acción explícita |
| 3.3.1 Identificación de errores | A | Aplicable | Errores junto al campo |
| 3.3.2 Etiquetas o instrucciones | A | Aplicable | Etiquetas visibles asociadas |
| 3.3.3 Sugerencia de error | AA | Aplicable | Corrección sugerida en español |
| 3.3.4 Prevención de errores | AAA | Aplicable | Operaciones reversibles, revisables y confirmadas antes de enviarse |
| 4.1.2 Nombre, rol, valor | A | Aplicable | Estados ARIA correctos en menús, colapsables y campos |
| 4.1.3 Estados de mensajes | AAA | Aplicable | Aviso de sesión y errores anunciados |
| 1.2.x Contenido temporal multimedia | A–AAA | No aplicable | La interfaz no contiene audio ni vídeo |
| 1.4.7 Audio sin voz de fondo | AAA | No aplicable | Sin audio |
| 1.4.9 Imágenes de texto | AAA | No aplicable | Sin imágenes que contengan texto |
| 2.3.2 Destellos generales | AAA | No aplicable | Sin destellos que superen el umbral |

Con esta tabla, el 100 % de los criterios AAA queda con una decisión documentada: aplicables, parcialmente aplicables o no aplicables con justificación, de modo que la matriz sea auditable.

### Key Entities

- **Libro, Categoría y Préstamo**: entidades existentes que solo cambian de presentación; sus atributos y reglas (ISBN único de 13 dígitos, año entre 1900 y el actual, disponibilidad obligatoria, máximo tres préstamos activos por estudiante) no se tocan.
- **Sesión de usuario**: estado de autenticación con 2 horas de inactividad, aviso con 5 minutos de antelación y posibilidad de extensión; no introduce roles ni permisos diferenciados.
- **Archivo de portada**: imagen JPG, PNG o WebP de hasta 2 MB; su forma de referencia en los datos no cambia.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En una auditoría de contraste sobre el texto real de las ocho pantallas, el 100 % del texto normal alcanza al menos 7:1.
- **SC-002**: El 100 % de las tareas principales (navegar, ver detalle, buscar, registrar préstamo, devolver, crear libro o categoría, iniciar sesión) se completa usando exclusivamente el teclado.
- **SC-003**: Una auditoría automatizada más la revisión manual frente a la matriz de criterios aplicables registra cero incumplimientos.
- **SC-004**: El 100 % de las pantallas y listados revisados presenta al menos una acción principal visible, y el registro de préstamos es alcanzable desde el home en dos pasos o menos.
- **SC-005**: Con JavaScript desactivado, el 100 % de las secciones y pantallas sigue siendo navegable y legible.
- **SC-006**: En los tres tamaños verificados (360 × 640 px, 768 × 1024 px y ≥ 1024 px), el 100 % de las pantallas se muestra sin recortes ni desbordamiento horizontal.
- **SC-007**: El 100 % de los intentos de subida con archivo fuera de límites (formato no permitido o más de 2 MB, en el cliente y en el servidor) muestra un mensaje claro en español y deja el formulario plenamente operable, sin página rota.
- **SC-008**: El aviso de sesión aparece con 5 minutos de antelación en el 100 % de las pruebas de inactividad, y la extensión se logra con una sola acción conservando los datos introducidos.
- **SC-009**: El 100 % de las listas vacías revisadas muestra un mensaje en español junto a su acción principal.
- **SC-010**: En una comparación entre dos pantallas distintas, el 100 % de los componentes evaluados comparte las mismas reglas de tipografía, espaciado, color y estados, y el README documenta el sistema de diseño.
- **SC-011**: El 100 % de las especificaciones 002 a 008 aparece declarado en la sección de contratos reemplazados, con su alcance de interfaz identificado.
- **SC-012**: El 100 % de las operaciones probadas (guardar, eliminar, registrar préstamo, devolver, subir portada) muestra una confirmación en español anunciada a las tecnologías de apoyo y deja el resultado visible en la pantalla aunque el mensaje desaparezca.

## Contratos de interfaz reemplazados (specs 002 a 008)

| Spec | Contrato de interfaz que asume 009 | Qué permanece vigente |
| --- | --- | --- |
| 002 - Consulta de catálogo | Estructura de listado y detalle del catálogo | Consulta, paginación y detalle como funciones |
| 003 - Gestión de libros | Formularios y listados de libros | CRUD, validación en servidor y reglas de ISBN y año |
| 004 - Gestión de préstamos | Listado, registro y devolución de préstamos | Reglas de disponibilidad, fechas y límite de tres activos |
| 005 - Gestión de portadas | Experiencia completa de subida de portadas | Almacenamiento, reemplazo, borrado e imagen predeterminada |
| 006 - Búsqueda en catálogo | Presentación de resultados y estados de búsqueda | Comportamiento de búsqueda y filtrado |
| 007 - Panel de biblioteca | Estructura y presentación del panel | Más prestados, vencidos y disponibles por categoría |
| 008 - Interfaz, validación y seguridad | Estructura común, errores junto a campos, accesibilidad, sesión y adaptación | Validación en servidor, CSRF, escapado de salida, DELETE, sesión de 2 horas y mensajes acordados |

**Declaración explícita**: los contratos de presentación e interacción de las especificaciones 002 a 008 quedan reemplazados por esta especificación (009). Las reglas de negocio, de datos y de seguridad contenidas en dichas especificaciones **no** se modifican y siguen aplicándose tal como fueron acordadas.

## Assumptions

- La herramienta de estilos (Tailwind 4 con Vite) procede de la decisión del equipo del 2026-11-17; esta especificación exige el resultado (sistema de diseño coherente y declarado en el README), no su implementación interna.
- La matriz de aplicabilidad registra una decisión justificada para cada criterio AAA; los criterios no aplicables se excluyen por la naturaleza de una aplicación de texto y datos.
- El aviso de sesión se emite con 5 minutos de antelación sobre la caducidad por 2 horas de inactividad (duración heredada de la spec 008); la extensión es una sola acción sin pérdida de datos.
- Los límites de portada (JPG, PNG o WebP hasta 2 MB) son los de la constitución y no cambian.
- No existen roles diferenciados: todos los usuarios autenticados ven las mismas acciones (consistente con la spec 008).
- La interfaz continúa en español; los mensajes de rechazo, error y estados vacíos conservan los textos ya acordados en especificaciones anteriores cuando existen.
- Los tres breakpoints verificados (360 × 640, 768 × 1024 y ≥ 1024 px) se conservan como únicos tamaños de aceptación.
- La mejora visual ("producto de primer nivel") se concreta en coherencia del sistema de diseño, jerarquía tipográfica y espaciado; no se fija una identidad de marca concreta.
- No se añaden objetivos de rendimiento más allá de los de una aplicación web local estándar.
- El registro de cuentas sigue sin ser público: la creación de usuarios la realiza el equipo.
