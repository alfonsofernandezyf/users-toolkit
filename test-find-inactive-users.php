<?php
/**
 * Script para buscar usuarios sin actividad
 * Ejecutar desde terminal: php test-find-inactive-users.php
 * 
 * Opciones:
 *   --role=rol          Filtrar por rol específico (ej: subscriber, author, editor)
 *   --roles=rol1,rol2   Filtrar por múltiples roles separados por coma
 *   --with-content      Incluir usuarios que son autores de posts/pages/CPTs
 *   --only-content      Mostrar SOLO usuarios con contenido (autores)
 */

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

// Parsear argumentos de línea de comandos
$args = array();
$role_filter = null;
$roles_filter = null;
$include_with_content = false;
$only_with_content = false;

foreach ( $argv as $arg ) {
	if ( strpos( $arg, '--role=' ) === 0 ) {
		$role_filter = str_replace( '--role=', '', $arg );
	} elseif ( strpos( $arg, '--roles=' ) === 0 ) {
		$roles_filter = explode( ',', str_replace( '--roles=', '', $arg ) );
		$roles_filter = array_map( 'trim', $roles_filter );
	} elseif ( $arg === '--with-content' ) {
		$include_with_content = true;
	} elseif ( $arg === '--only-content' ) {
		$only_with_content = true;
	}
}

echo "=== BÚSQUEDA DE USUARIOS ===\n\n";

// Obtener todos los usuarios primero
$all_users_raw = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID" );

// Filtrar por roles si se especificó
$all_users = array();
if ( $role_filter || ( $roles_filter && ! empty( $roles_filter ) ) ) {
	$roles_to_check = $role_filter ? array( $role_filter ) : $roles_filter;
	
	foreach ( $all_users_raw as $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			continue;
		}
		
		$user_roles = $user->roles;
		$has_role = false;
		
		foreach ( $roles_to_check as $role ) {
			if ( in_array( $role, $user_roles, true ) ) {
				$has_role = true;
				break;
			}
		}
		
		if ( $has_role ) {
			$all_users[] = $user_id;
		}
	}
	
	if ( empty( $all_users ) ) {
		$roles_display = $role_filter ? $role_filter : implode( ', ', $roles_filter );
		echo "⚠️  No se encontraron usuarios con el(s) rol(es): {$roles_display}\n\n";
		exit;
	}
} else {
	$all_users = $all_users_raw;
}

$total = count( $all_users );

echo "Filtros aplicados:\n";
if ( $role_filter ) {
	echo "  - Rol: {$role_filter}\n";
} elseif ( $roles_filter ) {
	echo "  - Roles: " . implode( ', ', $roles_filter ) . "\n";
}
if ( $include_with_content ) {
	echo "  - Incluir usuarios con contenido\n";
}
if ( $only_with_content ) {
	echo "  - SOLO usuarios con contenido\n";
}
echo "\nTotal de usuarios: {$total}\n\n";

$users_without_activity = array();
$users_with_content = array();
$users_with_courses = 0;
$users_with_orders = 0;
$users_with_certificates = 0;
$users_with_comments = 0;
$users_with_posts = 0;

$processed = 0;
$chunk_size = 100;

foreach ( $all_users as $user_id ) {
	$processed++;
	
	// Mostrar progreso cada 100 usuarios
	if ( $processed % $chunk_size == 0 ) {
		echo "Procesando... {$processed}/{$total}\n";
	}
	
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		continue;
	}

	// Excluir administradores
	if ( user_can( $user_id, 'manage_options' ) ) {
		continue;
	}

	// Verificar actividades
	// Verificar múltiples formas de tener cursos
	$has_courses = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->usermeta} 
		WHERE user_id = %d 
		AND meta_key LIKE 'course_%%_access_from'",
		$user_id
	) ) > 0;
	
	if ( ! $has_courses ) {
		// Verificar _sfwd-course_progress (progreso de curso)
		$has_courses = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} 
			WHERE user_id = %d 
			AND meta_key = '_sfwd-course_progress'",
			$user_id
		) ) > 0;
	}
	
	if ( ! $has_courses ) {
		// Verificar tabla learndash_user_activity si existe
		$activity_table = $wpdb->prefix . 'learndash_user_activity';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$activity_table}'" ) == $activity_table ) {
			$has_courses = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$activity_table} WHERE user_id = %d",
				$user_id
			) ) > 0;
		}
	}

	if ( $has_courses ) {
		$users_with_courses++;
	}

	$has_orders = false;
	$orders_table = $wpdb->prefix . 'wc_orders';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$orders_table}'" ) == $orders_table ) {
		$has_orders = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$orders_table} WHERE customer_id = %d",
			$user_id
		) ) > 0;
	}
	if ( ! $has_orders ) {
		$has_orders = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_customer_user' 
			AND pm.meta_value = %d
			AND p.post_type = 'shop_order'",
			$user_id
		) ) > 0;
	}

	if ( $has_orders ) {
		$users_with_orders++;
	}

	$has_certificates = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		WHERE p.post_type = 'sfwd-certificates'
		AND p.post_author = %d",
		$user_id
	) ) > 0;

	if ( $has_certificates ) {
		$users_with_certificates++;
	}

	$has_comments = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->comments} 
		WHERE user_id = %d 
		AND comment_approved != 'spam'",
		$user_id
	) ) > 0;

	if ( $has_comments ) {
		$users_with_comments++;
	}

	// Verificar si es autor de posts, pages o CPTs
	$has_content = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} 
		WHERE post_author = %d 
		AND post_status IN ('publish', 'draft', 'pending', 'private')
		AND post_type IN ('post', 'page')",
		$user_id
	) ) > 0;
	
	// Si no tiene posts/pages, verificar CPTs
	if ( ! $has_content ) {
		$cpt_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT post_type) FROM {$wpdb->posts} 
			WHERE post_author = %d 
			AND post_status IN ('publish', 'draft', 'pending', 'private')
			AND post_type NOT IN ('post', 'page', 'revision', 'nav_menu_item', 'attachment')",
			$user_id
		) );
		$has_content = $cpt_count > 0;
	}

	if ( $has_content ) {
		$users_with_posts++;
		$user_registered = $user->user_registered;
		$days_old = floor( ( time() - strtotime( $user_registered ) ) / ( 60 * 60 * 24 ) );
		
		// Obtener conteo de contenido
		$post_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_author = %d 
			AND post_status IN ('publish', 'draft', 'pending', 'private')
			AND post_type NOT IN ('revision', 'nav_menu_item')",
			$user_id
		) );
		
		$users_with_content[] = array(
			'ID'         => $user_id,
			'email'      => $user->user_email,
			'login'      => $user->user_login,
			'registered' => $user_registered,
			'days_old'   => $days_old,
			'post_count' => $post_count,
		);
	}

	// Si solo queremos usuarios con contenido, saltar el resto
	if ( $only_with_content ) {
		continue;
	}

	// Si no tiene ninguna actividad, agregar a la lista
	$has_any_activity = $has_courses || $has_orders || $has_certificates || $has_comments || ( $include_with_content && $has_content );
	if ( ! $has_any_activity ) {
		$user_registered = $user->user_registered;
		$days_old = floor( ( time() - strtotime( $user_registered ) ) / ( 60 * 60 * 24 ) );

		$users_without_activity[] = array(
			'ID'         => $user_id,
			'email'      => $user->user_email,
			'login'      => $user->user_login,
			'registered' => $user_registered,
			'days_old'   => $days_old,
		);
	}
}

echo "\n=== ESTADÍSTICAS ===\n";
echo "Total usuarios procesados: {$processed}\n";
echo "Usuarios con cursos: {$users_with_courses}\n";
echo "Usuarios con pedidos: {$users_with_orders}\n";
echo "Usuarios con certificados: {$users_with_certificates}\n";
echo "Usuarios con comentarios: {$users_with_comments}\n";
echo "Usuarios con contenido (posts/pages/CPTs): {$users_with_posts}\n";
if ( ! $only_with_content ) {
	echo "Usuarios SIN actividad: " . count( $users_without_activity ) . "\n";
}
echo "\n";

if ( $only_with_content ) {
	// Mostrar usuarios con contenido
	if ( ! empty( $users_with_content ) ) {
		echo "=== USUARIOS CON CONTENIDO (AUTORES) ===\n";
		foreach ( array_slice( $users_with_content, 0, 50 ) as $user ) {
			echo "ID: {$user['ID']} | Email: {$user['email']} | Login: {$user['login']} | Posts: {$user['post_count']} | Registrado: {$user['registered']} | Días: {$user['days_old']}\n";
		}
		if ( count( $users_with_content ) > 50 ) {
			echo "... y " . ( count( $users_with_content ) - 50 ) . " más.\n";
		}
	} else {
		echo "No se encontraron usuarios con contenido.\n";
	}
} else {
	// Mostrar usuarios sin actividad
	if ( ! empty( $users_without_activity ) ) {
		echo "=== USUARIOS SIN ACTIVIDAD ===\n";
		foreach ( array_slice( $users_without_activity, 0, 20 ) as $user ) {
			echo "ID: {$user['ID']} | Email: {$user['email']} | Login: {$user['login']} | Registrado: {$user['registered']} | Días: {$user['days_old']}\n";
		}
		if ( count( $users_without_activity ) > 20 ) {
			echo "... y " . ( count( $users_without_activity ) - 20 ) . " más.\n";
		}
	} else {
		echo "No se encontraron usuarios sin actividad.\n";
		if ( ! $include_with_content ) {
			echo "Esto significa que TODOS los usuarios tienen al menos una actividad (cursos, pedidos, certificados, comentarios o contenido).\n";
		} else {
			echo "Esto significa que TODOS los usuarios tienen al menos una actividad.\n";
		}
	}
	
	// Si se incluyen usuarios con contenido, mostrarlos también
	if ( $include_with_content && ! empty( $users_with_content ) ) {
		echo "\n=== USUARIOS CON CONTENIDO (AUTORES) ===\n";
		foreach ( array_slice( $users_with_content, 0, 20 ) as $user ) {
			echo "ID: {$user['ID']} | Email: {$user['email']} | Login: {$user['login']} | Posts: {$user['post_count']} | Registrado: {$user['registered']} | Días: {$user['days_old']}\n";
		}
		if ( count( $users_with_content ) > 20 ) {
			echo "... y " . ( count( $users_with_content ) - 20 ) . " más.\n";
		}
	}
}

echo "\n=== FIN ===\n";
echo "\n💡 Uso del script:\n";
echo "  php test-find-inactive-users.php                    # Todos los usuarios\n";
echo "  php test-find-inactive-users.php --role=subscriber  # Solo suscriptores\n";
echo "  php test-find-inactive-users.php --roles=author,editor  # Múltiples roles\n";
echo "  php test-find-inactive-users.php --with-content     # Incluir usuarios con contenido\n";
echo "  php test-find-inactive-users.php --only-content     # SOLO usuarios con contenido\n";
echo "  php test-find-inactive-users.php --role=author --only-content  # Autores con contenido\n";
echo "\n";
