# Contract: Mensajes visibles al usuario (FR-006, FR-008)

**Spec**: [../spec.md](../spec.md) FR-006, FR-008, SC-004, SC-005 | **Data model**: [../data-model.md](../data-model.md)

Todos los mensajes son **en español**, visibles para el usuario, y jamás incluyen credenciales, cadenas de conexión ni SQL con datos sensibles.

## 1. Fallo de conexión o credenciales incorrectas (FR-008, SC-004)

| Elemento | Contrato |
|---|---|
| **Disparador** | `PDOException`/`QueryException` de acceso denegado, host inexistente o base de datos no disponible (cualquier intento de iniciar/consultar) |
| **Comportamiento** | La aplicación **impide continuar**: no se muestra ninguna vista de datos |
| **Texto requerido** | (a) describe el fallo ("no se pudo conectar con la base de datos"); (b) indica la corrección ("revise la configuración de conexión en el archivo `.env`"); (c) **no** contiene usuario, contraseña, host ni SQL |
| **Verificación** | 100 % de los intentos con credenciales erróneas o motor apagado muestran el aviso (SC-004) |

## 2. Eliminación bloqueada por registros relacionados (FR-006, SC-005)

| Escenario | Comportamiento | Texto requerido |
|---|---|---|
| Eliminar categoría con libros | Bloqueado (prevención en app + `RESTRICT` en BD) | Indica que la categoría tiene libros asociados y que no puede eliminarse mientras los tenga |
| Eliminar libro con préstamos | Bloqueado (prevención en app + `RESTRICT` en BD) | Indica que el libro tiene préstamos asociados y que no puede eliminarse mientras los tenga |
| Eliminar categoría sin libros / libro sin préstamos | Eliminación correcta (mediante `DELETE`, nunca mediante GET; con confirmación — constitución Principio IV) | Confirmación de la operación |

El README documenta esta conducta en español (SC-005): sección «Eliminación de registros relacionados».

## 3. Errores de validación de datos (aplicación a fases posteriores, Principio II)

- Errores en español junto a cada campo, conservando valores con `old()`.
- No aplicable en esta fase: no hay formularios; la validación de datos de prueba se realiza en seeders/factories y migraciones.
