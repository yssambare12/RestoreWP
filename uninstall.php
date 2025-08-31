<?php
/**
 * RestoreWP Uninstall
 *
 * Uninstalling RestoreWP deletes options and backup files.
 *
 * @package RestoreWP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin constants for uninstall.
if ( ! defined( 'RESTOREWP_STORAGE_PATH' ) ) {
	define( 'RESTOREWP_STORAGE_PATH', WP_CONTENT_DIR . '/restorewp-backups' );
}

/**
 * Delete all plugin options.
 */
function restorewp_delete_options() {
	delete_option( 'restorewp_version' );
	delete_option( 'restorewp_db_version' );
	delete_option( 'restorewp_settings' );
	delete_option( 'restorewp_secret_key' );
	
	// Delete any transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_restorewp_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_restorewp_%'" );
}

/**
 * Delete backup files and storage directory.
 */
function restorewp_delete_backups() {
	$storage_path = RESTOREWP_STORAGE_PATH;
	
	if ( ! is_dir( $storage_path ) ) {
		return;
	}

	// Delete all files in storage directory.
	$files = glob( $storage_path . '/*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}

	// Remove directory.
	rmdir( $storage_path );
}

/**
 * Clear scheduled cron jobs.
 */
function restorewp_clear_cron_jobs() {
	wp_clear_scheduled_hook( 'restorewp_cleanup_backups' );
}

// Execute uninstall.
restorewp_delete_options();
restorewp_clear_cron_jobs();

// Note: We don't delete backup files by default as users might want to keep them.
// Uncomment the line below if you want to delete all backup files on uninstall.
// restorewp_delete_backups();