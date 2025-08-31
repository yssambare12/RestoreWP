<?php
/**
 * Installation related functions and actions.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Install Class.
 */
class RestoreWP_Install {

	/**
	 * Install RestoreWP.
	 */
	public static function install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we're not already running this routine.
		if ( 'yes' === get_transient( 'restorewp_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'restorewp_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		self::create_options();
		self::create_backup_directory();
		self::create_htaccess_files();
		self::maybe_update_db_version();

		delete_transient( 'restorewp_installing' );

		do_action( 'restorewp_installed' );
	}

	/**
	 * Deactivate RestoreWP.
	 */
	public static function deactivate() {
		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'restorewp_cleanup_backups' );
		
		do_action( 'restorewp_deactivated' );
	}

	/**
	 * Create default options.
	 */
	private static function create_options() {
		// Create secret key for security.
		if ( ! get_option( RESTOREWP_SECRET_KEY_OPTION ) ) {
			update_option( RESTOREWP_SECRET_KEY_OPTION, wp_generate_password( 32, false ) );
		}

		// Set version.
		update_option( 'restorewp_version', RESTOREWP_VERSION );
		update_option( 'restorewp_db_version', RESTOREWP_VERSION );
	}

	/**
	 * Create backup directory with security files.
	 */
	private static function create_backup_directory() {
		$backup_dir = RESTOREWP_STORAGE_PATH;

		// Create directory if it doesn't exist.
		if ( ! wp_mkdir_p( $backup_dir ) ) {
			error_log( 'RestoreWP: Could not create backup directory: ' . $backup_dir );
			return;
		}

		// Create index.php to prevent directory listing.
		$index_file = $backup_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		// Create .htaccess to deny access.
		$htaccess_file = $backup_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# RestoreWP Security\n";
			$htaccess_content .= "deny from all\n";
			$htaccess_content .= "<files ~ \"\\.(zip)$\">\n";
			$htaccess_content .= "deny from all\n";
			$htaccess_content .= "</files>\n";
			file_put_contents( $htaccess_file, $htaccess_content );
		}
	}

	/**
	 * Create .htaccess files for security.
	 */
	private static function create_htaccess_files() {
		// Already handled in create_backup_directory()
	}

	/**
	 * Update DB version if needed.
	 */
	private static function maybe_update_db_version() {
		$current_db_version = get_option( 'restorewp_db_version' );
		
		if ( version_compare( $current_db_version, RESTOREWP_VERSION, '<' ) ) {
			// Future DB upgrades would go here.
			update_option( 'restorewp_db_version', RESTOREWP_VERSION );
		}
	}

	/**
	 * Check if RestoreWP is installed.
	 *
	 * @return bool
	 */
	public static function is_installed() {
		return get_option( 'restorewp_version' ) !== false;
	}
}