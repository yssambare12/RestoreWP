<?php
/**
 * Backup management functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Backup Class.
 */
class RestoreWP_Backup {

	/**
	 * List all backups in the backup directory.
	 *
	 * @return array List of backups.
	 */
	public function list_backups() {
		$backups = array();
		$backup_dir = RESTOREWP_STORAGE_PATH;

		if ( ! is_dir( $backup_dir ) ) {
			return $backups;
		}

		$files = scandir( $backup_dir );
		
		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' || $file === 'index.php' || $file === '.htaccess' ) {
				continue;
			}

			$file_path = $backup_dir . '/' . $file;
			
			if ( is_file( $file_path ) && pathinfo( $file, PATHINFO_EXTENSION ) === 'zip' ) {
				$backups[] = array(
					'filename'    => $file,
					'size'        => filesize( $file_path ),
					'size_human'  => size_format( filesize( $file_path ) ),
					'created'     => filemtime( $file_path ),
					'created_human' => human_time_diff( filemtime( $file_path ) ) . ' ' . __( 'ago', 'restorewp' ),
					'download_url' => $this->get_download_url( $file ),
				);
			}
		}

		// Sort by creation time (newest first).
		usort( $backups, function( $a, $b ) {
			return $b['created'] - $a['created'];
		} );

		return $backups;
	}

	/**
	 * Delete a backup file.
	 *
	 * @param string $filename Backup filename.
	 * @return bool Success status.
	 */
	public function delete_backup( $filename ) {
		// Sanitize filename.
		$filename = sanitize_file_name( $filename );
		
		// Validate filename.
		if ( empty( $filename ) || strpos( $filename, '..' ) !== false ) {
			throw new Exception( __( 'Invalid filename.', 'restorewp' ) );
		}

		$file_path = RESTOREWP_STORAGE_PATH . '/' . $filename;
		
		// Check if file exists and is in backup directory.
		if ( ! file_exists( $file_path ) || dirname( $file_path ) !== RESTOREWP_STORAGE_PATH ) {
			throw new Exception( __( 'Backup file not found.', 'restorewp' ) );
		}

		// Delete file.
		if ( ! unlink( $file_path ) ) {
			throw new Exception( __( 'Could not delete backup file.', 'restorewp' ) );
		}

		return true;
	}

	/**
	 * Create a manual backup.
	 *
	 * @param array $options Backup options.
	 * @return array Backup result.
	 */
	public function create_backup( $options = array() ) {
		$export = new RestoreWP_Export();
		return $export->start( $options );
	}

	/**
	 * Get backup file info.
	 *
	 * @param string $filename Backup filename.
	 * @return array|false Backup info or false if not found.
	 */
	public function get_backup_info( $filename ) {
		$filename = sanitize_file_name( $filename );
		$file_path = RESTOREWP_STORAGE_PATH . '/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$config = $this->read_backup_config( $file_path );

		return array(
			'filename'     => $filename,
			'size'         => filesize( $file_path ),
			'size_human'   => size_format( filesize( $file_path ) ),
			'created'      => filemtime( $file_path ),
			'created_human' => human_time_diff( filemtime( $file_path ) ) . ' ' . __( 'ago', 'restorewp' ),
			'config'       => $config,
			'download_url' => $this->get_download_url( $filename ),
		);
	}

	/**
	 * Read config from backup file.
	 *
	 * @param string $file_path Path to backup file.
	 * @return array|null Config data or null if not found.
	 */
	private function read_backup_config( $file_path ) {
		$zip = new ZipArchive();
		
		if ( $zip->open( $file_path ) !== TRUE ) {
			return null;
		}

		$config_content = $zip->getFromName( RESTOREWP_CONFIG_FILE );
		$zip->close();

		if ( $config_content === false ) {
			return null;
		}

		return json_decode( $config_content, true );
	}

	/**
	 * Get download URL for backup file.
	 *
	 * @param string $filename Backup filename.
	 * @return string Download URL.
	 */
	private function get_download_url( $filename ) {
		return admin_url( 'admin-ajax.php?action=restorewp_download&file=' . urlencode( $filename ) . '&nonce=' . wp_create_nonce( 'restorewp_download' ) );
	}

	/**
	 * Handle backup file download.
	 */
	public static function handle_download() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Insufficient permissions.', 'restorewp' ), 403 );
		}

		if ( ! wp_verify_nonce( $_GET['nonce'], 'restorewp_download' ) ) {
			wp_die( __( 'Invalid nonce.', 'restorewp' ), 403 );
		}

		$filename = sanitize_file_name( $_GET['file'] );
		$file_path = RESTOREWP_STORAGE_PATH . '/' . $filename;

		if ( ! file_exists( $file_path ) || dirname( $file_path ) !== RESTOREWP_STORAGE_PATH ) {
			wp_die( __( 'File not found.', 'restorewp' ), 404 );
		}

		// Set headers for download.
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );

		// Output file.
		readfile( $file_path );
		exit;
	}

	/**
	 * Cleanup old backups.
	 *
	 * @param int $days Number of days to keep backups.
	 */
	public function cleanup_old_backups( $days = 30 ) {
		$backup_dir = RESTOREWP_STORAGE_PATH;
		$cutoff_time = time() - ( $days * DAY_IN_SECONDS );

		if ( ! is_dir( $backup_dir ) ) {
			return;
		}

		$files = scandir( $backup_dir );
		
		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' || $file === 'index.php' || $file === '.htaccess' ) {
				continue;
			}

			$file_path = $backup_dir . '/' . $file;
			
			if ( is_file( $file_path ) && filemtime( $file_path ) < $cutoff_time ) {
				unlink( $file_path );
			}
		}
	}
}

// Handle download requests.
add_action( 'wp_ajax_restorewp_download', array( 'RestoreWP_Backup', 'handle_download' ) );