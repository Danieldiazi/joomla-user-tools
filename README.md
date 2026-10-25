# Joomla User Tools

Herramientas de administración de cuentas para **Joomla 6**: mantén los accesos al día comparando una lista vigente de usuarios, revisa a mano las cuentas inactivas o programa su bloqueo automático. Nada se borra: las cuentas se bloquean y conservan sus datos.

Licencia GPL-2.0-or-later. La versión vigente es la de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest).

![Portada de User Tools en la administración de Joomla](docs/screenshots/01-portada.jpg)

## Qué incluye

| Herramienta | Tipo | Para qué sirve |
|---|---|---|
| [Comparar una lista de usuarios](#comparación-manual) | Manual | Pegas la lista completa de quién debe tener acceso; propone bloquear al resto y crear las cuentas nuevas que elijas. |
| [Revisar usuarios inactivos](#revisión-manual-por-inactividad) | Manual | Busca cuentas que llevan un plazo sin acceder y suspendes solo las que marques. |
| [Bloquear por inactividad](#tarea-programada-por-inactividad) | Programado | Tarea del programador de Joomla que bloquea las cuentas inactivas, con simulación previa. |
| [Resultados de tareas](#resultados-dentro-de-user-tools) | Consulta | Historial de ejecuciones con el detalle y el motivo de cada cuenta. |

Todas las operaciones muestran una revisión antes de aplicar cambios, protegen al usuario que las ejecuta y tienen [exclusiones independientes](#exclusiones-independientes).

**Índice:** [Instalación](#instalación-y-actualización) · [Exclusiones](#exclusiones-independientes) · [Comparación manual](#comparación-manual) · [Inactividad manual](#revisión-manual-por-inactividad) · [Tarea programada](#tarea-programada-por-inactividad) · [Resultados](#resultados-dentro-de-user-tools) · [Construcción y pruebas](#construcción-y-pruebas) · [Publicación](#publicación-automática-por-etiquetas)

¿Dudas concretas? Consulta las [preguntas frecuentes](FAQ.md).

## Instalación y actualización

1. Descarga de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest) el fichero **pkg_usertools-VERSIÓN.zip**.
2. Instálalo desde **Sistema → Instalar → Extensiones**. El paquete instala o actualiza el componente y el plugin de tareas.
3. Para usar la tarea programada, habilita «Tarea - User Tools» en **Sistema → Gestionar → Plugins**. La instalación no habilita tareas de bloqueo por sí sola.

Para actualizar, instala el paquete nuevo encima: **no desinstales el componente**. Se conservan las exclusiones por nombre de la versión 0.1.0.

También se publican ZIP separados del componente y del plugin. El plugin necesita el componente 2026.10.0 o posterior.

La numeración sigue **año.mes.revisión**, sin cero inicial en el mes: 2026.10.0, 2026.10.1; el siguiente mes empieza en 2026.11.0. Las etiquetas llevan el prefijo v.

## Exclusiones independientes

Cada herramienta tiene sus propios ajustes persistentes:

- **Excluir administradores**: activado por defecto. Protege superusuarios y cualquier cuenta con permiso de acceso a la administración. Se puede desactivar.
- **Nombres de usuario excluidos**: uno por línea; no distingue mayúsculas y minúsculas.
- **Grupos excluidos**: selección múltiple, incluidos los subgrupos.

Basta que se cumpla cualquiera de las exclusiones para proteger una cuenta. La cuenta que ejecuta la operación, cuando hay un usuario identificado, siempre queda protegida.

Las exclusiones de la comparación manual y las de la revisión manual por inactividad se guardan por separado en los parámetros del componente; las de la tarea, en los parámetros de **cada tarea programada**. No se copian ni se mezclan entre herramientas ni entre tareas.

## Comparación manual

Un asistente en tres pasos: **lista y exclusiones → revisar cambios → resultado**. Ningún cambio se aplica hasta confirmar en el último paso.

### 1. Lista y exclusiones

Abre **Componentes → User Tools** como superusuario y pulsa «Empezar comparación». Pega la lista **completa** de nombres vigentes, uno por línea (sirve una columna copiada de Excel), y configura las exclusiones. Puedes usar «Guardar exclusiones» sin pegar una lista.

![Paso 1: lista de usuarios vigentes y exclusiones](docs/screenshots/02-comparacion-lista.jpg)

Los grupos excluidos se eligen con casillas. Al activar «Excluir administradores», los grupos con permisos de administración aparecen marcados, en gris y bloqueados, con el motivo. Al desactivar la opción se recuperan las selecciones manuales. La protección automática no se guarda como una exclusión manual.

Los nombres se comparan sin distinguir mayúsculas y minúsculas: OTRO y otro son la misma cuenta. Se conservan los nombres originales y se eliminan espacios exteriores, líneas vacías y duplicados. Una lista vacía no se admite. No se admiten CSV ni se eliminan o reactivan usuarios.

### 2. Revisar cambios

Una pantalla independiente resume coincidencias, cuentas protegidas, bloqueos previstos y nombres nuevos. Revisa los bloqueos y marca los usuarios nuevos que quieres crear, indicando un correo distinto para cada uno. Los nombres desconocidos están **desmarcados por defecto**.

![Paso 2: revisión de bloqueos y altas seleccionadas](docs/screenshots/03-comparacion-revision.jpg)

«Volver y editar» conserva los datos introducidos. Para aplicar hay que marcar la casilla de confirmación.

Si **ningún nombre** de la lista coincide con una cuenta existente, la revisión lo avisa en rojo y propone bloquear todas las cuentas no protegidas; solo se aplican al marcar la confirmación. Una lista sin coincidencias permite tanto bajas como altas, siempre con el aviso y la confirmación.

![Aviso destacado cuando ningún nombre de la lista coincide](docs/screenshots/05-aviso-sin-coincidencias.jpg)

> [!WARNING]
> Una lista parcialmente incompleta puede producir bajas incorrectas: revisa la vista previa y utiliza la lista completa.

La vista previa caduca a los 15 minutos y se consume una sola vez. Un cambio de exclusiones invalida la vista previa. Antes de guardar se revalidan las cuentas.

### 3. Resultado

Se muestran las cuentas bloqueadas, las creadas y los errores, junto con las contraseñas temporales de las altas.

![Paso 3: resultado con contraseñas temporales (ocultas en la captura)](docs/screenshots/04-comparacion-resultado.jpg)

### Altas seleccionadas

Solo las cuentas seleccionadas se crean como **Registrados (grupo ID 2)**; se comprueba que el grupo exista y no conceda acceso a la administración. El nombre visible inicial coincide con el nombre de usuario.

Se valida el correo, su unicidad y la existencia del nombre antes de aplicar cambios. Si la selección es inválida, no se aplican ni altas ni bloqueos.

Cada cuenta recibe una contraseña aleatoria diferente y debe cambiarla en su primer acceso. Las contraseñas se muestran **una sola vez** en la pantalla de resultado. Se retiran del resultado guardado al mostrarlo y no se registran en logs. Al recargar o salir ya no se pueden consultar: usa la recuperación de contraseña de Joomla. Si el resultado no se abre, las credenciales pendientes caducan a los 15 minutos.

La herramienta suprime el correo de bienvenida del plugin de usuarios de Joomla durante las altas y restaura su configuración al terminar. Los demás plugins instalados siguen recibiendo los eventos normales de creación. Los fallos parciales aparecen en el resultado; no se revierten los cambios ya aplicados.

La revisión envía las selecciones y correos en un único campo JSON para no superar el límite de campos de PHP con listas grandes. Requiere JavaScript.

## Revisión manual por inactividad

En la portada, abre «Revisar cuentas inactivas». Configura un plazo en días, si se incluyen quienes nunca accedieron y sus exclusiones. Son independientes de la comparación de lista y de las tareas programadas. Buscar no modifica cuentas.

![Plazo de inactividad y exclusiones de la revisión manual](docs/screenshots/06-inactivos-plazo.jpg)

Marca solo las cuentas que quieres suspender y confirma. **No hay cuentas marcadas inicialmente** y puedes volver y editar. Se muestra la fecha límite UTC calculada.

![Selección de cuentas inactivas que se suspenderán](docs/screenshots/07-inactivos-seleccion.jpg)

Se revalidan fechas, exclusiones y sesiones antes de guardar, se protege al operador y solo se bloquean los IDs seleccionados en la revisión. La selección caduca a los 15 minutos. Se muestra el resultado final y no se eliminan cuentas.

## Tarea programada por inactividad

1. Habilita el plugin «Tarea - User Tools».
2. Ve a **Sistema → Tareas programadas → Nueva**.
3. Elige **User Tools: bloquear usuarios inactivos**.
4. Configura el periodo en días: **365** por defecto.
5. Configura sus propias exclusiones de administradores, nombres y grupos.
6. La **simulación está activada por defecto**. Ejecuta la tarea y revisa su resultado: indica los candidatos sin modificar cuentas.
7. Desactiva la simulación y establece una frecuencia, por ejemplo diaria, para aplicar los bloqueos.

![Parámetros de la tarea programada en el editor de tareas de Joomla](docs/screenshots/08-tarea-programada.jpg)

El último acceso debe ser **estrictamente anterior** al límite UTC. El periodo admite entre 1 y 36500 días; 365 días es una duración fija, no un año de calendario. Fechas inválidas y cuentas ya bloqueadas se omiten.

Los usuarios que nunca accedieron se excluyen por defecto. La opción «Incluir usuarios que nunca han accedido» permite tratarlos usando su fecha de registro.

La tarea revalida fechas y exclusiones con un objeto de usuario nuevo antes del guardado. Omite sesiones recientes registradas en la tabla de sesiones de Joomla; la detección depende de que Joomla mantenga esos metadatos. No garantiza cerrar sesiones ya abiertas.

Todas las herramientas bloquean mediante la API de usuarios de Joomla y ejecutan sus eventos de guardado. La tarea registra los bloqueos por ID y un resumen. Un error de guardado se comunica como fallo de tarea; los bloqueos previos de esa ejecución permanecen aplicados.

El plugin usa el programador nativo: para ejecutarlo aunque no haya visitas, configura el cron del alojamiento según la [guía oficial](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### Resultados dentro de User Tools

Abre **Componentes → User Tools → Resultados de tareas**. Cada ejecución registra fecha UTC, título e ID de tarea, simulación/real, plazo, estado y cantidades. Solo los superusuarios pueden consultarlos.

![Listado de ejecuciones de las tareas programadas](docs/screenshots/09-resultados-tareas.jpg)

«Ver informe» abre el detalle por usuario con el motivo de cada resultado: bloqueado por superar el plazo, protegido (administrador, grupo excluido, operador) u omitido (nunca accedió, registro reciente, sesión activa o cuenta ya bloqueada).

![Informe de una ejecución con el detalle por usuario](docs/screenshots/10-informe-tarea.jpg)

No depende del registro individual de Joomla. Las ejecuciones anteriores a esta funcionalidad no se importan. No se registran contraseñas ni correos. Los informes se conservan en la subcarpeta usertools-reports del directorio de registros, con cabecera PHP que impide su lectura directa por web. No se borran automáticamente.

Se guarda un informe inicial antes de modificar cuentas y se actualiza cada 50 usuarios y al terminar. Si se interrumpe la ejecución, el informe puede aparecer sin finalizar con datos parciales. Si no se puede guardar el informe inicial, la tarea termina con error antes de bloquear cuentas. Si falla una actualización posterior, puede haber cambios ya aplicados.

### Registro técnico opcional de Joomla

En **Sistema → Tareas programadas → Historial de ejecución** se consulta el estado de cada ejecución. Para generar un archivo de detalle, abre la tarea y habilita «Registro individual de la tarea» en la pestaña de registro; viene desactivado por defecto. Guarda y vuelve a ejecutar. El detalle se escribe en el directorio de registros configurado en Joomla, normalmente en el fichero task_ID.log.php (ID es el identificador de la tarea), salvo que se indique otro nombre en la tarea. Incluye IDs candidatos en simulación, bloqueos realizados y resumen de candidatos, bloqueados y errores.

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

Con Node.js, el comportamiento del formulario de revisión (carga, selección de altas, grupos de administración bloqueados e inactivos sin preselección):

~~~sh
node tests/review.js
~~~

tests/task.php comprueba el adaptador de la tarea con servicios simulados. No equivale a una prueba en Joomla real.

Con PowerShell y .NET:

~~~powershell
.\scripts\build.ps1 -OutputDirectory .\dist
~~~

Genera los tres paquetes instalables. El paquete principal contiene los ZIP del componente y del plugin. Las capturas de docs/screenshots no se incluyen en los paquetes.

Sintaxis PHP, políticas de exclusión, fechas límite, simulación y aplicación se comprueban localmente. Las capturas de este documento se han tomado en una instancia de pruebas de Joomla 6.2.0 con la extensión instalada.

## Publicación automática por etiquetas

Al enviar una etiqueta o publicar una release con formato **vAAAA.M.REVISIÓN** (por ejemplo **v2026.10.0**) al repositorio, GitHub Actions comprueba la sintaxis PHP, ejecuta las pruebas y genera los tres ZIP instalables. Después crea una release para esa etiqueta y adjunta los ZIP y un fichero SHA256SUMS.txt. Si la release ya existe, actualiza sus adjuntos.

La versión de la etiqueta se incorpora a los nombres de archivo y a los tres manifiestos dentro de los ZIP, sin modificar los fuentes. No hace falta cambiar manualmente los manifiestos para publicar una etiqueta. No se admiten sufijos de preversión en este flujo.

Desde GitHub: Releases → Draft a new release → Choose a tag → crea, por ejemplo, v2026.10.0 sobre main → Publish release. También puedes enviar la etiqueta desde Git. El flujo debe estar presente en el commit etiquetado. Crear una rama o enviar commits sin etiqueta no publica paquetes.

En la release, el fichero recomendado para instalar ambas herramientas es **pkg_usertools-VERSIÓN.zip** (por ejemplo, pkg_usertools-2026.10.0.zip). Los ZIP com_usertools y plg_task_usertools son las instalaciones separadas.

El flujo usa el GITHUB_TOKEN del propio repositorio, con permiso de escritura de contenido para adjuntar los archivos; no requiere un token personal. El lanzamiento y resultado se pueden consultar en la pestaña Actions.

Para construir una versión concreta localmente:

~~~powershell
.\scripts\build.ps1 -Version v2026.10.0 -OutputDirectory .\dist
.\tests\build.ps1 -Version v2026.10.0 -PackageDirectory .\dist
~~~

## Referencias

Implementación propia basada en la API del programador y el patrón de los plugins oficiales de Joomla 6.0.0: [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Proyecto independiente, sin afiliación oficial a Joomla.
