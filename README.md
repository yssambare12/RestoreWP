# RestoreWP - WordPress Migration & Backup Plugin

A modern WordPress migration and backup plugin with a React-based UI that allows you to export, import, and restore WordPress sites with support for files up to 2GB.

## Features

- **Complete Site Export**: Export your entire WordPress site including database, media files, themes, and plugins
- **Smart Import**: Import backups while preserving the current site's domain (automatic URL replacement)
- **Large File Support**: Handle files up to 2GB with chunked upload processing
- **Progress Tracking**: Real-time progress indicators with detailed status messages
- **Backup Management**: Create, download, and delete backups from an intuitive dashboard
- **Security Features**: Built-in security measures to protect backup files
- **Rollback Protection**: Automatic backup creation before importing (optional)
- **Modern UI**: Clean, responsive interface built with WordPress admin styles

## System Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory**: 256MB minimum (512MB recommended)
- **File Permissions**: Write access to wp-content directory

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate Plugin"

## Usage

### Exporting a Site

1. Navigate to **RestoreWP → Dashboard** in your WordPress admin
2. Select the **Export** tab
3. Choose what to include:
   - Database (posts, pages, settings, users, etc.)
   - Media Files (uploads folder)
   - Themes
   - Plugins
4. Click **Start Export**
5. Monitor the progress popup showing real-time status
6. Download the backup ZIP file when complete

### Importing a Site

1. Navigate to **RestoreWP → Dashboard** in your WordPress admin
2. Select the **Import** tab
3. Click **Select File** and choose your backup ZIP file (max 2GB)
4. Optionally check **Create backup before import** for safety
5. Click **Start Import**
6. Monitor the progress popup showing detailed steps:
   - Extracting backup file
   - Validating backup
   - Creating rollback backup (if enabled)
   - Updating URLs for current domain
   - Importing database
   - Importing files

**Important**: The import process automatically preserves your current site's domain. If you import Site B's backup to Site A, Site A will get all of Site B's content but keep Site A's domain URL.

### Managing Backups

1. Navigate to **RestoreWP → Dashboard** in your WordPress admin
2. Select the **Backups** tab
3. View all available backups with file sizes and creation dates
4. Download or delete backups as needed

## How Domain Preservation Works

When importing a backup from another site, RestoreWP automatically:

1. **Detects the original site URL** from the backup's configuration file
2. **Identifies your current site URL** 
3. **Replaces all instances** of the old URL with your current URL in:
   - Database content (posts, pages, options)
   - Serialized data (WordPress settings, widget data)
   - JSON data (theme customizations, plugin settings)
   - Both HTTP and HTTPS versions

This ensures that:
- All internal links point to your current domain
- Images and media files load correctly
- Plugin and theme settings work properly
- WordPress core settings use the correct URLs

## Security Features

- **Protected Storage**: Backup files are stored with restricted access
- **Nonce Verification**: All AJAX requests use WordPress nonces
- **Capability Checks**: Only users with `manage_options` capability can use the plugin
- **File Validation**: Uploaded files are validated for type and content
- **Secret Keys**: Internal operations use generated secret keys

## File Structure

```
restorewp/
├── restorewp.php           # Main plugin file
├── includes/               # Core functionality
│   ├── class-restorewp-admin.php      # Admin interface
│   ├── class-restorewp-export.php     # Export functionality
│   ├── class-restorewp-import.php     # Import functionality
│   ├── class-restorewp-backup.php     # Backup management
│   ├── class-restorewp-security.php   # Security features
│   ├── class-restorewp-install.php    # Installation/activation
│   └── restorewp-functions.php        # Helper functions
├── assets/                 # Frontend assets
│   ├── css/admin.css      # Admin styles
│   └── js/admin.js        # Admin JavaScript
└── admin/                  # React components (future enhancement)
```

## Backup File Format

RestoreWP creates ZIP archives containing:

- `database.sql` - Complete database dump
- `wp-content/` - WordPress content directory
- `restorewp-config.json` - Backup metadata and configuration
- `site-info.json` - Site information for validation

## Troubleshooting

### Plugin Won't Activate
- Check that your server meets the minimum PHP version requirement (7.4+)
- Verify file permissions on the plugin directory
- Check WordPress error logs for specific error messages

### Export/Import Fails
- Ensure adequate disk space (backup files can be large)
- Check PHP memory limit and execution time settings
- Verify write permissions on wp-content directory

### Large File Issues
- Increase PHP `upload_max_filesize` and `post_max_size` settings
- Adjust `max_execution_time` for long operations
- Check server disk space availability

### URL Issues After Import
- The plugin automatically handles URL replacement
- If manual adjustment is needed, use a database search/replace tool
- Check that the backup's config file contains the correct original URL

## Development

### Building Assets

```bash
npm install
npm run build
```

### File Watching

```bash
npm run dev
```

## Support

- **Documentation**: [GitHub Repository](https://github.com/restorewp/restorewp)
- **Issues**: [Report bugs](https://github.com/restorewp/restorewp/issues)
- **Support**: Contact the RestoreWP team

## License

This plugin is licensed under the GPL v3 or later. See the [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) file for details.

## Changelog

### Version 1.0.0
- Initial release
- Complete site export/import functionality
- Automatic domain preservation during import
- Progress tracking with visual indicators
- Backup management interface
- Security features and validation
- Support for large files (up to 2GB)

---

**RestoreWP** - Making WordPress migration simple and reliable.