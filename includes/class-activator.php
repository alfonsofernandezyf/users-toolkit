<?php

class Users_Toolkit_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 */
	public static function activate() {
		// Create upload directory for reports
		$upload_dir = wp_upload_dir();
		$reports_dir = $upload_dir['basedir'] . '/users-toolkit';
		
		if ( ! file_exists( $reports_dir ) ) {
			wp_mkdir_p( $reports_dir );
			file_put_contents( $reports_dir . '/.htaccess', 'deny from all' );
			file_put_contents( $reports_dir . '/index.php', '<?php // Silence is golden' );
		}
	}
}
