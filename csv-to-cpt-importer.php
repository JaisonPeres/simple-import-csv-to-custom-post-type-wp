<?php
/**
 * Plugin Name: CSV to Custom Post Type Importer
 * Plugin URI: https://example.com/plugins/csv-to-cpt-importer
 * Description: Import CSV data into WordPress custom post types
 * Version: 1.0.0
 * Author: Codeium
 * Author URI: https://example.com
 * Text Domain: csv-to-cpt-importer
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('CSV_TO_CPT_VERSION', '1.0.0');
define('CSV_TO_CPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CSV_TO_CPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CSV_TO_CPT_UPLOADS_DIR', CSV_TO_CPT_PLUGIN_DIR . 'uploads/');
define('CSV_TO_CPT_UPLOADS_URL', CSV_TO_CPT_PLUGIN_URL . 'uploads/');

/**
 * The core plugin class
 */
class CSV_To_CPT_Importer {

    /**
     * Plugin settings
     */
    private $settings;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Create uploads directory if it doesn't exist
        $this->setup_uploads_directory();
        
        // Load settings
        $this->load_settings();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register assets
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));
        
        // Ajax handlers
        add_action('wp_ajax_csv_to_cpt_get_post_types', array($this, 'ajax_get_post_types'));
        add_action('wp_ajax_csv_to_cpt_get_post_type_fields', array($this, 'ajax_get_post_type_fields'));
        add_action('wp_ajax_csv_to_cpt_get_taxonomy_terms', array($this, 'ajax_get_taxonomy_terms'));
        add_action('wp_ajax_csv_to_cpt_process_import', array($this, 'ajax_process_import'));
        add_action('wp_ajax_csv_to_cpt_save_settings', array($this, 'ajax_save_settings'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
    }

    /**
     * Setup the uploads directory
     */
    private function setup_uploads_directory() {
        // Create uploads directory if it doesn't exist
        if (!file_exists(CSV_TO_CPT_UPLOADS_DIR)) {
            wp_mkdir_p(CSV_TO_CPT_UPLOADS_DIR);
            
            // Create an .htaccess file to protect direct access
            $htaccess_content = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [F,L]\n</IfModule>";
            file_put_contents(CSV_TO_CPT_UPLOADS_DIR . '.htaccess', $htaccess_content);
            
            // Create an empty index.php file for additional security
            file_put_contents(CSV_TO_CPT_UPLOADS_DIR . 'index.php', '<?php // Silence is golden');            
        }
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $default_settings = array(
            'max_file_size' => 2, // In MB
            'allowed_file_types' => array('csv'),
            'overwrite_existing' => false,
            'delete_csv_after_import' => false,
        );
        
        $saved_settings = get_option('csv_to_cpt_settings', array());
        $this->settings = wp_parse_args($saved_settings, $default_settings);
    }
    
    /**
     * Plugin activation hook
     */
    public function activate_plugin() {
        // Create uploads directory
        $this->setup_uploads_directory();
        
        // Add default settings
        if (!get_option('csv_to_cpt_settings')) {
            $default_settings = array(
                'max_file_size' => 2, // In MB
                'allowed_file_types' => array('csv'),
                'overwrite_existing' => false,
                'delete_csv_after_import' => false,
            );
            
            update_option('csv_to_cpt_settings', $default_settings);
        }
        
        // Set file permissions
        $this->set_upload_permissions();
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate_plugin() {
        // Cleanup could be added here if needed
    }
    
    /**
     * Set proper permissions for upload directory
     */
    private function set_upload_permissions() {
        // Check if the directory exists
        if (file_exists(CSV_TO_CPT_UPLOADS_DIR)) {
            // Try to set directory permissions to 0755
            @chmod(CSV_TO_CPT_UPLOADS_DIR, 0755);
        }
    }
    
    /**
     * Save plugin settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('csv_to_cpt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $settings = array();
        
        // Sanitize and validate settings
        $settings['max_file_size'] = isset($_POST['max_file_size']) ? absint($_POST['max_file_size']) : 2;
        if ($settings['max_file_size'] < 1) {
            $settings['max_file_size'] = 1;
        }
        
        $settings['allowed_file_types'] = array('csv'); // Only CSV is supported for now
        
        $settings['overwrite_existing'] = isset($_POST['overwrite_existing']) ? 
            (bool) $_POST['overwrite_existing'] : false;
            
        $settings['delete_csv_after_import'] = isset($_POST['delete_csv_after_import']) ? 
            (bool) $_POST['delete_csv_after_import'] : false;
        
        // Save settings
        update_option('csv_to_cpt_settings', $settings);
        
        // Update local settings
        $this->settings = $settings;
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'csv-to-cpt-importer')
        ));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('CSV to CPT Importer', 'csv-to-cpt-importer'),
            __('CSV Importer', 'csv-to-cpt-importer'),
            'manage_options',
            'csv-to-cpt-importer',
            array($this, 'display_admin_page'),
            'dashicons-upload',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'csv-to-cpt-importer',
            __('CSV Importer Settings', 'csv-to-cpt-importer'),
            __('Settings', 'csv-to-cpt-importer'),
            'manage_options',
            'csv-to-cpt-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register scripts and styles
     */
    public function register_assets($hook) {
        if ('toplevel_page_csv-to-cpt-importer' !== $hook) {
            return;
        }

        // Select2 CSS
        wp_enqueue_style(
            'select2-css',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0-rc.0'
        );

        // Plugin CSS
        wp_enqueue_style(
            'csv-to-cpt-importer-css',
            CSV_TO_CPT_PLUGIN_URL . 'assets/css/admin.css',
            array('select2-css'),
            CSV_TO_CPT_VERSION
        );

        // Select2 JavaScript
        wp_enqueue_script(
            'select2-js',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0-rc.0',
            true
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'csv-to-cpt-importer-js',
            CSV_TO_CPT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'select2-js'),
            CSV_TO_CPT_VERSION,
            true
        );

        // Add localized script data
        wp_localize_script(
            'csv-to-cpt-importer-js',
            'csvToCptData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('csv_to_cpt_nonce'),
            )
        );
    }

    /**
     * Display the admin page
     */
    public function display_admin_page() {
        require_once CSV_TO_CPT_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Display the settings page
     */
    public function display_settings_page() {
        require_once CSV_TO_CPT_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Ajax handler to get taxonomy terms
     */
    public function ajax_get_taxonomy_terms() {
        check_ajax_referer('csv_to_cpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (empty($_POST['taxonomy'])) {
            wp_send_json_error('No taxonomy specified');
        }

        $taxonomy = sanitize_text_field($_POST['taxonomy']);
        
        // Check if taxonomy exists
        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error('Taxonomy does not exist');
        }
        
        // Get all terms for this taxonomy
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
        
        $formatted_terms = array();
        
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $formatted_terms[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count
                );
            }
        }
        
        wp_send_json_success($formatted_terms);
    }
    
    /**
     * Ajax handler to get all post types
     */
    public function ajax_get_post_types() {
        check_ajax_referer('csv_to_cpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $post_types = get_post_types(array(
            'public' => true,
            '_builtin' => false
        ), 'objects');

        // Also include built-in post and page types
        $built_in = get_post_types(array(
            'public' => true,
            '_builtin' => true,
            'name' => array('post', 'page')
        ), 'objects');

        $post_types = array_merge($post_types, $built_in);
        
        $formatted_post_types = array();
        
        foreach ($post_types as $post_type) {
            $formatted_post_types[] = array(
                'name' => $post_type->name,
                'label' => $post_type->label
            );
        }

        wp_send_json_success($formatted_post_types);
    }

    /**
     * Ajax handler to get fields for a post type
     */
    public function ajax_get_post_type_fields($post_type = '') {
        check_ajax_referer('csv_to_cpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (empty($_POST['post_type'])) {
            wp_send_json_error('No post type specified');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        
        // Default WordPress fields
        $default_fields = array(
            'post_title' => __('Title', 'csv-to-cpt-importer'),
            'post_content' => __('Content', 'csv-to-cpt-importer'),
            'post_excerpt' => __('Excerpt', 'csv-to-cpt-importer'),
            'post_status' => __('Status', 'csv-to-cpt-importer'),
            'post_author' => __('Author ID', 'csv-to-cpt-importer'),
            'post_date' => __('Date', 'csv-to-cpt-importer'),
        );
        
        // Get custom taxonomies for this post type
        $taxonomy_fields = array();
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_fields['tax_' . $taxonomy->name] = sprintf(
                __('Taxonomy: %s', 'csv-to-cpt-importer'),
                $taxonomy->label
            );
        }
        
        // Get custom fields (meta keys) for this post type
        $custom_fields = array();
        
        // For ACF fields (if ACF is active)
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => $post_type));
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                
                foreach ($fields as $field) {
                    $custom_fields['acf_' . $field['name']] = $field['label'];
                }
            }
        }
        
        // Get post meta keys from the database
        global $wpdb;
        $meta_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key FROM $wpdb->postmeta pm
                JOIN $wpdb->posts p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND meta_key NOT LIKE '\_%%'
                LIMIT 100",
                $post_type
            )
        );
        
        foreach ($meta_keys as $meta_key) {
            if (!isset($custom_fields[$meta_key])) {
                $custom_fields[$meta_key] = $meta_key;
            }
        }
        
        // Merge all fields, with taxonomies appearing after default fields but before custom fields
        $all_fields = array_merge($default_fields, $taxonomy_fields, $custom_fields);
        
        wp_send_json_success($all_fields);
    }

    /**
     * Process the CSV import
     */
    public function ajax_process_import() {
        check_ajax_referer('csv_to_cpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check if we have a file
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error('No file uploaded');
        }

        $file = $_FILES['csv_file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error: ' . $file['error']);
        }

        // Validate file type
        $file_type = wp_check_filetype(basename($file['name']), array('csv' => 'text/csv'));
        if ($file_type['ext'] !== 'csv') {
            wp_send_json_error('Invalid file type. Please upload a CSV file.');
        }
        
        // Check file size
        $max_size_bytes = $this->settings['max_file_size'] * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $max_size_bytes) {
            wp_send_json_error(sprintf(
                __('File is too large. Maximum allowed size is %d MB.', 'csv-to-cpt-importer'),
                $this->settings['max_file_size']
            ));
        }
        
        // Create a unique filename
        $upload_filename = 'import_' . time() . '_' . sanitize_file_name($file['name']);
        $upload_path = CSV_TO_CPT_UPLOADS_DIR . $upload_filename;
        
        // Move the uploaded file to our uploads directory
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            wp_send_json_error(__('Failed to save the uploaded file. Please check directory permissions.', 'csv-to-cpt-importer'));
        }

        // Get post type, field mappings, and CSV separator
        $post_type = sanitize_text_field($_POST['post_type']);
        $field_mappings = isset($_POST['field_mappings']) ? $_POST['field_mappings'] : array();
        $default_values = isset($_POST['default_values']) ? $_POST['default_values'] : array();
        $csv_separator = isset($_POST['csv_separator']) ? sanitize_text_field($_POST['csv_separator']) : ',';
        
        // Decode JSON if needed
        if (is_string($field_mappings)) {
            $field_mappings = json_decode(stripslashes($field_mappings), true);
        }
        
        if (is_string($default_values)) {
            $default_values = json_decode(stripslashes($default_values), true);
        }
        
        if (empty($post_type) || empty($field_mappings)) {
            wp_send_json_error('Missing required parameters');
        }

        // Process the CSV file
        $results = $this->process_csv_import($upload_path, $post_type, $field_mappings, $default_values, $csv_separator);
        
        // Delete the CSV file after import if setting is enabled
        if ($this->settings['delete_csv_after_import'] && file_exists($upload_path)) {
            @unlink($upload_path);
        }
        
        wp_send_json_success($results);
    }

    /**
     * Process the CSV file and create/update posts
     * 
     * @param string $file_path Path to the CSV file
     * @param string $post_type Post type to create/update
     * @param array $field_mappings Mapping of CSV columns to post fields
     * @param array $default_values Default values for fields not in CSV
     * @param string $csv_separator The separator used in the CSV file (',', ';', '\t', '|')
     * @return array Results of the import process
     */
    private function process_csv_import($file_path, $post_type, $field_mappings, $default_values = array(), $csv_separator = ',') {
        if (!file_exists($file_path)) {
            return array(
                'status' => 'error',
                'message' => 'File not found'
            );
        }

        $results = array(
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        // Open the CSV file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array(
                'status' => 'error',
                'message' => 'Could not open file'
            );
        }

        // Use the specified delimiter
        $delimiter = $csv_separator;
        
        // Get the header row with the specified delimiter
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return array(
                'status' => 'error',
                'message' => 'Empty or invalid CSV file'
            );
        }

        // Process each row with the specified delimiter
        $row_number = 1; // Start at 1 because header is row 0
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row_number++;
            $results['total']++;
            
            // Create a post data array with default values
            $post_data = array(
                'post_type' => $post_type,
            );
            
            // Apply default WordPress post field values
            $wp_fields = array('post_status', 'post_author', 'comment_status', 'ping_status');
            foreach ($wp_fields as $field) {
                if (!empty($default_values[$field])) {
                    $post_data[$field] = sanitize_text_field($default_values[$field]);
                }
            }
            
            // Set a fallback status if none provided
            if (!isset($post_data['post_status'])) {
                $post_data['post_status'] = 'draft';
            }
            
            $meta_data = array();
            $acf_data = array();
            $taxonomy_data = array();
            
            // Map CSV columns to post fields based on the mapping
            foreach ($field_mappings as $csv_column => $post_field) {
                $column_index = array_search($csv_column, $header);
                
                if ($column_index === false || !isset($row[$column_index])) {
                    continue;
                }
                
                $value = $row[$column_index];
                
                // Handle different field types
                if (strpos($post_field, 'post_') === 0) {
                    // WordPress core fields
                    $post_data[$post_field] = $value;
                } elseif (strpos($post_field, 'acf_') === 0) {
                    // ACF fields
                    $acf_field_name = substr($post_field, 4);
                    $acf_data[$acf_field_name] = $value;
                } elseif (strpos($post_field, 'tax_') === 0) {
                    // Taxonomy fields
                    $taxonomy_name = substr($post_field, 4);
                    $taxonomy_data[$taxonomy_name] = $value;
                } else {
                    // Regular post meta
                    $meta_data[$post_field] = $value;
                }
            }
            
            // If no title is set, skip this row
            if (empty($post_data['post_title'])) {
                $results['skipped']++;
                $results['errors'][] = "Row {$row_number}: Skipped - No title provided";
                continue;
            }
            
            // Check if we should update existing posts
            $existing_post_id = 0;
            if ($this->settings['overwrite_existing']) {
                // Try to find an existing post with the same title
                $existing_posts = get_posts(array(
                    'post_type' => $post_type,
                    'post_title' => $post_data['post_title'],
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ));
                
                if (!empty($existing_posts)) {
                    $existing_post_id = $existing_posts[0];
                    $post_data['ID'] = $existing_post_id; // Set ID for update
                }
            }
            
            // Insert or update the post
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                $results['errors'][] = "Row {$row_number}: " . $post_id->get_error_message();
                $results['skipped']++;
                continue;
            }
            
            // Add post meta (including defaults for meta fields)
            foreach ($meta_data as $meta_key => $meta_value) {
                update_post_meta($post_id, $meta_key, $meta_value);
            }
            
            // Add default meta values for fields not in CSV
            foreach ($default_values as $key => $value) {
                // Skip WordPress core fields that were already handled
                if (strpos($key, 'post_') === 0 || in_array($key, array('comment_status', 'ping_status'))) {
                    continue;
                }
                
                // Handle default taxonomy values
                if (strpos($key, 'tax_') === 0) {
                    $taxonomy_name = substr($key, 4);
                    if (!isset($taxonomy_data[$taxonomy_name])) {
                        $taxonomy_data[$taxonomy_name] = sanitize_text_field($value);
                    }
                    continue;
                }
                
                // Only add if not already set from CSV
                if (!isset($meta_data[$key])) {
                    update_post_meta($post_id, $key, sanitize_text_field($value));
                }
            }
            
            // Add ACF fields if ACF is active
            if (!empty($acf_data) && function_exists('update_field')) {
                foreach ($acf_data as $field_name => $field_value) {
                    update_field($field_name, $field_value, $post_id);
                }
            }
            
            // Set taxonomy terms
            if (!empty($taxonomy_data)) {
                foreach ($taxonomy_data as $taxonomy => $terms) {
                    if (empty($terms)) {
                        continue;
                    }
                    
                    // Check if the taxonomy exists for this post type
                    if (!taxonomy_exists($taxonomy)) {
                        continue;
                    }
                    
                    // Handle comma-separated terms
                    $term_list = array_map('trim', explode(',', $terms));
                    
                    // Set the terms for the post
                    wp_set_object_terms($post_id, $term_list, $taxonomy);
                }
            }
            
            if ($existing_post_id) {
                $results['updated']++;
            } else {
                $results['created']++;
            }
        }
        
        fclose($handle);
        
        return array(
            'status' => 'success',
            'results' => $results
        );
    }
}

// Initialize the plugin
$csv_to_cpt_importer = new CSV_To_CPT_Importer();
