# Contract: Aviso y extensión de sesión (FR-005)

**Spec**: [../spec.md](../spec.md) FR-005, SC-008 | **Research**: [../research.md](../research.md) D-053 | **Data model**: [../data-model.md](../data-model.md) §Estados de sesión

Contrato nuevo de `009` que complementa —sin sustituir— el de caducidad de `008` (2 horas de inactividad).

## 1. Datos de entrada al cliente

- El layout renderiza en `<body>`: `data-sesion-expira="<timestamp UNIX>"` = `now() + config('session.lifetime')` (120 minutos), recalculado **en cada petición** (la actividad de sesión se actualiza en cada request).
- Sin sesión iniciada no se emite el atributo y no hay aviso.

## 2. Comportamiento

| Momento | Comportamiento |
|---|---|
| Restan > 5 min | Silencioso; ningún indicador permanente que distraiga |
| Quedan **5 min** | Diálogo visible `role="alertdialog"` `aria-modal="true"` con `aria-labelledby` al mensaje: **«Quedan 5 minutos antes de que finalice su sesión.»** (con «Quedan N minutos» mientras siga bajando) |
| Acciones del diálogo | **«Extender sesión»** (primario) y **«Cerrar sesión»** (secundario) |
| «Extender sesión» | `POST /sesion/extender` con `@csrf` (grupo `auth`); al responder OK se renueva la sesión **conservando sus datos**, se cierra el diálogo y se reinicia el contador |
| «Cerrar sesión» | `POST /logout` (ya existente) |
| Sin respuesta | La sesión caduca con el comportamiento de `008`: la operación no se ejecuta, se pide iniciar sesión y se conservan los valores con `old()` cuando es posible |
| Interacción | El diálogo no cierra formularios ni pierde datos; el foco entra en el diálogo y `Escape` **no** lo descarta silenciosamente (solo «Cerrar sesión» o la extensión lo retiran) |

## 3. Accesibilidad

- Aviso anunciado (`role="alertdialog"` + foco al diálogo) y anunciado de nuevo si se actualiza el minutaje (1.4.13, 4.1.3).
- Contraste ≥ 7:1 en texto y botones; objetivos ≥ 44 px; operable solo con teclado (2.1.1).
- No depende del color; el texto indica siempre el tiempo restante.

## 4. Degradación sin JavaScript

- **No hay aviso ni extensión** (límite documentado): se conserva la caducidad por inactividad de 2 horas de `008`.
- Ninguna otra funcionalidad se pierde: el usuario simplemente recibe el mensaje de sesión caducada ya especificado.
- La extensión manual sigue posible de forma natural: cualquier interacción (recarga, envío) renueva la sesión, igual que hoy.

## 5. Verificación

- `SesionAvisoTest`: con el umbral reducido para prueba, el aviso aparece con 5 minutos restantes, «Extender sesión» conserva los datos de la sesión y la caducidad sin respuesta sigue comportándose como en `008`.
- Revisión manual en [../quickstart.md](../quickstart.md) V-009.
