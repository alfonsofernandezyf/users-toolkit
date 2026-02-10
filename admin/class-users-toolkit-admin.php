<?php

/**
 * The admin-specific functionality of the plugin.
 */
class Users_Toolkit_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'users-toolkit' ) === false ) {
			return;
		}
		wp_enqueue_style(
			$this->plugin_name,
			USERS_TOOLKIT_URL . 'admin/css/users-toolkit-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'users-toolkit' ) === false ) {
			return;
		}
		wp_enqueue_script(
			$this->plugin_name,
			USERS_TOOLKIT_URL . 'admin/js/users-toolkit-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'usersToolkit',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'users_toolkit_nonce' ),
				'strings' => array(
					'confirm_delete'     => __( '¿Estás seguro de que deseas eliminar estos usuarios? Esta acción no se puede deshacer.', 'users-toolkit' ),
					'identifying'        => __( 'Identificando usuarios spam...', 'users-toolkit' ),
					'deleting'           => __( 'Eliminando usuarios...', 'users-toolkit' ),
					'optimizing'         => __( 'Optimizando base de datos...', 'users-toolkit' ),
					'cleaning_cron'      => __( 'Limpiando cron...', 'users-toolkit' ),
					'error'              => __( 'Error:', 'users-toolkit' ),
					'success'            => __( 'Operación completada exitosamente', 'users-toolkit' ),
				),
			)
		);
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Users Toolkit', 'users-toolkit' ),
			__( 'Users Toolkit', 'users-toolkit' ),
			'manage_options',
			'users-toolkit',
			array( $this, 'display_dashboard_page' ),
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'users-toolkit',
			__( 'Dashboard', 'users-toolkit' ),
			__( 'Dashboard', 'users-toolkit' ),
			'manage_options',
			'users-toolkit',
			array( $this, 'display_dashboard_page' )
		);

		add_submenu_page(
			'users-toolkit',
			__( 'Usuarios Spam', 'users-toolkit' ),
			__( 'Usuarios Spam', 'users-toolkit' ),
			'manage_options',
			'users-toolkit-spam',
			array( $this, 'display_spam_users_page' )
		);

		add_submenu_page(
			'users-toolkit',
			__( 'Optimización DB', 'users-toolkit' ),
			__( 'Optimización DB', 'users-toolkit' ),
			'manage_options',
			'users-toolkit-db',
			array( $this, 'display_database_page' )
		);

		add_submenu_page(
			'users-toolkit',
			__( 'Backups', 'users-toolkit' ),
			__( 'Backups', 'users-toolkit' ),
			'manage_options',
			'users-toolkit-backup',
			array( $this, 'display_backup_page' )
		);
	}

	/**
	 * Handle admin actions
	 */
	public function handle_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle non-AJAX actions if needed
	}

	/**
	 * Display dashboard page
	 */
	public function display_dashboard_page() {
		$stats = Users_Toolkit_Database_Optimizer::get_statistics();
		include_once USERS_TOOLKIT_PATH . 'admin/partials/dashboard.php';
	}

	/**
	 * Display spam users page
	 */
	public function display_spam_users_page() {
		$upload_dir = wp_upload_dir();
		$reports_dir = $upload_dir['basedir'] . '/users-toolkit';
		$spam_users = array();
		$existing_json_files = array();

		if ( isset( $_GET['load_file'] ) && ! empty( $_GET['load_file'] ) ) {
			$file_path = $reports_dir . '/' . sanitize_file_name( $_GET['load_file'] );
			if ( file_exists( $file_path ) && pathinfo( $file_path, PATHINFO_EXTENSION ) === 'json' ) {
				$json_content = file_get_contents( $file_path );
				$json_data = json_decode( $json_content, true );
				
				// Manejar diferentes estructuras de JSON
				if ( is_array( $json_data ) ) {
					// Si tiene estructura de backup (backup_info + users)
					if ( isset( $json_data['users'] ) && is_array( $json_data['users'] ) ) {
						$spam_users = $json_data['users'];
					} 
					// Si es directamente un array de usuarios
					elseif ( isset( $json_data[0] ) && is_array( $json_data[0] ) && isset( $json_data[0]['ID'] ) ) {
						$spam_users = $json_data;
					}
					// Si no tiene estructura reconocida, intentar como array directo
					else {
						$spam_users = $json_data;
					}
				}
				
				if ( ! is_array( $spam_users ) ) {
					$spam_users = array();
				}
			}
		}

		// Listar archivos .json existentes para carga manual (si la búsqueda terminó pero el redirect falló)
		if ( is_dir( $reports_dir ) ) {
			$glob = glob( $reports_dir . '/spam-users-*.json' );
			if ( $glob ) {
				rsort( $glob );
				foreach ( $glob as $f ) {
					$existing_json_files[] = array(
						'name' => basename( $f ),
						'date' => date_i18n( 'Y-m-d H:i', filemtime( $f ) ),
					);
				}
			}
		}

		include_once USERS_TOOLKIT_PATH . 'admin/partials/spam-users.php';
	}

	/**
	 * Display database optimization page
	 */
	public function display_database_page() {
		// Cargar estadísticas solo si se solicita (se guarda la preferencia del usuario)
		$auto_load = get_user_meta( get_current_user_id(), 'users_toolkit_auto_load_stats', true );
		$auto_load = $auto_load !== '0' && $auto_load !== false; // Por defecto true
		$stats = $auto_load ? Users_Toolkit_Database_Optimizer::get_statistics() : array();
		include_once USERS_TOOLKIT_PATH . 'admin/partials/database.php';
	}

	/**
	 * Display backup page
	 */
	public function display_backup_page() {
		$backups = Users_Toolkit_Database_Backup::get_backup_list();
		include_once USERS_TOOLKIT_PATH . 'admin/partials/backup.php';
	}

	/**
	 * Build identify operation ID.
	 *
	 * @return string
	 */
	private function build_identify_operation_id() {
		return 'spam_identify_' . time() . '_' . wp_generate_password( 8, false );
	}

	/**
	 * Validate identify operation ID format.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return bool
	 */
	private function is_valid_identify_operation_id( $operation_id ) {
		return ! empty( $operation_id ) && strpos( $operation_id, 'spam_identify_' ) === 0;
	}

	/**
	 * Build transient key for identify worker token.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private function get_identify_worker_token_key( $operation_id ) {
		return 'users_toolkit_identify_worker_' . $operation_id;
	}

	/**
	 * Build transient key for identify worker payload.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private function get_identify_worker_payload_key( $operation_id ) {
		return 'users_toolkit_identify_payload_' . $operation_id;
	}

	/**
	 * Build transient key for identify worker lock.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private function get_identify_worker_lock_key( $operation_id ) {
		return 'users_toolkit_identify_lock_' . $operation_id;
	}

	/**
	 * Trigger async identify worker via loopback request.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @return bool
	 */
	private function dispatch_identify_worker_request( $operation_id, $worker_token ) {
		$url = admin_url( 'admin-ajax.php' );
		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => 1,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action'       => 'users_toolkit_process_identify',
					'operation_id' => $operation_id,
					'worker_token' => $worker_token,
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Schedule async identify worker using WP-Cron as fallback.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @param int    $delay        Delay in seconds.
	 * @return bool
	 */
	private function schedule_identify_worker_event( $operation_id, $worker_token, $delay = 10 ) {
		$delay = max( 1, (int) $delay );
		$args  = array( $operation_id, $worker_token );

		if ( wp_next_scheduled( 'users_toolkit_process_identify_event', $args ) ) {
			return true;
		}

		return (bool) wp_schedule_single_event( time() + $delay, 'users_toolkit_process_identify_event', $args );
	}

	/**
	 * Run identify process for worker endpoints.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @return true|WP_Error
	 */
	private function process_identify_operation( $operation_id, $worker_token ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$worker_token = sanitize_text_field( (string) $worker_token );

		if ( ! $this->is_valid_identify_operation_id( $operation_id ) || empty( $worker_token ) ) {
			return new WP_Error( 'invalid_request', __( 'Solicitud de identificación inválida', 'users-toolkit' ) );
		}

		$token_key    = $this->get_identify_worker_token_key( $operation_id );
		$stored_token = get_transient( $token_key );
		if ( ! is_string( $stored_token ) || ! hash_equals( $stored_token, $worker_token ) ) {
			return new WP_Error( 'invalid_token', __( 'Token de identificación inválido o expirado', 'users-toolkit' ) );
		}

		$payload_key = $this->get_identify_worker_payload_key( $operation_id );
		$payload     = get_transient( $payload_key );
		if ( ! is_array( $payload ) ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se encontró la configuración de búsqueda. Reintenta la operación.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			delete_transient( $token_key );
			return new WP_Error( 'missing_payload', __( 'Configuración de búsqueda no encontrada', 'users-toolkit' ) );
		}

		$lock_key = $this->get_identify_worker_lock_key( $operation_id );
		if ( get_transient( $lock_key ) ) {
			return true;
		}
		set_transient( $lock_key, 1, 1800 );
		$this->register_worker_shutdown_handler( $operation_id, $lock_key, $token_key, $payload_key, 'identify' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 1800 );
		}
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'max_execution_time', '1800' );
			@ini_set( 'memory_limit', '512M' );
		}

		Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 5, __( 'Procesando búsqueda de usuarios en segundo plano...', 'users-toolkit' ), false );

		try {
			$spam_users = Users_Toolkit_Spam_User_Identifier::identify_spam_users(
				$operation_id,
				isset( $payload['criteria_positive'] ) && is_array( $payload['criteria_positive'] ) ? $payload['criteria_positive'] : array(),
				isset( $payload['criteria_negative'] ) && is_array( $payload['criteria_negative'] ) ? $payload['criteria_negative'] : array(),
				! empty( $payload['match_all'] ),
				isset( $payload['post_types_positive'] ) && is_array( $payload['post_types_positive'] ) ? $payload['post_types_positive'] : array(),
				isset( $payload['post_types_negative'] ) && is_array( $payload['post_types_negative'] ) ? $payload['post_types_negative'] : array(),
				isset( $payload['user_roles'] ) && is_array( $payload['user_roles'] ) ? $payload['user_roles'] : array()
			);
		} catch ( Exception $e ) {
			$spam_users = new WP_Error( 'identify_exception', $e->getMessage() );
		} catch ( Error $e ) {
			$spam_users = new WP_Error( 'identify_error', $e->getMessage() );
		}

		if ( is_wp_error( $spam_users ) ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'Error durante la búsqueda:', 'users-toolkit' ) . ' ' . $spam_users->get_error_message(),
				true,
				array(
					'error'   => true,
					'message' => $spam_users->get_error_message(),
				)
			);
			delete_transient( $lock_key );
			delete_transient( $token_key );
			delete_transient( $payload_key );
			return $spam_users;
		}

		if ( empty( $spam_users ) ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se encontraron usuarios que coincidan con los criterios seleccionados.', 'users-toolkit' ),
				true,
				array(
					'count'     => 0,
					'file'      => '',
					'file_json' => '',
				)
			);
			delete_transient( $lock_key );
			delete_transient( $token_key );
			delete_transient( $payload_key );
			return true;
		}

		$file_path = Users_Toolkit_Spam_User_Identifier::save_spam_users_list( $spam_users );
		if ( false === $file_path ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se pudo guardar el archivo de resultados.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			delete_transient( $lock_key );
			delete_transient( $token_key );
			delete_transient( $payload_key );
			return new WP_Error( 'save_failed', __( 'No se pudo guardar el archivo de resultados.', 'users-toolkit' ) );
		}

		$file_json = pathinfo( $file_path, PATHINFO_FILENAME ) . '.json';
		Users_Toolkit_Progress_Tracker::set_progress(
			$operation_id,
			100,
			sprintf( __( 'Completado: %d usuarios spam encontrados', 'users-toolkit' ), count( $spam_users ) ),
			true,
			array(
				'count'     => count( $spam_users ),
				'file'      => basename( $file_path ),
				'file_json' => $file_json,
			)
		);

		delete_transient( $lock_key );
		delete_transient( $token_key );
		delete_transient( $payload_key );

		return true;
	}

	/**
	 * Register a shutdown guard to capture fatal errors in workers and avoid stuck locks.
	 *
	 * @param string      $operation_id Operation identifier.
	 * @param string      $lock_key     Lock transient key.
	 * @param string      $token_key    Token transient key.
	 * @param string|null $payload_key  Optional payload transient key.
	 * @param string      $worker_type  Worker label for messages.
	 */
	private function register_worker_shutdown_handler( $operation_id, $lock_key, $token_key, $payload_key = null, $worker_type = 'worker' ) {
		$reserved_memory = str_repeat( 'x', 32768 );
		register_shutdown_function(
			function() use ( $operation_id, $lock_key, $token_key, $payload_key, $worker_type, &$reserved_memory ) {
				$reserved_memory = null;
				$error = error_get_last();
				if ( ! is_array( $error ) || ! isset( $error['type'] ) ) {
					return;
				}

				$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
				if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
					return;
				}

				if ( ! get_transient( $lock_key ) ) {
					return;
				}

				$type_label = ( 'identify' === $worker_type ) ? __( 'búsqueda', 'users-toolkit' ) : __( 'backup', 'users-toolkit' );
				$line = isset( $error['line'] ) ? (int) $error['line'] : 0;
				$file = isset( $error['file'] ) ? basename( (string) $error['file'] ) : 'unknown';
				$message = isset( $error['message'] ) ? wp_strip_all_tags( (string) $error['message'] ) : __( 'Error fatal desconocido', 'users-toolkit' );

				Users_Toolkit_Progress_Tracker::set_progress(
					$operation_id,
					100,
					sprintf( __( 'Error fatal durante %1$s: %2$s (%3$s:%4$d)', 'users-toolkit' ), $type_label, $message, $file, $line ),
					true,
					array(
						'error'   => true,
						'fatal'   => true,
						'message' => $message,
						'file'    => $file,
						'line'    => $line,
					)
				);

				delete_transient( $lock_key );
				delete_transient( $token_key );
				if ( ! empty( $payload_key ) ) {
					delete_transient( $payload_key );
				}
			}
		);
	}

	/**
	 * AJAX handler for identifying spam users
	 */
	public function ajax_identify_spam() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$criteria_positive   = isset( $_POST['criteria_positive'] ) && is_array( $_POST['criteria_positive'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['criteria_positive'] ) ) : array();
		$criteria_negative   = isset( $_POST['criteria_negative'] ) && is_array( $_POST['criteria_negative'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['criteria_negative'] ) ) : array();
		$post_types_positive = isset( $_POST['post_types_positive'] ) && is_array( $_POST['post_types_positive'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types_positive'] ) ) : array();
		$post_types_negative = isset( $_POST['post_types_negative'] ) && is_array( $_POST['post_types_negative'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types_negative'] ) ) : array();
		$user_roles          = isset( $_POST['user_roles'] ) && is_array( $_POST['user_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['user_roles'] ) ) : array();
		$match_all           = isset( $_POST['match_all'] ) && wp_unslash( $_POST['match_all'] ) === '1';

		$operation_id = isset( $_POST['operation_id'] ) ? sanitize_key( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( ! $this->is_valid_identify_operation_id( $operation_id ) ) {
			$operation_id = $this->build_identify_operation_id();
		}

		$worker_token = wp_generate_password( 32, false, false );
		$payload = array(
			'criteria_positive'   => array_values( array_unique( $criteria_positive ) ),
			'criteria_negative'   => array_values( array_unique( $criteria_negative ) ),
			'post_types_positive' => array_values( array_unique( $post_types_positive ) ),
			'post_types_negative' => array_values( array_unique( $post_types_negative ) ),
			'user_roles'          => array_values( array_unique( $user_roles ) ),
			'match_all'           => (bool) $match_all,
		);

		set_transient( $this->get_identify_worker_token_key( $operation_id ), $worker_token, 7200 );
		set_transient( $this->get_identify_worker_payload_key( $operation_id ), $payload, 7200 );
		Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 1, __( 'Iniciando búsqueda de usuarios...', 'users-toolkit' ), false );

			$scheduled  = $this->schedule_identify_worker_event( $operation_id, $worker_token, 10 );
			$dispatched = $this->dispatch_identify_worker_request( $operation_id, $worker_token );
			delete_transient( 'users_toolkit_identify_retry_count_' . $operation_id );

		if ( ! $scheduled && ! $dispatched ) {
			delete_transient( $this->get_identify_worker_token_key( $operation_id ) );
			delete_transient( $this->get_identify_worker_payload_key( $operation_id ) );
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se pudo iniciar la búsqueda en segundo plano.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			wp_send_json_error( array( 'message' => __( 'No se pudo iniciar la búsqueda en segundo plano. Revisa loopback/WP-Cron.', 'users-toolkit' ) ) );
		}

		if ( $dispatched ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Búsqueda iniciada en segundo plano...', 'users-toolkit' ), false );
			$message = __( 'Búsqueda iniciada. Monitoreando progreso...', 'users-toolkit' );
		} else {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Búsqueda en cola (WP-Cron)...', 'users-toolkit' ), false );
			$message = __( 'No se pudo iniciar loopback inmediato; búsqueda en cola vía WP-Cron.', 'users-toolkit' );
		}

		wp_send_json_success(
			array(
				'operation_id' => $operation_id,
				'queued'       => true,
				'scheduled'    => $scheduled,
				'dispatched'   => $dispatched,
				'message'      => $message,
			)
		);
	}

	/**
	 * AJAX handler for processing identify worker.
	 */
	public function ajax_process_identify() {
		$operation_id = isset( $_REQUEST['operation_id'] ) ? sanitize_key( wp_unslash( $_REQUEST['operation_id'] ) ) : '';
		$worker_token = isset( $_REQUEST['worker_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['worker_token'] ) ) : '';

		$result = $this->process_identify_operation( $operation_id, $worker_token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Worker de identificación ejecutado.', 'users-toolkit' ) ) );
	}

	/**
	 * Cron fallback callback to process identify operation.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 */
	public function process_identify_event( $operation_id, $worker_token ) {
		$this->process_identify_operation( $operation_id, $worker_token );
	}

	/**
	 * AJAX handler for deleting spam users
	 */
	public function ajax_delete_spam() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$user_ids = isset( $_POST['user_ids'] ) ? array_map( 'absint', $_POST['user_ids'] ) : array();

		if ( empty( $user_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se proporcionaron IDs de usuario', 'users-toolkit' ) ) );
		}

		// Validación estricta de dry_run - solo acepta 'true' explícitamente, cualquier otra cosa es false
		$dry_run_raw = isset( $_POST['dry_run'] ) ? sanitize_text_field( $_POST['dry_run'] ) : 'false';
		$dry_run = ( $dry_run_raw === 'true' || $dry_run_raw === true || $dry_run_raw === '1' || $dry_run_raw === 1 );
		
		// Procesamiento por lotes: máximo 100 usuarios por vez para evitar timeouts
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 100;
		$batch_size = max( 1, min( 100, $batch_size ) ); // Entre 1 y 100
		
		$result = Users_Toolkit_Spam_User_Cleaner::delete_spam_users( $user_ids, $dry_run, $batch_size );

		// Preparar URL del respaldo si existe
		$backup_url = false;
		if ( ! empty( $result['backup_file'] ) ) {
			$upload_dir = wp_upload_dir();
			$backup_url = $upload_dir['baseurl'] . '/users-toolkit/' . $result['backup_file'];
		}

		if ( $dry_run ) {
			$message = sprintf( __( 'Simulación: %d usuarios serían eliminados', 'users-toolkit' ), $result['deleted'] );
			wp_send_json_success(
				array(
					'dry_run'     => true,
					'count'       => $result['deleted'],
					'message'     => $message,
					'backup_file' => $result['backup_file'],
					'backup_url'  => $backup_url,
				)
			);
		} else {
			$message = sprintf( __( '✅ Eliminación completada: %d usuario(s) eliminados', 'users-toolkit' ), $result['deleted'] );
			if ( $result['errors'] > 0 ) {
				$message .= sprintf( __( ', %d error(es)', 'users-toolkit' ), $result['errors'] );
			}
			
			// Si hay usuarios restantes, indicarlo en el mensaje
			if ( isset( $result['remaining'] ) && $result['remaining'] > 0 ) {
				$message .= sprintf( __( '. Pendientes: %d usuario(s)', 'users-toolkit' ), $result['remaining'] );
			}
			
			wp_send_json_success(
				array(
					'dry_run'     => false,
					'deleted'     => $result['deleted'],
					'errors'      => $result['errors'],
					'errors_details' => isset( $result['errors_details'] ) ? $result['errors_details'] : array(),
					'message'     => $message,
					'backup_file' => isset( $result['backup_file'] ) ? basename( $result['backup_file'] ) : false,
					'backup_url'  => $backup_url,
					'total_requested' => isset( $result['total_requested'] ) ? $result['total_requested'] : count( $user_ids ),
					'processed'   => isset( $result['processed'] ) ? $result['processed'] : $result['deleted'],
					'remaining'  => isset( $result['remaining'] ) ? $result['remaining'] : 0,
				)
			);
		}
	}

	/**
	 * AJAX handler for restoring users from backup
	 */
	public function ajax_restore_users() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$backup_file = isset( $_POST['backup_file'] ) ? sanitize_text_field( $_POST['backup_file'] ) : '';

		if ( empty( $backup_file ) ) {
			wp_send_json_error( array( 'message' => __( 'No se proporcionó archivo de backup', 'users-toolkit' ) ) );
		}

		// Construir ruta completa al archivo
		$upload_dir = wp_upload_dir();
		$backup_path = $upload_dir['basedir'] . '/users-toolkit/' . basename( $backup_file );

		$result = Users_Toolkit_Spam_User_Cleaner::restore_users_from_backup( $backup_path );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for exporting users with metadata
	 */
	public function ajax_export_users() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		// Aumentar tiempo para exportaciones grandes
		set_time_limit( 600 );
		ini_set( 'max_execution_time', 600 );

		$user_ids = isset( $_POST['user_ids'] ) ? array_map( 'absint', $_POST['user_ids'] ) : array();

		if ( empty( $user_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No se proporcionaron IDs de usuario', 'users-toolkit' ) ) );
		}

		// Exportar datos de usuarios usando la misma función que el backup
		$export_data = array();
		$total = count( $user_ids );
		$processed = 0;
		
		foreach ( $user_ids as $user_id ) {
			$user_export = Users_Toolkit_Spam_User_Cleaner::export_user_data( $user_id );
			if ( $user_export !== false ) {
				$export_data[] = $user_export;
			}
			$processed++;
			
			// Pequeña pausa cada 100 usuarios para no sobrecargar
			if ( $processed % 100 == 0 ) {
				usleep( 100000 ); // 0.1 segundos
			}
		}

		if ( empty( $export_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No se pudieron exportar datos de usuarios', 'users-toolkit' ) ) );
		}

		// Crear estructura de exportación
		$export_structure = array(
			'export_info' => array(
				'created_at'         => current_time( 'mysql' ),
				'created_timestamp'  => time(),
				'total_users'        => count( $export_data ),
				'wordpress_version'  => get_bloginfo( 'version' ),
				'export_type'       => 'user_export',
			),
			'users' => $export_data,
		);

		// Guardar archivo
		$upload_dir = wp_upload_dir();
		$reports_dir = $upload_dir['basedir'] . '/users-toolkit';
		
		if ( ! file_exists( $reports_dir ) ) {
			wp_mkdir_p( $reports_dir );
		}

		$filename = 'exported-users-' . date( 'Y-m-d-H-i-s' ) . '.json';
		$filepath = $reports_dir . '/' . $filename;

		$json_data = wp_json_encode( $export_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( file_put_contents( $filepath, $json_data ) === false ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo guardar el archivo de exportación', 'users-toolkit' ) ) );
		}

		$file_url = $upload_dir['baseurl'] . '/users-toolkit/' . $filename;
		
		// Crear URL de descarga directa vía AJAX para evitar problemas con servidores que no sirven JSON correctamente
		$download_nonce = wp_create_nonce( 'users_toolkit_download_' . $filename );
		$download_url = add_query_arg( array(
			'action' => 'users_toolkit_download_export',
			'file'   => $filename,
			'nonce'  => $download_nonce,
		), admin_url( 'admin-ajax.php' ) );

		// Verificar que el archivo se creó correctamente
		if ( ! file_exists( $filepath ) ) {
			wp_send_json_error( array( 'message' => __( 'El archivo se generó pero no se pudo verificar su existencia', 'users-toolkit' ) ) );
		}

		wp_send_json_success(
			array(
				'count'         => count( $export_data ),
				'file_name'     => $filename,
				'file_url'      => $file_url,
				'download_url'  => $download_url, // URL de descarga directa vía AJAX
				'download_nonce' => $download_nonce, // Nonce para usar en JavaScript si es necesario
				'file_path'     => $filepath,
				'message'       => sprintf( __( 'Se exportaron %d usuario(s) exitosamente', 'users-toolkit' ), count( $export_data ) ),
			)
		);
	}

	/**
	 * AJAX handler for downloading exported files
	 */
	public function ajax_download_export() {
		// Deshabilitar cualquier compresión ANTES de cualquier output
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
		@ini_set( 'output_handler', '' );
		
		// Limpiar cualquier output previo - hacerlo de forma más agresiva
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		
		$filename = isset( $_GET['file'] ) ? sanitize_file_name( $_GET['file'] ) : '';
		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

		if ( empty( $filename ) || empty( $nonce ) ) {
			http_response_code( 400 );
			die( 'Parámetros inválidos' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			http_response_code( 403 );
			die( 'Permisos insuficientes' );
		}

		// Verificar nonce
		if ( ! wp_verify_nonce( $nonce, 'users_toolkit_download_' . $filename ) ) {
			http_response_code( 403 );
			die( 'Nonce inválido' );
		}

		// Verificar que el archivo sea de exportación
		if ( strpos( $filename, 'exported-users-' ) !== 0 ) {
			http_response_code( 400 );
			die( 'Archivo no válido' );
		}

		$upload_dir = wp_upload_dir();
		$filepath = $upload_dir['basedir'] . '/users-toolkit/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			http_response_code( 404 );
			die( 'Archivo no encontrado: ' . $filepath );
		}

		// Enviar archivo con headers correctos
		header_remove(); // Limpiar headers previos de WordPress
		http_response_code( 200 );
		header( 'Content-Type: application/json; charset=utf-8', true );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"', true );
		header( 'Content-Length: ' . filesize( $filepath ), true );
		header( 'Content-Transfer-Encoding: binary', true );
		header( 'Cache-Control: no-cache, must-revalidate', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: 0', true );
		header( 'X-Content-Type-Options: nosniff', true );

		// Leer y enviar el archivo
		@readfile( $filepath );
		exit;
	}

	/**
	 * AJAX handler for database optimization
	 */
	public function ajax_optimize_db() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : 'all';

		$results = array();

		if ( $action === 'transients' || $action === 'all' ) {
			$results['transients'] = Users_Toolkit_Database_Optimizer::clean_transients();
		}

		if ( $action === 'comments' || $action === 'all' ) {
			$results['comments'] = Users_Toolkit_Database_Optimizer::clean_comments();
		}

		if ( $action === 'optimize' || $action === 'all' ) {
			$results['optimize'] = Users_Toolkit_Database_Optimizer::optimize_tables();
		}

		wp_send_json_success(
			array(
				'results' => $results,
				'message' => __( 'Optimización completada', 'users-toolkit' ),
			)
		);
	}

	/**
	 * AJAX handler for cleaning cron
	 */
	public function ajax_clean_cron() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$result = Users_Toolkit_Database_Optimizer::clean_cron();

		wp_send_json_success(
			array(
				'deleted' => $result['deleted'],
				'message' => sprintf( __( 'Se eliminaron %d eventos cron obsoletos', 'users-toolkit' ), $result['deleted'] ),
			)
		);
	}

	/**
	 * AJAX handler for loading database statistics
	 */
	public function ajax_load_stats() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$stats = Users_Toolkit_Database_Optimizer::get_statistics();
		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX handler for saving auto-load preference
	 */
	public function ajax_save_auto_load_pref() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$auto_load = isset( $_POST['auto_load'] ) && $_POST['auto_load'] === 'true';
		update_user_meta( get_current_user_id(), 'users_toolkit_auto_load_stats', $auto_load ? '1' : '0' );

		wp_send_json_success( array( 'message' => __( 'Preferencia guardada', 'users-toolkit' ) ) );
	}

	/**
	 * AJAX handler for preview/dry run of database optimizations
	 */
	public function ajax_preview_db() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : 'all';

		$results = array();

		if ( $action === 'transients' || $action === 'all' ) {
			$results['transients'] = Users_Toolkit_Database_Optimizer::preview_transients();
		}

		if ( $action === 'comments' || $action === 'all' ) {
			$results['comments'] = Users_Toolkit_Database_Optimizer::preview_comments();
		}

		if ( $action === 'cron' || $action === 'all' ) {
			$results['cron'] = Users_Toolkit_Database_Optimizer::preview_cron();
		}

		if ( $action === 'all' ) {
			$results['autoload'] = Users_Toolkit_Database_Optimizer::get_autoload_stats();
		}

		wp_send_json_success(
			array(
				'message' => __( 'Previsualización completada', 'users-toolkit' ),
				'results' => $results,
			)
		);
	}

	/**
	 * AJAX handler for getting autoload statistics
	 */
	public function ajax_get_autoload_stats() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$stats = Users_Toolkit_Database_Optimizer::get_autoload_stats();
		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX handler for preview disabling autoload
	 */
	public function ajax_preview_disable_autoload() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$threshold_kb = isset( $_POST['threshold_kb'] ) ? absint( $_POST['threshold_kb'] ) : 10;
		$threshold_kb = max( 1, min( 1000, $threshold_kb ) ); // Entre 1KB y 1MB

		$preview = Users_Toolkit_Database_Optimizer::preview_disable_autoload( $threshold_kb );

		wp_send_json_success(
			array(
				'message' => __( 'Previsualización completada', 'users-toolkit' ),
				'preview' => $preview,
			)
		);
	}

	/**
	 * AJAX handler for disabling autoload on options
	 */
	public function ajax_disable_autoload() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$option_names = isset( $_POST['option_names'] ) && is_array( $_POST['option_names'] ) 
			? array_map( 'sanitize_text_field', $_POST['option_names'] )
			: array();

		if ( empty( $option_names ) ) {
			wp_send_json_error( array( 'message' => __( 'No se proporcionaron opciones', 'users-toolkit' ) ) );
		}

		$result = Users_Toolkit_Database_Optimizer::disable_autoload_for_options( $option_names );

		wp_send_json_success(
			array(
				'message' => sprintf( __( 'Autoload desactivado para %d opciones. Errores: %d', 'users-toolkit' ), $result['updated'], $result['errors'] ),
				'updated' => $result['updated'],
				'errors'  => $result['errors'],
			)
		);
	}

	/**
	 * Build backup operation ID.
	 *
	 * @return string
	 */
	private function build_backup_operation_id() {
		return 'backup_' . time() . '_' . wp_generate_password( 8, false );
	}

	/**
	 * Validate backup operation ID format.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return bool
	 */
	private function is_valid_backup_operation_id( $operation_id ) {
		return ! empty( $operation_id ) && strpos( $operation_id, 'backup_' ) === 0;
	}

	/**
	 * Build transient key for backup worker token.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private function get_backup_worker_token_key( $operation_id ) {
		return 'users_toolkit_backup_worker_' . $operation_id;
	}

	/**
	 * Build transient key for backup worker lock.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private function get_backup_worker_lock_key( $operation_id ) {
		return 'users_toolkit_backup_lock_' . $operation_id;
	}

	/**
	 * Trigger async backup worker via loopback request.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @return bool
	 */
	private function dispatch_backup_worker_request( $operation_id, $worker_token ) {
		$url = admin_url( 'admin-ajax.php' );
		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => 1,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action'       => 'users_toolkit_process_backup',
					'operation_id' => $operation_id,
					'worker_token' => $worker_token,
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Schedule async backup worker using WP-Cron as fallback.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @param int    $delay        Delay in seconds.
	 * @return bool
	 */
	private function schedule_backup_worker_event( $operation_id, $worker_token, $delay = 5 ) {
		$delay = max( 1, (int) $delay );
		$args  = array( $operation_id, $worker_token );

		if ( wp_next_scheduled( 'users_toolkit_process_backup_event', $args ) ) {
			return true;
		}

		return (bool) wp_schedule_single_event( time() + $delay, 'users_toolkit_process_backup_event', $args );
	}

	/**
	 * Run backup process for worker endpoints.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 * @return true|WP_Error
	 */
	private function process_backup_operation( $operation_id, $worker_token ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$worker_token = sanitize_text_field( (string) $worker_token );

		if ( ! $this->is_valid_backup_operation_id( $operation_id ) || empty( $worker_token ) ) {
			return new WP_Error( 'invalid_request', __( 'Solicitud de backup inválida', 'users-toolkit' ) );
		}

		$token_key    = $this->get_backup_worker_token_key( $operation_id );
		$stored_token = get_transient( $token_key );

		if ( ! is_string( $stored_token ) || ! hash_equals( $stored_token, $worker_token ) ) {
			return new WP_Error( 'invalid_token', __( 'Token de backup inválido o expirado', 'users-toolkit' ) );
		}

		$lock_key = $this->get_backup_worker_lock_key( $operation_id );
		if ( get_transient( $lock_key ) ) {
			return true;
		}
		set_transient( $lock_key, 1, 1800 );
		$this->register_worker_shutdown_handler( $operation_id, $lock_key, $token_key, null, 'backup' );

		Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 5, __( 'Procesando backup en segundo plano...', 'users-toolkit' ), false );

		try {
			$result = Users_Toolkit_Database_Backup::create_backup( false, $operation_id );
		} catch ( Exception $e ) {
			$result = new WP_Error( 'backup_exception', $e->getMessage() );
		} catch ( Error $e ) {
			$result = new WP_Error( 'backup_error', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'Error al crear backup:', 'users-toolkit' ) . ' ' . $result->get_error_message(),
				true,
				array(
					'error'   => true,
					'message' => $result->get_error_message(),
				)
			);
			delete_transient( $lock_key );
			delete_transient( $token_key );
			return $result;
		}

		Users_Toolkit_Progress_Tracker::set_progress(
			$operation_id,
			100,
			sprintf( __( 'Backup completado: %s (%s)', 'users-toolkit' ), $result['filename'], $result['size_human'] ),
			true,
			$result
		);

		delete_transient( $lock_key );
		delete_transient( $token_key );

		return true;
	}

	/**
	 * AJAX handler for creating backup
	 */
	public function ajax_create_backup() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		// Permitir operation_id del cliente para que pueda iniciar polling inmediatamente.
		$operation_id = isset( $_POST['operation_id'] ) ? sanitize_key( wp_unslash( $_POST['operation_id'] ) ) : '';
		if ( ! $this->is_valid_backup_operation_id( $operation_id ) ) {
			$operation_id = $this->build_backup_operation_id();
		}

		$worker_token = wp_generate_password( 32, false, false );
		set_transient( $this->get_backup_worker_token_key( $operation_id ), $worker_token, 1800 );
		Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 1, __( 'Iniciando backup...', 'users-toolkit' ), false );

		// Programar fallback por cron y lanzar loopback para ejecución inmediata.
		$scheduled  = $this->schedule_backup_worker_event( $operation_id, $worker_token, 10 );
		$dispatched = $this->dispatch_backup_worker_request( $operation_id, $worker_token );
		delete_transient( 'users_toolkit_backup_retry_count_' . $operation_id );

		if ( ! $scheduled && ! $dispatched ) {
			delete_transient( $this->get_backup_worker_token_key( $operation_id ) );
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se pudo iniciar el proceso de backup en segundo plano.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			wp_send_json_error( array( 'message' => __( 'No se pudo iniciar el backup en segundo plano. Revisa configuración de loopback/WP-Cron.', 'users-toolkit' ) ) );
		}

		if ( $dispatched ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Backup iniciado en segundo plano...', 'users-toolkit' ), false );
			$message = __( 'Backup iniciado en segundo plano. Monitoreando progreso...', 'users-toolkit' );
		} else {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Backup en cola (WP-Cron)...', 'users-toolkit' ), false );
			$message = __( 'No se pudo lanzar loopback inmediato; backup en cola vía WP-Cron.', 'users-toolkit' );
		}

		wp_send_json_success(
			array(
				'operation_id' => $operation_id,
				'queued'       => true,
				'scheduled'    => $scheduled,
				'dispatched'   => $dispatched,
				'message'      => $message,
			)
		);
	}

	/**
	 * AJAX handler for processing backup in background worker.
	 */
	public function ajax_process_backup() {
		$operation_id = isset( $_REQUEST['operation_id'] ) ? sanitize_key( wp_unslash( $_REQUEST['operation_id'] ) ) : '';
		$worker_token = isset( $_REQUEST['worker_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['worker_token'] ) ) : '';

		$result = $this->process_backup_operation( $operation_id, $worker_token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Backup worker ejecutado.', 'users-toolkit' ) ) );
	}

	/**
	 * Cron fallback callback to process backup.
	 *
	 * @param string $operation_id Operation identifier.
	 * @param string $worker_token One-time worker token.
	 */
	public function process_backup_event( $operation_id, $worker_token ) {
		$this->process_backup_operation( $operation_id, $worker_token );
	}

	/**
	 * AJAX handler for deleting backup
	 */
	public function ajax_delete_backup() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

		if ( empty( $filename ) ) {
			wp_send_json_error( array( 'message' => __( 'No se proporcionó nombre de archivo', 'users-toolkit' ) ) );
		}

		$result = Users_Toolkit_Database_Backup::delete_backup( $filename );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Backup eliminado exitosamente', 'users-toolkit' ),
			)
		);
	}

	/**
	 * Handle backup download
	 */
	public function handle_backup_download() {
		if ( ! isset( $_GET['users_toolkit_download_backup'] ) || ! isset( $_GET['filename'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permisos insuficientes', 'users-toolkit' ) );
		}

		check_admin_referer( 'users_toolkit_download_backup', 'users_toolkit_backup_nonce' );

		$filename = sanitize_file_name( $_GET['filename'] );
		Users_Toolkit_Database_Backup::download_backup( $filename );
	}

	/**
	 * Try to recover a pending identify operation stuck at queue/start stage.
	 *
	 * @param string     $operation_id Operation identifier.
	 * @param array|bool $progress     Current progress.
	 * @return array|bool
	 */
	private function maybe_recover_pending_identify_progress( $operation_id, $progress ) {
		if ( ! $this->is_valid_identify_operation_id( $operation_id ) ) {
			return $progress;
		}

		$token = get_transient( $this->get_identify_worker_token_key( $operation_id ) );
		$payload = get_transient( $this->get_identify_worker_payload_key( $operation_id ) );
		$lock = get_transient( $this->get_identify_worker_lock_key( $operation_id ) );
		$pending = ( ( is_string( $token ) && '' !== $token && false !== $payload ) || $lock );

		if ( ! $pending ) {
			return $progress;
		}

		if ( ! is_array( $progress ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Búsqueda en cola o iniciando en segundo plano...', 'users-toolkit' ), false );
			$progress = Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		if ( ! is_array( $progress ) || ! empty( $progress['completed'] ) ) {
			return $progress;
		}

		$current = isset( $progress['current'] ) ? (int) $progress['current'] : 0;
		if ( $current > 2 ) {
			return $progress;
		}

		$now = time();
		$last_update = isset( $progress['timestamp'] ) ? (int) $progress['timestamp'] : 0;
		$age = $last_update > 0 ? ( $now - $last_update ) : 0;
		$retry_key = 'users_toolkit_identify_retry_' . $operation_id;
		$retry_count_key = 'users_toolkit_identify_retry_count_' . $operation_id;
		$last_retry = (int) get_transient( $retry_key );

		// No reintentar demasiado pronto.
		if ( $last_retry > 0 && ( $now - $last_retry ) < 45 ) {
			if ( $age > 1200 ) {
				Users_Toolkit_Progress_Tracker::set_progress(
					$operation_id,
					100,
					__( 'No se pudo iniciar la búsqueda en segundo plano. Revisa loopback y WP-Cron del servidor.', 'users-toolkit' ),
					true,
					array( 'error' => true )
				);
				return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
			}
			return $progress;
		}

		set_transient( $retry_key, $now, 900 );
		$retry_count = (int) get_transient( $retry_count_key );
		$retry_count++;
		set_transient( $retry_count_key, $retry_count, 7200 );

		if ( $retry_count > 20 ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'La búsqueda falló repetidamente al iniciar en segundo plano. Revisa errores fatales de plugins y WP-Cron.', 'users-toolkit' ),
				true,
				array( 'error' => true, 'retry_count' => $retry_count )
			);
			delete_transient( $this->get_identify_worker_lock_key( $operation_id ) );
			delete_transient( $this->get_identify_worker_token_key( $operation_id ) );
			delete_transient( $this->get_identify_worker_payload_key( $operation_id ) );
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		$dispatched = false;
		$scheduled = false;
		if ( is_string( $token ) && '' !== $token && ! $lock ) {
			$dispatched = $this->dispatch_identify_worker_request( $operation_id, $token );
			$scheduled = $this->schedule_identify_worker_event( $operation_id, $token, 5 );
		}

		if ( $dispatched || $scheduled ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				3,
				__( 'Reintentando iniciar búsqueda en segundo plano...', 'users-toolkit' ),
				false,
				array( 'retrying' => true )
			);
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		if ( $age > 1200 ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se pudo iniciar la búsqueda en segundo plano. Revisa loopback y WP-Cron del servidor.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		return $progress;
	}

	/**
	 * Try to recover a pending backup operation stuck at queue/start stage.
	 *
	 * @param string     $operation_id Operation identifier.
	 * @param array|bool $progress     Current progress.
	 * @return array|bool
	 */
	private function maybe_recover_pending_backup_progress( $operation_id, $progress ) {
		if ( ! $this->is_valid_backup_operation_id( $operation_id ) ) {
			return $progress;
		}

		$token = get_transient( $this->get_backup_worker_token_key( $operation_id ) );
		$lock = get_transient( $this->get_backup_worker_lock_key( $operation_id ) );
		$pending = ( ( is_string( $token ) && '' !== $token ) || $lock );

		if ( ! $pending ) {
			return $progress;
		}

		if ( ! is_array( $progress ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 2, __( 'Backup en cola o iniciando en segundo plano...', 'users-toolkit' ), false );
			$progress = Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		if ( ! is_array( $progress ) || ! empty( $progress['completed'] ) ) {
			return $progress;
		}

		$current = isset( $progress['current'] ) ? (int) $progress['current'] : 0;
		if ( $current > 2 ) {
			return $progress;
		}

		$now = time();
		$last_update = isset( $progress['timestamp'] ) ? (int) $progress['timestamp'] : 0;
		$age = $last_update > 0 ? ( $now - $last_update ) : 0;
		$retry_key = 'users_toolkit_backup_retry_' . $operation_id;
		$retry_count_key = 'users_toolkit_backup_retry_count_' . $operation_id;
		$last_retry = (int) get_transient( $retry_key );

		if ( $last_retry > 0 && ( $now - $last_retry ) < 45 ) {
			if ( $age > 1200 ) {
				Users_Toolkit_Progress_Tracker::set_progress(
					$operation_id,
					100,
					__( 'No se pudo iniciar el backup en segundo plano. Revisa loopback y WP-Cron del servidor.', 'users-toolkit' ),
					true,
					array( 'error' => true )
				);
				return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
			}
			return $progress;
		}

		set_transient( $retry_key, $now, 900 );
		$retry_count = (int) get_transient( $retry_count_key );
		$retry_count++;
		set_transient( $retry_count_key, $retry_count, 7200 );

		if ( $retry_count > 20 ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'El backup falló repetidamente al iniciar en segundo plano. Revisa errores fatales de plugins y WP-Cron.', 'users-toolkit' ),
				true,
				array( 'error' => true, 'retry_count' => $retry_count )
			);
			delete_transient( $this->get_backup_worker_lock_key( $operation_id ) );
			delete_transient( $this->get_backup_worker_token_key( $operation_id ) );
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		$dispatched = false;
		$scheduled = false;
		if ( is_string( $token ) && '' !== $token && ! $lock ) {
			$dispatched = $this->dispatch_backup_worker_request( $operation_id, $token );
			$scheduled = $this->schedule_backup_worker_event( $operation_id, $token, 5 );
		}

		if ( $dispatched || $scheduled ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				3,
				__( 'Reintentando iniciar backup en segundo plano...', 'users-toolkit' ),
				false,
				array( 'retrying' => true )
			);
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		if ( $age > 1200 ) {
			Users_Toolkit_Progress_Tracker::set_progress(
				$operation_id,
				100,
				__( 'No se pudo iniciar el backup en segundo plano. Revisa loopback y WP-Cron del servidor.', 'users-toolkit' ),
				true,
				array( 'error' => true )
			);
			return Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		}

		return $progress;
	}

	/**
	 * AJAX handler for getting progress
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'users_toolkit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes', 'users-toolkit' ) ) );
		}

		$operation_id = isset( $_POST['operation_id'] ) ? sanitize_key( wp_unslash( $_POST['operation_id'] ) ) : '';

		if ( empty( $operation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'ID de operación no proporcionado', 'users-toolkit' ) ) );
		}

		$progress = Users_Toolkit_Progress_Tracker::get_progress( $operation_id );
		$progress = $this->maybe_recover_pending_identify_progress( $operation_id, $progress );
		$progress = $this->maybe_recover_pending_backup_progress( $operation_id, $progress );

		if ( $progress === false ) {
			$is_identify_pending = (
				strpos( $operation_id, 'spam_identify_' ) === 0 &&
				(
					get_transient( 'users_toolkit_identify_worker_' . $operation_id ) ||
					get_transient( 'users_toolkit_identify_payload_' . $operation_id ) ||
					get_transient( 'users_toolkit_identify_lock_' . $operation_id )
				)
			);
			$is_backup_pending = (
				strpos( $operation_id, 'backup_' ) === 0 &&
				(
					get_transient( 'users_toolkit_backup_worker_' . $operation_id ) ||
					get_transient( 'users_toolkit_backup_lock_' . $operation_id )
				)
			);

			if ( $is_identify_pending || $is_backup_pending ) {
				wp_send_json_success(
					array(
						'current'   => 2,
						'message'   => __( 'Operación en cola o iniciando en segundo plano...', 'users-toolkit' ),
						'completed' => false,
						'timestamp' => time(),
						'data'      => array(),
					)
				);
			}

			wp_send_json_error( array( 'message' => __( 'Progreso no encontrado', 'users-toolkit' ) ) );
		}

		wp_send_json_success( $progress );
	}
}
