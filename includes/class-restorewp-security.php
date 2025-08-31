<?php
/**
 * Security functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Security Class.
 */
class RestoreWP_Security {

	/**
	 * Verify nonce for AJAX requests.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action = RESTOREWP_NONCE_ACTION ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Verify user capabilities.
	 *
	 * @param string $capability Required capability.
	 * @return bool
	 */
	public static function verify_capability( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Verify secret key.
	 *
	 * @param string $secret_key Secret key to verify.
	 * @return bool
	 */
	public static function verify_secret_key( $secret_key ) {
		$stored_key = get_option( RESTOREWP_SECRET_KEY_OPTION );
		return hash_equals( $stored_key, $secret_key );
	}

	/**
	 * Sanitize file path.
	 *
	 * @param string $path File path.
	 * @return string Sanitized path.
	 */
	public static function sanitize_file_path( $path ) {
		// Remove any path traversal attempts.
		$path = str_replace( array( '../', '..\\' ), '', $path );
		
		// Sanitize filename.
		return sanitize_file_name( basename( $path ) );
	}

	/**
	 * Validate file extension.
	 *
	 * @param string $filename Filename.
	 * @param array  $allowed_extensions Allowed extensions.
	 * @return bool
	 */
	public static function validate_file_extension( $filename, $allowed_extensions = array( 'zip' ) ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $extension, $allowed_extensions, true );
	}

	/**
	 * Check if file is within allowed directory.
	 *
	 * @param string $file_path File path.
	 * @param string $allowed_dir Allowed directory.
	 * @return bool
	 */
	public static function is_file_in_directory( $file_path, $allowed_dir ) {
		$real_file_path = realpath( $file_path );
		$real_allowed_dir = realpath( $allowed_dir );

		if ( ! $real_file_path || ! $real_allowed_dir ) {
			return false;
		}

		return strpos( $real_file_path, $real_allowed_dir ) === 0;
	}

	/**
	 * Sanitize export/import options.
	 *
	 * @param array $options Raw options.
	 * @return array Sanitized options.
	 */
	public static function sanitize_options( $options ) {
		$sanitized = array();

		// Boolean options.
		$boolean_options = array( 'include_database', 'include_uploads', 'include_themes', 'include_plugins', 'create_backup' );
		foreach ( $boolean_options as $option ) {
			$sanitized[ $option ] = ! empty( $options[ $option ] );
		}

		// Array options.
		$array_options = array( 'exclude_tables', 'exclude_plugins', 'exclude_themes' );
		foreach ( $array_options as $option ) {
			if ( isset( $options[ $option ] ) && is_array( $options[ $option ] ) ) {
				$sanitized[ $option ] = array_map( 'sanitize_text_field', $options[ $option ] );
			} else {
				$sanitized[ $option ] = array();
			}
		}

		// URL options.
		$url_options = array( 'old_url', 'new_url' );
		foreach ( $url_options as $option ) {
			if ( isset( $options[ $option ] ) ) {
				$sanitized[ $option ] = esc_url_raw( $options[ $option ] );
			}
		}

		// String options.
		$string_options = array( 'filename' );
		foreach ( $string_options as $option ) {
			if ( isset( $options[ $option ] ) ) {
				$sanitized[ $option ] = sanitize_text_field( $options[ $option ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Log security event.
	 *
	 * @param string $event Event description.
	 * @param array  $context Additional context.
	 */
	public static function log_security_event( $event, $context = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'user_ip'   => self::get_user_ip(),
			'event'     => $event,
			'context'   => $context,
		);

		error_log( 'RestoreWP Security: ' . wp_json_encode( $log_entry ) );
	}

	/**
	 * Get user IP address.
	 *
	 * @return string
	 */
	private static function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return $_SERVER['REMOTE_ADDR'];
		}
		
		return 'unknown';
	}

	/**
	 * Check if current request is secure.
	 *
	 * @return bool
	 */
	public static function is_request_secure() {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! self::verify_nonce( $_POST['nonce'] ) ) {
			self::log_security_event( 'Invalid nonce', array( 'action' => $_POST['action'] ?? 'unknown' ) );
			return false;
		}

		// Check capability.
		if ( ! self::verify_capability() ) {
			self::log_security_event( 'Insufficient capability', array( 'user_id' => get_current_user_id() ) );
			return false;
		}

		return true;
	}

	/**
	 * Rate limiting for requests.
	 *
	 * @param string $action Action name.
	 * @param int    $limit Request limit per minute.
	 * @return bool
	 */
	public static function check_rate_limit( $action, $limit = 10 ) {
		$user_id = get_current_user_id();
		$transient_key = "restorewp_rate_limit_{$action}_{$user_id}";
		
		$requests = get_transient( $transient_key );
		if ( $requests === false ) {
			$requests = 0;
		}

		if ( $requests >= $limit ) {
			self::log_security_event( 'Rate limit exceeded', array( 
				'action' => $action, 
				'user_id' => $user_id,
				'requests' => $requests,
				'limit' => $limit,
			) );
			return false;
		}

		set_transient( $transient_key, $requests + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Validate backup file for security.
	 *
	 * @param string $file_path Path to backup file.
	 * @return bool
	 */
	public static function validate_backup_file( $file_path ) {
		// Check file size.
		if ( filesize( $file_path ) > RESTOREWP_MAX_UPLOAD_SIZE ) {
			return false;
		}

		// Check file extension.
		if ( ! self::validate_file_extension( $file_path ) ) {
			return false;
		}

		// Check if file is within allowed directory.
		if ( ! self::is_file_in_directory( $file_path, RESTOREWP_STORAGE_PATH ) ) {
			return false;
		}

		// Basic ZIP validation.
		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== TRUE ) {
			return false;
		}

		$zip->close();
		return true;
	}
}