<?php
/**
 * Plugin verification script
 * Add this to your wp-config.php temporarily: include_once(WP_CONTENT_DIR . '/plugins/restorewp/verify-plugin.php');
 */

// Force admin menu registration for testing
add_action('admin_menu', function() {
    // Force add menu regardless of capabilities for testing
    add_menu_page(
        'RestoreWP Test',
        'RestoreWP Test',
        'read', // Lower capability for testing
        'restorewp-test-menu',
        function() {
            echo '<div class="wrap">';
            echo '<h1>RestoreWP Plugin Verification</h1>';
            echo '<p><strong>Plugin Status:</strong> Active and Loading!</p>';
            echo '<p><strong>Current User:</strong> ' . wp_get_current_user()->user_login . '</p>';
            echo '<p><strong>User Capabilities:</strong></p>';
            echo '<ul>';
            $caps = ['manage_options', 'edit_posts', 'read'];
            foreach($caps as $cap) {
                $has = current_user_can($cap) ? 'YES' : 'NO';
                echo '<li>' . $cap . ': ' . $has . '</li>';
            }
            echo '</ul>';
            echo '<p><strong>Plugin Path:</strong> ' . RESTOREWP_PLUGIN_PATH . '</p>';
            echo '<p><strong>Assets URL:</strong> ' . RESTOREWP_ASSETS_URL . '</p>';
            echo '</div>';
        },
        'dashicons-migrate',
        21
    );
}, 999);

// Add admin notice
add_action('admin_notices', function() {
    if (current_user_can('read')) {
        echo '<div class="notice notice-success"><p><strong>RestoreWP Verification:</strong> Plugin is loading! Check "RestoreWP Test" in the admin menu.</p></div>';
    }
});
