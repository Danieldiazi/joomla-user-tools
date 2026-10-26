# Preguntas frecuentes

Dudas habituales sobre **Joomla User Tools**. Para la descripción completa de cada herramienta, consulta el [README](README.md).

**Índice:** [General](#general) · [Comparación de listas](#comparación-de-listas) · [Altas y contraseñas](#altas-y-contraseñas) · [Inactividad y tarea programada](#inactividad-y-tarea-programada) · [Resultados e informes](#resultados-e-informes) · [Instalación y actualización](#instalación-y-actualización)

## General

### ¿Borra usuarios?

Solo la herramienta **Eliminar usuarios inactivos**, y únicamente las cuentas que marques una a una, con confirmación y escribiendo el número de cuentas. El resto de herramientas solo **bloquean** (el campo «Habilitado» de Joomla), y las cuentas bloqueadas conservan todos sus datos, grupos e historial. Ninguna herramienta reactiva cuentas.

### ¿Puedo recuperar una cuenta eliminada?

No. La eliminación es definitiva. En el registro de acciones de usuarios quedan su ID, su nombre de usuario y quién la eliminó, pero no sus datos. Si dudas, bloquéala primero con la revisión de inactivos y elimínala más adelante con «Solo bloqueadas».

### ¿Qué pasa con los artículos de una cuenta eliminada?

Se conservan, como cuando se elimina un usuario desde Joomla, pero dejan de estar asociados a una cuenta existente. Si necesitas reasignarlos, hazlo antes de eliminarla.

### He bloqueado una cuenta por error, ¿cómo la recupero?

Desde Joomla: **Usuarios → Gestionar**, marca la cuenta y usa la acción **Desbloquear**, o pulsa el icono de la columna «Habilitado». User Tools no deshace bloqueos automáticamente.

### ¿Qué cuentas no se bloquean ni se eliminan nunca?

- Los superusuarios, en todas las herramientas y aunque se desactive «Excluir administradores».
- La cuenta del usuario que ejecuta una operación manual.
- Con «Excluir administradores» activado (opción por defecto), cualquier cuenta con permiso para entrar en la administración.
- Quien no es superusuario nunca puede bloquear ni eliminar cuentas con acceso a la administración.
- Las que coincidan con los nombres o grupos excluidos de esa herramienta (los grupos incluyen sus subgrupos).

Basta con cumplir cualquiera de estas condiciones.

### ¿Las exclusiones son compartidas entre herramientas?

No. La comparación de listas, la revisión manual por inactividad, la eliminación de inactivos y **cada** tarea programada guardan sus propias exclusiones. Cambiar una no afecta a las demás.

### ¿Quién puede usarlo?

Los superusuarios y los usuarios con los permisos delegados correspondientes en **Opciones → Permisos**. Hay permisos separados para comparar listas y bloquear, crear cuentas, revisar usuarios inactivos, eliminar usuarios inactivos y consultar resultados de tareas. El de eliminar no se concede a nadie más que a los superusuarios hasta que lo des expresamente. El acceso al componente también requiere `core.manage`.

### ¿Necesita JavaScript?

Sí. Las pantallas de revisión usan JavaScript para gestionar las casillas y enviar las selecciones.

### ¿Cuántos usuarios admite?

Las pruebas automáticas cubren listas de 1800 usuarios. La revisión envía las selecciones en un único campo JSON precisamente para no chocar con el límite `max_input_vars` de PHP en listas grandes.

## Comparación de listas

### ¿Qué formato debe tener la lista?

Un nombre de usuario por línea. Sirve una columna copiada de Excel. No se admiten CSV con varias columnas. Se eliminan espacios exteriores, líneas vacías y duplicados.

### ¿Distingue mayúsculas y minúsculas?

No. `OTRO` y `otro` son la misma cuenta, tanto al comparar como en las exclusiones por nombre.

### ¿Qué pasa si pego una lista incompleta?

Todas las cuentas que falten se propondrán para bloqueo (salvo las protegidas). Por eso hay que pegar siempre la lista **completa** y revisar la pantalla de cambios antes de confirmar. Si **ningún** nombre coincide con una cuenta existente, la revisión muestra un aviso en rojo, porque probablemente se ha pegado la lista equivocada.

### ¿Se aplica algo al pulsar «Siguiente: revisar cambios»?

No. Ese paso solo guarda las exclusiones y calcula la vista previa. Los cambios se aplican únicamente al marcar la casilla de confirmación y pulsar «Confirmar y aplicar cambios».

### Me dice que la vista previa ha caducado

La vista previa dura **15 minutos** y solo se puede aplicar una vez. También se invalida si cambias las exclusiones o si cambia el conjunto de cuentas que se había propuesto bloquear. En ese caso debes volver a comparar; la lista introducida se conserva. Vuelve al primer paso (los datos se conservan con «Volver y editar») y genera una revisión nueva.

### ¿Por qué un usuario de la lista no aparece en ningún apartado?

Si existe en Joomla cuenta como coincidencia y no se toca. Las cuentas que ya estaban bloqueadas tampoco se proponen de nuevo.

## Altas y contraseñas

### ¿Se crean automáticamente los nombres nuevos de la lista?

No. Aparecen **desmarcados** en la revisión; solo se crean los que marques, cada uno con un correo distinto que no exista ya en Joomla.

### ¿En qué grupo se crean las cuentas nuevas?

En **Registrados** (grupo ID 2). La herramienta comprueba que el grupo exista y que no dé acceso a la administración. El nombre visible inicial es el propio nombre de usuario.

### ¿Se envía un correo de bienvenida?

Hay dos modos de alta. Por defecto se envía un correo con un enlace para elegir la contraseña, mediante la plantilla editable «User Tools: cuenta creada». También puedes elegir mostrar una contraseña temporal una sola vez y comunicarla tú mismo. En ambos casos se suprime el correo de bienvenida del plugin de usuarios de Joomla y después se restaura su configuración. Otros plugins siguen recibiendo los eventos normales de creación.

### He perdido la contraseña temporal

Las contraseñas se muestran **una sola vez** en la pantalla de resultado y no se guardan ni se registran en logs. Si recargas o sales, ya no se pueden recuperar: usa la recuperación de contraseña de Joomla o asigna una nueva desde **Usuarios → Gestionar**. En cualquier caso, la cuenta deberá cambiarla en su primer acceso.

### Una alta ha fallado y otras no, ¿se deshace todo?

Si la selección es inválida antes de empezar (correo repetido, nombre existente…), no se aplica nada. Los fallos que ocurran ya durante la aplicación aparecen en el resultado, pero los cambios que ya se hubieran hecho no se revierten.

## Inactividad y tarea programada

### ¿Qué diferencia hay entre la revisión manual y la tarea programada?

La **revisión manual** busca candidatos y tú marcas uno a uno los que se suspenden (ninguno está marcado de inicio). La **tarea programada** bloquea automáticamente todos los candidatos cada vez que se ejecuta, salvo que esté en modo simulación.

### ¿Cómo pruebo la tarea sin riesgo?

La tarea se crea con **Simulación (sin bloquear)** activada. Ejecútala y revisa en **Resultados de tareas** qué cuentas habría bloqueado. Cuando estés conforme, desactiva la simulación.

### ¿Cómo se calcula el plazo?

Se compara el último acceso con una fecha límite en **UTC**: hoy menos el número de días configurado (365 por defecto, entre 1 y 36500). El último acceso tiene que ser estrictamente anterior a ese límite. 365 días es una duración fija, no un año de calendario.

### ¿Por qué no se bloquea un usuario que nunca ha entrado?

Por defecto se excluyen las cuentas sin ningún acceso. Activa «Incluir usuarios que nunca han accedido» para tratarlas; entonces el plazo se cuenta desde su **fecha de registro**, de modo que las cuentas recién creadas tampoco se proponen.

### ¿Qué pasa con alguien que tiene la sesión abierta?

En las herramientas de inactividad, si tiene una sesión reciente en la tabla de sesiones de Joomla (dentro del tiempo de sesión configurado en la configuración global), se omite. Al bloquear una cuenta, User Tools solicita a Joomla cerrar sus sesiones abiertas. Ambas operaciones dependen de los metadatos de sesión de Joomla.

### La tarea no se ejecuta sola

Comprueba que el plugin **Tarea - User Tools** está habilitado y que la tarea tiene una regla de ejecución distinta de «Ejecución manual». El programador de Joomla depende por defecto de que haya visitas a la web; para que funcione sin tráfico, configura el cron del alojamiento según la [guía oficial de Joomla](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### ¿Puedo tener varias tareas con plazos distintos?

Sí. Cada tarea programada tiene su propio plazo, simulación y exclusiones, por ejemplo una de 365 días para todos y otra más estricta solo para un grupo.

### ¿Por qué una tarea se detiene por seguridad?

El límite contra bloqueos masivos detiene la ejecución real si los candidatos superan el porcentaje configurado de cuentas activas y alcanzan el mínimo de candidatos. Por defecto: más del 20 % y al menos 10 candidatos. En simulación solo avisa. Revisa el informe y la configuración antes de cambiar esos límites.

## Resultados e informes

### ¿Dónde veo qué ha hecho una tarea?

En **Componentes → User Tools → Resultados de tareas**. Cada ejecución muestra modo (simulación o real), estado y cantidades. «Ver informe» abre el detalle de cuentas bloqueadas, simuladas, protegidas o con error, y un resumen de las omitidas por motivo. Puedes filtrar ejecuciones y exportar cada informe a CSV.

### ¿Dónde se guardan los informes y ocupan espacio?

En la subcarpeta `usertools-reports` del directorio de registros de Joomla, protegidos contra la lectura directa por web. No guardan contraseñas ni correos. Se eliminan al terminar las ejecuciones según la retención configurada en **Opciones**: 90 días por defecto; 0 los conserva siempre.

### ¿Por qué no veo ejecuciones antiguas?

Los resultados se registran desde la versión que añadió esta funcionalidad; las ejecuciones anteriores no se importan. Para esas, consulta **Sistema → Tareas programadas → Historial de ejecución** o el registro individual de la tarea, si lo tenías activado.

### Un informe aparece «sin finalizar»

La ejecución se interrumpió a mitad. El informe se guarda al empezar, cada 50 usuarios y al terminar, así que refleja lo que se llegó a procesar; los bloqueos ya aplicados permanecen.

## Instalación y actualización

### ¿Qué versión de Joomla necesito?

Joomla 6. Las capturas de la documentación se han tomado en Joomla 6.2.0.

### ¿Qué fichero instalo?

**pkg_usertools-VERSIÓN.zip** de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest). Instala o actualiza el componente y el plugin a la vez. Los ZIP sueltos `com_usertools` y `plg_task_usertools` son para instalaciones separadas; deben corresponder a la misma versión del componente y del plugin.

### ¿Cómo actualizo sin perder la configuración?

Instala el paquete nuevo encima desde **Sistema → Instalar → Extensiones**. No desinstales el componente: perderías sus ajustes y exclusiones.

### ¿Cómo se numeran las versiones?

**año.mes.revisión**, sin cero inicial en el mes (2026.10.0, 2026.10.1, 2026.11.0…). Las etiquetas de GitHub llevan el prefijo `v`.

### He encontrado un error, ¿dónde lo comunico?

Abre una incidencia en [GitHub Issues](https://github.com/Danieldiazi/joomla-user-tools/issues) indicando la versión de Joomla y de la extensión, los pasos para reproducirlo y, si es posible, una captura.
