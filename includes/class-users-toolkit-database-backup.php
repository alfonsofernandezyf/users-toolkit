<?php

/**
 * Class to handle database backups
 */
class Users_Toolkit_Database_Backup {

	/**
	 * Create a database backup
	 *
	 * @param bool $download If true, download the file. If false, save to uploads directory
	 * @return array|WP_Error Result array with file info or WP_Error on failure
	 */
	public static function create_backup( $download = false, $operation_id = '' ) {
		global $wpdb;

		// Aumentar tiempo y memoria para operaciones largas
		set_time_limit( 1800 );
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', '256M' );
			@ini_set( 'max_execution_time', 1800 );
		}

		// Get database credentials
		$db_name = DB_NAME;
		$db_user = DB_USER;
		$db_pass = DB_PASSWORD;
		$db_host = DB_HOST;
		$db_charset = defined( 'DB_CHARSET' ) ? DB_CHARSET : 'utf8mb4';

		/*
		 * En operaciones con progreso (UI/admin AJAX), priorizamos el método PHP para
		 * evitar bloqueos de mysqldump que suelen dejar la barra congelada al 50%.
		 */
		$prefer_php_method = ! empty( $operation_id );

		// Permite reactivar mysqldump en UI si se requiere vía hook.
		$force_mysqldump_for_ui = (bool) apply_filters( 'users_toolkit_force_mysqldump_for_ui', false );

		$mysqldump_path = false;
		if ( ! $prefer_php_method || $force_mysqldump_for_ui ) {
			// Solo usar mysqldump si exec() está permitida (deshabilitada en muchos hostings).
			$mysqldump_path = function_exists( 'exec' ) ? self::find_mysqldump() : false;
		}

		if ( $mysqldump_path && ! $download ) {
			if ( ! empty( $operation_id ) ) {
				Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 50, __( 'Usando mysqldump para backup...', 'users-toolkit' ), false );
			}
			$result = self::create_backup_mysqldump( $mysqldump_path, $db_name, $db_user, $db_pass, $db_host, $db_charset );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			// Si mysqldump falla (conexión/credenciales/socket), usar fallback PHP.
			if ( ! empty( $operation_id ) ) {
				Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 55, __( 'mysqldump falló, usando método PHP...', 'users-toolkit' ), false );
			}
		}

		if ( ! empty( $operation_id ) && ! $mysqldump_path ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 3, __( 'Usando método PHP para backup...', 'users-toolkit' ), false );
		}

		// Fallback a exportación PHP (funciona sin exec/shell)
		return self::create_backup_php( $db_name, $db_charset, $download, $operation_id );
	}

	/**
	 * Find mysqldump executable
	 *
	 * @return string|false Path to mysqldump or false if not found
	 */
	private static function find_mysqldump() {
		$paths = array(
			'/usr/bin/mysqldump',
			'/usr/local/bin/mysqldump',
			'/opt/local/bin/mysqldump',
			'mysqldump', // Try in PATH
		);

		foreach ( $paths as $path ) {
			if ( @is_executable( $path ) ) {
				return $path;
			}
		}

		// Try which command (solo si shell_exec está permitida)
		if ( function_exists( 'shell_exec' ) ) {
			$output = @shell_exec( 'which mysqldump 2>/dev/null' );
			if ( ! empty( $output ) ) {
				return trim( $output );
			}
		}

		return false;
	}

	/**
	 * Create backup using mysqldump
	 *
	 * @param string $mysqldump_path Path to mysqldump
	 * @param string $db_name Database name
	 * @param string $db_user Database user
	 * @param string $db_pass Database password
	 * @param string $db_host Database host
	 * @param string $db_charset Database charset
	 * @return array|WP_Error
	 */
	private static function create_backup_mysqldump( $mysqldump_path, $db_name, $db_user, $db_pass, $db_host, $db_charset ) {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/users-toolkit/backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			file_put_contents( $backup_dir . '/.htaccess', 'deny from all' );
			file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
		}

		$filename = 'backup-' . date( 'Y-m-d-His' ) . '.sql';
		$filepath = $backup_dir . '/' . $filename;

		$connection = self::parse_db_host( $db_host );

		// Build mysqldump command
		$command = escapeshellarg( $mysqldump_path );
		if ( ! empty( $connection['socket'] ) ) {
			$command .= ' --socket=' . escapeshellarg( $connection['socket'] );
		} else {
			$command .= ' --host=' . escapeshellarg( $connection['host'] );
			$command .= ' --port=' . escapeshellarg( (string) $connection['port'] );
		}
		$command .= ' --user=' . escapeshellarg( $db_user );
		$command .= ' --password=' . escapeshellarg( $db_pass );
		$command .= ' --single-transaction';
		$command .= ' --quick';
		$command .= ' --lock-tables=false';
		$command .= ' --default-character-set=' . escapeshellarg( $db_charset );
		$command .= ' ' . escapeshellarg( $db_name );
		$command .= ' > ' . escapeshellarg( $filepath );

		// Execute command
		exec( $command . ' 2>&1', $output, $return_var );

		if ( $return_var !== 0 || ! file_exists( $filepath ) || filesize( $filepath ) === 0 ) {
			$error_message = 'Error al crear backup con mysqldump: ' . implode( "\n", $output );
			return new WP_Error( 'backup_failed', $error_message );
		}

		// Compress the file
		if ( function_exists( 'gzencode' ) ) {
			$compressed_filepath = $filepath . '.gz';
			$file_content = file_get_contents( $filepath );
			$compressed = gzencode( $file_content, 9 );
			file_put_contents( $compressed_filepath, $compressed );
			unlink( $filepath ); // Remove uncompressed file
			$filepath = $compressed_filepath;
			$filename .= '.gz';
		}

		$file_size = filesize( $filepath );

		return array(
			'success'   => true,
			'filepath'  => $filepath,
			'filename'  => $filename,
			'size'      => $file_size,
			'size_human' => size_format( $file_size, 2 ),
			'method'    => 'mysqldump',
		);
	}

	/**
	 * Parse DB_HOST to host/port/socket values compatible with mysqldump.
	 *
	 * @param string $db_host DB_HOST value.
	 * @return array{host:string,port:int,socket:string}
	 */
	private static function parse_db_host( $db_host ) {
		$host = trim( (string) $db_host );
		$port = 3306;
		$socket = '';

		// IPv6 format: [::1]:3306
		if ( preg_match( '/^\[([^\]]+)\](?::(.+))?$/', $host, $matches ) ) {
			$host = $matches[1];
			if ( isset( $matches[2] ) && '' !== $matches[2] ) {
				if ( ctype_digit( $matches[2] ) ) {
					$port = (int) $matches[2];
				} else {
					$socket = $matches[2];
				}
			}
		} else {
			$parts = explode( ':', $host, 2 );
			if ( 2 === count( $parts ) && '' !== $parts[1] ) {
				$host = $parts[0];
				if ( ctype_digit( $parts[1] ) ) {
					$port = (int) $parts[1];
				} else {
					$socket = $parts[1];
				}
			}
		}

		if ( '' === $host ) {
			$host = 'localhost';
		}
		if ( $port <= 0 ) {
			$port = 3306;
		}

		return array(
			'host'   => $host,
			'port'   => $port,
			'socket' => $socket,
		);
	}

	/**
	 * Create backup using PHP (fallback method)
	 *
	 * @param string $db_name Database name
	 * @param string $db_charset Database charset
	 * @param bool   $download If true, output directly for download
	 * @return array|WP_Error
	 */
	private static function create_backup_php( $db_name, $db_charset, $download = false, $operation_id = '' ) {
		global $wpdb;

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 5, __( 'Preparando backup...', 'users-toolkit' ), false );
		}

		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/users-toolkit/backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			file_put_contents( $backup_dir . '/.htaccess', 'deny from all' );
			file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
		}

		$filename = 'backup-' . date( 'Y-m-d-His' ) . '.sql';
		$filepath = $backup_dir . '/' . $filename;

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 10, __( 'Creando archivo de backup...', 'users-toolkit' ), false );
		}

		$fp = fopen( $filepath, 'w' );
		if ( ! $fp ) {
			return new WP_Error( 'backup_failed', 'No se pudo crear el archivo de backup.' );
		}

		$ok = self::generate_backup_to_file( $fp, $db_name, $db_charset, $operation_id );
		fclose( $fp );

		if ( is_wp_error( $ok ) ) {
			@unlink( $filepath );
			return $ok;
		}

		if ( $download ) {
			if ( ! empty( $operation_id ) ) {
				Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 95, __( 'Preparando descarga...', 'users-toolkit' ), false );
			}
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $filepath ) );
			readfile( $filepath );
			unlink( $filepath );
			exit;
		}

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 90, __( 'Comprimiendo backup...', 'users-toolkit' ), false );
		}

		$file_size = filesize( $filepath );
		if ( function_exists( 'gzopen' ) && $file_size > 0 && $file_size < 100 * 1024 * 1024 ) {
			$gz = gzopen( $filepath . '.gz', 'w9' );
			if ( $gz ) {
				$fh = fopen( $filepath, 'r' );
				while ( ! feof( $fh ) ) {
					gzwrite( $gz, fread( $fh, 524288 ) );
				}
				fclose( $fh );
				gzclose( $gz );
				unlink( $filepath );
				$filepath = $filepath . '.gz';
				$filename .= '.gz';
				$file_size = filesize( $filepath );
			}
		}

		return array(
			'success'     => true,
			'filepath'    => $filepath,
			'filename'    => $filename,
			'size'        => $file_size,
			'size_human'  => size_format( $file_size, 2 ),
			'method'      => 'php',
		);
	}

	/**
	 * Escribe el backup SQL a un archivo por streaming (tabla por tabla, filas en lotes).
	 * Evita agotar la memoria en bases grandes.
	 *
	 * @param resource $fp          File handle abierto en modo escritura
	 * @param string   $db_name     Database name
	 * @param string   $db_charset  Charset
	 * @param string   $operation_id Optional operation ID for progress tracking
	 * @return true|WP_Error
	 */
	private static function generate_backup_to_file( $fp, $db_name, $db_charset, $operation_id = '' ) {
		global $wpdb;

		$header = "-- Users Toolkit Database Backup\n";
		$header .= "-- Generated: " . date( 'Y-m-d H:i:s' ) . "\n";
		$header .= "-- Database: {$db_name}\n";
		$header .= "-- Charset: {$db_charset}\n\n";
		$header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
		$header .= "SET AUTOCOMMIT = 0;\n";
		$header .= "START TRANSACTION;\n";
		$header .= "SET time_zone = \"+00:00\";\n\n";
		fwrite( $fp, $header );

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$chunk_size = 500;
		$table_count = 0;
		$total_tables = count( $tables );

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 15, sprintf( __( 'Exportando %d tablas...', 'users-toolkit' ), $total_tables ), false );
		}

		foreach ( $tables as $table ) {
			$table_count++;
			$table_progress = 15 + floor( ( $table_count / $total_tables ) * 75 ); // 15% a 90%

			if ( ! empty( $operation_id ) ) {
				Users_Toolkit_Progress_Tracker::set_progress( $operation_id, $table_progress, sprintf( __( 'Exportando tabla %d/%d: %s...', 'users-toolkit' ), $table_count, $total_tables, $table ), false );
			}
			fwrite( $fp, "\n-- Table: {$table}\n" );
			fwrite( $fp, "DROP TABLE IF EXISTS `{$table}`;\n" );

			$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			if ( $create_table ) {
				fwrite( $fp, $create_table[1] . ";\n\n" );
			}

			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total > 0 ) {
				fwrite( $fp, "-- Data for table `{$table}`\n" );
				$offset = 0;
				$first = true;
				while ( $offset < $total ) {
					$sql = "SELECT * FROM `{$table}` LIMIT " . (int) $chunk_size . " OFFSET " . (int) $offset;
					$rows = $wpdb->get_results( $sql, ARRAY_A );
					if ( empty( $rows ) ) {
						break;
					}
					$values = array();
					foreach ( $rows as $row ) {
						$row_values = array();
						foreach ( $row as $value ) {
							if ( $value === null ) {
								$row_values[] = 'NULL';
							} else {
								$escaped = addslashes( $value );
								$escaped = str_replace( array( "\n", "\r", "\x00" ), array( '\\n', '\\r', '' ), $escaped );
								$row_values[] = "'" . $escaped . "'";
							}
						}
						$values[] = '(' . implode( ',', $row_values ) . ')';
					}
					fwrite( $fp, $first ? "INSERT INTO `{$table}` VALUES\n" : '' );
					fwrite( $fp, implode( ",\n", $values ) );
					fwrite( $fp, ( $offset + count( $rows ) >= $total ) ? ";\n\n" : ",\n" );
					$first = false;
					$offset += $chunk_size;
					unset( $rows, $values );
					if ( function_exists( 'gc_collect_cycles' ) ) {
						@gc_collect_cycles();
					}
				}
			}
		}

		fwrite( $fp, "COMMIT;\n" );

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 90, __( 'Completando backup...', 'users-toolkit' ), false );
		}

		return true;
	}

	/**
	 * Get list of backup files
	 *
	 * @return array List of backup files
	 */
	public static function get_backup_list() {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/users-toolkit/backups';

		if ( ! file_exists( $backup_dir ) ) {
			return array();
		}

		$files = glob( $backup_dir . '/backup-*.sql*' );
		$backups = array();

		foreach ( $files as $file ) {
			$filename = basename( $file );
			$file_size = filesize( $file );
			$file_time = filemtime( $file );

			// Extract date from filename
			if ( preg_match( '/backup-(\d{4}-\d{2}-\d{2}-\d{6})\.sql/', $filename, $matches ) ) {
				$date_str = $matches[1];
			} else {
				$date_str = date( 'Y-m-d-His', $file_time );
			}

			$backups[] = array(
				'filename'    => $filename,
				'filepath'    => $file,
				'size'        => $file_size,
				'size_human'  => size_format( $file_size, 2 ),
				'date'        => $date_str,
				'timestamp'   => $file_time,
				'date_human'  => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $file_time ),
			);
		}

		// Sort by date, newest first
		usort( $backups, function( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		} );

		return $backups;
	}

	/**
	 * Delete backup file
	 *
	 * @param string $filename Backup filename
	 * @return bool|WP_Error
	 */
	public static function delete_backup( $filename ) {
		$filepath = self::resolve_backup_filepath( $filename );
		if ( is_wp_error( $filepath ) ) {
			return $filepath;
		}

		if ( unlink( $filepath ) ) {
			return true;
		}

		return new WP_Error( 'delete_failed', 'Failed to delete backup file' );
	}

	/**
	 * Download backup file
	 *
	 * @param string $filename Backup filename
	 * @return void
	 */
	public static function download_backup( $filename ) {
		$filepath = self::resolve_backup_filepath( $filename );
		if ( is_wp_error( $filepath ) ) {
			wp_die( esc_html( $filepath->get_error_message() ) );
		}

		$file_size = filesize( $filepath );
		$download_name = basename( $filepath );

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		header( 'Content-Length: ' . $file_size );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );

		readfile( $filepath );
		exit;
	}

	/**
	 * Resolve and validate a backup file path.
	 *
	 * @param string $filename Backup filename.
	 * @return string|WP_Error Absolute filepath or WP_Error.
	 */
	private static function resolve_backup_filepath( $filename ) {
		$filename = sanitize_file_name( $filename );
		if ( ! self::is_valid_backup_filename( $filename ) ) {
			return new WP_Error( 'invalid_filename', 'Invalid backup filename' );
		}

		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/users-toolkit/backups';
		$backup_dir_real = realpath( $backup_dir );
		if ( false === $backup_dir_real ) {
			return new WP_Error( 'invalid_backup_dir', 'Backup directory not found' );
		}

		$filepath = $backup_dir . '/' . $filename;
		if ( ! file_exists( $filepath ) ) {
			return new WP_Error( 'file_not_found', 'Backup file not found' );
		}

		$filepath_real = realpath( $filepath );
		if ( false === $filepath_real ) {
			return new WP_Error( 'invalid_path', 'Invalid file path' );
		}

		$base = trailingslashit( $backup_dir_real );
		if ( strpos( $filepath_real, $base ) !== 0 ) {
			return new WP_Error( 'invalid_path', 'Invalid file path' );
		}

		return $filepath_real;
	}

	/**
	 * Validate backup filename format generated by this plugin.
	 *
	 * @param string $filename Filename.
	 * @return bool
	 */
	private static function is_valid_backup_filename( $filename ) {
		return (bool) preg_match( '/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.sql(?:\.gz)?$/', $filename );
	}
}
