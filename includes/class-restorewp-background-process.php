<?php
/**
 * Background process handler for export/import operations.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Background_Process Class.
 */
class RestoreWP_Background_Process {

	/**
	 * Process ID.
	 *
	 * @var string
	 */
	private $process_id;

	/**
	 * Process type (export/import).
	 *
	 * @var string
	 */
	private $process_type;

	/**
	 * Constructor.
	 *
	 * @param string $process_type Process type.
	 */
	public function __construct( $process_type = 'export' ) {
		$this->process_type = $process_type;
		$this->process_id = uniqid( $process_type . '_' );
	}

	/**
	 * Start background process.
	 *
	 * @param array $options Process options.
	 * @return string Process ID.
	 */
	public function start( $options = array() ) {
		// Store process data
		$process_data = array(
			'id' => $this->process_id,
			'type' => $this->process_type,
			'status' => 'starting',
			'progress' => 0,
			'message' => __( 'Initializing...', 'restorewp' ),
			'options' => $options,
			'started_at' => time(),
			'can_cancel' => true,
		);

		set_transient( 'restorewp_process_' . $this->process_id, $process_data, 3600 );

		// Schedule background task
		wp_schedule_single_event( time(), 'restorewp_background_process', array( $this->process_id ) );

		return $this->process_id;
	}

	/**
	 * Cancel process.
	 *
	 * @param string $process_id Process ID.
	 * @return bool Success.
	 */
	public static function cancel( $process_id ) {
		$process_data = get_transient( 'restorewp_process_' . $process_id );
		
		if ( ! $process_data || ! $process_data['can_cancel'] ) {
			return false;
		}

		// Mark as cancelled
		$process_data['status'] = 'cancelled';
		$process_data['message'] = __( 'Process cancelled by user', 'restorewp' );
		$process_data['can_cancel'] = false;
		
		set_transient( 'restorewp_process_' . $process_id, $process_data, 300 );

		// Clean up any temporary files
		self::cleanup_process( $process_id );

		return true;
	}

	/**
	 * Get process status.
	 *
	 * @param string $process_id Process ID.
	 * @return array|false Process data or false if not found.
	 */
	public static function get_status( $process_id ) {
		return get_transient( 'restorewp_process_' . $process_id );
	}

	/**
	 * Update process status.
	 *
	 * @param string $process_id Process ID.
	 * @param string $status Status.
	 * @param string $message Message.
	 * @param int    $progress Progress percentage.
	 * @param array  $data Additional data.
	 */
	public static function update_status( $process_id, $status, $message, $progress = null, $data = array() ) {
		$process_data = get_transient( 'restorewp_process_' . $process_id );
		
		if ( ! $process_data ) {
			return;
		}

		$process_data['status'] = $status;
		$process_data['message'] = $message;
		
		if ( $progress !== null ) {
			$process_data['progress'] = $progress;
		}

		if ( ! empty( $data ) ) {
			$process_data['data'] = $data;
		}

		// Update cancellation status
		$process_data['can_cancel'] = ! in_array( $status, array( 'completed', 'error', 'cancelled' ) );

		set_transient( 'restorewp_process_' . $process_id, $process_data, 3600 );
	}

	/**
	 * Execute background process.
	 *
	 * @param string $process_id Process ID.
	 */
	public static function execute( $process_id ) {
		$process_data = get_transient( 'restorewp_process_' . $process_id );
		
		if ( ! $process_data || $process_data['status'] === 'cancelled' ) {
			return;
		}

		try {
			if ( $process_data['type'] === 'export' ) {
				self::execute_export( $process_id, $process_data );
			} elseif ( $process_data['type'] === 'import' ) {
				self::execute_import( $process_id, $process_data );
			}
		} catch ( Exception $e ) {
			self::update_status( $process_id, 'error', $e->getMessage() );
		}
	}

	/**
	 * Execute export process.
	 *
	 * @param string $process_id Process ID.
	 * @param array  $process_data Process data.
	 */
	private static function execute_export( $process_id, $process_data ) {
		// Check if cancelled
		if ( self::is_cancelled( $process_id ) ) {
			return;
		}

		self::update_status( $process_id, 'running', __( 'Starting export...', 'restorewp' ), 5 );

		$export = new RestoreWP_Export();
		$export->set_process_id( $process_id );
		
		$result = $export->start( $process_data['options'] );
		
		self::update_status( $process_id, 'completed', __( 'Export completed successfully', 'restorewp' ), 100, $result );
	}

	/**
	 * Execute import process.
	 *
	 * @param string $process_id Process ID.
	 * @param array  $process_data Process data.
	 */
	private static function execute_import( $process_id, $process_data ) {
		// Check if cancelled
		if ( self::is_cancelled( $process_id ) ) {
			return;
		}

		self::update_status( $process_id, 'running', __( 'Starting import...', 'restorewp' ), 5 );

		$import = new RestoreWP_Import();
		$import->set_process_id( $process_id );
		
		$result = $import->start( $process_data['options'] );
		
		self::update_status( $process_id, 'completed', __( 'Import completed successfully', 'restorewp' ), 100, $result );
	}

	/**
	 * Check if process is cancelled.
	 *
	 * @param string $process_id Process ID.
	 * @return bool
	 */
	private static function is_cancelled( $process_id ) {
		$process_data = get_transient( 'restorewp_process_' . $process_id );
		return $process_data && $process_data['status'] === 'cancelled';
	}

	/**
	 * Clean up process files.
	 *
	 * @param string $process_id Process ID.
	 */
	private static function cleanup_process( $process_id ) {
		// Clean up any temporary files created during the process
		$temp_files = get_transient( 'restorewp_temp_files_' . $process_id );
		
		if ( $temp_files && is_array( $temp_files ) ) {
			foreach ( $temp_files as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
			delete_transient( 'restorewp_temp_files_' . $process_id );
		}
	}
}
