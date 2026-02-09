# Users Toolkit

Plugin de WordPress para buscar, borrar y optimizar la lista de usuarios y la base de datos.

## Características

- **Búsqueda avanzada de usuarios**: Identifica usuarios por roles, actividad, contenido publicado y más
- **Eliminación de usuarios**: Elimina usuarios de forma segura con confirmación
- **Optimización de base de datos**: Limpia transients, comentarios spam y optimiza tablas
- **Limpieza de cron**: Elimina eventos cron obsoletos que causan errores
- **Backup de base de datos**: Crea backups completos antes de realizar operaciones

## Instalación

1. Sube el directorio `users-toolkit` a `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración de WordPress
3. Accede a **Users Toolkit** en el menú de administración

## Uso

### Buscar usuarios

1. Ve a **Users Toolkit > Usuarios Spam**
2. Selecciona los criterios de búsqueda:
   - **Filtrar por rol**: Selecciona uno o más roles de usuario
   - **Debe TENER**: Cursos, pedidos, certificados, comentarios, o tipos de contenido específicos
   - **NO debe tener**: Criterios negativos para excluir usuarios
3. Haz clic en "Identificar Usuarios"
4. Revisa la lista de usuarios identificados
5. Selecciona los usuarios que deseas eliminar
6. Haz clic en "Eliminar Seleccionados" o "Eliminar Todos"

**Tip**: Para ver todos los usuarios de un rol específico (ej: todos los administradores), solo selecciona el rol y NO marques ningún otro criterio.

### Optimizar base de datos

1. Ve a **Users Toolkit > Optimización DB**
2. Selecciona la operación que deseas realizar:
   - **Limpiar Transients**: Elimina transients expirados
   - **Limpiar Comentarios**: Elimina comentarios spam y en papelera
   - **Limpiar Cron**: Elimina eventos cron obsoletos
   - **Optimizar Tablas**: Optimiza las tablas principales
   - **Optimización Completa**: Ejecuta todas las optimizaciones

### Backup de base de datos

1. Ve a **Users Toolkit > Backup**
2. Haz clic en "Crear Backup"
3. Espera a que se complete el proceso
4. Descarga el archivo de backup cuando esté listo

## Advertencias

⚠️ **IMPORTANTE**: Siempre realiza un backup completo de tu base de datos antes de:
- Eliminar usuarios
- Ejecutar optimizaciones de base de datos

## Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- Permisos de administrador

## Versión

1.0.0
