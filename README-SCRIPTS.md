# Scripts de Prueba - Users Toolkit

## Scripts Disponibles

### 1. `test-search-users.php`
Script para probar la búsqueda de usuarios con diferentes criterios.

**Uso:**
```bash
cd /ruta/a/tu/sitio/wordpress
php wp-content/plugins/users-toolkit/test-search-users.php
```

**Modificar criterios:**
Edita el archivo y cambia las variables al inicio:
- `$test_criteria_negative`: Array de criterios negativos (NO debe tener)
- `$test_criteria_positive`: Array de criterios positivos (Debe TENER)
- `$test_match_all`: true/false para coincidir con todos los criterios

**Ejemplo sin criterios (buscar usuarios sin actividad por defecto):**
```bash
php wp-content/plugins/users-toolkit/test-search-users.php default
```

### 2. `test-find-inactive-users.php`
Script para buscar usuarios sin actividad y mostrar estadísticas.

**Uso:**
```bash
cd /ruta/a/tu/sitio/wordpress
php wp-content/plugins/users-toolkit/test-find-inactive-users.php
```

**Muestra:**
- Total de usuarios procesados
- Usuarios con cursos
- Usuarios con pedidos
- Usuarios con certificados
- Usuarios con comentarios
- Usuarios SIN actividad (lista)

## Cambios Recientes

### Mejora en Detección de Cursos
Se actualizó la detección de cursos para incluir:
1. `course_%%_access_from` (meta_key original)
2. `_sfwd-course_progress` (progreso de curso)
3. Tabla `learndash_user_activity` (actividad de LearnDash)

Esto asegura que se detecten correctamente todos los usuarios con cursos.

## Notas

- Los scripts procesan todos los usuarios, pueden tardar varios minutos en sitios grandes.
- Los administradores se excluyen automáticamente (a menos que se seleccione explícitamente el rol).
- Los resultados se muestran en la terminal.
