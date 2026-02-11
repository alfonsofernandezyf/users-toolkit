<?php

/**
 * Class to clean spam users
 */
class Users_Toolkit_Spam_User_Cleaner {

	/**
	 * Restore users from a backup JSON file
	 *
	 * @param string $backup_file Path to the backup JSON file
	 * @return array Results with restored count and errors
	 */
	public static function restore_users_from_backup( $backup_file ) {
		if ( ! file_exists( $backup_file ) ) {
			return array(
				'success' => false,
				'message' => 'El archivo de backup no existe: ' . $backup_file,
				'restored' => 0,
				'errors' => 0,
			);
		}

		$json_content = file_get_contents( $backup_file );
		$backup_data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'message' => 'Error al parsear el archivo JSON: ' . json_last_error_msg(),
				'restored' => 0,
				'errors' => 0,
			);
		}

		if ( empty( $backup_data['users'] ) ) {
			return array(
				'success' => false,
				'message' => 'No hay usuarios en el archivo de backup',
				'restored' => 0,
				'errors' => 0,
			);
		}

		global $wpdb;
		$restored = 0;
		$errors = 0;
		$errors_details = array();
		$skipped = 0;

		foreach ( $backup_data['users'] as $user_data ) {
			$user_id = isset( $user_data['ID'] ) ? (int) $user_data['ID'] : 0;
			$user_login = isset( $user_data['user_login'] ) ? $user_data['user_login'] : '';
			$user_email = isset( $user_data['user_email'] ) ? $user_data['user_email'] : '';

			if ( ! $user_id || ! $user_login || ! $user_email ) {
				$errors++;
				$errors_details[] = "Datos incompletos para usuario ID {$user_id}";
				continue;
			}

			// Verificar si el usuario ya existe (por ID, login o email)
			$existing_by_id = get_user_by( 'ID', $user_id );
			$existing_by_login = get_user_by( 'login', $user_login );
			$existing_by_email = get_user_by( 'email', $user_email );

			if ( $existing_by_id || $existing_by_login || $existing_by_email ) {
				$skipped++;
				continue; // Usuario ya existe, saltar
			}

			// Insertar directamente en la tabla de usuarios para preservar el ID original
			$user_insert_data = array(
				'ID'                  => $user_id,
				'user_login'          => $user_login,
				'user_pass'           => wp_hash_password( wp_generate_password( 24 ) ), // Contraseña temporal
				'user_nicename'       => isset( $user_data['user_nicename'] ) ? $user_data['user_nicename'] : sanitize_title( $user_login ),
				'user_email'          => $user_email,
				'user_url'            => isset( $user_data['user_url'] ) ? $user_data['user_url'] : '',
				'user_registered'     => isset( $user_data['user_registered'] ) ? $user_data['user_registered'] : current_time( 'mysql' ),
				'user_activation_key' => '',
				'user_status'         => isset( $user_data['user_status'] ) ? $user_data['user_status'] : 0,
				'display_name'        => isset( $user_data['display_name'] ) ? $user_data['display_name'] : $user_login,
			);

			// Insertar usuario
			$result = $wpdb->insert( $wpdb->users, $user_insert_data );

			if ( $result === false ) {
				$errors++;
				$errors_details[] = "Error al insertar usuario {$user_login} (ID: {$user_id}): " . $wpdb->last_error;
				continue;
			}

			// Restaurar metadatos
			if ( ! empty( $user_data['meta'] ) && is_array( $user_data['meta'] ) ) {
				foreach ( $user_data['meta'] as $meta_key => $meta_value ) {
					// update_user_meta() maneja la serialización automáticamente
					// NO serializar manualmente para evitar doble serialización
					update_user_meta( $user_id, $meta_key, $meta_value );
				}
			}

			// Restaurar roles
			if ( ! empty( $user_data['roles'] ) && is_array( $user_data['roles'] ) ) {
				$user_obj = new WP_User( $user_id );
				foreach ( $user_data['roles'] as $role ) {
					$user_obj->add_role( $role );
				}
			}

			$restored++;

			// Pausa pequeña cada 50 usuarios
			if ( $restored % 50 == 0 ) {
				usleep( 100000 ); // 0.1 segundos
			}
		}

		// Limpiar caché de usuarios
		wp_cache_flush();

		return array(
			'success' => true,
			'message' => sprintf( 'Restauración completada. Restaurados: %d, Saltados (ya existían): %d, Errores: %d', $restored, $skipped, $errors ),
			'restored' => $restored,
			'skipped' => $skipped,
			'errors' => $errors,
			'errors_details' => $errors_details,
			'total_in_backup' => count( $backup_data['users'] ),
		);
	}

	/**
	 * Export user data (profile + metadata) for backup
	 *
	 * @param int $user_id User ID to export
	 * @return array|false User data array or false on failure
	 */
	/**
	 * Export user data with metadata
	 * Made public to allow export functionality
	 */
	public static function export_user_data( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		global $wpdb;

		// Datos básicos del perfil
		$user_data = array(
			'ID'              => $user->ID,
			'user_login'      => $user->user_login,
			'user_nicename'  => $user->user_nicename,
			'user_email'      => $user->user_email,
			'user_url'        => $user->user_url,
			'user_registered' => $user->user_registered,
			'user_status'     => $user->user_status,
			'display_name'    => $user->display_name,
			'roles'           => $user->roles,
			'capabilities'   => array(),
		);

		// Obtener todas las capabilities
		if ( ! empty( $user->allcaps ) ) {
			$user_data['capabilities'] = $user->allcaps;
		}

		// Obtener todos los metadatos del usuario
		$user_meta = get_user_meta( $user_id );
		$user_data['meta'] = array();

		// Procesar metadatos (deserializar si es necesario)
		foreach ( $user_meta as $key => $value ) {
			// get_user_meta retorna un array si hay múltiples valores
			// Tomamos el primer valor si es array
			if ( is_array( $value ) && count( $value ) === 1 ) {
				$value = $value[0];
			}
			
			// Intentar deserializar si es necesario
			$unserialized = maybe_unserialize( $value );
			if ( $unserialized !== false ) {
				$user_data['meta'][ $key ] = $unserialized;
			} else {
				$user_data['meta'][ $key ] = $value;
			}
		}

		// Información adicional útil
		$user_data['additional_info'] = array(
			'export_date'    => current_time( 'mysql' ),
			'export_timestamp' => time(),
			'posts_count'     => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d",
				$user_id
			) ),
			'comments_count'  => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d",
				$user_id
			) ),
		);

		return $user_data;
	}

	/**
	 * Save user backup to file
	 *
	 * @param array $users_data Array of user data arrays
	 * @param bool  $dry_run    If true, this is a simulation
	 * @return string|false Backup file path or false on failure
	 */
	private static function save_user_backup( $users_data, $dry_run = false ) {
		if ( empty( $users_data ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/users-toolkit';

		// Crear directorio si no existe
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Nombre del archivo con timestamp
		$timestamp = date( 'Y-m-d_H-i-s' );
		$prefix = $dry_run ? 'simulation' : 'backup';
		$filename = "{$prefix}-deleted-users-{$timestamp}.json";
		$filepath = $backup_dir . '/' . $filename;

		// Preparar datos para el archivo
		$backup_data = array(
			'backup_info' => array(
				'created_at'     => current_time( 'mysql' ),
				'created_timestamp' => time(),
				'is_simulation'  => $dry_run,
				'total_users'    => count( $users_data ),
				'wordpress_version' => get_bloginfo( 'version' ),
			),
			'users' => $users_data,
		);

		// Guardar como JSON
		$json_data = wp_json_encode( $backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( $json_data === false ) {
			return false;
		}

		$result = file_put_contents( $filepath, $json_data );

		if ( $result === false ) {
			return false;
		}

		return $filepath;
	}

	/**
	 * Delete spam users by IDs (with batch processing support)
	 *
	 * @param array $user_ids Array of user IDs to delete
	 * @param bool  $dry_run  If true, only simulate deletion
	 * @param int   $batch_size Maximum number of users to process in one batch (0 = no limit)
	 * @return array Results with deleted count and errors
	 */
	public static function delete_spam_users( $user_ids, $dry_run = false, $batch_size = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		// SEGURIDAD: Normalizar dry_run a booleano estricto
		$dry_run = ( $dry_run === true || $dry_run === 'true' || $dry_run === '1' || $dry_run === 1 );
		
		// Procesamiento por lotes: si batch_size > 0, limitar el procesamiento
		$total_users = count( $user_ids );
		if ( $batch_size > 0 && $total_users > $batch_size ) {
			$user_ids = array_slice( $user_ids, 0, $batch_size );
		}
		
		$deleted = 0;
		$errors = 0;
		$skipped = 0;
		$errors_details = array();
		$backup_data = array(); // Array para almacenar datos de respaldo

		if ( ! is_array( $user_ids ) || empty( $user_ids ) ) {
			return array(
				'deleted' => 0,
				'errors'  => 0,
				'skipped' => 0,
				'errors_details' => array( 'No se proporcionaron IDs de usuario' ),
				'backup_file' => false,
				'total_requested' => $total_users,
				'processed' => 0,
				'remaining' => $total_users,
			);
		}
		
		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );

			if ( ! $user_id ) {
				continue;
			}

			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				$skipped++;
				continue;
			}

			// No eliminar administradores
			if ( user_can( $user_id, 'manage_options' ) ) {
				$errors++;
				$errors_details[] = "No se puede eliminar administrador: ID {$user_id}";
				continue;
			}

			// Exportar datos del usuario para respaldo (tanto en simulación como en borrado real)
			$user_backup = self::export_user_data( $user_id );
			if ( $user_backup !== false ) {
				$backup_data[] = $user_backup;
			}

			if ( $dry_run ) {
				// SIMULACIÓN: Solo contar, NUNCA borrar
				$deleted++;
			} else {
				// BORRADO REAL: Solo ejecutar si dry_run es explícitamente false
				// SEGURIDAD: Doble verificación antes de borrar
				if ( $dry_run === true ) {
					$errors++;
					$errors_details[] = "ERROR: Intento de borrado en modo simulación para usuario ID {$user_id}";
					continue;
				}
				
				// wp_delete_user() requiere $reassign si el usuario tiene contenido
				// Si no tiene contenido, podemos pasar null
				// Primero verificamos si tiene contenido
				global $wpdb;
				$has_content = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d",
					$user_id
				) ) > 0;

				// Si tiene contenido, reasignamos al usuario actual (o al admin principal)
				$reassign = null;
				if ( $has_content ) {
					$current_user_id = get_current_user_id();
					if ( $current_user_id && $current_user_id != $user_id ) {
						$reassign = $current_user_id;
					} else {
						// Si no hay usuario actual válido, usar el primer admin
						$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'exclude' => array( $user_id ) ) );
						if ( ! empty( $admins ) ) {
							$reassign = $admins[0]->ID;
						} else {
							// Último recurso: reasignar a usuario ID 1 si existe
							$admin_1 = get_user_by( 'ID', 1 );
							if ( $admin_1 && $admin_1->ID != $user_id ) {
								$reassign = 1;
							}
						}
					}
				}

				// Intentar eliminar el usuario
				// wp_delete_user() retorna true si tiene éxito, false si falla
				// También puede lanzar un WP_Error en algunos casos
				$result = false;
				try {
					$result = wp_delete_user( $user_id, $reassign );
				} catch ( Exception $e ) {
					$errors++;
					$errors_details[] = "Excepción al eliminar usuario ID {$user_id} - {$user->user_email}: " . $e->getMessage();
					continue;
				} catch ( Error $e ) {
					$errors++;
					$errors_details[] = "Error al eliminar usuario ID {$user_id} - {$user->user_email}: " . $e->getMessage();
					continue;
				}

				if ( $result ) {
					$deleted++;
				} else {
					$reason = '';
					if ( $has_content ) {
						$reason = ' (tenía contenido';
						if ( $reassign ) {
							$reason .= ', reasignado a ID ' . $reassign;
						} else {
							$reason .= ', no se pudo reasignar';
						}
						$reason .= ')';
					}
					// Verificar si el usuario aún existe (puede haber sido eliminado por otro proceso)
					$user_check = get_user_by( 'ID', $user_id );
					if ( ! $user_check ) {
						// El usuario ya no existe, probablemente fue eliminado
						$deleted++;
					} else {
						$errors++;
						$errors_details[] = "Error al eliminar usuario ID {$user_id} - {$user->user_email}{$reason}";
					}
				}
			}

			// Pequeña pausa para no sobrecargar
			if ( $deleted % 10 == 0 ) {
				usleep( 100000 ); // 0.1 segundos
			}
		}

		// Guardar respaldo de todos los usuarios procesados
		$backup_file = false;
		$backup_file_path = false;
		if ( ! empty( $backup_data ) ) {
			$backup_file_path = self::save_user_backup( $backup_data, $dry_run );
			if ( $backup_file_path !== false ) {
				$backup_file = basename( $backup_file_path );
			}
		}

		return array(
			'deleted'        => $deleted,
			'errors'         => $errors,
			'skipped'        => $skipped,
			'errors_details' => $errors_details,
			'backup_file'    => $backup_file,
			'backup_path'    => $backup_file_path ? dirname( $backup_file_path ) : false,
			'total_requested' => $total_users,
			'processed'      => count( $user_ids ),
			'remaining'      => max( 0, $total_users - count( $user_ids ) ),
		);
	}
}
