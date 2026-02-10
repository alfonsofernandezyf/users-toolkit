# Users Toolkit

Plugin de WordPress para identificar usuarios potencialmente spam, ejecutar acciones masivas de usuarios y mantener la base de datos optimizada con respaldo previo.

**Versión:** `1.3.0`  
**Autor:** Alfonso Fernández  
**Email:** alfonso@cientifi.ca

## Resumen

`Users Toolkit` agrega un menú de administración con 4 módulos:

1. `Dashboard`: métricas rápidas de base de datos.
2. `Usuarios Spam`: búsqueda avanzada de usuarios por actividad/inactividad.
3. `Optimización DB`: limpieza segura de transients, comentarios y cron.
4. `Backups`: creación, descarga y eliminación de respaldos de base de datos.

## Características principales

- Identificación de usuarios por criterios positivos y negativos.
- Filtro por uno o varios roles de WordPress.
- Criterios soportados:
  - Cursos LearnDash
  - Certificados LearnDash
  - Pedidos WooCommerce
  - Membresías WooCommerce
  - Comentarios
  - Descargas WP-DLM
  - Correo sospechoso (dominios desechables, TLDs riesgosos y patrones de bot)
  - Autoría de contenido (incluye tipos de post dinámicos)
- Modo de coincidencia:
  - Coincidir con todos los criterios
  - Coincidir con cualquiera de los criterios
- Filtros avanzados en resultados:
  - País único o múltiples países
  - Inclusión o exclusión de países seleccionados
  - Atajo de selección `MX + LATAM`
- Acciones sobre usuarios:
  - Simulación (dry run)
  - Exportación con metadatos
  - Eliminación por lotes
- Backups de base de datos:
  - Ejecución en segundo plano con barra de progreso
  - Fallback por WP-Cron si falla loopback
  - Método PHP por streaming para mayor estabilidad en hosting compartido
  - Descarga y borrado desde interfaz
- Optimización de base de datos:
  - Limpieza de transients expirados
  - Limpieza de comentarios spam/papelera por API de WordPress
  - Limpieza de entradas cron malformadas
  - Optimización de tablas

## Requisitos

- WordPress `>= 5.8`
- PHP `>= 7.4`
- Rol con capacidad `manage_options`

## Instalación

1. Copia la carpeta `users-toolkit` en:
   - `wp-content/plugins/users-toolkit`
2. Activa el plugin en:
   - `WordPress Admin > Plugins`
3. Abre:
   - `WordPress Admin > Users Toolkit`

## Guía rápida de uso

### 1) Identificar usuarios

Ruta: `Users Toolkit > Usuarios Spam`

1. Opcional: selecciona uno o más roles.
2. Marca criterios en:
   - `Debe TENER`
   - `NO debe tener`
3. Opcional: activa `Coincidir con TODOS los criterios`.
4. Haz clic en `Identificar Usuarios`.
5. Revisa los resultados y ejecuta una acción.

Tip:
- Si solo quieres listar usuarios de un rol, selecciona el rol y no marques más criterios.

Nota de membresías:
- El filtro considera membresías históricas (incluyendo expiradas/canceladas) para evitar marcar como spam usuarios legítimos con actividad previa.

### 2) Ejecutar acciones sobre usuarios

Desde la tabla de resultados:

1. Define alcance:
   - Usuarios seleccionados
   - Todos los usuarios de la lista
2. Elige acción:
   - `Simular`
   - `Exportar usuarios con metadatos`
   - `Eliminar usuarios permanentemente`

Recomendación:
- Ejecuta primero `Simular` o `Exportar` antes de eliminar.

### 3) Optimizar base de datos

Ruta: `Users Toolkit > Optimización DB`

Puedes ejecutar previsualización y luego aplicar:

- Limpiar transients expirados
- Limpiar comentarios spam/papelera
- Limpiar eventos cron obsoletos/malformados
- Optimizar tablas

### 4) Crear backup de base de datos

Ruta: `Users Toolkit > Backups`

1. Haz clic en `Crear Backup`.
2. Espera la finalización en la barra de progreso.
3. Descarga el archivo generado.

Ubicación de backups:
- `wp-content/uploads/users-toolkit/backups/`

## Solución de problemas

### El backup se queda en progreso

- Verifica que WP-Cron esté habilitado o tenga un disparador externo si usas `DISABLE_WP_CRON`.
- Revisa loopback requests del sitio (Site Health).
- Revisa `wp-content/debug.log` para errores de PHP.

### Errores de comunicación AJAX

- El plugin mantiene el proceso en segundo plano y sigue reportando progreso.
- Si hay timeout del navegador/proxy, deja la pantalla abierta para que el polling continúe.

### Rendimiento lento en búsqueda de usuarios

- Limita por roles cuando sea posible.
- Ejecuta en horarios de baja carga.
- Evita combinar demasiados criterios en sitios con muchos usuarios.

## Scripts de prueba

El repositorio incluye scripts utilitarios en la raíz:

- `test-search-users.php`
- `test-find-inactive-users.php`
- `test-no-courses-no-orders.php`

Más detalles en:
- `README-SCRIPTS.md`

## Estructura del plugin

```text
users-toolkit/
├── admin/
│   ├── class-users-toolkit-admin.php
│   ├── js/users-toolkit-admin.js
│   └── partials/
├── includes/
│   ├── class-users-toolkit.php
│   ├── class-users-toolkit-spam-user-identifier.php
│   ├── class-users-toolkit-spam-user-cleaner.php
│   ├── class-users-toolkit-database-optimizer.php
│   ├── class-users-toolkit-database-backup.php
│   └── class-users-toolkit-progress-tracker.php
└── users-toolkit.php
```

## Seguridad y buenas prácticas

- Realiza backup antes de operaciones destructivas.
- Prueba primero con simulación.
- Revisa permisos de administrador y accesos al entorno de producción.

## Licencia

GPL v2 o superior.
