<?php
/**
 * REST API functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_REST_API Class.
 */
class RestoreWP_REST_API {

	/**
	 * API namespace.
	 */
	const API_NAMESPACE = 'restorewp/v1';

	/**
	 * Initialize REST API routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		// Export endpoint
		register_rest_route( self::API_NAMESPACE, '/export', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'export' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
			'args'                => array(
				'include_database' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'include_uploads' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'include_themes' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'include_plugins' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'exclude_tables' => array(
					'type'    => 'array',
					'default' => array(),
				),
			),
		) );

		// Import endpoint
		register_rest_route( self::API_NAMESPACE, '/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
			'args'                => array(
				'filename' => array(
					'type'     => 'string',
					'required' => true,
				),
				'create_backup' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'old_url' => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'new_url' => array(
					'type'   => 'string',
					'format' => 'uri',
				),
			),
		) );

		// Upload endpoint
		register_rest_route( self::API_NAMESPACE, '/upload', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'upload' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
		) );

		// Backups list endpoint
		register_rest_route( self::API_NAMESPACE, '/backups', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'list_backups' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
		) );

		// Delete backup endpoint
		register_rest_route( self::API_NAMESPACE, '/backups/(?P<filename>[a-zA-Z0-9._-]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_backup' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
			'args'                => array(
				'filename' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		) );

		// Status endpoint
		register_rest_route( self::API_NAMESPACE, '/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_status' ),
			'permission_callback' => array( __CLASS__, 'check_status_permissions' ),
			'args'                => array(
				'secret_key' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		) );

		// System info endpoint
		register_rest_route( self::API_NAMESPACE, '/system-info', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_system_info' ),
			'permission_callback' => array( __CLASS__, 'check_permissions' ),
		) );
	}

	/**
	 * Check permissions for standard operations.
	 *
	 * @return bool
	 */
	public static function check_permissions() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check permissions for status endpoint (uses secret key).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function check_status_permissions( $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		return RestoreWP_Security::verify_secret_key( $secret_key );
	}

	/**
	 * Export endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function export( $request ) {
		try {
			$options = array(
				'include_database' => $request->get_param( 'include_database' ),
				'include_uploads'  => $request->get_param( 'include_uploads' ),
				'include_themes'   => $request->get_param( 'include_themes' ),
				'include_plugins'  => $request->get_param( 'include_plugins' ),
				'exclude_tables'   => $request->get_param( 'exclude_tables' ),
			);

			$export = new RestoreWP_Export();
			$result = $export->start( $options );

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Import endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function import( $request ) {
		try {
			$options = array(
				'filename'      => $request->get_param( 'filename' ),
				'create_backup' => $request->get_param( 'create_backup' ),
				'old_url'       => $request->get_param( 'old_url' ),
				'new_url'       => $request->get_param( 'new_url' ),
			);

			$import = new RestoreWP_Import();
			$result = $import->start( $options );

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Upload endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function upload( $request ) {
		try {
			$import = new RestoreWP_Import();
			$result = $import->handle_upload( $_FILES, $request->get_params() );

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * List backups endpoint callback.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_backups() {
		try {
			$backup = new RestoreWP_Backup();
			$backups = $backup->list_backups();

			return new WP_REST_Response( $backups, 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Delete backup endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function delete_backup( $request ) {
		try {
			$filename = $request->get_param( 'filename' );
			$backup = new RestoreWP_Backup();
			$result = $backup->delete_backup( $filename );

			return new WP_REST_Response( array( 'success' => $result ), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Status endpoint callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_status( $request ) {
		$secret_key = $request->get_param( 'secret_key' );
		$status = get_transient( 'restorewp_status_' . $secret_key );
		
		return new WP_REST_Response( $status ?: array( 'status' => 'idle' ), 200 );
	}

	/**
	 * System info endpoint callback.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_system_info() {
		$system_info = restorewp_get_system_info();
		return new WP_REST_Response( $system_info, 200 );
	}
}

// Initialize REST API.
RestoreWP_REST_API::init();