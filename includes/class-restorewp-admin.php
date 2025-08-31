<?php
/**
 * Admin functionality.
 *
 * @package RestoreWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * RestoreWP_Admin Class.
 */
class RestoreWP_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var RestoreWP_Admin
	 */
	protected static $_instance = null;

	/**
	 * Main RestoreWP_Admin Instance.
	 *
	 * @return RestoreWP_Admin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * RestoreWP_Admin Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_filter( 'plugin_action_links_' . RESTOREWP_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'wp_ajax_restorewp_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_restorewp_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_restorewp_upload', array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_restorewp_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_restorewp_cancel', array( $this, 'ajax_cancel' ) );
		add_action( 'wp_ajax_restorewp_backup_list', array( $this, 'ajax_backup_list' ) );
		add_action( 'wp_ajax_restorewp_backup_delete', array( $this, 'ajax_backup_delete' ) );
		
		// Background process hook
		add_action( 'restorewp_background_process', array( 'RestoreWP_Background_Process', 'execute' ) );
		
		// Debug notice
		add_action( 'admin_notices', array( $this, 'debug_notice' ) );
	}

	/**
	 * Debug notice to verify plugin is loading.
	 */
	public function debug_notice() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-info is-dismissible"><p><strong>RestoreWP:</strong> Admin class loaded successfully!</p></div>';
		}
	}

	/**
	 * Add menu items.
	 */
	public function admin_menu() {
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'RestoreWP: admin_menu called' );
			error_log( 'RestoreWP: User can manage_options: ' . ( current_user_can( 'manage_options' ) ? 'YES' : 'NO' ) );
		}

		// Add main menu page below Pages (position 21).
		$page_hook = add_menu_page(
			__( 'RestoreWP', 'restorewp' ),
			__( 'RestoreWP', 'restorewp' ),
			'manage_options',
			'restorewp',
			array( $this, 'admin_page' ),
			'dashicons-migrate',
			21
		);

		// Add submenu pages
		add_submenu_page(
			'restorewp',
			__( 'Dashboard', 'restorewp' ),
			__( 'Dashboard', 'restorewp' ),
			'manage_options',
			'restorewp',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'restorewp',
			__( 'Export Site', 'restorewp' ),
			__( 'Export', 'restorewp' ),
			'manage_options',
			'restorewp-export',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'restorewp',
			__( 'Import Site', 'restorewp' ),
			__( 'Import', 'restorewp' ),
			'manage_options',
			'restorewp-import',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'restorewp',
			__( 'Manage Backups', 'restorewp' ),
			__( 'Backups', 'restorewp' ),
			'manage_options',
			'restorewp-backups',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'restorewp',
			__( 'Settings', 'restorewp' ),
			__( 'Settings', 'restorewp' ),
			'manage_options',
			'restorewp-settings',
			array( $this, 'settings_page' )
		);

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'RestoreWP: Menu pages added. Main page hook: ' . $page_hook );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function admin_scripts( $hook ) {
		// Only load on RestoreWP pages.
		if ( strpos( $hook, 'restorewp' ) === false ) {
			return;
		}

		// Enqueue admin script.
		wp_enqueue_script(
			'restorewp-admin',
			RESTOREWP_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			RESTOREWP_VERSION,
			true
		);

		wp_enqueue_style(
			'restorewp-admin',
			RESTOREWP_ASSETS_URL . 'css/admin.css',
			array( 'wp-components' ),
			RESTOREWP_VERSION
		);

		// Localize script.
		wp_localize_script(
			'restorewp-admin',
			'restoreWP',
			array(
				'nonce'        => wp_create_nonce( RESTOREWP_NONCE_ACTION ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'maxFileSize'  => RESTOREWP_MAX_UPLOAD_SIZE,
				'chunkSize'    => RESTOREWP_CHUNK_SIZE,
				'secretKey'    => get_option( RESTOREWP_SECRET_KEY_OPTION ),
				'strings'      => array(
					'export'         => __( 'Export', 'restorewp' ),
					'import'         => __( 'Import', 'restorewp' ),
					'backups'        => __( 'Backups', 'restorewp' ),
					'exportSite'     => __( 'Export Site', 'restorewp' ),
					'importSite'     => __( 'Import Site', 'restorewp' ),
					'manageBackups'  => __( 'Manage Backups', 'restorewp' ),
					'uploadFile'     => __( 'Upload File', 'restorewp' ),
					'selectFile'     => __( 'Select File', 'restorewp' ),
					'startExport'    => __( 'Start Export', 'restorewp' ),
					'startImport'    => __( 'Start Import', 'restorewp' ),
					'cancel'         => __( 'Cancel', 'restorewp' ),
					'delete'         => __( 'Delete', 'restorewp' ),
					'download'       => __( 'Download', 'restorewp' ),
					'restore'        => __( 'Restore', 'restorewp' ),
					'processing'     => __( 'Processing...', 'restorewp' ),
					'completed'      => __( 'Completed', 'restorewp' ),
					'failed'         => __( 'Failed', 'restorewp' ),
					'confirmDelete'  => __( 'Are you sure you want to delete this backup?', 'restorewp' ),
					'confirmRestore' => __( 'Are you sure you want to restore this backup? This will overwrite your current site.', 'restorewp' ),
				),
			)
		);
	}

	/**
	 * Add action links to plugin page.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'dashboard' => '<a href="' . admin_url( 'admin.php?page=restorewp' ) . '">' . __( 'Dashboard', 'restorewp' ) . '</a>',
			'settings'  => '<a href="' . admin_url( 'admin.php?page=restorewp-settings' ) . '">' . __( 'Settings', 'restorewp' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Add row meta links to plugin page.
	 *
	 * @param array  $links Existing row meta links.
	 * @param string $file Plugin file.
	 * @return array Modified row meta links.
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( RESTOREWP_PLUGIN_BASENAME === $file ) {
			$row_meta = array(
				'docs'    => '<a href="https://github.com/restorewp/restorewp" target="_blank">' . __( 'Documentation', 'restorewp' ) . '</a>',
				'support' => '<a href="https://github.com/restorewp/restorewp/issues" target="_blank">' . __( 'Support', 'restorewp' ) . '</a>',
			);

			return array_merge( $links, $row_meta );
		}

		return $links;
	}

	/**
	 * Admin page callback.
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'restorewp' ) );
		}

		// Get current page to set active tab.
		$current_page = $_GET['page'] ?? 'restorewp';
		$active_tab = 'export'; // default

		switch ( $current_page ) {
			case 'restorewp-export':
				$active_tab = 'export';
				break;
			case 'restorewp-import':
				$active_tab = 'import';
				break;
			case 'restorewp-backups':
				$active_tab = 'backups';
				break;
			default:
				$active_tab = 'export';
		}

		?>
		<div class="wrap">
			<div id="restorewp-admin-root" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
				<!-- JavaScript will replace this content -->
				<div style="padding: 20px; text-align: center;">
					<div class="spinner is-active" style="float: none; margin: 20px auto;"></div>
					<p>Loading RestoreWP Dashboard...</p>
				</div>
			</div>
		</div>
		
		<script type="text/javascript">
		// Ensure the dashboard loads even if there are issues
		jQuery(document).ready(function($) {
			// Add a fallback if the main script doesn't load
			setTimeout(function() {
				if ($('#restorewp-admin-root .restorewp-admin-container').length === 0) {
					console.log('RestoreWP: Main script not loaded, checking...');
					if (typeof RestoreWPAdmin !== 'undefined') {
						console.log('RestoreWP: Manually initializing...');
						RestoreWPAdmin.init();
					} else {
						console.error('RestoreWP: Admin script not loaded properly');
						$('#restorewp-admin-root').html('<div class="notice notice-error"><p>RestoreWP Dashboard failed to load. Please check browser console for errors.</p></div>');
					}
				}
			}, 1000);
		});
		</script>
		<?php
	}

	/**
	 * Settings page callback.
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'restorewp' ) );
		}

		// Handle settings form submission.
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['restorewp_settings_nonce'], 'restorewp_settings' ) ) {
			$this->save_settings();
		}

		$settings = restorewp_get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'restorewp_settings', 'restorewp_settings_nonce' ); ?>
				
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="max_backup_retention"><?php _e( 'Backup Retention (days)', 'restorewp' ); ?></label>
							</th>
							<td>
								<input type="number" id="max_backup_retention" name="max_backup_retention" value="<?php echo esc_attr( $settings['max_backup_retention'] ); ?>" min="1" max="365" />
								<p class="description"><?php _e( 'Number of days to keep automatic backups before cleanup.', 'restorewp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="auto_cleanup"><?php _e( 'Auto Cleanup', 'restorewp' ); ?></label>
							</th>
							<td>
								<label for="auto_cleanup">
									<input type="checkbox" id="auto_cleanup" name="auto_cleanup" value="1" <?php checked( $settings['auto_cleanup'] ); ?> />
									<?php _e( 'Automatically delete old backups based on retention period', 'restorewp' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="email_notifications"><?php _e( 'Email Notifications', 'restorewp' ); ?></label>
							</th>
							<td>
								<label for="email_notifications">
									<input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php checked( $settings['email_notifications'] ); ?> />
									<?php _e( 'Send email notifications for backup operations', 'restorewp' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="notification_email"><?php _e( 'Notification Email', 'restorewp' ); ?></label>
							</th>
							<td>
								<input type="email" id="notification_email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="regular-text" />
								<p class="description"><?php _e( 'Email address to receive backup notifications.', 'restorewp' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				
				<h2><?php _e( 'System Information', 'restorewp' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<?php
						$system_info = restorewp_get_system_info();
						foreach ( $system_info as $key => $value ) :
							$label = ucwords( str_replace( '_', ' ', $key ) );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><code><?php echo esc_html( $value ); ?></code></td>
						</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row"><?php _e( 'Storage Path', 'restorewp' ); ?></th>
							<td>
								<code><?php echo esc_html( RESTOREWP_STORAGE_PATH ); ?></code>
								<?php if ( is_writable( RESTOREWP_STORAGE_PATH ) ) : ?>
									<span style="color: green;">✓ <?php _e( 'Writable', 'restorewp' ); ?></span>
								<?php else : ?>
									<span style="color: red;">✗ <?php _e( 'Not Writable', 'restorewp' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	private function save_settings() {
		$settings = array(
			'max_backup_retention' => absint( $_POST['max_backup_retention'] ?? 30 ),
			'auto_cleanup'         => ! empty( $_POST['auto_cleanup'] ),
			'email_notifications'  => ! empty( $_POST['email_notifications'] ),
			'notification_email'   => sanitize_email( $_POST['notification_email'] ?? get_option( 'admin_email' ) ),
		);

		if ( restorewp_update_settings( $settings ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved successfully.', 'restorewp' ) . '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Failed to save settings.', 'restorewp' ) . '</p></div>';
			} );
		}
	}

	/**
	 * AJAX export handler.
	 */
	public function ajax_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
		}

		try {
			$background_process = new RestoreWP_Background_Process( 'export' );
			$process_id = $background_process->start( $_POST );
			
			wp_send_json_success( array( 
				'process_id' => $process_id,
				'message' => __( 'Export started in background', 'restorewp' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX import handler.
	 */
	public function ajax_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
		}

		try {
			$background_process = new RestoreWP_Background_Process( 'import' );
			$process_id = $background_process->start( $_POST );
			
			wp_send_json_success( array( 
				'process_id' => $process_id,
				'message' => __( 'Import started in background', 'restorewp' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX cancel handler.
	 */
	public function ajax_cancel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
		}

		$process_id = sanitize_text_field( $_POST['process_id'] ?? '' );
		
		if ( empty( $process_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid process ID', 'restorewp' ) ) );
		}

		$cancelled = RestoreWP_Background_Process::cancel( $process_id );
		
		if ( $cancelled ) {
			wp_send_json_success( array( 'message' => __( 'Process cancelled successfully', 'restorewp' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not cancel process', 'restorewp' ) ) );
		}
	}

	/**
	 * AJAX upload handler.
	 */
	public function ajax_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
		}

		try {
			$import = new RestoreWP_Import();
			$result = $import->handle_upload( $_FILES, $_POST );
			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX status handler.
	 */
	public function ajax_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		// Handle new background process status requests
		if ( isset( $_POST['process_id'] ) ) {
			if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
				wp_die( -1, 403 );
			}

			$process_id = sanitize_text_field( $_POST['process_id'] );
			
			if ( empty( $process_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid process ID', 'restorewp' ) ) );
			}

			$status = RestoreWP_Background_Process::get_status( $process_id );
			
			if ( $status ) {
				wp_send_json_success( $status );
			} else {
				wp_send_json_error( array( 'message' => __( 'Process not found', 'restorewp' ) ) );
			}
		} else {
			// Handle legacy status requests
			$secret_key = $_GET['secret_key'] ?? '';
			if ( $secret_key !== get_option( RESTOREWP_SECRET_KEY_OPTION ) ) {
				wp_die( -1, 403 );
			}

			$status = get_transient( 'restorewp_status_' . $secret_key );
			wp_send_json_success( $status ?: array( 'status' => 'idle' ) );
		}
	}

	/**
	 * AJAX backup list handler.
	 */
	public function ajax_backup_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
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
	 * AJAX backup delete handler.
	 */
	public function ajax_backup_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], RESTOREWP_NONCE_ACTION ) ) {
			wp_die( -1, 403 );
		}

		try {
			$backup = new RestoreWP_Backup();
			$result = $backup->delete_backup( $_POST['filename'] );
			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
