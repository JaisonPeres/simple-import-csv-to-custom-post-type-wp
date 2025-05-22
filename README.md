# CSV to Custom Post Type Importer

A WordPress plugin that allows you to import CSV data into any WordPress post type, including custom post types.

## Features

- Import CSV data into any WordPress post type (built-in or custom)
- Map CSV columns to WordPress fields and custom fields
- Support for Advanced Custom Fields (ACF) if installed
- Preview CSV data before import
- Auto-mapping of fields based on column names
- Detailed import results and error reporting

## Installation

1. Download the plugin files
2. Upload the plugin folder to the `/wp-content/plugins/` directory of your WordPress installation
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Access the importer from the 'CSV Importer' menu in the WordPress admin

## Usage

1. **Prepare your CSV file**:
   - Make sure your CSV file has headers in the first row
   - Each column header should represent a field you want to import
   - For best results, use header names that match WordPress field names (e.g., "Title", "Content", etc.)

2. **Import Process**:
   - Go to "CSV Importer" in the WordPress admin menu
   - Upload your CSV file
   - Select the target post type you want to import to
   - Click "Preview & Map Fields" to see your CSV data
   - Map each CSV column to the appropriate WordPress field
   - Click "Import CSV" to begin the import process

3. **Field Mapping**:
   - The plugin will attempt to auto-map fields based on column names
   - You can manually adjust the mapping as needed
   - Fields that you don't want to import can be set to "Do not import"

## Supported Fields

- **WordPress Core Fields**: Title, Content, Excerpt, Status, Author, Date
- **Custom Fields**: Any post meta fields associated with the selected post type
- **ACF Fields**: If Advanced Custom Fields plugin is active, all ACF fields will be available for mapping

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support or feature requests, please create an issue in the plugin's repository.
