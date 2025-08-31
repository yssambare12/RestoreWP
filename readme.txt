=== RestoreWP - Migration & Backup ===
Contributors: restorewp
Tags: migration, backup, export, import, restore
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Modern WordPress migration and backup plugin with support for files up to 2GB and clean React-based interface.

== Description ==

RestoreWP is a powerful yet easy-to-use WordPress migration and backup plugin that allows you to:

* **Export** your entire WordPress site (database, uploads, themes, plugins)
* **Import** backups up to 2GB in size
* **Create and manage** local backups
* **Automatic URL replacement** during import
* **Modern React-based** admin interface
* **Secure** with proper nonces, capabilities, and sanitization

= Key Features =

* **Large File Support**: Import files up to 2GB (not limited to 512MB like some plugins)
* **Modern UI**: Clean, React-based admin dashboard
* **Security First**: Follows WordPress.org security guidelines
* **Standard ZIP Format**: Uses standard ZIP archives, not proprietary formats
* **Selective Export**: Choose what to export (database, uploads, themes, plugins)
* **Rollback Protection**: Option to create backup before import
* **Developer Friendly**: Includes WordPress hooks and filters for extensibility
* **WP-CLI Support**: Command line interface for exports and imports

= Export Features =

* Export entire WordPress site to ZIP archive
* Selective export: choose database, uploads, themes, plugins
* Exclude specific database tables, plugins, or themes
* Export only database or only wp-content
* Progress tracking with status messages

= Import Features =

* Import ZIP backups up to 2GB
* Chunked upload support to bypass server limits
* Automatic URL search and replace with preview
* Compatibility validation (PHP, WordPress, MySQL versions)
* Background processing with progress indicators
* Rollback option: backup current site before import

= Backup Management =

* Local backup storage in `/wp-content/restorewp-backups/`
* Manual backup creation from dashboard
* List, download, and delete backups from UI
* Automatic cleanup of old backups

= Security Features =

* Restricted to users with `manage_options` capability
* Nonce verification for all AJAX actions
* Input sanitization and output escaping
* Rate limiting to prevent abuse
* Secure backup file storage with .htaccess protection

= Developer Features =

* WordPress hooks: `restorewp_before_export`, `restorewp_after_import`, etc.
* WP-CLI commands for automation
* REST API endpoints for custom integrations
* Translation ready with i18n support

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/restorewp/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'RestoreWP' in your admin menu to start using the plugin

== Frequently Asked Questions ==

= What file formats are supported? =

RestoreWP uses standard ZIP archives. This ensures compatibility and allows you to extract backups manually if needed.

= What's the maximum file size I can import? =

RestoreWP supports imports up to 2GB, significantly larger than the default WordPress upload limit.

= Is it compatible with multisite? =

Currently, RestoreWP is designed for single WordPress installations. Multisite support may be added in future versions.

= Can I schedule automatic backups? =

Manual backup creation is supported in this version. Scheduled backups may be added in future releases.

== Screenshots ==

1. Export interface with selective options
2. Import interface with drag-and-drop file upload
3. Backup management with download and delete options
4. Progress indicators during export/import operations

== Changelog ==

= 1.0.0 =
* Initial release
* Export/import functionality with ZIP archives
* Support for files up to 2GB
* Modern React-based admin interface
* Security features with proper WordPress standards
* Local backup management
* URL search and replace functionality
* WP-CLI support

== Upgrade Notice ==

= 1.0.0 =
Initial release of RestoreWP - a modern alternative to traditional migration plugins.