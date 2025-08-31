<?php
/**
 * WP-CLI commands for RestoreWP.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * RestoreWP CLI commands.
 */
class RestoreWP_CLI {

	/**
	 * Export WordPress site.
	 *
	 * ## OPTIONS
	 *
	 * [--filename=<filename>]
	 * : Output filename for the backup
	 *
	 * [--exclude-database]
	 * : Exclude database from export
	 *
	 * [--exclude-uploads]
	 * : Exclude media uploads from export
	 *
	 * [--exclude-themes]
	 * : Exclude themes from export
	 *
	 * [--exclude-plugins]
	 * : Exclude plugins from export
	 *
	 * [--exclude-tables=<tables>]
	 * : Comma-separated list of database tables to exclude
	 *
	 * ## EXAMPLES
	 *
	 *     wp restorewp export
	 *     wp restorewp export --filename=my-backup.zip
	 *     wp restorewp export --exclude-uploads --exclude-themes
	 *     wp restorewp export --exclude-tables=wp_posts,wp_comments
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		$options = array(
			'include_database' => ! isset( $assoc_args['exclude-database'] ),
			'include_uploads'  => ! isset( $assoc_args['exclude-uploads'] ),
			'include_themes'   => ! isset( $assoc_args['exclude-themes'] ),
			'include_plugins'  => ! isset( $assoc_args['exclude-plugins'] ),
			'exclude_tables'   => array(),
		);

		// Handle excluded tables.
		if ( isset( $assoc_args['exclude-tables'] ) ) {
			$options['exclude_tables'] = explode( ',', $assoc_args['exclude-tables'] );
			$options['exclude_tables'] = array_map( 'trim', $options['exclude_tables'] );
		}

		WP_CLI::log( __( 'Starting export...', 'restorewp' ) );

		try {
			$export = new RestoreWP_Export();
			$result = $export->start( $options );

			WP_CLI::success( sprintf( 
				__( 'Export completed! File: %s (Size: %s)', 'restorewp' ),
				$result['filename'],
				size_format( $result['size'] )
			) );

		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( __( 'Export failed: %s', 'restorewp' ), $e->getMessage() ) );
		}
	}

	/**
	 * Import WordPress site from backup file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the backup file to import
	 *
	 * [--old-url=<url>]
	 * : Old site URL to replace in database
	 *
	 * [--new-url=<url>]
	 * : New site URL for replacement
	 *
	 * [--no-backup]
	 * : Skip creating backup before import
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp restorewp import backup.zip
	 *     wp restorewp import backup.zip --old-url=https://old-site.com --new-url=https://new-site.com
	 *     wp restorewp import backup.zip --no-backup --yes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( __( 'Please provide a backup file path.', 'restorewp' ) );
		}

		$file_path = $args[0];

		// Check if file exists.
		if ( ! file_exists( $file_path ) ) {
			WP_CLI::error( sprintf( __( 'File not found: %s', 'restorewp' ), $file_path ) );
		}

		// Validate file.
		if ( ! RestoreWP_Security::validate_file_extension( $file_path ) ) {
			WP_CLI::error( __( 'Invalid file type. Please provide a ZIP file.', 'restorewp' ) );
		}

		$options = array(
			'filename'      => basename( $file_path ),
			'create_backup' => ! isset( $assoc_args['no-backup'] ),
			'old_url'       => $assoc_args['old-url'] ?? '',
			'new_url'       => $assoc_args['new-url'] ?? '',
		);

		// Copy file to storage directory.
		$storage_path = RESTOREWP_STORAGE_PATH . '/' . $options['filename'];
		if ( ! copy( $file_path, $storage_path ) ) {
			WP_CLI::error( __( 'Could not copy file to storage directory.', 'restorewp' ) );
		}

		// Confirmation prompt.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( __( 'This will import the backup and may overwrite your current site. Continue?', 'restorewp' ) );
		}

		WP_CLI::log( __( 'Starting import...', 'restorewp' ) );

		try {
			$import = new RestoreWP_Import();
			$result = $import->start( $options );

			WP_CLI::success( __( 'Import completed successfully!', 'restorewp' ) );

		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( __( 'Import failed: %s', 'restorewp' ), $e->getMessage() ) );
		}
	}

	/**
	 * List available backups.
	 *
	 * ## EXAMPLES
	 *
	 *     wp restorewp backups
	 *     wp restorewp backups --format=table
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function backups( $args, $assoc_args ) {
		try {
			$backup = new RestoreWP_Backup();
			$backups = $backup->list_backups();

			if ( empty( $backups ) ) {
				WP_CLI::log( __( 'No backups found.', 'restorewp' ) );
				return;
			}

			$table_data = array();
			foreach ( $backups as $backup_info ) {
				$table_data[] = array(
					'filename' => $backup_info['filename'],
					'size'     => $backup_info['size_human'],
					'created'  => date( 'Y-m-d H:i:s', $backup_info['created'] ),
				);
			}

			WP_CLI\Utils\format_items( 
				$assoc_args['format'] ?? 'table',
				$table_data,
				array( 'filename', 'size', 'created' )
			);

		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( __( 'Failed to list backups: %s', 'restorewp' ), $e->getMessage() ) );
		}
	}

	/**
	 * Delete a backup file.
	 *
	 * ## OPTIONS
	 *
	 * <filename>
	 * : Filename of the backup to delete
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp restorewp delete backup.zip
	 *     wp restorewp delete backup.zip --yes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function delete( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( __( 'Please provide a backup filename.', 'restorewp' ) );
		}

		$filename = $args[0];

		// Confirmation prompt.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( sprintf( __( 'Delete backup "%s"?', 'restorewp' ), $filename ) );
		}

		try {
			$backup = new RestoreWP_Backup();
			$backup->delete_backup( $filename );

			WP_CLI::success( sprintf( __( 'Backup "%s" deleted successfully.', 'restorewp' ), $filename ) );

		} catch ( Exception $e ) {
			WP_CLI::error( sprintf( __( 'Failed to delete backup: %s', 'restorewp' ), $e->getMessage() ) );
		}
	}

	/**
	 * Show system information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp restorewp info
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function info( $args, $assoc_args ) {
		$system_info = restorewp_get_system_info();

		WP_CLI::log( __( 'RestoreWP System Information:', 'restorewp' ) );
		WP_CLI::log( '================================' );

		foreach ( $system_info as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			WP_CLI::log( sprintf( '%-20s: %s', $label, $value ) );
		}

		$backup_count = count( ( new RestoreWP_Backup() )->list_backups() );
		WP_CLI::log( sprintf( '%-20s: %d', 'Total Backups', $backup_count ) );

		$storage_path = RESTOREWP_STORAGE_PATH;
		WP_CLI::log( sprintf( '%-20s: %s', 'Storage Path', $storage_path ) );
		WP_CLI::log( sprintf( '%-20s: %s', 'Storage Writable', is_writable( $storage_path ) ? 'Yes' : 'No' ) );
	}
}

// Register CLI commands if WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'restorewp', 'RestoreWP_CLI' );
}