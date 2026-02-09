<?php
/**
 * Script de prueba para buscar usuarios spam
 * Ejecutar desde terminal: php test-search-users.php
 */

// Cargar WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Cargar las clases necesarias
require_once __DIR__ . '/includes/class-spam-user-identifier.php';
require_once __DIR__ . '/includes/class-progress-tracker.php';

// Configuración - Puedes modificar estos valores para probar diferentes búsquedas
$test_criteria_negative = array( 'courses', 'certificates', 'orders' ); // Criterios negativos (NO debe tener)
$test_criteria_positive = array(); // Criterios positivos (Debe TENER)
$test_match_all = true; // Coincidir con todos

// Si se pasan argumentos, usarlos
if ( isset( $argv[1] ) ) {
	if ( $argv[1] === 'default' ) {
		// Sin criterios - busca usuarios sin actividad por defecto
		$test_criteria_negative = array();
		$test_criteria_positive = array();
		$test_match_all = false;
		echo "Modo: Sin criterios (buscar usuarios sin actividad por defecto)\n\n";
	} elseif ( $argv[1] === 'negative' ) {
		// Criterios negativos: NO debe tener cursos, certificados ni pedidos
		$test_criteria_negative = array( 'courses', 'certificates', 'orders' );
		$test_criteria_positive = array();
		$test_match_all = true;
		echo "Modo: Criterios negativos (NO debe tener: cursos, certificados, pedidos)\n\n";
	} elseif ( $argv[1] === 'no-courses-orders' ) {
		// Criterios negativos: NO debe tener cursos NI pedidos (los comentarios no importan)
		$test_criteria_negative = array( 'courses', 'orders' );
		$test_criteria_positive = array();
		$test_match_all = true;
		echo "Modo: Usuarios SIN cursos NI pedidos (ignorar comentarios)\n\n";
	}
}

echo "=== TEST DE BÚSQUEDA DE USUARIOS ===\n\n";
echo "Criterios negativos (NO debe tener): " . implode( ', ', $test_criteria_negative ) . "\n";
echo "Criterios positivos (Debe TENER): " . ( empty( $test_criteria_positive ) ? '(ninguno)' : implode( ', ', $test_criteria_positive ) ) . "\n";
echo "Match all: " . ( $test_match_all ? 'Sí' : 'No' ) . "\n";
echo "Si no hay criterios, buscará usuarios sin actividad por defecto.\n\n";

// Aumentar límites
set_time_limit( 300 );
ini_set( 'memory_limit', '256M' );

// Ejecutar búsqueda
$start_time = microtime( true );
$users = Users_Toolkit_Spam_User_Identifier::identify_spam_users( '', $test_criteria_positive, $test_criteria_negative, $test_match_all );
$end_time = microtime( true );
$execution_time = round( $end_time - $start_time, 2 );

echo "=== RESULTADOS ===\n";
echo "Tiempo de ejecución: {$execution_time} segundos\n";
echo "Total de usuarios encontrados: " . count( $users ) . "\n\n";

if ( ! empty( $users ) ) {
	echo "=== PRIMEROS 10 USUARIOS ===\n";
	foreach ( array_slice( $users, 0, 10 ) as $user ) {
		echo "ID: {$user['ID']} | Email: {$user['email']} | Login: {$user['login']} | Registrado: {$user['registered']} | Días: {$user['days_old']}\n";
	}
	if ( count( $users ) > 10 ) {
		echo "... y " . ( count( $users ) - 10 ) . " más.\n";
	}
} else {
	echo "No se encontraron usuarios que cumplan los criterios.\n\n";
	
	// Mostrar estadísticas de los primeros usuarios para debug
	global $wpdb;
	$sample_users = $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID LIMIT 10" );
	echo "\n=== MUESTRA DE USUARIOS (primeros 10) ===\n";
	foreach ( $sample_users as $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user || user_can( $user_id, 'manage_options' ) ) {
			continue;
		}
		
		// Verificar actividades
		$has_courses = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} 
			WHERE user_id = %d 
			AND meta_key LIKE 'course_%%_access_from'",
			$user_id
		) ) > 0;
		
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
		
		$has_certificates = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			WHERE p.post_type = 'sfwd-certificates'
			AND p.post_author = %d",
			$user_id
		) ) > 0;
		
		$has_comments = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments} 
			WHERE user_id = %d 
			AND comment_approved != 'spam'",
			$user_id
		) ) > 0;
		
		echo "ID: {$user_id} | Email: {$user->user_email}\n";
		echo "  Cursos: " . ( $has_courses ? 'SÍ' : 'NO' ) . " | Pedidos: " . ( $has_orders ? 'SÍ' : 'NO' ) . " | Certificados: " . ( $has_certificates ? 'SÍ' : 'NO' ) . " | Comentarios: " . ( $has_comments ? 'SÍ' : 'NO' ) . "\n";
	}
}

echo "\n=== FIN ===\n";
