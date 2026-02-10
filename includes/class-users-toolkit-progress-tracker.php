<?php

/**
 * Class to track progress of long-running operations
 */
class Users_Toolkit_Progress_Tracker {

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
		set_transient( 'users_toolkit_progress_' . $operation_id, $progress, $ttl );
	}

	/**
	 * Get progress for an operation
	 *
	 * @param string $operation_id Operation identifier
	 * @return array|false Progress data or false if not found
	 */
	public static function get_progress( $operation_id ) {
		return get_transient( 'users_toolkit_progress_' . $operation_id );
	}

	/**
	 * Delete progress for an operation
	 *
	 * @param string $operation_id Operation identifier
	 */
	public static function delete_progress( $operation_id ) {
		delete_transient( 'users_toolkit_progress_' . $operation_id );
	}
}
