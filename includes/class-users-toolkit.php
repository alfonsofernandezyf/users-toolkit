<?php

/**
 * The core plugin class.
 */
class Users_Toolkit {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @var      Users_Toolkit_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->version     = USERS_TOOLKIT_VERSION;
		$this->plugin_name = 'users-toolkit';

		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-loader.php';
		require_once USERS_TOOLKIT_PATH . 'admin/class-users-toolkit-admin.php';
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-spam-user-identifier.php';
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-spam-user-cleaner.php';
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-database-optimizer.php';
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-database-backup.php';
		require_once USERS_TOOLKIT_PATH . 'includes/class-users-toolkit-progress-tracker.php';

		$this->loader = new Users_Toolkit_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Users_Toolkit_Admin( $this->get_plugin_name(), $this->get_version() );

			$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
			$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_admin_actions' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_identify_spam', $plugin_admin, 'ajax_identify_spam' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_process_identify', $plugin_admin, 'ajax_process_identify' );
			$this->loader->add_action( 'wp_ajax_nopriv_users_toolkit_process_identify', $plugin_admin, 'ajax_process_identify' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_delete_spam', $plugin_admin, 'ajax_delete_spam' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_optimize_db', $plugin_admin, 'ajax_optimize_db' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_clean_cron', $plugin_admin, 'ajax_clean_cron' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_load_stats', $plugin_admin, 'ajax_load_stats' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_save_auto_load_pref', $plugin_admin, 'ajax_save_auto_load_pref' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_preview_db', $plugin_admin, 'ajax_preview_db' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_get_autoload_stats', $plugin_admin, 'ajax_get_autoload_stats' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_preview_disable_autoload', $plugin_admin, 'ajax_preview_disable_autoload' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_disable_autoload', $plugin_admin, 'ajax_disable_autoload' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_create_backup', $plugin_admin, 'ajax_create_backup' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_process_backup', $plugin_admin, 'ajax_process_backup' );
			$this->loader->add_action( 'wp_ajax_nopriv_users_toolkit_process_backup', $plugin_admin, 'ajax_process_backup' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_delete_backup', $plugin_admin, 'ajax_delete_backup' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_get_progress', $plugin_admin, 'ajax_get_progress' );
			$this->loader->add_action( 'users_toolkit_process_backup_event', $plugin_admin, 'process_backup_event', 10, 2 );
			$this->loader->add_action( 'users_toolkit_process_identify_event', $plugin_admin, 'process_identify_event', 10, 2 );
			$this->loader->add_action( 'wp_ajax_users_toolkit_restore_users', $plugin_admin, 'ajax_restore_users' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_export_users', $plugin_admin, 'ajax_export_users' );
			$this->loader->add_action( 'wp_ajax_users_toolkit_download_export', $plugin_admin, 'ajax_download_export' );
			$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_backup_download' );
		}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Users_Toolkit_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
