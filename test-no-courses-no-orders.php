<?php
/**
 * Script para buscar usuarios SIN cursos NI pedidos (ignorando comentarios)
 * Ejecutar desde terminal: php test-no-courses-no-orders.php
 */

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

echo "=== BÚSQUEDA: USUARIOS SIN CURSOS NI PEDIDOS ===\n\n";
echo "Nota: Los comentarios se ignoran en esta búsqueda.\n\n";

set_time_limit( 300 );
ini_set( 'memory_limit', '256M' );

// Obtener todos los usuarios (excepto admins)
$all_users = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID" );
$total = count( $all_users );
echo "Total de usuarios a procesar: {$total}\n\n";

// Consulta SQL optimizada para encontrar usuarios sin cursos ni pedidos
echo "Ejecutando consulta SQL optimizada...\n";

$activity_table = $wpdb->prefix . 'learndash_user_activity';
$has_activity_table = $wpdb->get_var( "SHOW TABLES LIKE '{$activity_table}'" ) == $activity_table;
$orders_table = $wpdb->prefix . 'wc_orders';
$has_orders_table = $wpdb->get_var( "SHOW TABLES LIKE '{$orders_table}'" ) == $orders_table;

// Construir subconsultas para usuarios con cursos
$course_conditions = array(
	"user_id IN (SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE 'course_%%_access_from')",
	"user_id IN (SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_sfwd-course_progress')"
);

if ( $has_activity_table ) {
	$course_conditions[] = "user_id IN (SELECT DISTINCT user_id FROM {$activity_table})";
}

$course_where = implode( ' OR ', $course_conditions );

// Construir subconsultas para usuarios con pedidos
$order_conditions = array();

if ( $has_orders_table ) {
	$order_conditions[] = "user_id IN (SELECT DISTINCT customer_id FROM {$orders_table})";
}

$order_conditions[] = "user_id IN (
	SELECT DISTINCT pm.meta_value 
	FROM {$wpdb->postmeta} pm
	INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
	WHERE pm.meta_key = '_customer_user' 
	AND p.post_type = 'shop_order'
	AND pm.meta_value != ''
)";

$order_where = implode( ' OR ', $order_conditions );

// Obtener administradores
$admin_cap = $wpdb->get_col( 
	"SELECT user_id FROM {$wpdb->usermeta} 
	WHERE meta_key = 'wp_capabilities' 
	AND meta_value LIKE '%administrator%'"
);
$admin_ids = ! empty( $admin_cap ) ? implode( ',', array_map( 'intval', $admin_cap ) ) : '0';

// Consulta final: usuarios que NO tienen cursos NI pedidos
$sql = "
	SELECT u.ID, u.user_email, u.user_login, u.user_registered
	FROM {$wpdb->users} u
	WHERE u.ID NOT IN ({$admin_ids})
	AND u.ID NOT IN (
		SELECT DISTINCT user_id FROM (
			SELECT user_id FROM {$wpdb->users} WHERE {$course_where}
			UNION
			SELECT user_id FROM {$wpdb->users} WHERE {$order_where}
		) AS with_activity
	)
	ORDER BY u.user_registered DESC
";

echo "Ejecutando consulta...\n";
$users_result = $wpdb->get_results( $sql );

$users_without_courses_or_orders = array();
foreach ( $users_result as $user_row ) {
	$days_old = floor( ( time() - strtotime( $user_row->user_registered ) ) / ( 60 * 60 * 24 ) );
	
	$users_without_courses_or_orders[] = array(
		'ID'         => $user_row->ID,
		'email'      => $user_row->user_email,
		'login'      => $user_row->user_login,
		'registered' => $user_row->user_registered,
		'days_old'   => $days_old,
	);
}

echo "\n=== RESULTADOS ===\n";
echo "Total usuarios en BD: {$total}\n";
echo "Usuarios SIN cursos NI pedidos: " . count( $users_without_courses_or_orders ) . "\n\n";

if ( ! empty( $users_without_courses_or_orders ) ) {
	echo "=== LISTA DE USUARIOS ===\n";
	foreach ( $users_without_courses_or_orders as $user ) {
		echo "ID: {$user['ID']} | Email: {$user['email']} | Login: {$user['login']} | Registrado: {$user['registered']} | Días: {$user['days_old']}\n";
	}
	
	// Guardar en archivo JSON
	$upload_dir = wp_upload_dir();
	$json_dir = $upload_dir['basedir'] . '/users-toolkit';
	if ( ! file_exists( $json_dir ) ) {
		wp_mkdir_p( $json_dir );
	}
	
	$json_file = $json_dir . '/users-no-courses-no-orders-' . date( 'Y-m-d-H-i-s' ) . '.json';
	file_put_contents( $json_file, json_encode( $users_without_courses_or_orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	
	echo "\n=== ARCHIVO GENERADO ===\n";
	echo "JSON guardado en: {$json_file}\n";
	echo "Total de usuarios: " . count( $users_without_courses_or_orders ) . "\n";
} else {
	echo "No se encontraron usuarios sin cursos ni pedidos.\n";
	echo "Todos los usuarios tienen al menos cursos o pedidos.\n";
}

echo "\n=== FIN ===\n";
