<?php
/**
 * Export functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Export Class.
 */
class RestoreWP_Export {

	/**
	 * Export ID for tracking progress.
	 *
	 * @var string
	 */
	private $export_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->export_id = uniqid( 'export_' );
	}

	/**
	 * Start the export process.
	 *
	 * @param array $options Export options.
	 * @return array Export result.
	 */
	public function start( $options = array() ) {
		$options = wp_parse_args( $options, array(
			'include_database' => true,
			'include_uploads'   => true,
			'include_themes'    => true,
			'include_plugins'   => true,
			'exclude_tables'    => array(),
			'exclude_plugins'   => array(),
			'exclude_themes'    => array(),
		) );

		$this->update_status( 'starting', __( 'Starting export...', 'restorewp' ) );

		try {
			// Create backup filename.
			$filename = $this->generate_filename();
			$backup_path = RESTOREWP_STORAGE_PATH . '/' . $filename;

			// Initialize ZIP archive.
			$zip = new ZipArchive();
			if ( $zip->open( $backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== TRUE ) {
				throw new Exception( __( 'Could not create backup file.', 'restorewp' ) );
			}

			// Export database.
			if ( $options['include_database'] ) {
				$this->export_database( $zip, $options );
			}

			// Export wp-content.
			if ( $options['include_uploads'] || $options['include_themes'] || $options['include_plugins'] ) {
				$this->export_wp_content( $zip, $options );
			}

			// Add config file.
			$this->add_config_file( $zip, $options );

			$zip->close();

			$this->update_status( 'completed', __( 'Export completed successfully.', 'restorewp' ), array(
				'filename' => $filename,
				'size'     => filesize( $backup_path ),
				'download_url' => $this->get_download_url( $filename ),
			) );

			return array(
				'filename' => $filename,
				'size'     => filesize( $backup_path ),
				'download_url' => $this->get_download_url( $filename ),
			);

		} catch ( Exception $e ) {
			$this->update_status( 'error', $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Export database to ZIP.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @param array      $options Export options.
	 */
	private function export_database( $zip, $options ) {
		global $wpdb;

		$this->update_status( 'database', __( 'Exporting database...', 'restorewp' ) );

		// Get all tables.
		$tables = $wpdb->get_col( "SHOW TABLES" );
		
		// Filter out excluded tables.
		if ( ! empty( $options['exclude_tables'] ) ) {
			$tables = array_diff( $tables, $options['exclude_tables'] );
		}

		$sql_content = '';
		$sql_content .= "-- RestoreWP Database Export\n";
		$sql_content .= "-- Generated on: " . date( 'Y-m-d H:i:s' ) . "\n\n";
		$sql_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
		$sql_content .= "SET time_zone = \"+00:00\";\n\n";

		foreach ( $tables as $table ) {
			$this->update_status( 'database', sprintf( __( 'Exporting table: %s', 'restorewp' ), $table ) );

			// Get table structure.
			$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_A );
			$sql_content .= "\n-- Table structure for table `{$table}`\n";
			$sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
			$sql_content .= $create_table['Create Table'] . ";\n\n";

			// Get table data.
			$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
			if ( $rows ) {
				$sql_content .= "-- Dumping data for table `{$table}`\n";
				foreach ( $rows as $row ) {
					$values = array();
					foreach ( $row as $value ) {
						$values[] = is_null( $value ) ? 'NULL' : "'" . esc_sql( $value ) . "'";
					}
					$sql_content .= "INSERT INTO `{$table}` VALUES (" . implode( ', ', $values ) . ");\n";
				}
				$sql_content .= "\n";
			}
		}

		$zip->addFromString( RESTOREWP_DATABASE_FILE, $sql_content );
	}

	/**
	 * Export wp-content to ZIP.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @param array      $options Export options.
	 */
	private function export_wp_content( $zip, $options ) {
		$wp_content_path = WP_CONTENT_DIR;

		if ( $options['include_uploads'] ) {
			$this->update_status( 'uploads', __( 'Exporting uploads...', 'restorewp' ) );
			$this->add_directory_to_zip( $zip, $wp_content_path . '/uploads', 'wp-content/uploads' );
		}

		if ( $options['include_themes'] ) {
			$this->update_status( 'themes', __( 'Exporting themes...', 'restorewp' ) );
			$this->add_directory_to_zip( $zip, $wp_content_path . '/themes', 'wp-content/themes', $options['exclude_themes'] );
		}

		if ( $options['include_plugins'] ) {
			$this->update_status( 'plugins', __( 'Exporting plugins...', 'restorewp' ) );
			$this->add_directory_to_zip( $zip, $wp_content_path . '/plugins', 'wp-content/plugins', $options['exclude_plugins'] );
		}
	}

	/**
	 * Add directory to ZIP archive.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @param string     $source_path Source directory path.
	 * @param string     $zip_path Path in ZIP archive.
	 * @param array      $exclude_items Items to exclude.
	 */
	private function add_directory_to_zip( $zip, $source_path, $zip_path, $exclude_items = array() ) {
		if ( ! is_dir( $source_path ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			$relative_path = substr( $file_path, strlen( $source_path ) + 1 );
			$zip_file_path = $zip_path . '/' . $relative_path;

			// Check if item should be excluded.
			if ( $this->should_exclude_item( $relative_path, $exclude_items ) ) {
				continue;
			}

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $zip_file_path );
			} elseif ( $file->isFile() ) {
				$zip->addFile( $file_path, $zip_file_path );
			}
		}
	}

	/**
	 * Check if item should be excluded.
	 *
	 * @param string $item_path Item path.
	 * @param array  $exclude_items Items to exclude.
	 * @return bool
	 */
	private function should_exclude_item( $item_path, $exclude_items ) {
		foreach ( $exclude_items as $exclude_item ) {
			if ( strpos( $item_path, $exclude_item ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add config file to ZIP.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @param array      $options Export options.
	 */
	private function add_config_file( $zip, $options ) {
		$config = array(
			'version'     => RESTOREWP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'mysql_version' => $this->get_mysql_version(),
			'site_url'    => get_site_url(),
			'home_url'    => get_home_url(),
			'options'     => $options,
			'created_at'  => current_time( 'mysql' ),
		);

		$zip->addFromString( RESTOREWP_CONFIG_FILE, wp_json_encode( $config, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get MySQL version.
	 *
	 * @return string
	 */
	private function get_mysql_version() {
		global $wpdb;
		return $wpdb->get_var( "SELECT VERSION()" );
	}

	/**
	 * Generate filename for backup.
	 *
	 * @return string
	 */
	private function generate_filename() {
		$site_name = sanitize_title( get_bloginfo( 'name' ) );
		$timestamp = date( 'Y-m-d_H-i-s' );
		return $site_name . '_' . $timestamp . RESTOREWP_ARCHIVE_EXTENSION;
	}

	/**
	 * Get download URL for backup file.
	 *
	 * @param string $filename Backup filename.
	 * @return string
	 */
	private function get_download_url( $filename ) {
		return admin_url( 'admin-ajax.php?action=restorewp_download&file=' . urlencode( $filename ) . '&nonce=' . wp_create_nonce( 'restorewp_download' ) );
	}

	/**
	 * Update export status.
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