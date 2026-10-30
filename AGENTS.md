# AGENTS.md

Guía para agentes de código (Copilot, Codex, Claude y similares) y para cualquier persona que contribuya. La documentación de usuario está en [README.md](README.md) y [FAQ.md](FAQ.md).

## Qué es

User Tools es un paquete para **Joomla 6** (PHP ≥ 8.3, MySQL/MariaDB y PostgreSQL) que gestiona cuentas de usuario. Tiene siete herramientas y cuatro pantallas de consulta:

- **Comparar una lista**: bloquea las cuentas que no están en una lista vigente y da de alta las nuevas.
- **Revisar usuarios inactivos**: bloquea las cuentas seleccionadas.
- **Eliminar usuarios inactivos**: borra las cuentas seleccionadas.
- **Anonimizar cuentas**: sustituye los datos personales de las cuentas elegidas por lista, inactividad o grupo, sin eliminarlas. Usa los eventos de privacidad de Joomla (`onPrivacyCanRemoveData`/`onPrivacyRemoveData`) con una solicitud sin guardar, y borra además perfil, notas y valores de campos personalizados. La lógica está en `AnonymizeSelection` y la escritura en `AccountAnonymizer`; permiso `usertools.anonymize`.
- **Tarea programada**: bloquea por inactividad.
- **Añadir cuentas a grupos**: asignación aditiva por lista/grupo o pares `usuario;grupo`, identificando el grupo por ID o ruta completa. `BulkGroupAssignments` determina candidatos y rechazos, `AddgroupsModel` controla la revisión de un solo uso y las tandas reanudables, y `AccountGroupAdder` guarda por la API de Joomla. Exige `usertools.addgroups`; no cambia superusuarios, cuentas con acceso administrativo ni al operador, y nunca asigna grupos con acceso a la administración. Cada pertenencia añadida se registra en `ActionLogger` y se cierran las sesiones.
- **Quitar de grupos**: saca una cuenta de los grupos que se desmarquen y, en los que se marque «y sus subgrupos», de todos los que cuelgan de ellos (se decide grupo a grupo). Una cuenta cada vez; la lógica está en `GroupRemoval` y la escritura en `AccountRegrouper`.
- **Mover grupos**: cambia el grupo padre de varios grupos a la vez, con sus subgrupos, tras una revisión de los niveles que ganan y pierden. Rechaza los movimientos que cambiarían el acceso a la administración o los permisos de superusuario de cualquier grupo afectado. La lógica está en `GroupMove` y la escritura en `GroupMover` (modelo de grupos de Joomla). Solo superusuarios: no tiene permiso en `access.xml`; usa `DisplayController::SUPER_USERS`, que se comprueba en el activo raíz.
- **Grupos y niveles de acceso** (solo lectura): qué niveles tiene cada grupo, asignados o heredados, con exportación a CSV. La lógica está en `AccessMatrix`.
- **Contenido por nivel de acceso** (solo lectura): cuántos elementos de cada tipo usan cada nivel, con enlace a los listados filtrados de Joomla. La lógica está en `AccessUsage` y la lectura en `AccessUsageReader`. Comparte el permiso `usertools.access`.
- **Contenido por antigüedad** (solo lectura): cuántos elementos se modificaron por última vez en cada año (de momento, artículos), filtrable por nivel, con la lista de cada año y enlace a su edición. La lógica está en `ContentAge` y la lectura en `ContentAgeReader`; permiso `usertools.content`, y la lista exige además poder gestionar ese contenido en Joomla.
- **Diagnóstico de accesos** (solo lectura): cuentas con acceso a la administración sin actividad, superusuarios activos, niveles sin grupos ni cuentas, contenido con niveles inexistentes y grupos sin miembros. Cada aviso explica qué, por qué y cómo revisarlo, sin corregir nada. La lógica está en `AccessHealth`; permiso `usertools.diagnose`.

Además, un módulo de administración resume en el panel de Usuarios las cuentas pendientes, el estado de las tareas y los avisos, y un módulo del sitio lista los grupos de un usuario o los miembros de un grupo.

Se distribuye como un paquete con el componente `com_usertools`, el plugin `plg_task_usertools`, el módulo de administración `mod_usertools` y el módulo del sitio `mod_usertools_groups`.

## Estructura

| Ruta | Contenido |
|---|---|
| `admin/src/Service/` | Lógica de dominio sin dependencias de la interfaz: `Exclusions`, `InactivityPolicy`, `Comparison`, `UserDirectory` (carga por lotes), `AccountBlocker`, `AccountDeleter`, `ActionLogger`, `Settings`, `Toolkit` (acceso a servicios de Joomla)… La comparten el componente y el plugin. |
| `admin/src/Model/` | Un modelo por herramienta (`CompareModel`, `InactiveModel`, `DeleteModel`, `ReportsModel`) y `ToolStateTrait`, con las previsualizaciones de un solo uso y los resultados en sesión. |
| `admin/src/Controller/` | `DisplayController` comprueba el permiso de cada vista. Los controladores de cada herramienta **heredan de él** y usan `ToolControllerTrait` (token CSRF, permiso, avisos). |
| `admin/src/View/`, `admin/tmpl/`, `admin/layouts/usertools/` | Vistas MVC, plantillas y layouts compartidos (exclusiones, pasos, confirmación). |
| `admin/access.xml`, `admin/config.xml` | Permisos propios (`usertools.*`) y Opciones. |
| `admin/language/{es-ES,en-GB}/` | Idiomas del componente. |
| `media/` | CSS y JS, registrados en `joomla.asset.json`. |
| `plugins/task/usertools/` | Plugin de tarea programada. |
| `modules/mod_usertools/` | Módulo del panel de Usuarios. Solo presenta datos: las cifras y avisos los calculan `DashboardSummary` y `Toolkit::dashboard()`, con caché que se limpia con `Toolkit::clearDashboardCache()` cuando cambian cuentas u Opciones. |
| `modules/mod_usertools_groups/` | Módulo del sitio de solo lectura. No depende del componente: `GroupTree` (reglas puras del árbol de grupos, con pruebas unitarias) y `MembershipHelper` (consultas). Nunca muestra correos ni cuentas bloqueadas, y por defecto oculta las cuentas con acceso a la administración. |
| `package/` | Manifiesto del paquete, `script.php` de instalación (comprobaciones, migración de opciones, registro de acciones, plantilla de correo, colocación inicial del módulo) e idiomas. |
| `scripts/build.php` | Construye los ZIP, `SHA256SUMS.txt` y `updates.xml`. |
| `tests/` | `Unit/` (PHPUnit), `regression/` (controladores y modelos con sustitutos de Joomla), `js/` (jsdom), `integration/` (Joomla real), `verify-build.php`. |

## Reglas de seguridad que no se pueden romper

Cualquier cambio debe mantener estas garantías. Hay pruebas que las cubren.

1. **Los superusuarios nunca se bloquean, eliminan ni anonimizan**, en ninguna herramienta, aunque se desactive «Excluir administradores». Lo decide `Exclusions::reason()`.
2. **La cuenta que ejecuta una operación manual siempre queda protegida.** La tarea programada actúa sin identidad (`Operator::system()`).
3. **Quien no es superusuario nunca puede bloquear, eliminar, anonimizar ni quitar de grupos cuentas con acceso a la administración.** Un superusuario solo puede perder ese estado si queda otro superusuario activo.
4. **Nada se modifica sin revisión previa.** Las previsualizaciones son de un solo uso, caducan a los 15 minutos y se consumen antes de escribir. Cada cuenta se revalida con un objeto `User` recién cargado justo antes de cambiarla, y la que haya cambiado se omite, nunca se sustituye por otra.
5. **Los cambios en cuentas pasan por la API de usuarios de Joomla** (`User::save()`, `User::delete()`) para que se ejecuten los eventos de los plugins. No se toca `#__users` con SQL para modificar cuentas.
6. **Cada bloqueo, cambio de grupos o anonimización cierra las sesiones de la cuenta**, y cada bloqueo, alta, eliminación, anonimización o cambio de grupos se anota en el registro de acciones de usuarios (`ActionLogger`).
7. **Las contraseñas temporales nunca se guardan**: ni en sesión, ni en base de datos, ni en registros. Solo se muestran en la respuesta que crea la cuenta.
8. **Confirmaciones**: la comparación y la revisión de inactivos piden escribir el número de cuentas a partir del umbral configurado. La eliminación y la anonimización lo piden siempre.
9. **La tarea programada tiene un límite de seguridad**: si los candidatos superan el porcentaje configurado de cuentas activas, no bloquea ninguna.
10. **Cada POST comprueba el token CSRF y el permiso de la herramienta**, y las páginas se sirven con `Cache-Control: no-store`.

Si una petición choca con alguna de estas reglas, se señala el conflicto antes de cambiar nada.

## Convenciones

- **Idioma**: el código, los comentarios, los mensajes de commit y los nombres en inglés. La documentación de usuario (README, FAQ) y los textos de la interfaz en español, con su traducción al inglés.
- **Textos de la interfaz**: siempre con claves de idioma (`COM_USERTOOLS_…`, `PLG_TASK_USERTOOLS_…`, `MOD_USERTOOLS_…`, `MOD_USERTOOLS_GROUPS_…`), nunca escritos en el código. Cada clave existe **en es-ES y en-GB a la vez**, sin duplicados. Los servicios lanzan `UserFacingException` con una clave y sus argumentos, y la traducción se hace en controladores y vistas.
- **Salida HTML**: todo valor variable se escapa (`$this->escape()` en las plantillas).
- **Estilos**: componentes y convenciones públicos de Joomla, sin dependencias de una plantilla concreta. Aplica las reglas de «Interfaz, usabilidad y accesibilidad».
- **Base de datos**: consultas con el query builder de Joomla y parámetros enlazados, compatibles con MySQL y PostgreSQL. Nada de consultas por cuenta dentro de bucles grandes: se usa `UserDirectory`.
- **Exclusiones**: cada herramienta tiene las suyas, guardadas en Opciones con su prefijo (`compare_`, `inactive_`, `delete_`, `anonymize_`). Cada tarea programada guarda las suyas en sus parámetros.
- **Panel**: todo lo que cambie cuentas u Opciones llama a `Toolkit::clearDashboardCache()`. Las cifras del módulo deben usar las mismas reglas que la herramienta a la que enlazan.
- **Herramienta nueva**: añade su permiso en `access.xml` (o `DisplayController::SUPER_USERS` si es solo para superusuarios), la vista en `DisplayController::PERMISSIONS`, su pestaña en `config.xml` si tiene opciones, la tarjeta en la portada, el controlador en `tests/regression/mvc.php`, una sección en `tests/integration/run.php` y la documentación en README y FAQ.

## Interfaz, usabilidad y accesibilidad

Estas reglas se aplican a las pantallas del componente y a los módulos. Integrarse con Joomla no significa depender de Atum ni de otra plantilla concreta.

- **Componentes públicos de Joomla**: utiliza sus APIs, layouts y controles públicos cuando correspondan. Mantén las convenciones de navegación, formularios, barras de herramientas y mensajes de Joomla. Si un control propio es necesario, conserva su semántica y accesibilidad.
- **Independencia de la plantilla**: no acoples la extensión al DOM, selectores internos, archivos o estilos exclusivos de Atum. Usa las clases de los componentes disponibles en la versión de Joomla soportada y variables de estilo con alternativas adecuadas; evita colores y tipografías fijos. Limita el CSS propio a la maquetación y a necesidades específicas de la extensión. Esto reduce el mantenimiento, pero no garantiza compatibilidad automática con cualquier plantilla o versión futura.
- **Organización por finalidad**: agrupa herramientas relacionadas bajo encabezados claros y una jerarquía semántica coherente. Documenta las decisiones de distribución de cada pantalla aparte; no impongas tarjetas, pestañas, acordeones ni las tres categorías de la portada a todas las interfaces.
- **Explicaciones para decidir**: conserva visibles el propósito de cada herramienta y las consecuencias de sus acciones. Evita repeticiones y etiquetas que no aporten información. Sitúa las instrucciones detalladas dentro del flujo, sin ocultar información necesaria para elegir o confirmar.
- **Textos consistentes**: usa nombres y verbos claros y coherentes entre título, explicación, botón y resultado. Distingue bloquear, eliminar y quitar de grupos. Mantén las claves es-ES y en-GB sincronizadas y aplica las directrices de textos de Joomla; la guía inglesa se orienta a en-GB.
- **Acciones destructivas**: identifica el borrado con texto explícito como «Permanente» y explica que no se puede deshacer. El color o un icono nunca son el único medio para comunicar riesgo, estado, éxito o error. Conserva las revisiones y confirmaciones del servidor.
- **Objetivo WCAG 2.2 AA**: usa HTML semántico, encabezados ordenados, etiquetas asociadas a los campos y nombres accesibles para los controles. Toda función debe poder usarse con teclado, con foco visible y orden lógico. Usa ARIA solo cuando sea necesario y comunica de forma accesible los cambios dinámicos relevantes, errores y progreso.
- **Adaptación visual**: permite ampliar texto y usar zoom, así como pantallas estrechas, sin perder información ni funciones ni recortar controles. Comprueba contraste, modo claro y oscuro cuando estén disponibles, y el comportamiento de tablas anchas.
- **Verificación**: en cambios de interfaz, comprueba manualmente teclado, foco, claridad de textos, contraste, zoom y distribución estrecha en la administración o el sitio, según corresponda. Complementa con pruebas automáticas y comprobaciones con tecnología de asistencia según el alcance; una prueba automática o usar componentes de Joomla no acredita por sí solo conformidad WCAG.
- **Evolución de Joomla**: revisa las referencias y los componentes utilizados al cambiar de versión principal de Joomla, y verifica la integración con la plantilla usada en las pruebas. No prometas compatibilidad con plantillas no probadas.

Referencias oficiales:

- [Directrices de textos de interfaz de Joomla](https://manual.joomla.org/docs/next/user-interface-text/).
- [Accesibilidad y estándares W3C](https://manual.joomla.org/docs/next/accessibility/w3c-standards/).
- [Accesibilidad para extensiones](https://manual.joomla.org/docs/next/accessibility/).

Estas referencias usan la ruta `next`, cuyo contenido puede cambiar. Contrasta su versión con la de Joomla soportada; las directrices no obligan a una distribución única de la portada.

## Documentación bilingüe

- El español sigue siendo el idioma predeterminado del repositorio. Mantén estas parejas completas y sincronizadas en la misma rama y pull request:
  - `README.md` ↔ `README.en.md`.
  - `FAQ.md` ↔ `FAQ.en.md`.
  - `docs/arquitectura.md` ↔ `docs/architecture.en.md`.
  - `docs/capturas.md` ↔ `docs/screenshots.en.md`.
- Todo cambio de instrucciones, comportamiento, requisitos, ejemplos, límites o capturas en uno de estos documentos debe reflejarse en su pareja antes de dar el trabajo por terminado. Una traducción debe conservar el contenido completo, las advertencias y los ejemplos; no sustituirlos por un resumen.
- Inmediatamente después del título principal, ambas versiones llevan un selector **Español · English**: el idioma actual se destaca y el otro enlaza al documento equivalente mediante una ruta relativa.
- Los enlaces entre documentos deben mantenerse en el idioma que se está leyendo, incluidos los fragmentos de sección. Los enlaces al código y a documentación externa conservan su destino. Si un documento solo existe en español, indícalo al enlazarlo desde inglés.
- Las capturas se pueden compartir entre idiomas. Traduce sus textos alternativos y pies, e indica en la versión inglesa que muestran la interfaz en español; no inventes capturas de una interfaz inglesa.
- Al crear documentación nueva destinada a usuarios o colaboradores, añade ambas versiones y sus selectores, y registra la pareja aquí. Los archivos de instrucciones para agentes (`AGENTS.md`, `CLAUDE.md`) no necesitan una copia traducida.
- Al verificar un cambio documental, comprueba en ambas versiones los enlaces relativos, los fragmentos del índice, los selectores recíprocos, las rutas de imágenes y la coherencia de versiones, cifras y comandos. Mantén también las reglas generales de pruebas y CI de esta guía.

## Versiones

- La numeración es **año.mes.revisión**, sin cero inicial en el mes: 2026.10.5; el mes siguiente empieza en 2026.11.0.
- Cada versión nueva cambia el número en **seis sitios**:
  - `com_usertools.xml`
  - `plugins/task/usertools/usertools.xml`
  - `modules/mod_usertools/mod_usertools.xml`
  - `modules/mod_usertools_groups/mod_usertools_groups.xml`
  - `package/pkg_usertools.xml`
  - `media/joomla.asset.json`
- No se reutiliza un número que ya tenga release.

## Pruebas

Antes de proponer un cambio, deben pasar todas estas:

~~~sh
composer install && npm ci
vendor/bin/phpunit                                          # unitarias y regresión MVC
npm test                                                    # JavaScript con jsdom
JOOMLA_PATH=/ruta/a/joomla-6 vendor/bin/phpstan analyse     # nivel 6, sin errores
php scripts/build.php --output=dist && php tests/verify-build.php VERSIÓN dist
~~~

La prueba de extremo a extremo (`tests/integration/run.php`) necesita un Joomla 6 **desechable**, porque crea, bloquea y borra cuentas. También necesita el servidor de correo de pruebas `tests/integration/smtp-sink.php`. Las variables de entorno están en la cabecera del fichero. La CI de GitHub la ejecuta con PostgreSQL y con MySQL en cada push y pull request.

Todo cambio de comportamiento lleva su prueba. Los cambios que afecten a bloqueos, borrados o altas llevan también su comprobación en la prueba de extremo a extremo.

## Publicación en el Joomla! Extensions Directory (JED)

Estas reglas del proyecto preservan la preparación para el JED; no sustituyen sus requisitos vigentes ni acreditan su aprobación.

- **Licencia en cada PHP distribuido**: conserva y añade en los archivos nuevos el encabezado `@copyright` y `@license GNU General Public License version 2 or later; see LICENSE`. Mantén el titular real (`Danieldiazi` en el código propio actual) y los avisos de terceros.
- **Paquetes**: conserva el texto íntegro GPL en `LICENSE` y su inclusión en todos los ZIP generados por `scripts/build.php`, también en los paquetes internos. Revisa el archivo final, no solo las fuentes.
- **Actualizaciones**: preserva el servidor declarado en el manifiesto del paquete y la generación de `updates.xml`. Comprueba versión, compatibilidad, URL del ZIP y hash, además de la detección e instalación de una actualización en Joomla de pruebas cuando cambie ese flujo.
- **Ficha**: mantén coherentes el nombre instalado («User Tools»), las funciones, los requisitos y la compatibilidad anunciados. Revisa los enlaces de descarga, documentación, soporte y licencia cuando cambien.
- **Antes de enviar o actualizar el paquete en el JED**: ejecuta [JED Checker](https://extensions.joomla.org/extension/jedchecker/) sobre el ZIP final, incluidos sus paquetes internos; corrige los errores y revisa los avisos. Registra versión, hash del ZIP, versión del comprobador y resultado en la pull request o en la evidencia de publicación. Si no se ha ejecutado, indícalo como pendiente.
- **JAMSS**: examina cada aviso en su contexto. No cambies código legítimo solo para silenciar patrones ni declares que un análisis garantiza ausencia de malware. Los escapes `\\xEF\\xBB\\xBF` usados para el BOM UTF-8 y los métodos propios `assertKind`/`assertReviewedCandidates` han generado avisos: vuelve a revisar su implementación si cambian.
- **Verificación**: PHPUnit, PHPStan, CI y `verify-build.php` complementan JED Checker; no lo sustituyen. No marques como realizada una comprobación pendiente.

Consulta antes de publicar los [requisitos del JED](https://extensions.joomla.org/support/knowledgebase/submission-requirements/), su [lista de comprobación](https://extensions.joomla.org/support/knowledgebase/submission-requirements/jed-entries-checklists/) y el [requisito del sistema de actualizaciones](https://extensions.joomla.org/support/knowledgebase/submission-requirements/joomla-update-system-requirement/).

## Flujo de trabajo

- Trabaja en una rama y abre una pull request contra `main`. No hagas push directo a `main`.
- La CI debe estar en verde antes de fusionar.
- La release la publica una persona: crea en GitHub una release con la etiqueta `vAAAA.M.REVISIÓN` sobre `main`. GitHub Actions construye y adjunta los paquetes y el `updates.xml`. La etiqueta debe coincidir con la versión de los manifiestos del commit etiquetado; si no coincide, la publicación falla.
- Actualiza README y FAQ cuando cambie lo que ve o configura el usuario. Si cambian las pantallas, renueva las capturas de `docs/screenshots/`.
