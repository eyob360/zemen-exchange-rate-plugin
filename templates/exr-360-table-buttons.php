<?php
function exr_display_exchange_rate_table()
{
    global $wpdb;

    $today = date("Y-m-d");
    $table_name = $wpdb->prefix . "exr360_daily_info";

    // Fetch all rows for today (any type of rate)
    $rates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_date LIKE %s ORDER BY post_date DESC",
            $today . "%"
        )
    );

    // If no data for today, fetch all records for the most recent day
    if (empty($rates)) {
        $latest_date = $wpdb->get_var("SELECT MAX(DATE(post_date)) FROM $table_name");

        if ($latest_date) {
            $rates = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE DATE(post_date) = %s ORDER BY post_date DESC",
                    $latest_date
                )
            );
        }
    }

    $base_date = !empty($rates)
        ? date("Y-m-d", strtotime($rates[0]->post_date))
        : $today;

    $avg_date = date("Y-m-d", strtotime($base_date . " -1 day"));

    $transaction_rates = array_values(array_filter($rates, function ($rate) {
        return $rate->buying_rate !== null && $rate->selling_rate !== null;
    }));

    $cash_rates = array_values(array_filter($rates, function ($rate) {
        return $rate->cash_buying_rate !== null && $rate->cash_selling_rate !== null;
    }));

    $avg_rates = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE DATE(post_date) = %s ORDER BY post_date DESC",
            $avg_date
        )
    );
    $avg_rates = array_values(array_filter($avg_rates, function ($rate) {
        return $rate->avg_buying_rate !== null || $rate->avg_selling_rate !== null;
    }));

    $json_data = file_get_contents(plugin_dir_path(__FILE__) . "/currencies-with-symbols.json");
    $currencies = json_decode($json_data, true);

    $formatted_date = date("M j, Y", strtotime($base_date));
    $formatted_avg_date = date("M j, Y", strtotime($avg_date));

    $render_rate = function ($value, $placeholder = "--") {
        return $value !== null ? esc_html(number_format((float) $value, 4)) : $placeholder;
    };
    ?>
    <style>
        .exr-section-heading {
            font-weight: 600;
            font-size: 18px;
            color: #000;
            padding: 0px 12px;
        }
        .exr-tab-controls {
            display: flex;
            gap: 10px;
            padding: 0px 12px !important;
            flex-wrap: wrap;
        }
        .exr-tab-btn {
            padding: 8px 12px !important;
            color: #ED1D24 !important;
            border: 1px solid #faebeb !important;
            background: #fff !important;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500 !important;
            font-size: 14px !important;
        }
        .exr-tab-btn:hover {
            background: #faebeb !important;
            border-color: #faebeb !important;
            color: #ED1D24 !important;
        }

        .exr-tab-btn.active {
            background: #ED1D24 !important;
            color: #fff !important;
            border-color: #ED1D24 !important;
        }
        .exr-tab-panel {
            display: none;
        }
        .exr-date-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            flex-direction: row !important;
        }
        .exr-show-currency-convertor {
            margin-left: auto;
            font-size: 14px !important;
            padding: 8px 12px !important;
            margin-right: 12px !important;
            color: #ed1c24 !important;
            border-radius: 4px !important;
            font-weight: 500 !important;
        }
        .exr-show-currency-convertor:hover {
            background: #faebeb !important;
            border-color: #faebeb !important;
        }
        .exr-popup-heading {
            width: 100%;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .exr-tab-strip {
            border-bottom: 1px solid #ed1c24;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .exr-convertor-row {
            padding: 12px;
            border-top: 1px solid #faebeb;
            display: flex;
            justify-content: flex-end;
        }
        .exr-tab-subtitle {
            padding: 8px 12px;
            font-size: 13px;
            color: #555;
        }
    </style>
    <section class="exr-table-btn-container" id="exr-table-btn-container">
        <div class="exr-table-container" id="exr-table-container" style="display: none;">
            <div class="exr-date-container exr-popup-heading">
                <div class="exr-section-heading">Foreign Exchange Rates Applicable on <?php echo esc_html($formatted_date); ?></div>
            </div>
            <div class="exr-tab-strip">
                <div class="exr-tab-controls">
                    <button type="button" class="exr-tab-btn active" data-target="today">Transaction Rates</button>
                    <button type="button" class="exr-tab-btn" data-target="cash">Cash Rates</button>
                    <button type="button" class="exr-tab-btn" data-target="avg">Weighted Average Rates</button>
                </div>
            </div>
            <div class="exr-tab-panels">
                <div class="exr-tab-panel" data-panel="today" style="display:block;">
                    <div class="exr-card-container">
                        <div class="exr-head-container">
                            <div class="exr-column">Currency</div>
                            <div class="exr-column">Buying</div>
                            <div class="exr-column">Selling</div>
                        </div>
                        <div class="exr-body-container">
                            <?php if (!empty($transaction_rates)): ?>
                                <?php foreach ($transaction_rates as $rate):
                                    $currency_details = current(array_filter($currencies, fn($c) => $c["code"] === $rate->currency_code));
                                    if (!$currency_details) {
                                        continue;
                                    }
                                    $flag_url = plugin_dir_url(__DIR__) . "public/flags/1x1/" . $currency_details["flag"] . ".svg";
                                    ?>
                                    <div class="exr-row">
                                        <div class="exr-column">
                                            <div class="exr-currency-flag-code-name-container">
                                                <div class="exr-currency-flag-code">
                                                    <div class="exr-currency-flag">
                                                        <img src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($rate->currency_code); ?> Flag">
                                                    </div>
                                                    <p class="exr-currency-code"><?php echo esc_html($rate->currency_code); ?></p>
                                                </div>
                                                <div class="exr-currency-icon-name-container">
                                                    <span class="exr-currency-icon"><?php echo esc_html($currency_details["symbol"]); ?></span>
                                                    <span class="exr-currency-name"><?php echo esc_html($currency_details["name"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="exr-column"><?php echo $render_rate($rate->buying_rate); ?></div>
                                        <div class="exr-column"><?php echo $render_rate($rate->selling_rate); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: gray; text-align:center; width:100%; font-size: 16px; padding-top: 12px;">No transaction rates available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="exr-tab-panel" data-panel="cash">
                    <div class="exr-card-container">
                        <div class="exr-head-container">
                            <div class="exr-column">Currency</div>
                            <div class="exr-column">Cash Buying</div>
                            <div class="exr-column">Cash Selling</div>
                        </div>
                        <div class="exr-body-container">
                            <?php if (!empty($cash_rates)): ?>
                                <?php foreach ($cash_rates as $rate):
                                    $currency_details = current(array_filter($currencies, fn($c) => $c["code"] === $rate->currency_code));
                                    if (!$currency_details) {
                                        continue;
                                    }
                                    $flag_url = plugin_dir_url(__DIR__) . "public/flags/1x1/" . $currency_details["flag"] . ".svg";
                                    ?>
                                    <div class="exr-row">
                                        <div class="exr-column">
                                            <div class="exr-currency-flag-code-name-container">
                                                <div class="exr-currency-flag-code">
                                                    <div class="exr-currency-flag">
                                                        <img src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($rate->currency_code); ?> Flag">
                                                    </div>
                                                    <p class="exr-currency-code"><?php echo esc_html($rate->currency_code); ?></p>
                                                </div>
                                                <div class="exr-currency-icon-name-container">
                                                    <span class="exr-currency-icon"><?php echo esc_html($currency_details["symbol"]); ?></span>
                                                    <span class="exr-currency-name"><?php echo esc_html($currency_details["name"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="exr-column"><?php echo $render_rate($rate->cash_buying_rate); ?></div>
                                        <div class="exr-column"><?php echo $render_rate($rate->cash_selling_rate); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: gray; text-align:center; width:100%; font-size: 16px; padding-top: 12px;">No cash rates available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="exr-tab-panel" data-panel="avg">
                    <div class="exr-card-container">
                        <div class="exr-tab-subtitle">Previous day: <?php echo esc_html($formatted_avg_date); ?></div>
                        <div class="exr-head-container">
                            <div class="exr-column">Currency</div>
                            <div class="exr-column">Avg Buying</div>
                            <div class="exr-column">Avg Selling</div>
                        </div>
                        <div class="exr-body-container">
                            <?php if (!empty($avg_rates)): ?>
                                <?php foreach ($avg_rates as $avg_rate):
                                    $currency_details = current(array_filter($currencies, fn($c) => $c["code"] === $avg_rate->currency_code));
                                    if (!$currency_details) {
                                        continue;
                                    }
                                    $flag_url = plugin_dir_url(__DIR__) . "public/flags/1x1/" . $currency_details["flag"] . ".svg";
                                    ?>
                                    <div class="exr-row">
                                        <div class="exr-column">
                                            <div class="exr-currency-flag-code-name-container">
                                                <div class="exr-currency-flag-code">
                                                    <div class="exr-currency-flag">
                                                        <img src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($avg_rate->currency_code); ?> Flag">
                                                    </div>
                                                    <p class="exr-currency-code"><?php echo esc_html($avg_rate->currency_code); ?></p>
                                                </div>
                                                <div class="exr-currency-icon-name-container">
                                                    <span class="exr-currency-icon"><?php echo esc_html($currency_details["symbol"]); ?></span>
                                                    <span class="exr-currency-name"><?php echo esc_html($currency_details["name"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="exr-column">
                                            <?php echo $render_rate($avg_rate->avg_buying_rate, "No Trade"); ?>
                                        </div>
                                        <div class="exr-column">
                                            <?php echo $render_rate($avg_rate->avg_selling_rate, "No Trade"); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: gray; text-align:center; width:100%; font-size: 16px; padding-top: 12px;">No weighted average rates available for the previous day.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="exr-convertor-row">
                <a class="exr-show-currency-convertor" href="/exchange-rates/">Currency Converter</a>
            </div>
        </div>
    </section>
    <!-- Toggle Button for Showing/Closing the Table -->
    <button class="exr-toggle-table-btn" id="exr-toggle-table-btn">
        <div class="exr-icon-btn">
            <span class="exr-toggle-exchange">
                <span class="exr-icon">
                <?php $image_url = plugin_dir_url(__DIR__) . "public/images/exr-icon.svg"; ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="Exchange Icon" style="width: 35px; height: 35px;">
                </span>
                <span>Exchange Rate</span>
            </span>
            <span class="exr-toggle-close" style="display: none;">
                <span class="exr-icon">
                <?php $image_url = plugin_dir_url(__DIR__) . "public/images/close-circle.svg"; ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="Close Icon" style="width: 35px; height: 35px;">
                </span>
                <span>Close</span>
            </span>
        </div>
    </button>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var tableContainer = document.getElementById('exr-table-container');
        if (!tableContainer) return;

        tableContainer.addEventListener('click', function(e) {
            var btn = e.target.closest('.exr-tab-btn');
            if (!btn) return;
            var target = btn.getAttribute('data-target');
            var tabButtons = tableContainer.querySelectorAll('.exr-tab-btn');
            var tabPanels = tableContainer.querySelectorAll('.exr-tab-panel');

            tabButtons.forEach(function(button) {
                button.classList.toggle('active', button === btn);
            });

            tabPanels.forEach(function(panel) {
                if (panel.getAttribute('data-panel') === target) {
                    panel.style.display = 'block';
                } else {
                    panel.style.display = 'none';
                }
            });
        });
    });
    </script>
    <?php
}
exr_display_exchange_rate_table();
?>
