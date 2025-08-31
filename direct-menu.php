<?php
/**
 * Direct menu registration for RestoreWP
 */

// Direct menu registration
add_action('admin_menu', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Add main RestoreWP menu
    add_menu_page(
        'RestoreWP Dashboard',
        'RestoreWP',
        'manage_options',
        'restorewp-direct',
        function() {
            ?>
            <div class="wrap">
                <div id="restorewp-admin-root" data-active-tab="export">
                    <div style="padding: 20px; text-align: center;">
                        <div class="spinner is-active" style="float: none; margin: 20px auto;"></div>
                        <p>Loading RestoreWP Dashboard...</p>
                    </div>
                </div>
            </div>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Load CSS
                $('<link>')
                    .appendTo('head')
                    .attr({
                        type: 'text/css',
                        rel: 'stylesheet',
                        href: '<?php echo RESTOREWP_ASSETS_URL; ?>css/admin.css?v=<?php echo RESTOREWP_VERSION; ?>'
                    });
                
                // Load JS
                $.getScript('<?php echo RESTOREWP_ASSETS_URL; ?>js/admin.js?v=<?php echo RESTOREWP_VERSION; ?>')
                    .done(function() {
                        console.log('RestoreWP: Script loaded successfully');
                        if (typeof RestoreWPAdmin !== 'undefined') {
                            RestoreWPAdmin.init();
                        }
                    })
                    .fail(function() {
                        console.error('RestoreWP: Failed to load admin script');
                        $('#restorewp-admin-root').html('<div class="notice notice-error"><p>Failed to load RestoreWP dashboard. Check console for errors.</p></div>');
                    });
                
                // Set up restoreWP object
                window.restoreWP = {
                    nonce: '<?php echo wp_create_nonce(RESTOREWP_NONCE_ACTION); ?>',
                    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                    maxFileSize: <?php echo RESTOREWP_MAX_UPLOAD_SIZE; ?>,
                    chunkSize: <?php echo RESTOREWP_CHUNK_SIZE; ?>,
                    secretKey: '<?php echo get_option(RESTOREWP_SECRET_KEY_OPTION); ?>',
                    strings: {
                        export: 'Export',
                        import: 'Import',
                        backups: 'Backups',
                        exportSite: 'Export Site',
                        importSite: 'Import Site',
                        manageBackups: 'Manage Backups',
                        uploadFile: 'Upload File',
                        selectFile: 'Select File',
                        startExport: 'Start Export',
                        startImport: 'Start Import',
                        cancel: 'Cancel',
                        delete: 'Delete',
                        download: 'Download',
                        restore: 'Restore',
                        processing: 'Processing...',
                        completed: 'Completed',
                        failed: 'Failed',
                        confirmDelete: 'Are you sure you want to delete this backup?',
                        confirmRestore: 'Are you sure you want to restore this backup? This will overwrite your current site.'
                    }
                };
            });
            </script>
            <?php
        },
        'dashicons-migrate',
        21
    );
}, 5);
