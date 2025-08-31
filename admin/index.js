/**
 * RestoreWP Admin Dashboard
 */

import { render } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AdminApp from './components/AdminApp';

/**
 * Initialize the admin app.
 */
function initializeAdminApp() {
	const container = document.getElementById( 'restorewp-admin-root' );
	
	if ( container ) {
		render( <AdminApp />, container );
	}
}

// Initialize when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initializeAdminApp );
} else {
	initializeAdminApp();
}