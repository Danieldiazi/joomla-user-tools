# Joomla User Tools

Herramientas de administración para Joomla 6: comparación de una lista vigente y tarea programada de bloqueo por inactividad. Versión 0.2.0, GPL-2.0-or-later.

## Instalación y actualización

Instala **pkg_usertools-0.2.0.zip** desde Sistema → Instalar → Extensiones. El paquete instala/actualiza el componente y el plugin de tareas. Conserva las exclusiones por nombre de la versión 0.1.0. No desinstales el componente para actualizar.

También se generan ZIP separados del componente y del plugin. El plugin necesita el componente 0.2.0 o posterior. Habilita «Tarea - User Tools» desde Sistema → Gestionar → Plugins; la instalación no habilita tareas de bloqueo por sí sola.

## Exclusiones independientes

Cada herramienta tiene sus propios ajustes persistentes:

- **Excluir administradores**: activado por defecto. Protege superusuarios y cualquier cuenta con permiso de acceso a la administración. Se puede desactivar.
- **Nombres de usuario excluidos**: uno por línea; distingue mayúsculas y minúsculas.
- **Grupos excluidos**: selección múltiple, incluidos los subgrupos.

Basta que se cumpla cualquiera de las exclusiones para proteger una cuenta. La cuenta que ejecuta la operación, cuando hay un usuario identificado, siempre queda protegida.

Las exclusiones de la comparación manual se guardan en los parámetros del componente. Las de la tarea se guardan en los parámetros de **cada tarea programada**. No se copian ni se mezclan entre ambas herramientas ni entre tareas.

## Comparación manual

1. Abre Componentes → User Tools como superusuario.
2. Pega la lista **completa** de nombres vigentes, uno por línea.
3. Configura las exclusiones de la comparación manual y pulsa Comparar.
4. Revisa los candidatos y confirma los bloqueos.

Puedes usar «Guardar exclusiones» sin pegar una lista. No se admiten CSV ni se crean, eliminan o reactivan usuarios.

Se eliminan espacios exteriores, líneas vacías y duplicados. Una lista vacía o sin coincidencias no permite bloquear cuentas. La vista previa caduca a los 15 minutos y se consume una sola vez. Un cambio de exclusiones invalida la vista previa. Antes de guardar se revalidan las cuentas.

Una lista parcialmente incompleta puede producir bajas incorrectas: revisa la vista previa y utiliza la lista completa.

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

## Construcción y pruebas

Con PHP 8.3/8.4:

~~~sh
php tests/comparison.php
php tests/policies.php
php tests/task.php
~~~

El último fichero comprueba el adaptador de la tarea con servicios simulados. No equivale a una prueba en Joomla real.

Con PowerShell y .NET:

~~~powershell
.\scripts\build.ps1 -OutputDirectory .\dist
~~~

Genera los tres paquetes instalables. El paquete principal contiene los ZIP del componente y del plugin.

Sintaxis PHP, políticas de exclusión, fechas límite, simulación y aplicación se comprueban localmente. **Pendiente de validar la instalación, edición de tareas y ejecución completa en una instancia real de Joomla 6.**

## Publicación automática por etiquetas

Al enviar una etiqueta o publicar una release con formato **vX.Y.Z** (por ejemplo **v0.2.1**) al repositorio, GitHub Actions comprueba la sintaxis PHP, ejecuta las pruebas y genera los tres ZIP instalables. Después crea una release para esa etiqueta y adjunta los ZIP y un fichero SHA256SUMS.txt. Si la release ya existe, actualiza sus adjuntos.

La versión de la etiqueta se incorpora a los nombres de archivo y a los tres manifiestos dentro de los ZIP, sin modificar los fuentes. No hace falta cambiar manualmente los manifiestos para publicar una etiqueta. No se admiten sufijos de preversión en este flujo.

Desde GitHub: Releases → Draft a new release → Choose a tag → crea, por ejemplo, v0.2.1 sobre main → Publish release. También puedes enviar la etiqueta desde Git. El flujo debe estar presente en el commit etiquetado. Crear una rama o enviar commits sin etiqueta no publica paquetes.

En la release, el fichero recomendado para instalar ambas herramientas es **pkg_usertools-X.Y.Z.zip**. Los ZIP com_usertools y plg_task_usertools son las instalaciones separadas.

El flujo usa el GITHUB_TOKEN del propio repositorio, con permiso de escritura de contenido para adjuntar los archivos; no requiere un token personal. El lanzamiento y resultado se pueden consultar en la pestaña Actions.

Para construir una versión concreta localmente:

~~~powershell
.\scripts\build.ps1 -Version v0.2.1 -OutputDirectory .\dist
.\tests\build.ps1 -Version v0.2.1 -PackageDirectory .\dist
~~~

## Referencias

Implementación propia basada en la API del programador y el patrón de los plugins oficiales de Joomla 6.0.0: [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs). La funcionalidad solicitada tiene como referencia [Task - Inactive Users de RicheyWeb](https://www.richeyweb.com/software/joomla/plugins/task-inactive-users); no se ha copiado su implementación.

Proyecto independiente, sin afiliación oficial a Joomla.
