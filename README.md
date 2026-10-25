# Joomla User Tools

Herramientas de administración para Joomla 6: comparación de una lista vigente y tarea programada de bloqueo por inactividad. Versión 2026.10.0, GPL-2.0-or-later.

La numeración sigue **año.mes.revisión**, sin cero inicial en el mes: 2026.10.0, 2026.10.1; el siguiente mes empieza en 2026.11.0. Las etiquetas llevan el prefijo v.

## Instalación y actualización

Instala **pkg_usertools-2026.10.0.zip** desde Sistema → Instalar → Extensiones. El paquete instala/actualiza el componente y el plugin de tareas. Conserva las exclusiones por nombre de la versión 0.1.0. No desinstales el componente para actualizar.

También se generan ZIP separados del componente y del plugin. El plugin necesita el componente 2026.10.0 o posterior. Habilita «Tarea - User Tools» desde Sistema → Gestionar → Plugins; la instalación no habilita tareas de bloqueo por sí sola.

## Exclusiones independientes

Cada herramienta tiene sus propios ajustes persistentes:

- **Excluir administradores**: activado por defecto. Protege superusuarios y cualquier cuenta con permiso de acceso a la administración. Se puede desactivar.
- **Nombres de usuario excluidos**: uno por línea; no distingue mayúsculas y minúsculas.
- **Grupos excluidos**: selección múltiple, incluidos los subgrupos.

Basta que se cumpla cualquiera de las exclusiones para proteger una cuenta. La cuenta que ejecuta la operación, cuando hay un usuario identificado, siempre queda protegida.

Las exclusiones de la comparación manual se guardan en los parámetros del componente. Las de la tarea se guardan en los parámetros de **cada tarea programada**. No se copian ni se mezclan entre ambas herramientas ni entre tareas.

## Comparación manual

1. Abre Componentes → User Tools como superusuario.
2. Pega la lista **completa** de nombres vigentes, uno por línea.
3. Configura las exclusiones y pulsa «Siguiente: revisar cambios».
4. En una pantalla independiente, revisa los bloqueos y marca los usuarios nuevos que deseas crear, indicando un correo distinto para cada uno.
5. Usa «Volver y editar» si necesitas corregir la lista: conserva los datos introducidos. Confirma para aplicar y consultar el resultado.

Puedes usar «Guardar exclusiones» sin pegar una lista. No se admiten CSV ni se eliminan o reactivan usuarios.

Los nombres se comparan sin distinguir mayúsculas y minúsculas: OTRO y otro son la misma cuenta. Se conservan los nombres originales y se eliminan espacios exteriores, líneas vacías y duplicados. Una lista vacía o sin coincidencias no permite bloquear cuentas. La vista previa caduca a los 15 minutos y se consume una sola vez. Un cambio de exclusiones invalida la vista previa. Antes de guardar se revalidan las cuentas.

Una lista parcialmente incompleta puede producir bajas incorrectas: revisa la vista previa y utiliza la lista completa.

### Altas seleccionadas

La portada permite elegir herramienta; el asistente manual tiene pantallas de entrada, revisión y resultado. Los grupos excluidos se eligen con casillas. Al activar «Excluir administradores», los grupos con permisos de administración aparecen marcados, en gris y bloqueados, con el motivo. Al desactivar la opción se recuperan las selecciones manuales. La protección automática no se guarda como una exclusión manual.

Los nombres desconocidos están desmarcados por defecto. Solo las cuentas seleccionadas se crean como **Registrados (grupo ID 2)**; se comprueba que el grupo exista y no conceda acceso a la administración. El nombre visible inicial coincide con el nombre de usuario.

Se valida el correo, su unicidad y la existencia del nombre antes de aplicar cambios. Si la selección es inválida, no se aplican ni altas ni bloqueos. Una lista sin coincidencias sigue impidiendo bajas, pero permite seleccionar altas.

Cada cuenta recibe una contraseña aleatoria diferente y debe cambiarla en su primer acceso. Las contraseñas se muestran **una sola vez** en la pantalla de resultado. Se retiran del resultado guardado al mostrarlo y no se registran en logs. Al recargar o salir ya no se pueden consultar: usa la recuperación de contraseña de Joomla. Si el resultado no se abre, las credenciales pendientes caducan a los 15 minutos.

La herramienta suprime el correo de bienvenida del plugin de usuarios de Joomla durante las altas y restaura su configuración al terminar. Los demás plugins instalados siguen recibiendo los eventos normales de creación. Los fallos parciales aparecen en el resultado; no se revierten los cambios ya aplicados.

La revisión envía las selecciones y correos en un único campo JSON para no superar el límite de campos de PHP con listas grandes. Requiere JavaScript.

La portada y la sección de tareas muestran si el plugin está instalado/habilitado y ofrecen accesos a Plugins y Tareas programadas.

## Revisión manual por inactividad

En la portada, abre «Revisar usuarios inactivos». Configura un plazo en días, si se incluyen quienes nunca accedieron y sus exclusiones. Son independientes de la comparación de lista y de las tareas programadas.

Busca candidatos, marca solo las cuentas que quieres suspender y confirma. Puedes volver y editar. No hay cuentas marcadas inicialmente. Se revalidan fechas, exclusiones y sesiones antes de guardar, se protege al operador y solo se bloquean los IDs seleccionados en la revisión. La selección caduca a los 15 minutos. Se muestra el resultado final y no se eliminan cuentas.

## Tarea programada por inactividad

1. Habilita el plugin «Tarea - User Tools».
2. Ve a Sistema → Tareas programadas → Nueva.
3. Elige **User Tools: bloquear usuarios inactivos**.
4. Configura el periodo en días: **365** por defecto.
5. Configura sus propias exclusiones de administradores, nombres y grupos.
6. La **simulación está activada por defecto**. Ejecuta la tarea y revisa su registro: indica los IDs candidatos sin modificar cuentas.
7. Desactiva la simulación y establece una frecuencia, por ejemplo diaria, para aplicar los bloqueos.

El último acceso debe ser **estrictamente anterior** al límite UTC. El periodo admite entre 1 y 36500 días; 365 días es una duración fija, no un año de calendario. Fechas inválidas y cuentas ya bloqueadas se omiten.

Los usuarios que nunca accedieron se excluyen por defecto. La opción «Incluir usuarios que nunca han accedido» permite tratarlos usando su fecha de registro.

La tarea revalida fechas y exclusiones con un objeto de usuario nuevo antes del guardado. Omite sesiones recientes registradas en la tabla de sesiones de Joomla; la detección depende de que Joomla mantenga esos metadatos. No garantiza cerrar sesiones ya abiertas.

Ambas herramientas bloquean mediante la API de usuarios de Joomla y ejecutan sus eventos de guardado. La tarea registra los bloqueos por ID y un resumen. Un error de guardado se comunica como fallo de tarea; los bloqueos previos de esa ejecución permanecen aplicados.

El plugin usa el programador nativo: para ejecutarlo aunque no haya visitas, configura el cron del alojamiento según la [guía oficial](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### Resultados dentro de User Tools

Abre Componentes → User Tools → Resultados de tareas. Cada ejecución registra fecha UTC, título e ID de tarea, simulación/real, plazo, estado, cantidades y detalle por usuario. Se indican motivos de protección u omisión, como administrador, grupo excluido, nunca accedió, registro reciente, sesión activa o cuenta ya bloqueada. Solo los superusuarios pueden consultarlos.

No depende del registro individual de Joomla. Las ejecuciones anteriores a esta versión no se importan. No se registran contraseñas ni correos. Los informes se conservan en la subcarpeta usertools-reports del directorio de registros, con cabecera PHP que impide su lectura directa por web. No se borran automáticamente.

Se guarda un informe inicial antes de modificar cuentas y se actualiza cada 50 usuarios y al terminar. Si se interrumpe la ejecución, el informe puede aparecer sin finalizar con datos parciales. Si no se puede guardar el informe inicial, la tarea termina con error antes de bloquear cuentas. Si falla una actualización posterior, puede haber cambios ya aplicados.

### Registro técnico opcional de Joomla

En Sistema → Tareas programadas → Historial de ejecución se consulta el estado de cada ejecución. Para generar un archivo de detalle, abre la tarea y habilita «Registro individual de la tarea» en la pestaña de registro; viene desactivado por defecto. Guarda y vuelve a ejecutar. El detalle se escribe en el directorio de registros configurado en Joomla, normalmente en el fichero task_ID.log.php (ID es el identificador de la tarea), salvo que se indique otro nombre en la tarea. Incluye IDs candidatos en simulación, bloqueos realizados y resumen de candidatos, bloqueados y errores.

## Construcción y pruebas

Con PHP 8.3/8.4:

~~~sh
php tests/comparison.php
php tests/policies.php
php tests/task.php
php tests/registration.php
php tests/wizard.php
php tests/username-case.php
php tests/reports.php
php tests/inactive.php
~~~

tests/task.php comprueba el adaptador de la tarea con servicios simulados. No equivale a una prueba en Joomla real.

Con PowerShell y .NET:

~~~powershell
.\scripts\build.ps1 -OutputDirectory .\dist
~~~

Genera los tres paquetes instalables. El paquete principal contiene los ZIP del componente y del plugin.

Sintaxis PHP, políticas de exclusión, fechas límite, simulación y aplicación se comprueban localmente. **Pendiente de validar la instalación, edición de tareas y ejecución completa en una instancia real de Joomla 6.**

## Publicación automática por etiquetas

Al enviar una etiqueta o publicar una release con formato **vAAAA.M.REVISIÓN** (por ejemplo **v2026.10.0**) al repositorio, GitHub Actions comprueba la sintaxis PHP, ejecuta las pruebas y genera los tres ZIP instalables. Después crea una release para esa etiqueta y adjunta los ZIP y un fichero SHA256SUMS.txt. Si la release ya existe, actualiza sus adjuntos.

La versión de la etiqueta se incorpora a los nombres de archivo y a los tres manifiestos dentro de los ZIP, sin modificar los fuentes. No hace falta cambiar manualmente los manifiestos para publicar una etiqueta. No se admiten sufijos de preversión en este flujo.

Desde GitHub: Releases → Draft a new release → Choose a tag → crea, por ejemplo, v2026.10.0 sobre main → Publish release. También puedes enviar la etiqueta desde Git. El flujo debe estar presente en el commit etiquetado. Crear una rama o enviar commits sin etiqueta no publica paquetes.

En la release, el fichero recomendado para instalar ambas herramientas es **pkg_usertools-2026.10.0.zip**. Los ZIP com_usertools y plg_task_usertools son las instalaciones separadas.

El flujo usa el GITHUB_TOKEN del propio repositorio, con permiso de escritura de contenido para adjuntar los archivos; no requiere un token personal. El lanzamiento y resultado se pueden consultar en la pestaña Actions.

Para construir una versión concreta localmente:

~~~powershell
.\scripts\build.ps1 -Version v2026.10.0 -OutputDirectory .\dist
.\tests\build.ps1 -Version v2026.10.0 -PackageDirectory .\dist
~~~

## Referencias

Implementación propia basada en la API del programador y el patrón de los plugins oficiales de Joomla 6.0.0: [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Proyecto independiente, sin afiliación oficial a Joomla.
