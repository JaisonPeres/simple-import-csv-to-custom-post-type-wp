<div class="wrap csv-to-cpt-settings">
    <h1><?php _e('CSV Importer Settings', 'csv-to-cpt-importer'); ?></h1>
    
    <div class="csv-to-cpt-container">
        <div class="csv-to-cpt-card">
            <h2><?php _e('File Upload Settings', 'csv-to-cpt-importer'); ?></h2>
            
            <form id="csv-to-cpt-settings-form">
                <?php wp_nonce_field('csv_to_cpt_nonce', 'settings_nonce'); ?>
                
                <div class="form-group">
                    <label for="max_file_size"><?php _e('Maximum File Size (MB)', 'csv-to-cpt-importer'); ?></label>
                    <input type="number" name="max_file_size" id="max_file_size" min="1" max="50" value="<?php echo esc_attr($this->settings['max_file_size']); ?>">
                    <p class="description">
                        <?php 
                        $max_upload = min((int)(ini_get('upload_max_filesize')), (int)(ini_get('post_max_size')));
                        printf(
                            __('Server maximum upload size: %d MB. If you need to upload larger files, adjust your PHP settings.', 'csv-to-cpt-importer'),
                            $max_upload
                        ); 
                        ?>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="overwrite_existing"><?php _e('Overwrite Existing Posts', 'csv-to-cpt-importer'); ?></label>
                    <label class="switch">
                        <input type="checkbox" name="overwrite_existing" id="overwrite_existing" <?php checked($this->settings['overwrite_existing'], true); ?>>
                        <span class="slider round"></span>
                    </label>
                    <p class="description"><?php _e('If enabled, existing posts with the same title will be updated instead of creating new ones.', 'csv-to-cpt-importer'); ?></p>
                </div>
                
                <div class="form-group">
                    <label for="delete_csv_after_import"><?php _e('Delete CSV After Import', 'csv-to-cpt-importer'); ?></label>
                    <label class="switch">
                        <input type="checkbox" name="delete_csv_after_import" id="delete_csv_after_import" <?php checked($this->settings['delete_csv_after_import'], true); ?>>
                        <span class="slider round"></span>
                    </label>
                    <p class="description"><?php _e('If enabled, the uploaded CSV file will be deleted after successful import.', 'csv-to-cpt-importer'); ?></p>
                </div>
                
                <div class="form-group">
                    <h3><?php _e('Upload Directory Information', 'csv-to-cpt-importer'); ?></h3>
                    <p><strong><?php _e('Upload Directory:', 'csv-to-cpt-importer'); ?></strong> <?php echo CSV_TO_CPT_UPLOADS_DIR; ?></p>
                    
                    <?php 
                    // Check if directory is writable
                    $is_writable = is_writable(CSV_TO_CPT_UPLOADS_DIR);
                    $status_class = $is_writable ? 'status-ok' : 'status-error';
                    $status_text = $is_writable ? __('Writable', 'csv-to-cpt-importer') : __('Not Writable', 'csv-to-cpt-importer');
                    ?>
                    
                    <p>
                        <strong><?php _e('Status:', 'csv-to-cpt-importer'); ?></strong> 
                        <span class="directory-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </p>
                    
                    <?php if (!$is_writable): ?>
                        <div class="notice notice-error inline">
                            <p>
                                <?php _e('The uploads directory is not writable. Please set the correct permissions (755) to allow file uploads.', 'csv-to-cpt-importer'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'csv-to-cpt-importer'); ?></button>
                </div>
            </form>
            
            <div id="settings-message" style="display: none;"></div>
        </div>
        
        <div class="csv-to-cpt-card">
            <h2><?php _e('Upload Security', 'csv-to-cpt-importer'); ?></h2>
            <p><?php _e('This plugin implements several security measures for file uploads:', 'csv-to-cpt-importer'); ?></p>
            <ul>
                <li><?php _e('Files are stored in a protected directory with restricted access', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Only CSV files are allowed', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('File size is limited to prevent server overload', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Files can be automatically deleted after import', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Only administrators can upload and import files', 'csv-to-cpt-importer'); ?></li>
            </ul>
            
            <h3><?php _e('Troubleshooting', 'csv-to-cpt-importer'); ?></h3>
            <p><?php _e('If you encounter issues with file uploads:', 'csv-to-cpt-importer'); ?></p>
            <ol>
                <li><?php _e('Ensure your uploads directory has proper permissions (755)', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Check that your CSV file is properly formatted with headers in the first row', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Verify that your file size is within the limits set above', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('If using a server with mod_security, temporary disable it or whitelist CSV uploads', 'csv-to-cpt-importer'); ?></li>
            </ol>
        </div>
    </div>
</div>
