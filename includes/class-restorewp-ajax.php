<?php
/**
 * AJAX functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Ajax Class.
 */
class RestoreWP_Ajax {

	/**
	 * Hook in AJAX events.
	 */
	public static function init() {
		$ajax_events = array(
			'export',
			'import', 
			'upload',
			'status',
			'backup_list',
			'backup_delete',
			'backup_create',
			'download',
		);

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_restorewp_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * Export AJAX handler.
	 */
	public static function export() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		if ( ! RestoreWP_Security::check_rate_limit( 'export', 5 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before trying again.', 'restorewp' ) ), 429 );
		}

		try {
			$options = RestoreWP_Security::sanitize_options( $_POST );
			$export = new RestoreWP_Export();
			$result = $export->start( $options );
			
			RestoreWP_Security::log_security_event( 'Export started', array( 'options' => $options ) );
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			RestoreWP_Security::log_security_event( 'Export failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Import AJAX handler.
	 */
	public static function import() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		if ( ! RestoreWP_Security::check_rate_limit( 'import', 3 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before trying again.', 'restorewp' ) ), 429 );
		}

		try {
			$options = RestoreWP_Security::sanitize_options( $_POST );
			$import = new RestoreWP_Import();
			$result = $import->start( $options );
			
			RestoreWP_Security::log_security_event( 'Import started', array( 'filename' => $options['filename'] ) );
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			RestoreWP_Security::log_security_event( 'Import failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Upload AJAX handler.
	 */
	public static function upload() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		if ( ! RestoreWP_Security::check_rate_limit( 'upload', 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before trying again.', 'restorewp' ) ), 429 );
		}

		try {
			$import = new RestoreWP_Import();
			$result = $import->handle_upload( $_FILES, $_POST );
			
			RestoreWP_Security::log_security_event( 'File uploaded', array( 'filename' => $result['filename'] ) );
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			RestoreWP_Security::log_security_event( 'Upload failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Status AJAX handler.
	 */
	public static function status() {
		$secret_key = sanitize_text_field( $_GET['secret_key'] ?? '' );
		
		if ( ! RestoreWP_Security::verify_secret_key( $secret_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid secret key.', 'restorewp' ) ), 403 );
		}

		$status = get_transient( 'restorewp_status_' . $secret_key );
		wp_send_json_success( $status ?: array( 'status' => 'idle' ) );
	}

	/**
	 * Backup list AJAX handler.
	 */
	public static function backup_list() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		try {
			$backup = new RestoreWP_Backup();
			$backups = $backup->list_backups();
			wp_send_json_success( $backups );
			
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Backup delete AJAX handler.
	 */
	public static function backup_delete() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		if ( ! RestoreWP_Security::check_rate_limit( 'backup_delete', 5 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before trying again.', 'restorewp' ) ), 429 );
		}

		try {
			$filename = RestoreWP_Security::sanitize_file_path( $_POST['filename'] );
			$backup = new RestoreWP_Backup();
			$result = $backup->delete_backup( $filename );
			
			RestoreWP_Security::log_security_event( 'Backup deleted', array( 'filename' => $filename ) );
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			RestoreWP_Security::log_security_event( 'Backup delete failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Backup create AJAX handler.
	 */
	public static function backup_create() {
		if ( ! RestoreWP_Security::is_request_secure() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'restorewp' ) ), 403 );
		}

		if ( ! RestoreWP_Security::check_rate_limit( 'backup_create', 3 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait before trying again.', 'restorewp' ) ), 429 );
		}

		try {
			$options = RestoreWP_Security::sanitize_options( $_POST );
			$backup = new RestoreWP_Backup();
			$result = $backup->create_backup( $options );
			
			RestoreWP_Security::log_security_event( 'Manual backup created', array( 'filename' => $result['filename'] ) );
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			RestoreWP_Security::log_security_event( 'Manual backup failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Download AJAX handler.
	 */
	public static function download() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions.', 'restorewp' ), 403 );
		}

		if ( ! wp_verify_nonce( $_GET['nonce'], 'restorewp_download' ) ) {
			wp_die( __( 'Invalid nonce.', 'restorewp' ), 403 );
		}

		$filename = RestoreWP_Security::sanitize_file_path( $_GET['file'] );
		$file_path = RESTOREWP_STORAGE_PATH . '/' . $filename;

		if ( ! RestoreWP_Security::validate_backup_file( $file_path ) ) {
			wp_die( __( 'Invalid backup file.', 'restorewp' ), 400 );
		}

		RestoreWP_Security::log_security_event( 'Backup downloaded', array( 'filename' => $filename ) );

		// Set headers for download.
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Output file.
		readfile( $file_path );
		exit;
	}
}

// Initialize AJAX handlers.
RestoreWP_Ajax::init();