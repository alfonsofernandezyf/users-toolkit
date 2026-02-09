<?php
/**
 * Script para obtener usuarios con cursos pero SIN pedidos
 * Ejecutar desde terminal: php get-users-no-orders.php
 */

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

echo "=== OBTENIENDO USUARIOS CON CURSOS PERO SIN PEDIDOS ===\n\n";

set_time_limit( 300 );
ini_set( 'memory_limit', '256M' );

// Consulta SQL optimizada
$users_result = $wpdb->get_results( "
    SELECT DISTINCT u.ID, u.user_email, u.user_login, u.user_registered,
           (SELECT COUNT(*) FROM {$wpdb->usermeta} um2 
            WHERE um2.user_id = u.ID 
            AND (um2.meta_key LIKE 'course_%%_access_from' OR um2.meta_key = '_sfwd-course_progress')) as course_count,
           (SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}wc_orders WHERE customer_id = u.ID) +
           (SELECT COUNT(*) FROM {$wpdb->postmeta} pm2
            INNER JOIN {$wpdb->posts} p2 ON pm2.post_id = p2.ID
            WHERE pm2.meta_key = '_customer_user' 
            AND pm2.meta_value = u.ID
            AND p2.post_type = 'shop_order') as orders_count
    FROM {$wpdb->users} u
    WHERE u.ID IN (
        SELECT DISTINCT user_id FROM {$wpdb->usermeta} 
        WHERE meta_key LIKE 'course_%%_access_from' OR meta_key = '_sfwd-course_progress'
        UNION
        SELECT DISTINCT user_id FROM {$wpdb->prefix}learndash_user_activity
    )
    AND u.ID NOT IN (
        SELECT DISTINCT customer_id FROM {$wpdb->prefix}wc_orders WHERE customer_id > 0
    )
    AND u.ID NOT IN (
        SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_customer_user' 
        AND p.post_type = 'shop_order' 
        AND pm.meta_value != '' 
        AND pm.meta_value REGEXP '^[0-9]+$'
    )
    AND u.ID NOT IN (
        SELECT user_id FROM {$wpdb->usermeta} 
        WHERE meta_key = 'wp_capabilities' 
        AND meta_value LIKE '%administrator%'
    )
    ORDER BY u.user_registered DESC
" );

$users_list = array();
foreach ( $users_result as $user_row ) {
	$days_old = floor( ( time() - strtotime( $user_row->user_registered ) ) / ( 60 * 60 * 24 ) );
	
	// Asegurar que los conteos sean números
	$course_count = (int) $user_row->course_count;
	$orders_count = (int) $user_row->orders_count;
	
	$users_list[] = array(
		'ID'         => $user_row->ID,
		'email'      => $user_row->user_email,
		'login'      => $user_row->user_login,
		'registered' => $user_row->user_registered,
		'days_old'   => $days_old,
		'courses'    => $course_count,
		'orders'     => $orders_count,
	);
}

echo "Total encontrados: " . count( $users_list ) . "\n\n";

// Guardar en archivo JSON
$upload_dir = wp_upload_dir();
$json_dir = $upload_dir['basedir'] . '/users-toolkit';
if ( ! file_exists( $json_dir ) ) {
	wp_mkdir_p( $json_dir );
}

$json_file = $json_dir . '/users-with-courses-no-orders-' . date( 'Y-m-d-H-i-s' ) . '.json';
file_put_contents( $json_file, json_encode( $users_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

echo "=== ARCHIVO GENERADO ===\n";
echo "JSON guardado en: {$json_file}\n";
echo "Total de usuarios: " . count( $users_list ) . "\n\n";

// Mostrar primeros 20
echo "=== PRIMEROS 20 USUARIOS ===\n";
foreach ( array_slice( $users_list, 0, 20 ) as $user ) {
	echo "ID: {$user['ID']} | Email: {$user['email']} | Cursos: {$user['courses']} | Pedidos: {$user['orders']} | Días: {$user['days_old']}\n";
}

echo "\n=== FIN ===\n";
