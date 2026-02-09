<?php

/**
 * Class to optimize database
 */
class Users_Toolkit_Database_Optimizer {

	/**
	 * Clean expired transients
	 *
	 * @return array Results
	 */
	public static function clean_transients() {
		global $wpdb;

		// Count expired transients first (one logical transient per timeout row).
		$deleted_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_%'
			AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		$wpdb->query(
			"DELETE timeout_row, value_row
			FROM {$wpdb->options} AS timeout_row
			LEFT JOIN {$wpdb->options} AS value_row
			ON value_row.option_name = CONCAT('_transient_', SUBSTRING(timeout_row.option_name, 20))
			WHERE timeout_row.option_name LIKE '_transient_timeout_%'
			AND CAST(timeout_row.option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		$deleted_site_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->options}
			WHERE option_name LIKE '_site_transient_timeout_%'
			AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		$wpdb->query(
			"DELETE timeout_row, value_row
			FROM {$wpdb->options} AS timeout_row
			LEFT JOIN {$wpdb->options} AS value_row
			ON value_row.option_name = CONCAT('_site_transient_', SUBSTRING(timeout_row.option_name, 25))
			WHERE timeout_row.option_name LIKE '_site_transient_timeout_%'
			AND CAST(timeout_row.option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		return array(
			'transients'      => $deleted_transients,
			'site_transients' => $deleted_site_transients,
			'total'           => $deleted_transients + $deleted_site_transients,
		);
	}

	/**
	 * Clean spam and trash comments
	 *
	 * @return array Results
	 */
	public static function clean_comments() {
		$deleted_spam = self::delete_comments_by_status( 'spam' );
		$deleted_trash = self::delete_comments_by_status( 'trash' );

		return array(
			'spam'  => $deleted_spam,
			'trash' => $deleted_trash,
			'total' => $deleted_spam + $deleted_trash,
		);
	}

	/**
	 * Clean old cron events
	 *
	 * @return array Results
	 */
	public static function clean_cron() {
		$cron = self::get_cron_array();

		if ( ! $cron || ! is_array( $cron ) ) {
			return array(
				'deleted' => 0,
			);
		}

		$cleaned = 0;
		$has_changes = false;

		foreach ( $cron as $timestamp => $cronhooks ) {
			if ( ! is_numeric( $timestamp ) || (int) $timestamp <= 0 || ! is_array( $cronhooks ) ) {
				unset( $cron[ $timestamp ] );
				$cleaned++;
				$has_changes = true;
				continue;
			}

			foreach ( $cronhooks as $hook => $events ) {
				if ( ! is_array( $events ) ) {
					unset( $cron[ $timestamp ][ $hook ] );
					$cleaned++;
					$has_changes = true;
					continue;
				}

				foreach ( $events as $signature => $event ) {
					$is_invalid = ! is_array( $event );
					$is_invalid = $is_invalid || ( isset( $event['args'] ) && ! is_array( $event['args'] ) );
					$is_invalid = $is_invalid || ( isset( $event['schedule'] ) && ! is_string( $event['schedule'] ) );
					$is_invalid = $is_invalid || ( isset( $event['interval'] ) && ! is_numeric( $event['interval'] ) );

					if ( $is_invalid ) {
						unset( $cron[ $timestamp ][ $hook ][ $signature ] );
						$cleaned++;
						$has_changes = true;
					}
				}

				if ( empty( $cron[ $timestamp ][ $hook ] ) ) {
					unset( $cron[ $timestamp ][ $hook ] );
				}
			}

			if ( empty( $cron[ $timestamp ] ) ) {
				unset( $cron[ $timestamp ] );
			}
		}

		if ( $has_changes ) {
			self::set_cron_array( $cron );
		}

		return array(
			'deleted' => $cleaned,
		);
	}

	/**
	 * Optimize database tables
	 *
	 * @return array Results
	 */
	public static function optimize_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->users,
			$wpdb->usermeta,
			$wpdb->comments,
			$wpdb->commentmeta,
			$wpdb->options,
		);

		$optimized = array();
		foreach ( $tables as $table ) {
			$result = $wpdb->query( "OPTIMIZE TABLE {$table}" );
			if ( $result !== false ) {
				$optimized[] = $table;
			}
		}

		return array(
			'optimized' => count( $optimized ),
			'tables'    => $optimized,
		);
	}

	/**
	 * Get database statistics
	 *
	 * @return array Statistics
	 */
	public static function get_statistics() {
		global $wpdb;

		$stats = array(
			'total_users'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
			'total_posts'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
			'total_comments'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" ),
			'spam_comments'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trash_comments'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'transients_count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ),
			'options_count'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" ),
		);

		return $stats;
	}

	/**
	 * Preview transients that would be deleted (dry run)
	 *
	 * @return array Preview results
	 */
	public static function preview_transients() {
		global $wpdb;

		// Count expired transients
		$expired_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_timeout_%' 
			AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		$orphaned_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->options} AS timeout_row
			LEFT JOIN {$wpdb->options} AS value_row
			ON value_row.option_name = CONCAT('_transient_', SUBSTRING(timeout_row.option_name, 20))
			WHERE timeout_row.option_name LIKE '_transient_timeout_%'
			AND CAST(timeout_row.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
			AND value_row.option_id IS NULL"
		);

		// Count expired site transients
		$expired_site_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_site_transient_timeout_%' 
			AND CAST(option_value AS UNSIGNED) < UNIX_TIMESTAMP()"
		);

		$orphaned_site_transients = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->options} AS timeout_row
			LEFT JOIN {$wpdb->options} AS value_row
			ON value_row.option_name = CONCAT('_site_transient_', SUBSTRING(timeout_row.option_name, 25))
			WHERE timeout_row.option_name LIKE '_site_transient_timeout_%'
			AND CAST(timeout_row.option_value AS UNSIGNED) < UNIX_TIMESTAMP()
			AND value_row.option_id IS NULL"
		);

		$total = $expired_transients + $expired_site_transients;

		return array(
			'expired_transients'     => $expired_transients,
			'orphaned_transients'    => $orphaned_transients,
			'expired_site_transients' => $expired_site_transients,
			'orphaned_site_transients' => $orphaned_site_transients,
			'total'                  => $total,
			'transients_count'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ),
		);
	}

	/**
	 * Preview comments that would be deleted (dry run)
	 *
	 * @return array Preview results
	 */
	public static function preview_comments() {
		global $wpdb;

		$spam = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$trash = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

		return array(
			'spam'  => $spam,
			'trash' => $trash,
			'total' => $spam + $trash,
		);
	}

	/**
	 * Preview cron events that would be deleted (dry run)
	 *
	 * @return array Preview results
	 */
	public static function preview_cron() {
		$cron = self::get_cron_array();

		if ( ! $cron || ! is_array( $cron ) ) {
			return array(
				'deleted' => 0,
				'total'   => 0,
			);
		}

		$cleaned = 0;
		$obsolete_events = array();

		foreach ( $cron as $timestamp => $cronhooks ) {
			if ( ! is_numeric( $timestamp ) || (int) $timestamp <= 0 || ! is_array( $cronhooks ) ) {
				$cleaned++;
				if ( count( $obsolete_events ) < 10 ) {
					$obsolete_events[] = array(
						'timestamp' => $timestamp,
						'date'      => is_numeric( $timestamp ) ? date( 'Y-m-d H:i:s', (int) $timestamp ) : __( 'inválido', 'users-toolkit' ),
						'hooks'     => is_array( $cronhooks ) ? array_keys( $cronhooks ) : array(),
					);
				}
				continue;
			}

			foreach ( $cronhooks as $hook => $events ) {
				if ( ! is_array( $events ) ) {
					$cleaned++;
					if ( count( $obsolete_events ) < 10 ) {
						$obsolete_events[] = array(
							'timestamp' => $timestamp,
							'date'      => date( 'Y-m-d H:i:s', (int) $timestamp ),
							'hooks'     => array( (string) $hook ),
						);
					}
					continue;
				}

				foreach ( $events as $event ) {
					$is_invalid = ! is_array( $event );
					$is_invalid = $is_invalid || ( isset( $event['args'] ) && ! is_array( $event['args'] ) );
					$is_invalid = $is_invalid || ( isset( $event['schedule'] ) && ! is_string( $event['schedule'] ) );
					$is_invalid = $is_invalid || ( isset( $event['interval'] ) && ! is_numeric( $event['interval'] ) );

					if ( $is_invalid ) {
						$cleaned++;
						if ( count( $obsolete_events ) < 10 ) {
							$obsolete_events[] = array(
								'timestamp' => $timestamp,
								'date'      => date( 'Y-m-d H:i:s', (int) $timestamp ),
								'hooks'     => array( (string) $hook ),
							);
						}
					}
				}
			}
		}

		return array(
			'deleted'        => $cleaned,
			'total'          => count( $cron ),
			'obsolete_events' => $obsolete_events,
		);
	}

	/**
	 * Delete comments by status using WordPress API to keep metadata and counters consistent.
	 *
	 * @param string $status Comment status.
	 * @return int
	 */
	private static function delete_comments_by_status( $status ) {
		$deleted = 0;
		$batch_size = 200;

		do {
			$ids = get_comments(
				array(
					'status'        => $status,
					'fields'        => 'ids',
					'number'        => $batch_size,
					'orderby'       => 'comment_ID',
					'order'         => 'ASC',
					'no_found_rows' => true,
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			$deleted_in_batch = 0;
			foreach ( $ids as $comment_id ) {
				if ( wp_delete_comment( (int) $comment_id, true ) ) {
					$deleted++;
					$deleted_in_batch++;
				}
			}

			if ( 0 === $deleted_in_batch ) {
				break;
			}
		} while ( count( $ids ) === $batch_size );

		return $deleted;
	}

	/**
	 * Get cron array without the internal version key.
	 *
	 * @return array
	 */
	private static function get_cron_array() {
		if ( function_exists( '_get_cron_array' ) ) {
			$cron = _get_cron_array();
			return is_array( $cron ) ? $cron : array();
		}

		$cron = get_option( 'cron' );
		if ( ! is_array( $cron ) ) {
			return array();
		}

		unset( $cron['version'] );
		return $cron;
	}

	/**
	 * Save cron array in a way compatible with WordPress internals.
	 *
	 * @param array $cron Cron array.
	 * @return bool
	 */
	private static function set_cron_array( $cron ) {
		if ( function_exists( '_set_cron_array' ) ) {
			$result = _set_cron_array( $cron );
			return ! is_wp_error( $result );
		}

		$cron['version'] = 2;
		return (bool) update_option( 'cron', $cron );
	}

	/**
	 * Preview all optimizations (dry run)
	 *
	 * @return array Preview results
	 */
	public static function preview_all() {
		return array(
			'transients' => self::preview_transients(),
			'comments'   => self::preview_comments(),
			'cron'       => self::preview_cron(),
			'autoload'   => self::get_autoload_stats(),
		);
	}

	/**
	 * Get autoloaded options statistics
	 *
	 * @return array Statistics
	 */
	public static function get_autoload_stats() {
		global $wpdb;

		$stats = array(
			'total_autoloaded'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'" ),
			'total_size_bytes'  => (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'" ),
			'total_size_mb'     => 0,
			'large_options'     => array(),
		);

		$stats['total_size_mb'] = round( $stats['total_size_bytes'] / 1024 / 1024, 2 );

		// Get top 20 largest autoloaded options
		$large_options = $wpdb->get_results(
			"SELECT option_name, 
				LENGTH(option_value) as size_bytes,
				ROUND(LENGTH(option_value)/1024, 2) as size_kb,
				SUBSTRING(option_value, 1, 100) as preview
			FROM {$wpdb->options} 
			WHERE autoload = 'yes' 
			ORDER BY size_bytes DESC 
			LIMIT 20",
			ARRAY_A
		);

		$stats['large_options'] = $large_options ? $large_options : array();

		return $stats;
	}

	/**
	 * Disable autoload for specific options
	 *
	 * @param array $option_names Array of option names to disable autoload
	 * @return array Results
	 */
	public static function disable_autoload_for_options( $option_names ) {
		global $wpdb;

		if ( empty( $option_names ) || ! is_array( $option_names ) ) {
			return array(
				'updated' => 0,
				'errors'  => 0,
			);
		}

		$updated = 0;
		$errors = 0;

		// Lista de opciones críticas que NO deben desactivarse
		$critical_options = array(
			'active_plugins',
			'blog_charset',
			'blogdescription',
			'blogname',
			'current_theme',
			'db_version',
			'home',
			'initial_db_version',
			'mailserver_login',
			'mailserver_pass',
			'mailserver_port',
			'mailserver_url',
			'siteurl',
			'start_of_week',
			'stylesheet',
			'template',
			'timezone_string',
			'upload_path',
			'uploads_use_yearmonth_folders',
			'users_can_register',
			'WPLANG',
		);

		foreach ( $option_names as $option_name ) {
			// Verificar que no sea una opción crítica
			if ( in_array( $option_name, $critical_options, true ) ) {
				$errors++;
				continue;
			}

			// Verificar que la opción exista y esté autoloaded
			$option = $wpdb->get_var( $wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s AND autoload = 'yes'",
				$option_name
			) );

			if ( $option ) {
				$result = $wpdb->update(
					$wpdb->options,
					array( 'autoload' => 'no' ),
					array( 'option_name' => $option_name ),
					array( '%s' ),
					array( '%s' )
				);

				if ( $result !== false ) {
					$updated++;
				} else {
					$errors++;
				}
			}
		}

		return array(
			'updated' => $updated,
			'errors'  => $errors,
		);
	}

	/**
	 * Preview which options could have autoload disabled (dry run)
	 *
	 * @param int $threshold_kb Minimum size in KB to suggest disabling autoload
	 * @return array Preview results
	 */
	public static function preview_disable_autoload( $threshold_kb = 1 ) {
		global $wpdb;

		// Opciones críticas que nunca deben desactivarse
		$critical_options = array(
			'active_plugins',
			'blog_charset',
			'blogdescription',
			'blogname',
			'current_theme',
			'db_version',
			'home',
			'initial_db_version',
			'mailserver_login',
			'mailserver_pass',
			'mailserver_port',
			'mailserver_url',
			'siteurl',
			'start_of_week',
			'stylesheet',
			'template',
			'timezone_string',
			'upload_path',
			'uploads_use_yearmonth_folders',
			'users_can_register',
			'WPLANG',
		);

		$threshold_bytes = $threshold_kb * 1024;

		// Escapar opciones críticas para la consulta
		$critical_escaped = array_map( function( $option ) use ( $wpdb ) {
			return $wpdb->_escape( $option );
		}, $critical_options );

		$critical_list = "'" . implode( "','", $critical_escaped ) . "'";

		// Obtener opciones autoloaded grandes (excluyendo críticas)
		$query = $wpdb->prepare(
			"SELECT option_name, 
				LENGTH(option_value) as size_bytes,
				ROUND(LENGTH(option_value)/1024, 2) as size_kb,
				ROUND(LENGTH(option_value)/1024/1024, 2) as size_mb,
				SUBSTRING(option_value, 1, 150) as preview
			FROM {$wpdb->options} 
			WHERE autoload = 'yes' 
			AND LENGTH(option_value) >= %d
			AND option_name NOT IN ($critical_list)
			ORDER BY size_bytes DESC 
			LIMIT 50",
			$threshold_bytes
		);

		$suggested_options = $wpdb->get_results( $query, ARRAY_A );

		$total_size_bytes = 0;
		if ( $suggested_options ) {
			foreach ( $suggested_options as $option ) {
				$total_size_bytes += $option['size_bytes'];
			}
		}

		return array(
			'suggested_count'     => $suggested_options ? count( $suggested_options ) : 0,
			'total_size_bytes'    => $total_size_bytes,
			'total_size_mb'       => round( $total_size_bytes / 1024 / 1024, 2 ),
			'suggested_options'   => $suggested_options ? $suggested_options : array(),
			'threshold_kb'        => $threshold_kb,
		);
	}
}
