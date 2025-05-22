jQuery(document).ready(function($) {
    // Cache DOM elements for import page
    const $form = $('#csv-to-cpt-form');
    const $csvFile = $('#csv_file');
    const $postType = $('#post_type');
    const $previewBtn = $('#preview-csv');
    const $importBtn = $('#import-csv');
    const $previewContainer = $('#csv-preview-container');
    const $previewTable = $('#csv-preview-table');
    const $importResults = $('#import-results');
    const $importProgress = $('#import-progress');
    
    // File drop area elements
    const $dropArea = $('#csv-drop-area');
    const $browseButton = $('#csv-browse-button');
    const $fileName = $('#csv-file-name');
    
    // Cache DOM elements for settings page
    const $settingsForm = $('#csv-to-cpt-settings-form');
    const $settingsMessage = $('#settings-message');
    
    // Initialize based on which page we're on
    if ($form.length > 0) {
        // We're on the import page
        loadPostTypes();
        initFileDropArea();
        
        // Event listeners for import page
        $previewBtn.on('click', handlePreviewClick);
        $postType.on('change', handlePostTypeChange);
        $form.on('submit', handleFormSubmit);
        $(document).on('click', '#add-custom-default', addCustomDefaultField);
        $(document).on('click', '.remove-row', removeCustomDefaultField);
    }
    
    /**
     * Initialize the file drop area functionality
     */
    function initFileDropArea() {
        // Click the hidden file input when the browse button is clicked
        $browseButton.on('click', function() {
            $csvFile.click();
        });
        
        // Handle file selection via the file input
        $csvFile.on('change', function() {
            handleFileSelection(this.files);
        });
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            $dropArea.on(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            $dropArea.on(eventName, function() {
                $dropArea.addClass('highlight');
            });
        });
        
        // Remove highlight when item is dragged out or dropped
        ['dragleave', 'drop'].forEach(eventName => {
            $dropArea.on(eventName, function() {
                $dropArea.removeClass('highlight');
            });
        });
        
        // Handle dropped files
        $dropArea.on('drop', function(e) {
            const dt = e.originalEvent.dataTransfer;
            const files = dt.files;
            
            // Manually set the file to the file input element
            if (files.length > 0) {
                try {
                    // Try to use DataTransfer API (modern browsers)
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(files[0]);
                    $csvFile[0].files = dataTransfer.files;
                } catch (err) {
                    // Fallback for browsers that don't support DataTransfer
                    console.log('DataTransfer API not supported, using alternative approach');
                    // Store the file in a global variable that we can access later
                    window.droppedFile = files[0];
                }
            }
            
            handleFileSelection(files);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function handleFileSelection(files) {
            if (files.length) {
                const file = files[0];
                
                // Check if it's a CSV file
                if (file.name.toLowerCase().endsWith('.csv')) {
                    // Update the file name display
                    $fileName.text(file.name);
                    
                    // Enable preview button if post type is also selected
                    if ($postType.val()) {
                        $previewBtn.prop('disabled', false);
                    }
                } else {
                    showError('Please select a CSV file');
                    $csvFile.val('');
                    $fileName.text('');
                }
            }
        }
    }
    
    // Event listeners for settings page
    if ($settingsForm.length > 0) {
        $settingsForm.on('submit', handleSettingsSubmit);
    }
    
    /**
     * Load available post types via AJAX
     */
    function loadPostTypes() {
        $.ajax({
            url: csvToCptData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csv_to_cpt_get_post_types',
                nonce: csvToCptData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    populatePostTypes(response.data);
                } else {
                    showError('Failed to load post types');
                }
            },
            error: function() {
                showError('Ajax error: Failed to load post types');
            }
        });
    }
    
    /**
     * Populate post type dropdown
     */
    function populatePostTypes(postTypes) {
        $postType.find('option:not(:first)').remove();
        
        postTypes.forEach(function(type) {
            $postType.append(
                $('<option></option>')
                    .attr('value', type.name)
                    .text(type.label)
            );
        });
    }
    
    /**
     * Handle post type change
     */
    function handlePostTypeChange() {
        const selectedType = $postType.val();
        
        if (!selectedType) {
            return;
        }
        
        // If we have a CSV file and post type, enable preview button
        if ($csvFile[0].files.length > 0 || window.droppedFile) {
            $previewBtn.prop('disabled', false);
        }
    }
    
    /**
     * Handle preview button click
     */
    function handlePreviewClick() {
        // Get the file either from the input or from our global variable for dropped files
        let file = $csvFile[0].files[0];
        
        // If no file in the input, check if we have a dropped file (for browsers without DataTransfer support)
        if (!file && window.droppedFile) {
            file = window.droppedFile;
        }
        
        const postType = $postType.val();
        const separator = $('#csv_separator').val();
        
        if (!file || !postType) {
            showError('Please select both a CSV file and post type');
            return;
        }
        
        // Parse CSV file
        parseCSV(file, separator, function(data) {
            if (!data || !data.length) {
                showError('Could not parse CSV file or file is empty');
                return;
            }
            
            // Get post type fields
            getPostTypeFields(postType, function(fields) {
                // Display preview and mapping interface
                displayCSVPreview(data, fields);
            });
        });
    }
    
    /**
     * Parse CSV file using FileReader
     * @param {File} file - The CSV file to parse
     * @param {string} separatorOption - The separator option selected by the user
     * @param {Function} callback - Callback function to handle parsed data
     */
    function parseCSV(file, separatorOption, callback) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const csv = e.target.result;
            // Handle different line endings (\r\n, \r, \n)
            const lines = csv.split(/\r\n|\r|\n/).filter(line => line.trim() !== '');
            
            if (lines.length === 0) {
                showError('CSV file appears to be empty');
                callback(null);
                return;
            }
            
            const result = [];
            
            // Use the user-specified delimiter
            let delimiter = separatorOption;
            const firstLine = lines[0];
            
            // Get headers - handle quoted values properly
            const headers = parseCSVLine(firstLine, delimiter);
            
            // Process data rows
            for (let i = 1; i < lines.length; i++) {
                if (lines[i].trim() === '') continue;
                
                const values = parseCSVLine(lines[i], delimiter);
                const row = {};
                
                // Only process if we have values
                if (values.length > 0) {
                    // Map values to headers
                    for (let j = 0; j < headers.length; j++) {
                        row[headers[j]] = j < values.length ? values[j] : '';
                    }
                    
                    result.push(row);
                }
            }
            
            if (result.length === 0) {
                showError('Could not parse any data rows from the CSV');
                callback(null);
                return;
            }
            
            callback(result);
        };
        
        reader.onerror = function() {
            showError('Error reading file');
            callback(null);
        };
        
        reader.readAsText(file);
    }
    
    /**
     * Parse a single CSV line, handling quoted values correctly
     */
    function parseCSVLine(line, delimiter) {
        const result = [];
        let inQuotes = false;
        let currentValue = '';
        let i = 0;
        
        while (i < line.length) {
            const char = line[i];
            
            if (char === '"') {
                // Handle quotes
                if (i + 1 < line.length && line[i + 1] === '"') {
                    // Escaped quote
                    currentValue += '"';
                    i += 2;
                } else {
                    // Toggle quote state
                    inQuotes = !inQuotes;
                    i++;
                }
            } else if (char === delimiter && !inQuotes) {
                // End of field
                result.push(currentValue);
                currentValue = '';
                i++;
            } else {
                // Regular character
                currentValue += char;
                i++;
            }
        }
        
        // Add the last field
        result.push(currentValue);
        
        return result;
    }
    
    /**
     * Get available fields for the selected post type
     */
    function getPostTypeFields(postType, callback) {
        $.ajax({
            url: csvToCptData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csv_to_cpt_get_post_type_fields',
                nonce: csvToCptData.nonce,
                post_type: postType
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Check if we have taxonomy fields
                    const taxonomyFields = {};
                    
                    Object.keys(response.data).forEach(function(key) {
                        if (key.startsWith('tax_')) {
                            taxonomyFields[key] = response.data[key];
                        }
                    });
                    
                    // Display taxonomy default fields if any taxonomies exist
                    if (Object.keys(taxonomyFields).length > 0) {
                        displayTaxonomyDefaultFields(taxonomyFields);
                    }
                    
                    callback(response.data);
                } else {
                    showError('Failed to load post type fields');
                    callback({});
                }
            },
            error: function() {
                showError('Ajax error: Failed to load post type fields');
                callback({});
            }
        });
    }
    
    /**
     * Display CSV preview and field mapping interface
     */
    function displayCSVPreview(data, fields) {
        if (!data || !data.length) {
            return;
        }
        
        const headers = Object.keys(data[0]);
        let html = '<table class="widefat striped">';
        
        // Table header with field mapping dropdowns
        html += '<thead><tr>';
        headers.forEach(function(header) {
            html += '<th>' + header + '<br>';
            html += '<select name="field_mapping[' + header + ']" class="field-mapping">';
            html += '<option value="">-- Do not import --</option>';
            
            // Add options for all available fields
            Object.keys(fields).forEach(function(fieldKey) {
                html += '<option value="' + fieldKey + '">' + fields[fieldKey] + '</option>';
            });
            
            html += '</select></th>';
        });
        html += '</tr></thead>';
        
        // Preview first 5 rows
        html += '<tbody>';
        const previewRows = data.slice(0, 5);
        
        previewRows.forEach(function(row) {
            html += '<tr>';
            headers.forEach(function(header) {
                html += '<td>' + (row[header] || '') + '</td>';
            });
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        // Display the preview
        $previewTable.html(html);
        $previewContainer.show();
        $importBtn.show();
        
        // Try to auto-map fields based on header names
        autoMapFields(headers, fields);
    }
    
    /**
     * Auto-map CSV headers to post fields where possible
     */
    function autoMapFields(headers, fields) {
        headers.forEach(function(header) {
            const $select = $('select[name="field_mapping[' + header + ']"]');
            const normalizedHeader = header.toLowerCase().replace(/[^a-z0-9]/g, '');
            
            // Look for matching field
            let matched = false;
            
            Object.keys(fields).forEach(function(fieldKey) {
                const fieldName = fields[fieldKey].toLowerCase().replace(/[^a-z0-9]/g, '');
                
                if (normalizedHeader === fieldName || 
                    normalizedHeader === fieldKey.toLowerCase().replace(/[^a-z0-9]/g, '')) {
                    $select.val(fieldKey);
                    matched = true;
                }
            });
            
            // Special case for common field names
            if (!matched) {
                if (normalizedHeader === 'title') {
                    $select.val('post_title');
                } else if (normalizedHeader === 'content' || normalizedHeader === 'description') {
                    $select.val('post_content');
                } else if (normalizedHeader === 'excerpt') {
                    $select.val('post_excerpt');
                } else if (normalizedHeader === 'status') {
                    $select.val('post_status');
                } else if (normalizedHeader === 'date' || normalizedHeader === 'publishdate') {
                    $select.val('post_date');
                }
            }
        });
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        // Get the file either from the input or from our global variable for dropped files
        let file = $csvFile[0].files[0];
        
        // If no file in the input, check if we have a dropped file (for browsers without DataTransfer support)
        if (!file && window.droppedFile) {
            file = window.droppedFile;
        }
        
        const postType = $postType.val();
        
        if (!file || !postType) {
            showError('Please select both a CSV file and post type');
            return;
        }
        
        // Collect field mappings
        const fieldMappings = {};
        $('.field-mapping').each(function() {
            const $this = $(this);
            const csvColumn = $this.attr('name').match(/\[(.*?)\]/)[1];
            const postField = $this.val();
            
            if (postField) {
                fieldMappings[csvColumn] = postField;
            }
        });
        
        if (Object.keys(fieldMappings).length === 0) {
            showError('Please map at least one CSV column to a post field');
            return;
        }
        
        // Collect default values
        const defaultValues = {};
        
        // Built-in defaults
        if ($('#default_post_status').length) {
            defaultValues['post_status'] = $('#default_post_status').val();
        }
        
        if ($('#default_post_author').length) {
            defaultValues['post_author'] = $('#default_post_author').val();
        }
        
        if ($('#default_comment_status').length) {
            defaultValues['comment_status'] = $('#default_comment_status').val();
        }
        
        if ($('#default_ping_status').length) {
            defaultValues['ping_status'] = $('#default_ping_status').val();
        }
        
        // Custom defaults
        $('.custom-default-row').each(function() {
            const $row = $(this);
            const fieldName = $row.find('.field-name input').val();
            const fieldValue = $row.find('.field-value input').val();
            
            if (fieldName && fieldValue) {
                defaultValues[fieldName] = fieldValue;
            }
        });
        
        // Taxonomy terms from select dropdowns
        $('.taxonomy-terms-select').each(function() {
            const $select = $(this);
            const fieldName = $select.attr('name').match(/\[(.*?)\]/)[1];
            const selectedValues = $select.val();
            
            // Only add if there are selected values
            if (selectedValues && selectedValues.length) {
                // Join multiple values with commas for backend processing
                defaultValues[fieldName] = selectedValues.join(',');
            }
        });
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'csv_to_cpt_process_import');
        formData.append('csv_separator', $('#csv_separator').val());
        formData.append('nonce', csvToCptData.nonce);
        formData.append('csv_file', file);
        formData.append('post_type', postType);
        formData.append('field_mappings', JSON.stringify(fieldMappings));
        formData.append('default_values', JSON.stringify(defaultValues));
        
        // Show progress
        $importProgress.show();
        $importBtn.prop('disabled', true);
        
        // Submit the import
        $.ajax({
            url: csvToCptData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $importProgress.hide();
                $importBtn.prop('disabled', false);
                
                if (response.success && response.data) {
                    displayImportResults(response.data);
                } else {
                    showError('Import failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                $importProgress.hide();
                $importBtn.prop('disabled', false);
                showError('Ajax error: Import failed');
            }
        });
    }
    
    /**
     * Display import results
     */
    function displayImportResults(data) {
        let html = '<div class="import-results-container">';
        
        // Summary
        html += '<div class="import-summary">';
        html += '<h3>' + csvToCptData.importCompleteText + '</h3>';
        html += '<p><strong>' + csvToCptData.totalRowsText + ':</strong> ' + data.total + '</p>';
        html += '<p><strong>' + csvToCptData.createdText + ':</strong> ' + data.created + '</p>';
        
        if (data.updated > 0) {
            html += '<p><strong>' + csvToCptData.updatedText + ':</strong> ' + data.updated + '</p>';
        }
        
        if (data.skipped > 0) {
            html += '<p><strong>' + csvToCptData.skippedText + ':</strong> ' + data.skipped + '</p>';
        }
        html += '</div>';
        
        // Errors if any
        if (data.errors && data.errors.length > 0) {
            html += '<div class="import-errors">';
            html += '<h4>' + csvToCptData.errorsText + ':</h4>';
            html += '<ul>';
            
            data.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $importResults.html(html).show();
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        alert(message);
    }
    
    /**
     * Add a new custom default field
     */
    function addCustomDefaultField() {
        const $container = $('#custom-defaults-container');
        const rowIndex = $container.find('.custom-default-row').length;
        
        const $row = $(`
            <div class="custom-default-row">
                <div class="field-name">
                    <label for="custom_field_${rowIndex}">Field Name</label>
                    <input type="text" id="custom_field_${rowIndex}" name="custom_field_name[]" placeholder="meta_key">
                </div>
                <div class="field-value">
                    <label for="custom_value_${rowIndex}">Value</label>
                    <input type="text" id="custom_value_${rowIndex}" name="custom_field_value[]" placeholder="value">
                </div>
                <button type="button" class="button remove-row">×</button>
            </div>
        `);
        
        $container.append($row);
    }
    
    /**
     * Remove a custom default field
     */
    function removeCustomDefaultField() {
        $(this).closest('.custom-default-row').remove();
    }
    
    /**
     * Display fields for setting default taxonomy values
     */
    function displayTaxonomyDefaultFields(taxonomyFields) {
        const $container = $('#taxonomy-defaults-fields');
        $container.empty();
        
        // Create a field for each taxonomy
        Object.keys(taxonomyFields).forEach(function(taxKey) {
            const taxLabel = taxonomyFields[taxKey];
            const taxName = taxKey.replace('tax_', '');
            const fieldId = 'default_tax_' + taxName;
            
            // Create a container for this taxonomy
            const $taxField = $(`
                <div class="taxonomy-default-field" data-taxonomy="${taxName}">
                    <label for="${fieldId}">${taxLabel}</label>
                    <div class="taxonomy-field-container">
                        <select id="${fieldId}" name="default_values[${taxKey}]" class="taxonomy-terms-select" multiple="multiple">
                            <option value="" disabled>Loading terms...</option>
                        </select>
                    </div>
                    <p class="description">${taxName} - You can select multiple terms</p>
                    <div class="taxonomy-loading">Loading terms...</div>
                </div>
            `);
            
            $container.append($taxField);
            
            // Load terms for this taxonomy
            loadTaxonomyTerms(taxName, fieldId);
        });
        
        // Show the taxonomy defaults section
        $('#taxonomy-defaults-container').show();
    }
    
    /**
     * Load taxonomy terms via AJAX and populate the select dropdown
     */
    function loadTaxonomyTerms(taxonomy, fieldId) {
        $.ajax({
            url: csvToCptData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'csv_to_cpt_get_taxonomy_terms',
                nonce: csvToCptData.nonce,
                taxonomy: taxonomy
            },
            success: function(response) {
                const $select = $('#' + fieldId);
                $select.empty();
                
                // Add a placeholder option
                $select.append('<option value="" disabled>Select terms</option>');
                
                if (response.success && response.data && response.data.length > 0) {
                    // Add terms to the select dropdown
                    response.data.forEach(function(term) {
                        $select.append(`<option value="${term.slug}">${term.name} (${term.count})</option>`);
                    });
                    
                    // Initialize select2 for better UX with multiple selections
                    if ($.fn.select2) {
                        $select.select2({
                            placeholder: 'Select terms',
                            allowClear: true,
                            width: '100%',
                            tags: true,
                            tokenSeparators: [','],
                        });
                    }
                } else {
                    // No terms found
                    $select.append('<option value="" disabled>No terms found</option>');
                }
                
                // Hide loading indicator
                $select.closest('.taxonomy-default-field').find('.taxonomy-loading').hide();
            },
            error: function() {
                const $select = $('#' + fieldId);
                $select.empty().append('<option value="" disabled>Error loading terms</option>');
                
                // Hide loading indicator
                $select.closest('.taxonomy-default-field').find('.taxonomy-loading').hide();
            }
        });
    }
    
    /**
     * Handle settings form submission
     */
    function handleSettingsSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'csv_to_cpt_save_settings');
        formData.append('nonce', csvToCptData.nonce);
        
        // Show loading state
        const $submitButton = $(this).find('button[type="submit"]');
        const originalText = $submitButton.text();
        $submitButton.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: csvToCptData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $submitButton.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    $settingsMessage.removeClass('error')
                        .html('<p>' + response.data + '</p>')
                        .show();
                    
                    // Hide message after 3 seconds
                    setTimeout(function() {
                        $settingsMessage.fadeOut();
                    }, 3000);
                } else {
                    $settingsMessage.addClass('error')
                        .html('<p>' + (response.data || 'Error saving settings') + '</p>')
                        .show();
                }
            },
            error: function() {
                $submitButton.prop('disabled', false).text(originalText);
                
                $settingsMessage.addClass('error')
                    .html('<p>Ajax error: Failed to save settings</p>')
                    .show();
            }
        });
    }
});
