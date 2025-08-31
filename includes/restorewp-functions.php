<?php
/**
 * RestoreWP helper functions.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get backup storage path.
 *
 * @return string
 */
function restorewp_get_storage_path() {
	return RESTOREWP_STORAGE_PATH;
}

/**
 * Get backup storage URL.
 *
 * @return string
 */
function restorewp_get_storage_url() {
	$upload_dir = wp_upload_dir();
	return $upload_dir['baseurl'] . '/restorewp-backups';
}

/**
 * Format file size in human readable format.
 *
 * @param int $size File size in bytes.
 * @return string Formatted size.
 */
function restorewp_format_size( $size ) {
	return size_format( $size );
}

/**
 * Check if user can perform backup operations.
 *
 * @return bool
 */
function restorewp_user_can_backup() {
	return current_user_can( 'edit_posts' );
}

/**
 * Get available disk space.
 *
 * @return int|false Available space in bytes or false if unknown.
 */
function restorewp_get_available_disk_space() {
	$storage_path = restorewp_get_storage_path();
	
	if ( function_exists( 'disk_free_space' ) ) {
		return disk_free_space( $storage_path );
	}
	
	return false;
}

/**
 * Check if there's enough disk space for operation.
 *
 * @param int $required_space Required space in bytes.
 * @return bool
 */
function restorewp_has_enough_disk_space( $required_space ) {
	$available_space = restorewp_get_available_disk_space();
	
	if ( $available_space === false ) {
		// If we can't determine disk space, assume it's available.
		return true;
	}
	
	// Add 20% buffer.
	$required_space_with_buffer = $required_space * 1.2;
	
	return $available_space >= $required_space_with_buffer;
}

/**
 * Get WordPress upload limits.
 *
 * @return array
 */
function restorewp_get_upload_limits() {
	return array(
		'max_upload_size'    => wp_max_upload_size(),
		'max_post_size'      => wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) ),
		'max_execution_time' => ini_get( 'max_execution_time' ),
		'memory_limit'       => wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ),
	);
}

/**
 * Create backup filename.
 *
 * @param string $prefix Optional prefix.
 * @return string
 */
function restorewp_create_backup_filename( $prefix = '' ) {
	$site_name = sanitize_title( get_bloginfo( 'name' ) );
	$timestamp = date( 'Y-m-d_H-i-s' );
	
	$filename = '';
	if ( ! empty( $prefix ) ) {
		$filename .= sanitize_title( $prefix ) . '_';
	}
	$filename .= $site_name . '_' . $timestamp . RESTOREWP_ARCHIVE_EXTENSION;
	
	return $filename;
}

/**
 * Validate file extension.
 *
 * @param string $filename Filename to validate.
 * @param array  $allowed_extensions Allowed extensions.
 * @return bool
 */
function restorewp_validate_file_extension( $filename, $allowed_extensions = array( 'zip' ) ) {
	$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	return in_array( $extension, $allowed_extensions, true );
}

/**
 * Get system information.
 *
 * @return array
 */
function restorewp_get_system_info() {
	global $wpdb;
	
	return array(
		'wp_version'     => get_bloginfo( 'version' ),
		'php_version'    => PHP_VERSION,
		'mysql_version'  => $wpdb->get_var( "SELECT VERSION()" ),
		'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
		'max_upload_size' => size_format( wp_max_upload_size() ),
		'memory_limit'   => ini_get( 'memory_limit' ),
		'max_execution_time' => ini_get( 'max_execution_time' ),
		'disk_space'     => restorewp_get_available_disk_space() ? size_format( restorewp_get_available_disk_space() ) : __( 'Unknown', 'restorewp' ),
	);
}

/**
 * Log RestoreWP events.
 *
 * @param string $message Log message.
 * @param string $level Log level (info, warning, error).
 */
function restorewp_log( $message, $level = 'info' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'RestoreWP [%s]: %s', strtoupper( $level ), $message ) );
	}
}

/**
 * Get plugin settings.
 *
 * @return array
 */
function restorewp_get_settings() {
	$defaults = array(
		'max_backup_retention' => 30, // days
		'auto_cleanup'         => true,
		'email_notifications'  => false,
		'notification_email'   => get_option( 'admin_email' ),
	);
	
	$settings = get_option( 'restorewp_settings', array() );
	return wp_parse_args( $settings, $defaults );
}

/**
 * Update plugin settings.
 *
 * @param array $settings Settings to update.
 * @return bool
 */
function restorewp_update_settings( $settings ) {
	$current_settings = restorewp_get_settings();
	$updated_settings = wp_parse_args( $settings, $current_settings );
	
	return update_option( 'restorewp_settings', $updated_settings );
}

/**
 * Get backup file URL for download.
 *
 * @param string $filename Backup filename.
 * @return string
 */
function restorewp_get_backup_download_url( $filename ) {
	return add_query_arg( array(
		'action' => 'restorewp_download',
		'file'   => urlencode( $filename ),
		'nonce'  => wp_create_nonce( 'restorewp_download' ),
	), admin_url( 'admin-ajax.php' ) );
}

/**
 * Schedule cleanup cron if not already scheduled.
 */
function restorewp_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'restorewp_cleanup_backups' ) ) {
		wp_schedule_event( time(), 'daily', 'restorewp_cleanup_backups' );
	}
}

/**
 * Cleanup old backups (cron job).
 */
function restorewp_cleanup_old_backups() {
	$settings = restorewp_get_settings();
	
	if ( $settings['auto_cleanup'] ) {
		$backup = new RestoreWP_Backup();
		$backup->cleanup_old_backups( $settings['max_backup_retention'] );
		
		restorewp_log( 'Automatic backup cleanup completed' );
	}
}

// Hook cleanup function to cron.
add_action( 'restorewp_cleanup_backups', 'restorewp_cleanup_old_backups' );

/**
 * Get WordPress hooks for extending functionality.
 */
function restorewp_register_hooks() {
	// Before export hook.
	do_action( 'restorewp_before_export' );
	
	// After export hook.
	do_action( 'restorewp_after_export' );
	
	// Before import hook.
	do_action( 'restorewp_before_import' );
	
	// After import hook.
	do_action( 'restorewp_after_import' );
	
	// Before backup creation hook.
	do_action( 'restorewp_before_backup' );
	
	// After backup creation hook.
	do_action( 'restorewp_after_backup' );
}