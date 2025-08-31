<?php
/**
 * Debug script to check user permissions
 * Add ?debug_permissions=1 to your admin URL to see this output
 */

if ( isset( $_GET['debug_permissions'] ) && current_user_can( 'manage_options' ) ) {
    echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo '<h3>RestoreWP Permission Debug</h3>';
    
    echo '<p><strong>Current User ID:</strong> ' . get_current_user_id() . '</p>';
    echo '<p><strong>User Roles:</strong> ' . implode( ', ', wp_get_current_user()->roles ) . '</p>';
    
    $capabilities = array( 'manage_options', 'export', 'import', 'upload_files', 'delete_posts' );
    foreach ( $capabilities as $cap ) {
        $can = current_user_can( $cap ) ? 'YES' : 'NO';
        $color = current_user_can( $cap ) ? 'green' : 'red';
        echo '<p><strong>' . $cap . ':</strong> <span style="color: ' . $color . ';">' . $can . '</span></p>';
    }
    
    echo '<p><strong>Plugin Active:</strong> ' . ( is_plugin_active( 'restorewp/restorewp.php' ) ? 'YES' : 'NO' ) . '</p>';
    echo '<p><strong>Admin Class Exists:</strong> ' . ( class_exists( 'RestoreWP_Admin' ) ? 'YES' : 'NO' ) . '</p>';
    
    echo '</div>';
}