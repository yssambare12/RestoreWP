<?php
/**
 * Plugin Name: RestoreWP
 * Plugin URI: https://github.com/restorewp/restorewp
 * Description: A modern WordPress migration and backup plugin with React-based UI. Export, import, and restore your WordPress site with support for files up to 2GB.
 * Version: 1.0.0
 * Author: RestoreWP Team
 * Text Domain: restorewp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package RestoreWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'RESTOREWP_VERSION', '1.0.0' );
define( 'RESTOREWP_PLUGIN_FILE', __FILE__ );
define( 'RESTOREWP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RESTOREWP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESTOREWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RESTOREWP_INCLUDES_PATH', RESTOREWP_PLUGIN_PATH . 'includes/' );
define( 'RESTOREWP_ADMIN_PATH', RESTOREWP_PLUGIN_PATH . 'admin/' );
define( 'RESTOREWP_ASSETS_URL', RESTOREWP_PLUGIN_URL . 'assets/' );

// Storage paths
if ( ! defined( 'RESTOREWP_STORAGE_PATH' ) ) {
	define( 'RESTOREWP_STORAGE_PATH', WP_CONTENT_DIR . '/restorewp-backups' );
}

// Archive constants
define( 'RESTOREWP_ARCHIVE_EXTENSION', '.zip' );
define( 'RESTOREWP_DATABASE_FILE', 'database.sql' );
define( 'RESTOREWP_CONFIG_FILE', 'restorewp-config.json' );
define( 'RESTOREWP_MAX_UPLOAD_SIZE', 2 * 1024 * 1024 * 1024 ); // 2GB
define( 'RESTOREWP_CHUNK_SIZE', 5 * 1024 * 1024 ); // 5MB chunks

// Security
define( 'RESTOREWP_SECRET_KEY_OPTION', 'restorewp_secret_key' );
define( 'RESTOREWP_NONCE_ACTION', 'restorewp_nonce' );

/**
 * Main plugin class
 */
class RestoreWP {

	/**
	 * The single instance of the class.
	 *
	 * @var RestoreWP
	 */
	protected static $_instance = null;

	/**
	 * Main RestoreWP Instance.
	 *
	 * @return RestoreWP
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * RestoreWP Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define RestoreWP Constants.
	 */
	private function define_constants() {
		$this->define( 'RESTOREWP_ABSPATH', dirname( RESTOREWP_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string $name  Constant name.
	 * @param string $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required core files.
	 */
	public function includes() {
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-install.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-admin.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-ajax.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-rest-api.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-background-process.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-export.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-import.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-backup.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-security.php';
		include_once RESTOREWP_INCLUDES_PATH . 'class-restorewp-cli.php';
		include_once RESTOREWP_INCLUDES_PATH . 'restorewp-functions.php';
		
		// Direct menu registration (working solution)
		include_once RESTOREWP_PLUGIN_PATH . 'direct-menu.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( RESTOREWP_PLUGIN_FILE, array( 'RestoreWP_Install', 'install' ) );
		register_deactivation_hook( RESTOREWP_PLUGIN_FILE, array( 'RestoreWP_Install', 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'ensure_admin_menu' ), 5 );
	}

	/**
	 * Ensure admin menu is loaded.
	 */
	public function ensure_admin_menu() {
		// Disabled to prevent duplicate menus - using direct-menu.php instead
		// if ( is_admin() && class_exists( 'RestoreWP_Admin' ) ) {
		// 	RestoreWP_Admin::instance();
		// }
	}

	/**
	 * Init RestoreWP when WordPress Initialises.
	 */
	public function init() {
		// Before init action.
		do_action( 'before_restorewp_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Init action.
		do_action( 'restorewp_init' );
	}

	/**
	 * Admin init.
	 */
	public function admin_init() {
		// Admin class is now handled in ensure_admin_menu
	}

	/**
	 * Load Localisation files.
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'restorewp' );

		unload_textdomain( 'restorewp' );
		load_textdomain( 'restorewp', WP_LANG_DIR . '/restorewp/restorewp-' . $locale . '.mo' );
		load_plugin_textdomain( 'restorewp', false, plugin_basename( dirname( RESTOREWP_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', RESTOREWP_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( RESTOREWP_PLUGIN_FILE ) );
	}
}

/**
 * Main instance of RestoreWP.
 *
 * @return RestoreWP
 */
function RestoreWP() {
	return RestoreWP::instance();
}

// Global for backwards compatibility.
$GLOBALS['restorewp'] = RestoreWP();