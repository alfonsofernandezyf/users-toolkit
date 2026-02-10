<?php

/**
 * Class to track progress of long-running operations
 */
class Users_Toolkit_Progress_Tracker {

	/**
	 * Build persistent option key for operation progress.
	 *
	 * @param string $operation_id Operation identifier.
	 * @return string
	 */
	private static function get_option_key( $operation_id ) {
		return 'users_toolkit_progress_opt_' . sanitize_key( (string) $operation_id );
	}

	/**
	 * Set progress for an operation
	 *
	 * @param string $operation_id Operation identifier (e.g., 'backup_123', 'spam_identify_456')
	 * @param int    $current      Current progress (0-100)
	 * @param string $message      Progress message
	 * @param bool   $completed    Whether the operation is completed
	 * @param array  $data         Additional data to return
	 */
	public static function set_progress( $operation_id, $current, $message = '', $completed = false, $data = array() ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$progress = array(
			'current'   => max( 0, min( 100, (int) $current ) ),
			'message'   => $message,
			'completed' => $completed,
			'timestamp' => time(),
			'data'      => $data,
		);

		$ttl = (int) apply_filters( 'users_toolkit_progress_ttl', 7200, $operation_id, $completed );
		if ( $ttl < 60 ) {
			$ttl = 60;
		}
		$progress['expires_at'] = time() + $ttl;

		set_transient( 'users_toolkit_progress_' . $operation_id, $progress, $ttl );
		update_option( self::get_option_key( $operation_id ), $progress, false );
	}

	/**
	 * Get progress for an operation
	 *
	 * @param string $operation_id Operation identifier
	 * @return array|false Progress data or false if not found
	 */
	public static function get_progress( $operation_id ) {
		$operation_id = sanitize_key( (string) $operation_id );
		$transient_key = 'users_toolkit_progress_' . $operation_id;
		$progress = get_transient( $transient_key );

		if ( false === $progress ) {
			$progress = get_option( self::get_option_key( $operation_id ), false );
			if ( false === $progress ) {
				return false;
			}
		}

		if ( ! is_array( $progress ) ) {
			return false;
		}

		if ( isset( $progress['expires_at'] ) && time() > (int) $progress['expires_at'] ) {
			self::delete_progress( $operation_id );
			return false;
		}

		// Repoblar transient si se perdió por caché/evicción.
		if ( false === get_transient( $transient_key ) ) {
			$remaining = isset( $progress['expires_at'] ) ? max( 60, (int) $progress['expires_at'] - time() ) : 300;
			set_transient( $transient_key, $progress, $remaining );
		}

		return $progress;
	}

	/**
	 * Delete progress for an operation
	 *
	 * @param string $operation_id Operation identifier
	 */
	public static function delete_progress( $operation_id ) {
		$operation_id = sanitize_key( (string) $operation_id );
		delete_transient( 'users_toolkit_progress_' . $operation_id );
		delete_option( self::get_option_key( $operation_id ) );
	}
}
