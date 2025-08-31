<?php
/**
 * Import functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Import Class.
 */
class RestoreWP_Import {

	/**
	 * Import ID for tracking progress.
	 *
	 * @var string
	 */
	private $import_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->import_id = uniqid( 'import_' );
	}

	/**
	 * Handle file upload with chunked upload support.
	 *
	 * @param array $files $_FILES array.
	 * @param array $post $_POST array.
	 * @return array Upload result.
	 */
	public function handle_upload( $files, $post ) {
		if ( ! isset( $files['file'] ) ) {
			throw new Exception( __( 'No file uploaded.', 'restorewp' ) );
		}

		$file = $files['file'];
		
		// Validate file.
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			throw new Exception( $this->get_upload_error_message( $file['error'] ) );
		}

		// Validate file extension.
		$file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		if ( strtolower( $file_extension ) !== 'zip' ) {
			throw new Exception( __( 'Invalid file type. Please upload a ZIP file.', 'restorewp' ) );
		}

		// Validate file size.
		if ( $file['size'] > RESTOREWP_MAX_UPLOAD_SIZE ) {
			throw new Exception( sprintf( 
				__( 'File too large. Maximum allowed size is %s.', 'restorewp' ),
				size_format( RESTOREWP_MAX_UPLOAD_SIZE )
			) );
		}

		// Generate unique filename.
		$filename = $this->generate_upload_filename( $file['name'] );
		$destination = RESTOREWP_STORAGE_PATH . '/' . $filename;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			throw new Exception( __( 'Could not save uploaded file.', 'restorewp' ) );
		}

		// Validate ZIP file.
		$this->validate_backup_file( $destination );

		return array(
			'filename' => $filename,
			'size'     => filesize( $destination ),
		);
	}

	/**
	 * Start the import process.
	 *
	 * @param array $options Import options.
	 * @return array Import result.
	 */
	public function start( $options = array() ) {
		if ( empty( $options['filename'] ) ) {
			throw new Exception( __( 'No backup file specified.', 'restorewp' ) );
		}

		$backup_path = RESTOREWP_STORAGE_PATH . '/' . sanitize_file_name( $options['filename'] );
		
		if ( ! file_exists( $backup_path ) ) {
			throw new Exception( __( 'Backup file not found.', 'restorewp' ) );
		}

		$this->update_status( 'starting', __( 'Starting import...', 'restorewp' ) );

		try {
			// Create backup of current site if requested.
			if ( ! empty( $options['create_backup'] ) ) {
				$this->create_rollback_backup();
			}

			// Extract backup file.
			$extract_path = $this->extract_backup( $backup_path );

			// Read config file.
			$config = $this->read_config_file( $extract_path );

			// Validate compatibility.
			$this->validate_compatibility( $config );

			// Import database with URL replacement to preserve current domain.
			if ( file_exists( $extract_path . '/' . RESTOREWP_DATABASE_FILE ) ) {
				// Automatically detect old URL from config and use current site URL
				$current_url = rtrim( get_site_url(), '/' );
				$old_url = isset( $config['site_url'] ) ? rtrim( $config['site_url'], '/' ) : '';
				
				// Add URL replacement options
				$options['old_url'] = $old_url;
				$options['new_url'] = $current_url;
				
				$this->import_database( $extract_path, $options );
			}

			// Import wp-content.
			$this->import_wp_content( $extract_path, $options );

			// Cleanup.
			$this->cleanup_temp_files( $extract_path );

			$this->update_status( 'completed', __( 'Import completed successfully.', 'restorewp' ) );

			return array(
				'message' => __( 'Site imported successfully.', 'restorewp' ),
			);

		} catch ( Exception $e ) {
			$this->update_status( 'error', $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Extract backup file to temporary directory.
	 *
	 * @param string $backup_path Path to backup file.
	 * @return string Path to extracted files.
	 */
	private function extract_backup( $backup_path ) {
		$this->update_status( 'extracting', __( 'Extracting backup file...', 'restorewp' ) );

		$extract_path = RESTOREWP_STORAGE_PATH . '/temp_' . $this->import_id;
		wp_mkdir_p( $extract_path );

		$zip = new ZipArchive();
		if ( $zip->open( $backup_path ) !== TRUE ) {
			throw new Exception( __( 'Could not open backup file.', 'restorewp' ) );
		}

		if ( ! $zip->extractTo( $extract_path ) ) {
			$zip->close();
			throw new Exception( __( 'Could not extract backup file.', 'restorewp' ) );
		}

		$zip->close();
		return $extract_path;
	}

	/**
	 * Import database from backup.
	 *
	 * @param string $extract_path Path to extracted files.
	 * @param array  $options Import options.
	 */
	private function import_database( $extract_path, $options ) {
		global $wpdb;

		$this->update_status( 'database', __( 'Importing database...', 'restorewp' ) );

		$sql_file = $extract_path . '/' . RESTOREWP_DATABASE_FILE;
		$sql_content = file_get_contents( $sql_file );

		if ( empty( $sql_content ) ) {
			throw new Exception( __( 'Database file is empty.', 'restorewp' ) );
		}

		// Perform URL replacement if specified.
		if ( ! empty( $options['old_url'] ) && ! empty( $options['new_url'] ) ) {
			$sql_content = $this->replace_urls( $sql_content, $options['old_url'], $options['new_url'] );
		}

		// Execute SQL.
		$queries = $this->split_sql_file( $sql_content );
		foreach ( $queries as $query ) {
			if ( ! empty( trim( $query ) ) ) {
				$wpdb->query( $query );
				if ( $wpdb->last_error ) {
					error_log( 'RestoreWP SQL Error: ' . $wpdb->last_error );
				}
			}
		}
	}

	/**
	 * Import wp-content from backup.
	 *
	 * @param string $extract_path Path to extracted files.
	 * @param array  $options Import options.
	 */
	private function import_wp_content( $extract_path, $options ) {
		$wp_content_source = $extract_path . '/wp-content';
		
		if ( ! is_dir( $wp_content_source ) ) {
			return;
		}

		$this->update_status( 'files', __( 'Importing files...', 'restorewp' ) );

		// Copy files recursively.
		$this->copy_directory( $wp_content_source, WP_CONTENT_DIR );
	}

	/**
	 * Copy directory recursively.
	 *
	 * @param string $source Source directory.
	 * @param string $destination Destination directory.
	 */
	private function copy_directory( $source, $destination ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$dest_path = $destination . '/' . $iterator->getSubPathName();

			if ( $file->isDir() ) {
				wp_mkdir_p( $dest_path );
			} else {
				wp_mkdir_p( dirname( $dest_path ) );
				copy( $file, $dest_path );
			}
		}
	}

	/**
	 * Replace URLs in SQL content.
	 *
	 * @param string $sql_content SQL content.
	 * @param string $old_url Old URL.
	 * @param string $new_url New URL.
	 * @return string Modified SQL content.
	 */
	private function replace_urls( $sql_content, $old_url, $new_url ) {
		if ( empty( $old_url ) || $old_url === $new_url ) {
			return $sql_content;
		}

		$this->update_status( 'url_replace', sprintf( __( 'Replacing URLs: %s → %s', 'restorewp' ), $old_url, $new_url ) );

		// Handle both HTTP and HTTPS versions
		$old_urls = array( $old_url );
		$new_urls = array( $new_url );
		
		if ( strpos( $old_url, 'https://' ) === 0 ) {
			$old_urls[] = str_replace( 'https://', 'http://', $old_url );
			$new_urls[] = str_replace( 'https://', 'http://', $new_url );
		} elseif ( strpos( $old_url, 'http://' ) === 0 ) {
			$old_urls[] = str_replace( 'http://', 'https://', $old_url );
			$new_urls[] = str_replace( 'http://', 'https://', $new_url );
		}

		foreach ( $old_urls as $index => $url_to_replace ) {
			$replacement_url = $new_urls[ $index ];
			
			// Basic URL replacement
			$sql_content = str_replace( $url_to_replace, $replacement_url, $sql_content );
			
			// Replace serialized URLs with proper length adjustment
			$sql_content = preg_replace_callback(
				'/s:(\d+):"([^"]*' . preg_quote( $url_to_replace, '/' ) . '[^"]*)";/',
				function( $matches ) use ( $url_to_replace, $replacement_url ) {
					$updated_string = str_replace( $url_to_replace, $replacement_url, $matches[2] );
					$new_length = strlen( $updated_string );
					return 's:' . $new_length . ':"' . $updated_string . '";';
				},
				$sql_content
			);
			
			// Replace escaped URLs (for JSON in database)
			$escaped_old = addslashes( $url_to_replace );
			$escaped_new = addslashes( $replacement_url );
			$sql_content = str_replace( $escaped_old, $escaped_new, $sql_content );
		}

		return $sql_content;
	}

	/**
	 * Split SQL file into individual queries.
	 *
	 * @param string $sql_content SQL content.
	 * @return array Array of SQL queries.
	 */
	private function split_sql_file( $sql_content ) {
		// Remove comments and split by semicolon.
		$sql_content = preg_replace( '/^--.*$/m', '', $sql_content );
		$queries = explode( ";\n", $sql_content );
		
		return array_filter( array_map( 'trim', $queries ) );
	}

	/**
	 * Read config file from extracted backup.
	 *
	 * @param string $extract_path Path to extracted files.
	 * @return array Config data.
	 */
	private function read_config_file( $extract_path ) {
		$config_file = $extract_path . '/' . RESTOREWP_CONFIG_FILE;
		
		if ( ! file_exists( $config_file ) ) {
			// Fallback for backups without config file.
			return array(
				'version' => '1.0.0',
				'wp_version' => '5.0',
			);
		}

		$config_content = file_get_contents( $config_file );
		return json_decode( $config_content, true );
	}

	/**
	 * Validate backup compatibility.
	 *
	 * @param array $config Backup config.
	 */
	private function validate_compatibility( $config ) {
		// Check WordPress version compatibility.
		if ( isset( $config['wp_version'] ) ) {
			$backup_wp_version = $config['wp_version'];
			$current_wp_version = get_bloginfo( 'version' );
			
			if ( version_compare( $backup_wp_version, $current_wp_version, '>' ) ) {
				error_log( sprintf( 
					'RestoreWP: Backup created with newer WordPress version (%s) than current (%s)',
					$backup_wp_version,
					$current_wp_version
				) );
			}
		}

		// Check PHP version compatibility.
		if ( isset( $config['php_version'] ) ) {
			$backup_php_version = $config['php_version'];
			$current_php_version = PHP_VERSION;
			
			if ( version_compare( $backup_php_version, $current_php_version, '>' ) ) {
				error_log( sprintf( 
					'RestoreWP: Backup created with newer PHP version (%s) than current (%s)',
					$backup_php_version,
					$current_php_version
				) );
			}
		}
	}

	/**
	 * Validate backup file.
	 *
	 * @param string $file_path Path to backup file.
	 */
	private function validate_backup_file( $file_path ) {
		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== TRUE ) {
			throw new Exception( __( 'Invalid ZIP file.', 'restorewp' ) );
		}

		// Check if it contains required files (either database.sql or wp-content folder).
		$has_database = $zip->locateName( RESTOREWP_DATABASE_FILE ) !== false;
		$has_wp_content = $zip->locateName( 'wp-content/' ) !== false;

		$zip->close();

		if ( ! $has_database && ! $has_wp_content ) {
			throw new Exception( __( 'Invalid backup file. Missing database or wp-content folder.', 'restorewp' ) );
		}
	}

	/**
	 * Create rollback backup before import.
	 */
	private function create_rollback_backup() {
		$this->update_status( 'backup', __( 'Creating rollback backup...', 'restorewp' ) );

		$export = new RestoreWP_Export();
		$export->start( array(
			'include_database' => true,
			'include_uploads'   => true,
			'include_themes'    => true,
			'include_plugins'   => true,
		) );
	}

	/**
	 * Cleanup temporary files.
	 *
	 * @param string $extract_path Path to temporary files.
	 */
	private function cleanup_temp_files( $extract_path ) {
		$this->update_status( 'cleanup', __( 'Cleaning up temporary files...', 'restorewp' ) );
		
		if ( is_dir( $extract_path ) ) {
			$this->delete_directory( $extract_path );
		}
	}

	/**
	 * Delete directory recursively.
	 *
	 * @param string $dir Directory path.
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				unlink( $path );
			}
		}
		
		rmdir( $dir );
	}

	/**
	 * Generate filename for uploaded file.
	 *
	 * @param string $original_name Original filename.
	 * @return string Generated filename.
	 */
	private function generate_upload_filename( $original_name ) {
		$timestamp = date( 'Y-m-d_H-i-s' );
		$extension = pathinfo( $original_name, PATHINFO_EXTENSION );
		$basename = pathinfo( $original_name, PATHINFO_FILENAME );
		$basename = sanitize_file_name( $basename );
		
		return $basename . '_uploaded_' . $timestamp . '.' . $extension;
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code Upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The uploaded file exceeds the maximum file size limit.', 'restorewp' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The uploaded file was only partially uploaded.', 'restorewp' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'restorewp' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Missing a temporary folder.', 'restorewp' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'restorewp' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the file upload.', 'restorewp' );
			default:
				return __( 'Unknown upload error.', 'restorewp' );
		}
	}

	/**
	 * Update import status.
	 *
	 * @param string $status Status.
	 * @param string $message Message.
	 * @param array  $data Additional data.
	 */
	private function update_status( $status, $message, $data = array() ) {
		$status_data = array(
			'status'  => $status,
			'message' => $message,
			'data'    => $data,
			'time'    => time(),
		);

		set_transient( 'restorewp_status_' . get_option( RESTOREWP_SECRET_KEY_OPTION ), $status_data, 300 );
	}
}