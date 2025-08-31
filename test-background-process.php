<?php
/**
 * Test background processing functionality
 * 
 * This file can be run to test if the background processing works correctly
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	// For testing outside WordPress, define basic constants
	define( 'ABSPATH', dirname( __FILE__ ) . '/../../../' );
	
	// Load WordPress
	require_once ABSPATH . 'wp-config.php';
	require_once ABSPATH . 'wp-load.php';
}

// Only allow admin users
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied' );
}

echo '<h1>RestoreWP Background Process Test</h1>';

// Test 1: Check if classes are loaded
echo '<h2>Class Loading Test</h2>';
if ( class_exists( 'RestoreWP_Background_Process' ) ) {
	echo '✅ RestoreWP_Background_Process class loaded<br>';
} else {
	echo '❌ RestoreWP_Background_Process class NOT loaded<br>';
}

if ( class_exists( 'RestoreWP_Export' ) ) {
	echo '✅ RestoreWP_Export class loaded<br>';
} else {
	echo '❌ RestoreWP_Export class NOT loaded<br>';
}

// Test 2: Test background process creation
echo '<h2>Background Process Creation Test</h2>';
try {
	$background_process = new RestoreWP_Background_Process( 'export' );
	echo '✅ Background process instance created<br>';
	
	// Test process start (without actually running)
	$process_id = uniqid( 'test_export_' );
	echo "✅ Test process ID generated: {$process_id}<br>";
	
} catch ( Exception $e ) {
	echo '❌ Error creating background process: ' . $e->getMessage() . '<br>';
}

// Test 3: Test status management
echo '<h2>Status Management Test</h2>';
try {
	$test_process_id = 'test_' . time();
	
	// Test status update
	RestoreWP_Background_Process::update_status( 
		$test_process_id, 
		'testing', 
		'This is a test status', 
		50 
	);
	echo '✅ Status update successful<br>';
	
	// Test status retrieval
	$status = RestoreWP_Background_Process::get_status( $test_process_id );
	if ( $status && $status['status'] === 'testing' ) {
		echo '✅ Status retrieval successful<br>';
		echo 'Status: ' . $status['status'] . '<br>';
		echo 'Message: ' . $status['message'] . '<br>';
		echo 'Progress: ' . $status['progress'] . '%<br>';
	} else {
		echo '❌ Status retrieval failed<br>';
	}
	
	// Test cancellation
	$cancelled = RestoreWP_Background_Process::cancel( $test_process_id );
	if ( $cancelled ) {
		echo '✅ Process cancellation successful<br>';
	} else {
		echo '❌ Process cancellation failed<br>';
	}
	
} catch ( Exception $e ) {
	echo '❌ Error in status management: ' . $e->getMessage() . '<br>';
}

// Test 4: Check WordPress cron functionality
echo '<h2>WordPress Cron Test</h2>';
if ( function_exists( 'wp_schedule_single_event' ) ) {
	echo '✅ wp_schedule_single_event function available<br>';
} else {
	echo '❌ wp_schedule_single_event function NOT available<br>';
}

// Check if our hook is registered
if ( has_action( 'restorewp_background_process' ) ) {
	echo '✅ restorewp_background_process hook registered<br>';
} else {
	echo '❌ restorewp_background_process hook NOT registered<br>';
}

echo '<h2>Test Complete</h2>';
echo '<p><a href="' . admin_url( 'admin.php?page=restorewp' ) . '">← Back to RestoreWP Dashboard</a></p>';
