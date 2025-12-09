<?php
   /**
    * Plugin Name: Exchange Rate 360
    * Description: A plugin to display exchange rates with a toggle button. Displays the exchange rates globally on all pages.
    * Author: 360Ground
    * Author URI: https://360ground.com
    * Domain Path: /languages
    */

   define("EXR_PLUGIN_DIR_PATH", plugin_dir_path(__FILE__));

// Default XML URL for exchange rates
function exr_360_get_default_xml_url() {
    return "https://share.zemenbank.com/exrate/RateXml.xml";
}

// Default XML URL for average exchange rates
function exr_360_get_default_avg_xml_url() {
    return ""; // Empty by default, user must configure
}

// Log rotation function to prevent log accumulation
function exr_360_rotate_logs($new_logs) {
    $max_logs = get_option("exr_360_max_logs", 50); // Configurable max log entries
    $stored_logs = get_transient('exr_fetch_exchange_rates_logs');

    if (!$stored_logs) {
        $stored_logs = [];
    }

    // Add new logs
    $all_logs = array_merge($stored_logs, $new_logs);

    // Keep only the last N entries (oldest entries are removed)
    if (count($all_logs) > $max_logs) {
        $all_logs = array_slice($all_logs, -$max_logs);
    }

    // Store for 7 days (longer than transient default to ensure persistence)
    set_transient('exr_fetch_exchange_rates_logs', $all_logs, 60 * 60 * 24 * 7);
}

   // Enqueue styles and scripts
   function exr_360_enqueue_scripts()
   {
       wp_enqueue_style(
           "exr-360-table-btn-styles",
           plugin_dir_url(__FILE__) . "assets/css/exr_360_table_btn.css"
       );
       wp_enqueue_script(
           "exr-360-table-btn-scripts",
           plugin_dir_url(__FILE__) . "assets/js/exr_360_table_btn.js",
           ["jquery"],
           null,
           true
       );
   }
   add_action("wp_enqueue_scripts", "exr_360_enqueue_scripts");

   // Display the exchange rate table globally
   function exr_360_display_globally()
   {
       include plugin_dir_path(__FILE__) . "templates/exr-360-table-buttons.php";
   }
   add_action("wp_footer", "exr_360_display_globally");

   // Add menu and submenus to the dashboard
   function exr_360_add_admin_menu()
   {
       // Main menu
       add_menu_page(
           "360ExchangeRate", // Page title
           "360ExchangeRate", // Menu title
           "manage_options", // Capability
           "360exchangerate", // Menu slug
           "exr_360_main_page", // Callback function
           "dashicons-chart-bar", // Icon
           26 // Position
       );

       // Submenu - Upload Daily Exchange
       add_submenu_page(
           "360exchangerate", // Parent slug
           "Upload Daily Exchange", // Page title
           "Upload Daily Exchange", // Menu title
           "manage_options", // Capability
           "upload-daily-exchange", // Menu slug
           "exr_360_upload_daily_page" // Callback function
       );

       // Submenu - Manage Exchange
       add_submenu_page(
           "360exchangerate", // Parent slug
           "Manage Exchange", // Page title
           "Manage Exchange", // Menu title
           "manage_options", // Capability
           "manage-exchange", // Menu slug
           "exr_360_manage_page" // Callback function
       );

       // Submenu - Fetch Exchange Rates
       add_submenu_page(
           "360exchangerate", // Parent slug
           "Fetch Exchange Rates", // Page title
           "Fetch Exchange Rates", // Menu title
           "manage_options", // Capability
           "fetch-exchange-rates", // Menu slug
           "exr_360_fetch_exchange_rates_page" // Callback function
       );

       // Submenu - Settings
       add_submenu_page(
           "360exchangerate", // Parent slug
           "Settings", // Page title
           "Settings", // Menu title
           "manage_options", // Capability
           "exr-settings", // Menu slug
           "exr_360_settings_page" // Callback function
       );
   }
   add_action("admin_menu", "exr_360_add_admin_menu");

   // DB table creation on plugin activation
   register_activation_hook(__FILE__, "exr_create_table");

   // DB table creation on plugin activation
   register_activation_hook(__FILE__, "exr_create_table");

   function exr_create_table()
   {
       global $wpdb;

       // Define the table name
       $table_name1 = $wpdb->prefix . "exr360_daily_info";

       // Get the appropriate collation settings
       $table_collate = $wpdb->get_charset_collate();

       // SQL command to create the table
       $sql_command1 = "CREATE TABLE $table_name1 (
            exchange_id INT AUTO_INCREMENT PRIMARY KEY,
            currency_code VARCHAR(10) NOT NULL,
            buying_rate DECIMAL(10, 4) NULL,
            selling_rate DECIMAL(10, 4) NULL,
            avg_buying_rate DECIMAL(10, 4) NULL,
            avg_selling_rate DECIMAL(10, 4) NULL,
            post_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $table_collate;";

       // Include WordPress's dbDelta function
       require_once ABSPATH . "wp-admin/includes/upgrade.php";
       dbDelta($sql_command1); // Execute the SQL command to create the table

       // Migration: Add average rate columns if they don't exist (for existing installations)
       $column_exists_avg_buy = $wpdb->get_results("SHOW COLUMNS FROM $table_name1 LIKE 'avg_buying_rate'");
       if (empty($column_exists_avg_buy)) {
           $wpdb->query("ALTER TABLE $table_name1 ADD COLUMN avg_buying_rate DECIMAL(10, 4) NULL AFTER selling_rate");
       }
       
       $column_exists_avg_sell = $wpdb->get_results("SHOW COLUMNS FROM $table_name1 LIKE 'avg_selling_rate'");
       if (empty($column_exists_avg_sell)) {
           $wpdb->query("ALTER TABLE $table_name1 ADD COLUMN avg_selling_rate DECIMAL(10, 4) NULL AFTER avg_buying_rate");
       }

       // Check if the 'exchange-rates' page exists, if not, create it
       $page_title = "Exchange Rates";
       $page_slug = "exchange-rates";

       // Check if the page already exists
       $page = get_page_by_path($page_slug);
       if (!$page) {
           // Create the page
           $page_id = wp_insert_post([
               "post_title" => $page_title,
               "post_name" => $page_slug,
               "post_status" => "publish",
               "post_type" => "page",
               "post_content" => "[exr_exchange_rates]", // Shortcode to display the exchange rate calculator
               "page_template" => "templates/exr_exchange_rates_calculator.php", // Template file
           ]);
        }
   }

   /**
    * Ensure live rate columns accept NULL so we don't coerce missing values to zero.
    */
   function exr_360_maybe_allow_null_live_rates() {
       global $wpdb;
       $table_name = $wpdb->prefix . "exr360_daily_info";

       // Bail early if the table hasn't been created yet.
       $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
       if ($table_exists !== $table_name) {
           return;
       }

       $buying_col = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'buying_rate'");
       if ($buying_col && strtoupper($buying_col->Null) === 'NO') {
           $wpdb->query("ALTER TABLE $table_name MODIFY buying_rate DECIMAL(10,4) NULL");
       }

       $selling_col = $wpdb->get_row("SHOW COLUMNS FROM $table_name LIKE 'selling_rate'");
       if ($selling_col && strtoupper($selling_col->Null) === 'NO') {
           $wpdb->query("ALTER TABLE $table_name MODIFY selling_rate DECIMAL(10,4) NULL");
       }
   }
   add_action('plugins_loaded', 'exr_360_maybe_allow_null_live_rates');

   // DB table deletion on plugin deactivation
   register_uninstall_hook(__FILE__, "exr_delete_table");

   function exr_delete_table()
   {
       global $wpdb;

       // Define the table name
       $table_name = $wpdb->prefix . "exr360_daily_info";

       // SQL command to drop the table
       $sql_command = "DROP TABLE IF EXISTS $table_name;";

       // Execute the SQL command
       $wpdb->query($sql_command);

       // Delete the 'exchange-rates' page if it exists
       $page = get_page_by_path("exchange-rates");
       if ($page) {
           wp_delete_post($page->ID, true); // Delete the page permanently
       }
   }

   // Register the shortcode
   function exr_exchange_rates_shortcode()
   {
       ob_start();
       include plugin_dir_path(__FILE__) .
           "templates/exr_exchange_rates_calculator.php";
       return ob_get_clean();
   }
   add_shortcode("exr_exchange_rates", "exr_exchange_rates_shortcode");

   // Callback function for the main menu page
   function exr_360_main_page()
   {
       echo '<div class="wrap"><h1>Welcome to 360ExchangeRate</h1></div>';
   }
   
   // Callback function for the Upload Daily Exchange submenu
   function exr_360_upload_daily_page()
   {
       ob_start();
       include_once EXR_PLUGIN_DIR_PATH . "templates/exr-daily-upload-form.php";
       echo ob_get_clean();
   }
   
   // Callback function for the Manage Exchange submenu
// Callback function for the Manage Exchange submenu
function exr_360_manage_page() {
    ?>
    <div class="wrap">
        <h1>Manage Exchange Rates</h1>
        <p>Manage your exchange rates data here.</p>

        <!-- Add New Exchange Rate Button -->
        <button id="addNewExchangeRateBtn" class="button-primary" style="margin-bottom: 20px;">Add New Exchange Rate</button>

        <!-- Search Bar -->
        <!-- <input type="text" id="exchangeRateSearch" placeholder="Search by currency..." class="regular-text" style="width: 300px; margin-bottom: 20px;"> -->

        <div id="exchangeRateTree">
            <!-- Exchange Rates will be loaded here via AJAX -->
        </div>
    </div>

    <!-- Modal for Add/Edit Exchange Rate -->
    <div id="exchangeRateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add New Exchange Rate</h2>
            <form id="exchangeRateForm">
                <input type="hidden" name="exchange_id" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="currency_code">Currency Code</label></th>
                        <td><input type="text" name="currency_code" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="post_date">Date</label></th>
                        <td><input type="date" name="post_date" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="buying_rate">Buying Rate</label></th>
                        <td><input type="number" step="0.0001" name="buying_rate" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="selling_rate">Selling Rate</label></th>
                        <td><input type="number" step="0.0001" name="selling_rate" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avg_buying_rate">Average Buying Rate</label></th>
                        <td><input type="number" step="0.0001" name="avg_buying_rate" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="avg_selling_rate">Average Selling Rate</label></th>
                        <td><input type="number" step="0.0001" name="avg_selling_rate" /></td>
                    </tr>
                </table>
                <input type="submit" class="button-primary" value="Save Exchange Rate" />
            </form>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            padding: 10px;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 600px;
            width: 100%;
            position: relative;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            margin: 0 auto;
        }

        .close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: #000;
            cursor: pointer;
        }

        /* Button Styling */
        button.editExchangeRateButton, button.deleteExchangeRateButton {
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s ease, color 0.3s ease;
            margin: 5px;
        }

        /* Edit Button Styling */
        button.editExchangeRateButton {
            background-color: #0073aa; /* WordPress blue */
            color: white;
        }

        button.editExchangeRateButton:hover {
            background-color: #005a8d; /* Darker blue */
        }

        /* Delete Button Styling */
        button.deleteExchangeRateButton {
            background-color: #d9534f; /* Red for delete */
            color: white;
        }

        button.deleteExchangeRateButton:hover {
            background-color: #c9302c; /* Darker red */
        }

        /* Table Row Styling */
        tr:hover {
            background-color: #f9f9f9;
        }

        /* Date Section Styling */
        .dateNode {
            padding: 10px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 20px;
            background-color: #f7f7f7;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 16px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .dateNode:hover {
            background-color: #eaeaea;
        }

        .dateNode .expandButton {
            background-color: #0073aa;
            color: white;
            font-size: 18px;
            padding: 5px;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .dateNode .expandButton:hover {
            background-color: #005a8d;
        }

        /* Table for Exchange Rates */
        .exchangeRateTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .exchangeRateTable th, .exchangeRateTable td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .exchangeRateTable th {
            background-color: #f1f1f1;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Show modal for adding new exchange rate
        $('#addNewExchangeRateBtn').on('click', function() {
            $('#exchangeRateForm')[0].reset();
            $('#modalTitle').text('Add New Exchange Rate');
            $('#exchangeRateForm input[name="post_date"]').val(new Date().toISOString().slice(0, 10));
            $('#exchangeRateModal').show();
        });

        // Close modal
        $('.close').on('click', function() {
            $('#exchangeRateModal').hide();
        });

        $(window).on('click', function(event) {
            if ($(event.target).is('#exchangeRateModal')) {
                $('#exchangeRateModal').hide();
            }
        });

        // Fetch exchange rates grouped by date on page load
        loadExchangeRates();

        // Add/Edit exchange rate functionality
        $(document).on('click', '.editExchangeRateButton', function() {
            var exchange_id = $(this).data('id');
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: { action: 'get_exchange_rate_data', exchange_id: exchange_id },
                success: function(response) {
                    var data = JSON.parse(response);
                    $('#exchangeRateForm input[name="exchange_id"]').val(data.exchange_id);
                    $('#exchangeRateForm input[name="currency_code"]').val(data.currency_code);
                    $('#exchangeRateForm input[name="post_date"]').val((data.post_date || '').split(' ')[0]);
                    $('#exchangeRateForm input[name="buying_rate"]').val(data.buying_rate);
                    $('#exchangeRateForm input[name="selling_rate"]').val(data.selling_rate);
                    $('#exchangeRateForm input[name="avg_buying_rate"]').val(data.avg_buying_rate || '');
                    $('#exchangeRateForm input[name="avg_selling_rate"]').val(data.avg_selling_rate || '');
                    $('#modalTitle').text('Edit Exchange Rate');
                    $('#exchangeRateModal').show();
                }
            });
        });

        // Delete exchange rate functionality
        $(document).on('click', '.deleteExchangeRateButton', function() {
            var exchange_id = $(this).data('id');
            if (confirm('Are you sure you want to delete this exchange rate?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'delete_exchange_rate', exchange_id: exchange_id },
                    success: function(response) {
                        alert(response);
                        loadExchangeRates(); // Refresh exchange rates list
                    }
                });
            }
        });

        // Handle form submission for Add/Edit
        $('#exchangeRateForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.post(ajaxurl, { action: 'save_exchange_rate', formData: formData }, function(response) {
                alert(response);
                loadExchangeRates();
                $('#exchangeRateModal').hide();
            });
        });

        // Load exchange rates data grouped by date and display in tables
        function loadExchangeRates() {
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: { action: 'get_all_exchange_rates_by_date' }, // New AJAX action
                success: function(response) {
                    var exchangeRatesByDate = JSON.parse(response);
                    var displayAvgCell = function(value) {
                        return (value === null || value === undefined || value === '') ? 'No Trade' : value;
                    };
                    var treeHtml = '';
                    exchangeRatesByDate.forEach(function(dateGroup) {
                        treeHtml += '<div class="dateNode" data-date="' + dateGroup.date + '">';
                        treeHtml += '<span>' + dateGroup.date + '</span>';
                        treeHtml += '<span class="expandButton">+</span>';
                        treeHtml += '</div>';
                        treeHtml += '<div class="exchangeRates" id="rates_' + dateGroup.date + '" style="display: none;">';
                        treeHtml += '<table class="exchangeRateTable"><thead><tr><th>Currency Code</th><th>Buying Rate</th><th>Selling Rate</th><th>Avg Buying Rate</th><th>Avg Selling Rate</th><th>Actions</th></tr></thead><tbody>';

                        dateGroup.rates.forEach(function(rate) {
                            treeHtml += '<tr>';
                            treeHtml += '<td>' + rate.currency_code + '</td>';
                            treeHtml += '<td>' + rate.buying_rate + '</td>';
                            treeHtml += '<td>' + rate.selling_rate + '</td>';
                            treeHtml += '<td>' + displayAvgCell(rate.avg_buying_rate) + '</td>';
                            treeHtml += '<td>' + displayAvgCell(rate.avg_selling_rate) + '</td>';
                            treeHtml += '<td>';
                            treeHtml += '<button class="editExchangeRateButton" data-id="' + rate.exchange_id + '">Edit</button>';
                            treeHtml += '<button class="deleteExchangeRateButton" data-id="' + rate.exchange_id + '">Delete</button>';
                            treeHtml += '</td>';
                            treeHtml += '</tr>';
                        });

                        treeHtml += '</tbody></table></div>';
                    });

                    $('#exchangeRateTree').html(treeHtml);
                }
            });
        }

        // Expand or collapse the date section when clicked
        $(document).on('click', '.dateNode', function() {
            var date = $(this).data('date');
            var ratesDiv = $('#rates_' + date);
            var button = $(this).find('.expandButton');

            if (ratesDiv.is(':visible')) {
                ratesDiv.slideUp();
                button.text('+');
            } else {
                ratesDiv.slideDown();
                button.text('-');
            }
        });
    });
    </script>
    <?php
}


// handler
// Get exchange rate data for edit
add_action('wp_ajax_get_exchange_rate_data', 'get_exchange_rate_data');
function get_exchange_rate_data() {
    global $wpdb;
    $exchange_id = intval($_GET['exchange_id']);
    $table_name = $wpdb->prefix . 'exr360_daily_info';
    $rate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE exchange_id = %d", $exchange_id));
    echo json_encode($rate);
    wp_die();
}

// Save exchange rate
add_action('wp_ajax_save_exchange_rate', 'save_exchange_rate');
function save_exchange_rate() {
    global $wpdb;
    parse_str($_POST['formData'], $formData);
    $exchange_id = intval($formData['exchange_id']);
    $currency_code = sanitize_text_field($formData['currency_code']);
    $post_date = sanitize_text_field($formData['post_date']);
    $buying_rate = floatval($formData['buying_rate']);
    $selling_rate = floatval($formData['selling_rate']);
    $avg_buying_rate = isset($formData['avg_buying_rate']) && $formData['avg_buying_rate'] !== '' ? floatval($formData['avg_buying_rate']) : null;
    $avg_selling_rate = isset($formData['avg_selling_rate']) && $formData['avg_selling_rate'] !== '' ? floatval($formData['avg_selling_rate']) : null;
    // Guard against invalid/empty dates; fallback to current time in site TZ
    $parsed_post_date = $post_date ? strtotime($post_date) : false;
    $post_datetime = $parsed_post_date ? date('Y-m-d H:i:s', $parsed_post_date) : current_time('mysql');
    
    if ($exchange_id) {
        $update_data = [
            'currency_code' => $currency_code,
            'post_date' => $post_datetime,
            'buying_rate' => $buying_rate,
            'selling_rate' => $selling_rate
        ];
        if ($avg_buying_rate !== null) {
            $update_data['avg_buying_rate'] = $avg_buying_rate;
        }
        if ($avg_selling_rate !== null) {
            $update_data['avg_selling_rate'] = $avg_selling_rate;
        }
        $wpdb->update(
            $wpdb->prefix . 'exr360_daily_info',
            $update_data,
            ['exchange_id' => $exchange_id]
        );
    } else {
        $insert_data = [
            'currency_code' => $currency_code,
            'post_date' => $post_datetime,
            'buying_rate' => $buying_rate,
            'selling_rate' => $selling_rate
        ];
        if ($avg_buying_rate !== null) {
            $insert_data['avg_buying_rate'] = $avg_buying_rate;
        }
        if ($avg_selling_rate !== null) {
            $insert_data['avg_selling_rate'] = $avg_selling_rate;
        }
        $wpdb->insert(
            $wpdb->prefix . 'exr360_daily_info',
            $insert_data
        );
    }
    echo 'Exchange rate saved successfully.';
    wp_die();
}

// Delete exchange rate
add_action('wp_ajax_delete_exchange_rate', 'delete_exchange_rate');
function delete_exchange_rate() {
    global $wpdb;
    $exchange_id = intval($_POST['exchange_id']);
    $wpdb->delete($wpdb->prefix . 'exr360_daily_info', ['exchange_id' => $exchange_id]);
    echo 'Exchange rate deleted successfully.';
    wp_die();
}

// Get all exchange rates
add_action('wp_ajax_get_all_exchange_rates', 'get_all_exchange_rates');
function get_all_exchange_rates() {
    global $wpdb;
    $rates = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "exr360_daily_info");
    echo json_encode($rates);
    wp_die();
}
// Handle the AJAX request to get exchange rates grouped by date
add_action("wp_ajax_get_all_exchange_rates_by_date", "get_all_exchange_rates_by_date");
function get_all_exchange_rates_by_date()
{
    global $wpdb;

    // Get the exchange rates grouped by date
    $table_name = $wpdb->prefix . "exr360_daily_info";
    $rates = $wpdb->get_results("
        SELECT DATE(post_date) as date, currency_code, buying_rate, selling_rate, avg_buying_rate, avg_selling_rate, exchange_id
        FROM $table_name
        ORDER BY post_date DESC
    ");

    // Group exchange rates by date
    $ratesByDate = [];
    foreach ($rates as $rate) {
        $ratesByDate[$rate->date][] = $rate;
    }

    // Format the response
    $response = [];
    foreach ($ratesByDate as $date => $rates) {
        $response[] = [
            'date' => $date,
            'rates' => $rates
        ];
    }

    // Send the response
    echo json_encode($response);
    wp_die(); // Don't forget to end the request
}

   
   // csv upload handle
   
   // Register the AJAX handler function
   add_action("wp_ajax_cdu_submit_form_data", "exr_csv__handler");
//    add_action("wp_ajax_nopriv_cdu_submit_form_data", "exr_csv__handler"); 
   // For non-logged-in users as well
   
   function exr_csv__handler()
   {
       if (!empty($_FILES["csv_data_file"]["tmp_name"])) {
           // Process the uploaded CSV file
           $csv_file = $_FILES["csv_data_file"]["tmp_name"];
   
           // Open the CSV file for reading
           $handle = fopen($csv_file, "r");
           global $wpdb;
           $table_name = $wpdb->prefix . "exr360_daily_info"; // Ensure table name is consistent
   
           if ($handle) {
               $row = 0;
               $invalid_rows = 0; // Counter for invalid rows
               // Read the CSV file line by line
               while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                   if ($row == 0) {
                       $row++;
                       continue; // Skip header row
                   }
   
                   // Validate the data format
                   $currency_code = sanitize_text_field($data[0]);
                   $buying_rate = floatval($data[1]);
                   $selling_rate = floatval($data[2]);
   
                   // Check if the required columns are not empty and if rates are valid
                   if (
                       empty($currency_code) ||
                       !is_string($currency_code) ||
                       strlen($currency_code) > 10
                   ) {
                       $invalid_rows++;
                       continue; // Skip invalid row
                   }
   
                   if ($buying_rate <= 0 || $selling_rate <= 0) {
                       $invalid_rows++;
                       continue; // Skip row if rates are not valid
                   }
   
                   // Insert valid data into the database
                   $wpdb->insert($table_name, [
                       "currency_code" => $currency_code,
                       "buying_rate" => $buying_rate,
                       "selling_rate" => $selling_rate,
                   ]);
               }
               fclose($handle); // Close the file after reading
   
               // Check if any invalid rows were found
               if ($invalid_rows > 0) {
                   echo json_encode([
                       "status" => "error",
                       "message" => "$invalid_rows invalid rows were skipped.",
                   ]);
               } else {
                   echo json_encode([
                       "status" => "success",
                       "message" => "CSV file uploaded successfully.",
                   ]);
               }
           } else {
               echo json_encode([
                   "status" => "error",
                   "message" => "Failed to open the CSV file.",
               ]);
           }
       } else {
           echo json_encode([
               "status" => "error",
               "message" => "No file uploaded.",
           ]);
       }
   
       wp_die(); // Terminate the request
   }
   
   //
   // Handle the AJAX request for fetching the exchange rate
   add_action("wp_ajax_get_exchange_rate", "get_exchange_rate");
//    add_action("wp_ajax_nopriv_get_exchange_rate", "get_exchange_rate");
   
function get_exchange_rate() {
    global $wpdb;

    $currency = sanitize_text_field($_POST["currency"]);
    $date = sanitize_text_field($_POST["date"]);

    // Fetch the exchange rate from the database
    $table_name = $wpdb->prefix . "exr360_daily_info";
    $rate = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE currency_code = %s AND DATE(post_date) = %s",
            $currency,
            $date
        )
    );

    if ($rate) {
        echo json_encode([
            "status" => "success",
            "buying_rate" => number_format($rate->buying_rate, 4),
            "selling_rate" => number_format($rate->selling_rate, 4),
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Exchange rate not found.",
        ]);
    }

    wp_die();
}
   
   // Handle the AJAX request for fetching exchange rates by date
   add_action("wp_ajax_get_exchange_rates", "get_exchange_rates");
   add_action("wp_ajax_nopriv_get_exchange_rates", "get_exchange_rates");
   
function get_exchange_rates()
{
    global $wpdb;

    $date = sanitize_text_field($_POST["date"]);
    $table_name = $wpdb->prefix . "exr360_daily_info";

    // Fallback to today if date is empty or invalid
    if (empty($date) || !strtotime($date)) {
        $date = current_time('Y-m-d');
    }

    // Today's rates (live)
    $rates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE DATE(post_date) = %s AND buying_rate IS NOT NULL AND selling_rate IS NOT NULL",
            $date
        )
    );

    // Yesterday's averages
    $avg_date = date('Y-m-d', strtotime("$date -1 day"));
    $avg_rates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE DATE(post_date) = %s",
            $avg_date
        )
    );

    if ($rates || $avg_rates) {
        echo json_encode([
            "status" => "success",
            "date" => $date,
            "avg_date" => $avg_date,
            "rates" => [
                "day" => $rates ?: [],
                "average" => $avg_rates ?: [],
            ],
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No exchange rates available for the selected date.",
           ]);
       }
   
       wp_die(); // End the AJAX request
   }


// Define custom intervals
add_filter('cron_schedules', 'exr_360_custom_cron_schedules');
function exr_360_custom_cron_schedules($schedules) {
    $schedules['every_five_minutes'] = [
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every 5 Minutes')
    ];
    $schedules['every_fifteen_minutes'] = [
        'interval' => 900, // 900 seconds = 15 minutes
        'display'  => __('Every 15 Minutes')
    ];
    $schedules['every_thirty_minutes'] = [
        'interval' => 1800, // 1800 seconds = 30 minutes
        'display'  => __('Every 30 Minutes')
    ];
    return $schedules;
}

// Ensure scheduled event exists on init (self-heal)
add_action('init', 'exr_360_ensure_cron_scheduled');
function exr_360_ensure_cron_scheduled() {
    // If cron is disabled, skip
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        return;
    }

    $hook = 'exr_360_fetch_exchange_rates';
    if (!wp_next_scheduled($hook)) {
        $frequency = get_option('exr_360_cron_frequency', 'every_fifteen_minutes');
        if (!in_array($frequency, array_keys(apply_filters('cron_schedules', [])))) {
            $frequency = 'every_fifteen_minutes';
        }
        wp_schedule_event(time(), $frequency, $hook);
    }
}

// Schedule the event on plugin activation
register_activation_hook(__FILE__, 'exr_360_schedule_event');

// Run initial fetch on plugin activation
register_activation_hook(__FILE__, 'exr_360_initial_fetch');

// Clear the event on plugin deactivation
register_deactivation_hook(__FILE__, 'exr_360_clear_scheduled_event');

// Schedule the event
function exr_360_schedule_event() {
    if (!wp_next_scheduled('exr_360_fetch_exchange_rates')) {
        // Schedule to run every 15 minutes by default for more frequent updates
        wp_schedule_event(time(), 'every_fifteen_minutes', 'exr_360_fetch_exchange_rates');
    } else {
        // Event already scheduled
    }
}

// Run initial fetch on plugin activation
function exr_360_initial_fetch() {
    // Run the fetch function immediately on activation
    exr_360_fetch_exchange_rates();
}

// Clear the scheduled event
function exr_360_clear_scheduled_event() {
    $timestamp = wp_next_scheduled('exr_360_fetch_exchange_rates');
    if ($timestamp) {
        // error_log('Clearing scheduled event: exr_360_fetch_exchange_rates');
        wp_unschedule_event($timestamp, 'exr_360_fetch_exchange_rates');
    } else {
        // error_log('No scheduled event found to clear.');
    }
}

// Hook the function to the scheduled event
add_action('exr_360_fetch_exchange_rates', 'exr_360_fetch_exchange_rates');

// Function to fetch exchange rates from XML URL and save to database
function exr_360_fetch_exchange_rates() {
    $logs = [];
    $xml_url = get_option("exr_360_xml_url", exr_360_get_default_xml_url());

    // Fetch XML data from URL
    $response = wp_remote_get($xml_url, [
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'WordPress Exchange Rate 360 Plugin'
        ]
    ]);

    if (is_wp_error($response)) {
        $logs[] = date('Y-m-d H:i:s'). " - Error fetching XML from URL: " . $response->get_error_message();
        set_transient('exr_fetch_exchange_rates_logs', $logs, 60 * 60 * 24);
        return;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        $logs[] = date('Y-m-d H:i:s'). " - HTTP Error $http_code when fetching XML from URL";
        set_transient('exr_fetch_exchange_rates_logs', $logs, 60 * 60 * 24);
        return;
    }

    $xml_content = wp_remote_retrieve_body($response);
    if (empty($xml_content)) {
        $logs[] = date('Y-m-d H:i:s'). " - Empty response from XML URL";
        set_transient('exr_fetch_exchange_rates_logs', $logs, 60 * 60 * 24);
        return;
    }

    // Parse the XML content
    $xml = simplexml_load_string($xml_content);
    if (!$xml) {
        $logs[] = date('Y-m-d H:i:s'). " - Failed to parse XML content from URL";
        set_transient('exr_fetch_exchange_rates_logs', $logs, 60 * 60 * 24);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'exr360_daily_info';

    // Use current time as the timestamp for the exchange rates
    $current_time = current_time('Y-m-d H:i:s');
    $current_day = current_time('Y-m-d');

    $update_count = 0;
    $insert_count = 0;
    $error_count = 0;

    foreach ($xml->ROW as $row) {
        $currency_code = sanitize_text_field((string) $row->CCY1);
        $buying_rate = floatval($row->BUY_RATE);
        $selling_rate = floatval($row->SALE_RATE);

        if (empty($currency_code) || $buying_rate <= 0 || $selling_rate <= 0) {
            $error_count++;
            continue;
        }

        // Check if a row already exists for this currency for today
        $existing_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT exchange_id, buying_rate, selling_rate FROM $table_name WHERE currency_code = %s AND DATE(post_date) = %s",
                $currency_code,
                $current_day
            )
        );

        if ($existing_row) {
            // Check if the rates have changed to avoid unnecessary updates
            if ($existing_row->buying_rate != $buying_rate || $existing_row->selling_rate != $selling_rate) {
                // Update the existing row with new rates
                $updated = $wpdb->update(
                    $table_name,
                    [
                        'buying_rate'  => $buying_rate,
                        'selling_rate' => $selling_rate,
                        'post_date'    => $current_time
                    ],
                    [ 'exchange_id' => $existing_row->exchange_id ]
                );

                if ($updated !== false) {
                    $update_count++;
                } else {
                    $error_count++;
                    $logs[] = date('Y-m-d H:i:s'). " - Failed to update data for $currency_code. Error: " . $wpdb->last_error;
                }
            }
        } else {
            // Insert a new row if no record exists for today
            $inserted = $wpdb->insert(
                $table_name,
                [
                    'currency_code' => $currency_code,
                    'buying_rate'   => $buying_rate,
                    'selling_rate'  => $selling_rate,
                    'post_date'     => $current_time
                ]
            );

            if ($inserted) {
                $insert_count++;
            } else {
                $error_count++;
                $logs[] = date('Y-m-d H:i:s'). " - Failed to insert data for $currency_code. Error: " . $wpdb->last_error;
            }
        }
    }

    // Log summary only
    $logs[] = date('Y-m-d H:i:s'). " - Fetch completed: {$update_count} updated, {$insert_count} inserted, {$error_count} errors";

    // Fetch average exchange rates if URL is configured
    $avg_xml_url = get_option("exr_360_avg_xml_url", exr_360_get_default_avg_xml_url());
    if (!empty($avg_xml_url)) {
        $avg_logs = [];
        $avg_update_count = 0;
        $avg_error_count = 0;

        // Average XML is reported for the previous day, so shift the target date back one day
        $avg_day_timestamp = strtotime('-1 day', current_time('timestamp'));
        $avg_day = date('Y-m-d', $avg_day_timestamp);
        $avg_day_time = date('Y-m-d H:i:s', $avg_day_timestamp);

        // Fetch average rates XML data from URL
        $avg_response = wp_remote_get($avg_xml_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'WordPress Exchange Rate 360 Plugin'
            ]
        ]);

        if (is_wp_error($avg_response)) {
            $avg_logs[] = date('Y-m-d H:i:s'). " - Error fetching average rates XML: " . $avg_response->get_error_message();
        } else {
            $avg_http_code = wp_remote_retrieve_response_code($avg_response);
            if ($avg_http_code !== 200) {
                $avg_logs[] = date('Y-m-d H:i:s'). " - HTTP Error $avg_http_code when fetching average rates XML";
            } else {
                $avg_xml_content = wp_remote_retrieve_body($avg_response);
                if (empty($avg_xml_content)) {
                    $avg_logs[] = date('Y-m-d H:i:s'). " - Empty response from average rates XML URL";
                } else {
                    // Parse the average rates XML content
                    $avg_xml = simplexml_load_string($avg_xml_content);
                    if (!$avg_xml) {
                        $avg_logs[] = date('Y-m-d H:i:s'). " - Failed to parse average rates XML content";
                    } else {
                        // Process average rates
                        foreach ($avg_xml->ROW as $avg_row) {
                            $avg_currency_code = sanitize_text_field((string) $avg_row->WEI_CCY1);
                            $avg_buying_raw = trim((string) $avg_row->WEI_BUY_RATE);
                            $avg_selling_raw = trim((string) $avg_row->WEI_SALE_RATE);

                            $is_buy_no_trade = strcasecmp($avg_buying_raw, 'NO TRADE') === 0;
                            $is_sell_no_trade = strcasecmp($avg_selling_raw, 'NO TRADE') === 0;

                            // Treat "NO TRADE" (any casing) as an intentional null so it can be shown in the UI
                            $avg_buying_rate = $is_buy_no_trade
                                ? null
                                : (is_numeric($avg_buying_raw) ? floatval($avg_buying_raw) : null);
                            $avg_selling_rate = $is_sell_no_trade
                                ? null
                                : (is_numeric($avg_selling_raw) ? floatval($avg_selling_raw) : null);

                            $has_invalid_numeric = ($avg_buying_rate !== null && $avg_buying_rate <= 0) ||
                                ($avg_selling_rate !== null && $avg_selling_rate <= 0);
                            $missing_required_values = (!$is_buy_no_trade && $avg_buying_rate === null) ||
                                (!$is_sell_no_trade && $avg_selling_rate === null);

                            if (empty($avg_currency_code) || $has_invalid_numeric || $missing_required_values) {
                                $avg_error_count++;
                                continue;
                            }

                            // Find existing row for this currency and date
                            $existing_avg_row = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT exchange_id, avg_buying_rate, avg_selling_rate FROM $table_name WHERE currency_code = %s AND DATE(post_date) = %s",
                                    $avg_currency_code,
                                    $avg_day
                                )
                            );

                            if ($existing_avg_row) {
                                // Update average rates if they've changed
                                $update_data = [
                                    'avg_buying_rate'  => $avg_buying_rate,
                                    'avg_selling_rate' => $avg_selling_rate
                                ];

                                // Clean up legacy placeholder zeros for live rates when no data was available.
                                if ((float) $existing_avg_row->buying_rate === 0.0 && (float) $existing_avg_row->selling_rate === 0.0) {
                                    $update_data['buying_rate'] = null;
                                    $update_data['selling_rate'] = null;
                                }

                                if ($existing_avg_row->avg_buying_rate != $avg_buying_rate || $existing_avg_row->avg_selling_rate != $avg_selling_rate || array_key_exists('buying_rate', $update_data)) {
                                    $avg_updated = $wpdb->update(
                                        $table_name,
                                        $update_data,
                                        [ 'exchange_id' => $existing_avg_row->exchange_id ]
                                    );

                                    if ($avg_updated !== false) {
                                        $avg_update_count++;
                                    } else {
                                        $avg_error_count++;
                                        $avg_logs[] = date('Y-m-d H:i:s'). " - Failed to update average rates for $avg_currency_code. Error: " . $wpdb->last_error;
                                    }
                                }
                            } else {
                                // If no row exists, create one with average rates (regular rates will be NULL)
                                $avg_inserted = $wpdb->insert(
                                    $table_name,
                                    [
                                        'currency_code' => $avg_currency_code,
                                        'buying_rate'   => null,
                                        'selling_rate'  => null,
                                        'avg_buying_rate'  => $avg_buying_rate,
                                        'avg_selling_rate' => $avg_selling_rate,
                                        'post_date'     => $avg_day_time
                                    ]
                                );

                                if ($avg_inserted) {
                                    $avg_update_count++;
                                } else {
                                    $avg_error_count++;
                                    $avg_logs[] = date('Y-m-d H:i:s'). " - Failed to insert average rates for $avg_currency_code. Error: " . $wpdb->last_error;
                                }
                            }
                        }

                        if ($avg_update_count > 0 || $avg_error_count > 0) {
                            $avg_logs[] = date('Y-m-d H:i:s'). " - Average rates fetch completed: {$avg_update_count} updated, {$avg_error_count} errors";
                        }
                    }
                }
            }
        }

        // Merge average rate logs with main logs
        $logs = array_merge($logs, $avg_logs);
    }

    // Record last successful run if there were no transport/parse errors
    update_option('exr_360_last_successful_fetch', current_time('mysql'));

    // Store logs with rotation - keep only last 50 entries
    exr_360_rotate_logs($logs);
}

// Callback function for the Fetch Exchange Rates submenu
function exr_360_fetch_exchange_rates_page() {
    ?>
    <div class="wrap">
        <h1>Fetch Exchange Rates</h1>
        <button id="fetchExchangeRatesBtn" class="button-primary">Fetch Exchange Rates</button>
        <div id="fetchExchangeRatesResult"></div>
        <h2>Logs</h2>
        <div id="fetchExchangeRatesLogs">
            <?php
            $logs = get_transient('exr_fetch_exchange_rates_logs');
            if ($logs) {
                echo '<ul>';
                foreach ($logs as $log) {
                    echo '<li>' . esc_html($log) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No logs found.</p>';
            }
            ?>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#fetchExchangeRatesBtn').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'manual_fetch_exchange_rates' },
                success: function(response) {
                    $('#fetchExchangeRatesResult').html('<p>' + response + '</p>');
                }
            });
        });
    });
    </script>
    <?php
}

// Handle the AJAX request to manually fetch exchange rates
add_action('wp_ajax_manual_fetch_exchange_rates', 'manual_fetch_exchange_rates');
function manual_fetch_exchange_rates() {
    exr_360_fetch_exchange_rates();
    echo 'Exchange rates fetched successfully.';
    wp_die();
}

// Add a temporary admin notice to show next scheduled run time
add_action('admin_notices', 'exr_360_admin_notice');
function exr_360_admin_notice() {
    if (isset($_GET['page']) && strpos($_GET['page'], '360exchangerate') !== false) {
        $next_run = wp_next_scheduled('exr_360_fetch_exchange_rates');
        $last_run = get_option('exr_360_last_successful_fetch');
        $cron_disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);

        $messages = [];
        if ($next_run) {
            $messages[] = '<strong>Next scheduled fetch:</strong> ' . date('Y-m-d H:i:s', $next_run);
        } else {
            $messages[] = '<strong>Next scheduled fetch:</strong> not scheduled';
        }

        if ($last_run) {
            $messages[] = '<strong>Last successful fetch:</strong> ' . esc_html($last_run);
        } else {
            $messages[] = '<strong>Last successful fetch:</strong> never';
        }

        if ($cron_disabled) {
            $messages[] = '<strong>WP-Cron status:</strong> disabled (DISABLE_WP_CRON is true)';
        }

        $notice_class = $cron_disabled || !$next_run ? 'notice-warning' : 'notice-info';
        echo '<div class="notice ' . $notice_class . '"><p><strong>Exchange Rate 360:</strong> ' . implode(' | ', $messages) . '</p></div>';
    }
}

// Callback function for the Settings submenu
function exr_360_settings_page() {
    ?>
    <div class="wrap">
        <h1>Exchange Rate 360 Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields("exr_360_settings_group");
            do_settings_sections("exr-settings");
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action("admin_init", "exr_360_register_settings");
function exr_360_register_settings() {
    register_setting("exr_360_settings_group", "exr_360_xml_url", "exr_360_sanitize_xml_url");
    register_setting("exr_360_settings_group", "exr_360_avg_xml_url", "exr_360_sanitize_xml_url");
    register_setting("exr_360_settings_group", "exr_360_cron_frequency", "exr_360_update_cron_schedule");
    register_setting("exr_360_settings_group", "exr_360_max_logs", "exr_360_sanitize_max_logs");

    add_settings_section(
        "exr_360_settings_section",
        "Exchange Rate Source Settings",
        null,
        "exr-settings"
    );

    add_settings_field(
        "exr_360_xml_url",
        "XML URL",
        "exr_360_xml_url_callback",
        "exr-settings",
        "exr_360_settings_section"
    );

    add_settings_field(
        "exr_360_avg_xml_url",
        "Average Exchange Rate XML URL",
        "exr_360_avg_xml_url_callback",
        "exr-settings",
        "exr_360_settings_section"
    );

    add_settings_field(
        "exr_360_cron_frequency",
        "Update Frequency",
        "exr_360_cron_frequency_callback",
        "exr-settings",
        "exr_360_settings_section"
    );

    add_settings_field(
        "exr_360_max_logs",
        "Max Log Entries",
        "exr_360_max_logs_callback",
        "exr-settings",
        "exr_360_settings_section"
    );
}

// Sanitize XML URL
function exr_360_sanitize_xml_url($url) {
    return esc_url_raw($url);
}

// Sanitize max logs setting
function exr_360_sanitize_max_logs($max_logs) {
    $max_logs = intval($max_logs);
    return ($max_logs >= 10 && $max_logs <= 500) ? $max_logs : 50;
}

// Update cron schedule when frequency setting is saved
function exr_360_update_cron_schedule($frequency) {
    // Clear existing scheduled event
    $timestamp = wp_next_scheduled('exr_360_fetch_exchange_rates');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'exr_360_fetch_exchange_rates');
    }

    // Schedule new event with the selected frequency
    if (!wp_next_scheduled('exr_360_fetch_exchange_rates')) {
        wp_schedule_event(time(), $frequency, 'exr_360_fetch_exchange_rates');
    }

    return $frequency;
}

// Callback function for the XML URL field
function exr_360_xml_url_callback() {
    $xml_url = get_option("exr_360_xml_url", exr_360_get_default_xml_url());
    echo '<input type="url" name="exr_360_xml_url" value="' . esc_attr($xml_url) . '" class="regular-text" style="width: 500px;" />';
    echo '<p class="description">The URL where the XML exchange rate data is located. Default: ' . exr_360_get_default_xml_url() . '</p>';
}

// Callback function for the Average XML URL field
function exr_360_avg_xml_url_callback() {
    $avg_xml_url = get_option("exr_360_avg_xml_url", exr_360_get_default_avg_xml_url());
    echo '<input type="url" name="exr_360_avg_xml_url" value="' . esc_attr($avg_xml_url) . '" class="regular-text" style="width: 500px;" />';
    echo '<p class="description">The URL where the average exchange rate XML data is located. Leave empty to disable average rate fetching.</p>';
}

// Callback function for the cron frequency field
function exr_360_cron_frequency_callback() {
    $frequency = get_option("exr_360_cron_frequency", "every_fifteen_minutes");
    $frequencies = [
        'every_five_minutes' => 'Every 5 Minutes',
        'every_fifteen_minutes' => 'Every 15 Minutes',
        'every_thirty_minutes' => 'Every 30 Minutes',
        'hourly' => 'Hourly',
        'daily' => 'Daily'
    ];

    echo '<select name="exr_360_cron_frequency">';
    foreach ($frequencies as $value => $label) {
        $selected = selected($frequency, $value, false);
        echo "<option value='$value' $selected>$label</option>";
    }
    echo '</select>';
    echo '<p class="description">How often to fetch exchange rates from the XML URL. More frequent updates ensure fresher data.</p>';
}

// Callback function for the max logs field
function exr_360_max_logs_callback() {
    $max_logs = get_option("exr_360_max_logs", 50);
    echo '<input type="number" name="exr_360_max_logs" value="' . esc_attr($max_logs) . '" min="10" max="500" />';
    echo '<p class="description">Maximum number of log entries to keep (10-500). Logs are automatically rotated to prevent accumulation.</p>';
}

// DB table deletion on plugin uninstallation
register_uninstall_hook(__FILE__, "exr_delete_table");
?>
