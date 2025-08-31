<?php
/**
 * Simple test to verify plugin loading
 */

// Add this to wp-config.php temporarily: define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);

add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-info"><p>RestoreWP Plugin Test: Plugin is loading correctly!</p></div>';
    }
});

// Test menu registration
add_action('admin_menu', function() {
    error_log('RestoreWP: admin_menu hook fired');
    
    if (!current_user_can('manage_options')) {
        error_log('RestoreWP: User does not have manage_options capability');
        return;
    }
    
    $page = add_menu_page(
        'RestoreWP Test',
        'RestoreWP Test',
        'manage_options',
        'restorewp-test',
        function() {
            echo '<div class="wrap"><h1>RestoreWP Test Page</h1><p>If you see this, the menu is working!</p></div>';
        },
        'dashicons-migrate',
        21
    );
    
    error_log('RestoreWP: Menu page added: ' . $page);
}, 999);
