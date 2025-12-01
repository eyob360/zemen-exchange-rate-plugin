<?php
function exr_display_exchange_rate_table()
{
    global $wpdb;

    $today = date("Y-m-d");
    $table_name = $wpdb->prefix . "exr360_daily_info";

    // Fetch all data for today
    $rates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_date LIKE %s ORDER BY post_date DESC",
            $today . "%"
        )
    );

    // If no data for today, fetch all records for the most recent day
    if (empty($rates)) {
        $latest_date = $wpdb->get_var(
            "SELECT MAX(DATE(post_date)) FROM $table_name"
        );

        if ($latest_date) {
            $rates = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE DATE(post_date) = %s ORDER BY post_date DESC",
                    $latest_date
                )
            );
        }
    }

    $json_data = file_get_contents(
        plugin_dir_path(__FILE__) . "/currencies-with-symbols.json"
    );
    $currencies = json_decode($json_data, true);

    // Format date for display
    $formatted_date = !empty($rates) ? date("M j, Y", strtotime($rates[0]->post_date)) : date("M j, Y");
    $output =
        '<section class="exr-table-btn-container" id="exr-table-btn-container">
                <div class="exr-table-container" id="exr-table-container" style="display: none;">
                    <div class="exr-date-container">
                        <p>' .
        esc_html($formatted_date) .
        '</p>
                        <a class="exr-show-currency-convertor" href="exchange-rates/" style="color:#ed1c24 !important;">Currency Converter</a>
                    </div>
                    <div class="exr-card-container">
                        <div class="exr-head-container">
                            <div class="exr-column">Currency</div>
                            <div class="exr-column">Buying</div>
                            <div class="exr-column">Selling</div>
                            <div class="exr-column">Avg Buying</div>
                            <div class="exr-column">Avg Selling</div>
                        </div>
                        <div class="exr-body-container">';

    foreach ($rates as $rate) {
        $currency_details = current(
            array_filter(
                $currencies,
                fn($c) => $c["code"] === $rate->currency_code
            )
        );
        if ($currency_details) {
            $flag_url =
                plugin_dir_url(__DIR__) .
                "public/flags/1x1/" .
                $currency_details["flag"] .
                ".svg";
            $output .=
                '<div class="exr-row">
                            <div class="exr-column">
                                <div class="exr-currency-flag-code-name-container">
                                    <div class="exr-currency-flag-code">
                                        <div class="exr-currency-flag">
                                            <img src="' .
                esc_url($flag_url) .
                '" alt="' .
                esc_attr($rate->currency_code) .
                ' Flag">
                                        </div>
                                        <p class="exr-currency-code">' .
                esc_html($rate->currency_code) .
                '</p>
                                    </div>
                                    <div class="exr-currency-icon-name-container">
                                        <span class="exr-currency-icon">' .
                esc_html($currency_details["symbol"]) .
                '</span>
                                        <span class="exr-currency-name">' .
                esc_html($currency_details["name"]) .
                '</span>
                                    </div>
                                </div>
                            </div>
                            <div class="exr-column">' .
                esc_html(number_format($rate->buying_rate, 4)) .
                '</div>
                            <div class="exr-column">' .
                esc_html(number_format($rate->selling_rate, 4)) .
                '</div>
                            <div class="exr-column">' .
                ($rate->avg_buying_rate ? esc_html(number_format($rate->avg_buying_rate, 4)) : '--') .
                '</div>
                            <div class="exr-column">' .
                ($rate->avg_selling_rate ? esc_html(number_format($rate->avg_selling_rate, 4)) : '--') .
                '</div>
                        </div>';
        }
    }

    $output .= "</div></div></div></section>";

    echo $output;
}
exr_display_exchange_rate_table();
?>
<!-- Toggle Button for Showing/Closing the Table -->
<button class="exr-toggle-table-btn" id="exr-toggle-table-btn">
    <div class="exr-icon-btn">
        <span class="exr-toggle-exchange">
            <span class="exr-icon">
            <?php $image_url =
                plugin_dir_url(__DIR__) . "public/images/exr-icon.svg"; ?>
            <img src="<?php echo esc_url(
                $image_url
                ); ?>" alt="Exchange Icon" style="width: 35px; height: 35px;">
            </span>
            <span>Exchange Rate</span>
        </span>
        <span class="exr-toggle-close" style="display: none;">
            <span class="exr-icon">
            <?php $image_url =
                plugin_dir_url(__DIR__) . "public/images/close-circle.svg"; ?>
            <img src="<?php echo esc_url(
                $image_url
                ); ?>" alt="Close Icon" style="width: 35px; height: 35px;">
            </span>
            <span>Close</span>
        </span>
    </div>
</button>
