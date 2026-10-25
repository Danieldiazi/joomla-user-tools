# Joomla User Tools

Componente de administración para Joomla 6. Pega una lista completa de nombres de usuario y bloquea las cuentas que ya no aparecen, tras revisar una vista previa.

## Uso

1. Instala `com_usertools-0.1.0.zip` desde Sistema → Instalar → Extensiones.
2. Abre Componentes → User Tools como superusuario.
3. Pega los nombres vigentes, uno por línea.
4. Indica las cuentas de servicio excluidas, una por línea.
5. Pulsa Comparar, revisa el resultado y confirma los bloqueos.

No importa CSV ni crea, elimina o reactiva usuarios. Protege automáticamente al operador y a cualquier cuenta con permiso de acceso a la administración. Las exclusiones manuales se guardan al comparar y se recuperan en las siguientes sesiones.

Se ignoran líneas vacías y espacios al principio/final, se eliminan duplicados y se distinguen mayúsculas y minúsculas. Los nombres desconocidos se muestran. Una lista vacía o sin coincidencias nunca permite bloquear cuentas. La vista previa caduca a los 15 minutos, se consume una sola vez y se vuelve a comprobar cada cuenta al aplicar los cambios.

El fichero pegado debe ser la lista COMPLETA de usuarios vigentes. La vista previa no puede detectar que falte una parte de una lista válida.

Bloquea mediante la API de usuarios de Joomla para ejecutar sus eventos de guardado. No garantiza cerrar sesiones ya abiertas. No incluye tareas programadas ni bloqueo por antigüedad.

## Estado y pruebas

Versión inicial 0.1.0. Sintaxis y lógica comprobables con PHP 8.3/8.4. Pendiente de validar instalación y flujo completo en una instancia real de Joomla 6 antes de usar en producción.

```sh
php tests/comparison.php
find admin tests -name '*.php' -exec php -l {} \;
```

El paquete instalable contiene únicamente `com_usertools.xml` y `admin/` en su raíz.

## Licencia

GPL-2.0-or-later. Proyecto independiente, sin afiliación oficial a Joomla.
