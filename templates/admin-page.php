<div class="wrap csv-to-cpt-importer">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="csv-to-cpt-container">
        <div class="csv-to-cpt-card">
            <h2><?php _e('Import CSV to Custom Post Type', 'csv-to-cpt-importer'); ?></h2>
            
            <form id="csv-to-cpt-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file"><?php _e('Select CSV File', 'csv-to-cpt-importer'); ?></label>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    <p class="description"><?php _e('Upload a CSV file with headers in the first row.', 'csv-to-cpt-importer'); ?></p>
                </div>
                
                <div class="form-group">
                    <label for="post_type"><?php _e('Select Post Type', 'csv-to-cpt-importer'); ?></label>
                    <select name="post_type" id="post_type" required>
                        <option value=""><?php _e('-- Select Post Type --', 'csv-to-cpt-importer'); ?></option>
                        <!-- Options will be populated via AJAX -->
                    </select>
                </div>
                
                <div id="csv-preview-container" style="display: none;">
                    <h3><?php _e('CSV Preview & Field Mapping', 'csv-to-cpt-importer'); ?></h3>
                    <p><?php _e('Map each CSV column to a post field:', 'csv-to-cpt-importer'); ?></p>
                    
                    <div id="csv-preview-table"></div>
                    
                    <div id="default-values-container" class="default-values-section">
                        <h3><?php _e('Default Values', 'csv-to-cpt-importer'); ?></h3>
                        <p><?php _e('Set default values for fields not in your CSV:', 'csv-to-cpt-importer'); ?></p>
                        
                        <div class="default-values-grid">
                            <div class="default-value-item">
                                <label for="default_post_status"><?php _e('Post Status', 'csv-to-cpt-importer'); ?></label>
                                <select id="default_post_status" name="default_values[post_status]">
                                    <option value="draft"><?php _e('Draft', 'csv-to-cpt-importer'); ?></option>
                                    <option value="publish"><?php _e('Published', 'csv-to-cpt-importer'); ?></option>
                                    <option value="pending"><?php _e('Pending', 'csv-to-cpt-importer'); ?></option>
                                    <option value="private"><?php _e('Private', 'csv-to-cpt-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="default-value-item">
                                <label for="default_post_author"><?php _e('Author ID', 'csv-to-cpt-importer'); ?></label>
                                <input type="number" id="default_post_author" name="default_values[post_author]" min="1" value="1">
                            </div>
                            
                            <div class="default-value-item">
                                <label for="default_comment_status"><?php _e('Comment Status', 'csv-to-cpt-importer'); ?></label>
                                <select id="default_comment_status" name="default_values[comment_status]">
                                    <option value="open"><?php _e('Open', 'csv-to-cpt-importer'); ?></option>
                                    <option value="closed"><?php _e('Closed', 'csv-to-cpt-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="default-value-item">
                                <label for="default_ping_status"><?php _e('Ping Status', 'csv-to-cpt-importer'); ?></label>
                                <select id="default_ping_status" name="default_values[ping_status]">
                                    <option value="open"><?php _e('Open', 'csv-to-cpt-importer'); ?></option>
                                    <option value="closed"><?php _e('Closed', 'csv-to-cpt-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="default-value-item add-custom-default">
                                <button type="button" id="add-custom-default" class="button button-secondary">
                                    <?php _e('+ Add Custom Default', 'csv-to-cpt-importer'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="taxonomy-defaults-container" class="taxonomy-defaults-section" style="display: none;">
                            <h4><?php _e('Default Taxonomy Values', 'csv-to-cpt-importer'); ?></h4>
                            <p class="description"><?php _e('Set default taxonomy terms for posts (comma-separated for multiple terms)', 'csv-to-cpt-importer'); ?></p>
                            <div id="taxonomy-defaults-fields"></div>
                        </div>
                        
                        <div id="custom-defaults-container"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" id="preview-csv" class="button button-secondary"><?php _e('Preview & Map Fields', 'csv-to-cpt-importer'); ?></button>
                    <button type="submit" id="import-csv" class="button button-primary" style="display: none;"><?php _e('Import CSV', 'csv-to-cpt-importer'); ?></button>
                </div>
            </form>
            
            <div id="import-results" style="display: none;"></div>
            
            <div id="import-progress" style="display: none;">
                <h3><?php _e('Import Progress', 'csv-to-cpt-importer'); ?></h3>
                <div class="progress-bar-container">
                    <div class="progress-bar"></div>
                </div>
                <p class="progress-status"><?php _e('Processing...', 'csv-to-cpt-importer'); ?></p>
            </div>
        </div>
        
        <div class="csv-to-cpt-card">
            <h2><?php _e('Instructions', 'csv-to-cpt-importer'); ?></h2>
            <ol>
                <li><?php _e('Upload a CSV file with column headers in the first row.', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Select the WordPress post type you want to import to.', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Click "Preview & Map Fields" to see your CSV data and map columns.', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('For each CSV column, select the corresponding post field.', 'csv-to-cpt-importer'); ?></li>
                <li><?php _e('Click "Import CSV" to begin the import process.', 'csv-to-cpt-importer'); ?></li>
            </ol>
            <p><strong><?php _e('Note:', 'csv-to-cpt-importer'); ?></strong> <?php _e('The importer supports WordPress core fields, custom fields, and ACF fields (if Advanced Custom Fields plugin is active).', 'csv-to-cpt-importer'); ?></p>
        </div>
    </div>
</div>
