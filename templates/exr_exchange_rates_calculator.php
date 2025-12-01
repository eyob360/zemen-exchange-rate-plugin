<?php
global $wpdb;

// Define the table name
$table_name = $wpdb->prefix . "exr360_daily_info";

// Fetch today's exchange rates
$todays_date = current_time("Y-m-d");
$todays_exchange_rates = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table_name WHERE DATE(post_date) = %s",
        $todays_date
    )
);

$first_currency = !empty($todays_exchange_rates)
    ? $todays_exchange_rates[0]->currency_code
    : "";
$json_data = file_get_contents(
    plugin_dir_path(__FILE__) . "/currencies-with-symbols.json"
);
$currencies = json_decode($json_data, true);

// Format date to "Dec 21, 2024"
$formatted_date = date("M d, Y", strtotime($todays_date));
?>
<div class="exr-container">
    <!-- Currency Converter Form -->
    <div class="exr-calculator-container">
        <form id="exr-calc-form">
            <!-- Date Input -->
            <div class="exr-form-group">
                <label for="date">Select Date</label>
                <div style="display: flex; gap: 10px;">
                    <input type="date" id="date" name="date" value="<?php echo esc_attr($todays_date); ?>" required>
                    <div class="exr-search-button" onclick="document.getElementById('exr-search-date').click();">
                        <span class="exr-icon">
                            <?php $image_url = plugin_dir_url(__DIR__) . "public/images/search-exr.svg"; ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="Search Icon">
                        </span>
                        <button type="button" id="exr-search-date">Search</button>
                    </div>
                </div>
            </div>
            <!-- Currency Dropdown -->
            <div class="exr-form-group">
                <label for="currency">Select Currency</label>
                <select id="currency" name="currency" required>
                    <?php foreach ($todays_exchange_rates as $rate): ?>
                        <option value="<?php echo esc_attr($rate->currency_code); ?>" <?php selected($rate->currency_code, $first_currency); ?>>
                            <?php echo esc_html($rate->currency_code); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Amount Input -->
            <div class="exr-form-group">
                <label for="amount" id="amount-label">Amount in </label>
                <input type="number" id="amount" name="amount" step="1" required min="0">

            </div>

            <div id="exr-result-container" style="margin-top: 40px;">
                <p id="exr-cash-buying"><span>Buying</span>: --</p>
                <p id="exr-cash-selling"><span>Selling</span>: --</p>
            </div>
            <!-- Br Amount Input -->
            <div class="exr-form-group" style="margin-top: 90px;">
                <label for="br-amount">Enter amount in Birr</label>
                <input type="number" id="br-amount" required placeholder="ETB" min="0">
            </div>
        </form>
        <!-- Result Container -->
        <div id="exr-result-container" style="margin-top: -40px;">
            <p id="exr-cash-buying-br"><span>Buying</span>: --</p>
            <p id="exr-cash-selling-br"><span>Selling</span>: --</p>
        </div>
    </div>
    <!-- Exchange Rates Display -->
    <div class="exchange-rates-container">
        <div id="ex-loading-screen" style="display: none;">
            <div>
                <div class="ex-spinner"></div>
                <p>Loading...</p>
            </div>
        </div>
        <h3>Exchange Rates for <span id="selected-date"><?php echo esc_html($formatted_date); ?></span></h3>
        <div id="exr-rates-wrapper">
            <div id="ex-loading-screen" style="display: none;">
                <div>
                    <div class="ex-spinner"></div>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    #ex-loading-screen {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .ex-spinner {
        border: 8px solid #f3f3f3;
        border-top: 8px solid #ED1C24;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .exchange-rates-container {
        position: relative;
    }

    .ex-radio-container {
        display: inline-block;
        position: relative;
        margin-right: 15px;
        padding-left: 25px;
        font-size: 16px;
        cursor: pointer;
    }

    .ex-radio-container input[type="radio"] {
        position: absolute;
        left: 0;
        opacity: 0;
    }

    .radio-mark {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #ED1D24;
        display: inline-block;
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        background-color: transparent;
    }

    .ex-radio-container input[type="radio"]:checked+.radio-mark {
        background-color: #ED1D24;
    }
</style>
<script>
   jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const currenciesJsonUrl = '<?php echo plugin_dir_url(__FILE__) . "currencies-with-symbols.json"; ?>';
    let currencies = {};
    let exchangeRates = {}; // Cache for exchange rates

    // Function to show the loading spinner
    function showLoadingSpinner() {
        $('#ex-loading-screen').css('display', 'flex');
    }

    // Function to hide the loading spinner
    function hideLoadingSpinner() {
        $('#ex-loading-screen').css('display', 'none');
    }

    // Function to format numbers with commas
    function formatNumber(number) {
        return number.toLocaleString('en-US', {
            maximumFractionDigits: 4
        });
    }

    // Load currency metadata (e.g., symbols, flags) once on page load
    $.getJSON(currenciesJsonUrl, function(data) {
        currencies = data;
    });

    // Function to enrich rates with metadata
    function enrichRates(rates) {
        return rates.map(rate => {
            const currencyDetails = currencies.find(c => c.code === rate.currency_code) || {};
            rate.flag_url = currencyDetails.flag ?
                `<?php echo plugin_dir_url(__DIR__) . "public/flags/1x1/"; ?>${currencyDetails.flag}.svg` :
                '';
            rate.symbol = currencyDetails.symbol || '';
            rate.name = currencyDetails.name || '';
            return rate;
        });
    }

    // Fetch and display exchange rates
    function fetchExchangeRates(date = '') {
        showLoadingSpinner(); // Show spinner before AJAX request

        $.post(ajaxUrl, {
            action: 'get_exchange_rates',
            date: date
        }, function(response) {
            try {
                const result = JSON.parse(response);

                // Update the header with the selected date
                const selectedDateFormatted = new Date(date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                $('#selected-date').text(selectedDateFormatted);

                if (result.status === 'success') {
                    const enrichedRates = enrichRates(result.rates);
                    exchangeRates = enrichedRates; // Cache the rates

                    // Header
                    const header = `
                        <div class="exr-head-container">
                            <div class="exr-column">Currency</div>
                            <div class="exr-column">Buying</div>
                            <div class="exr-column">Selling</div>
                            <div class="exr-column">Avg Buying</div>
                            <div class="exr-column">Avg Selling</div>
                        </div>
                    `;

                    // Update rates list
                    const rows = enrichedRates.map(rate => `
                        <div class="exr-row">
                            <div class="exr-column">
                                <div class="exr-currency-flag-code-name-container">
                                    <div class="exr-currency-flag-code">
                                        <div class="exr-currency-flag">
                                            ${rate.flag_url ? `<img src="${rate.flag_url}" alt="${rate.currency_code} Flag">` : ''}
                                        </div>
                                        <p class="exr-currency-code-2">${rate.currency_code}</p>
                                    </div>
                                    <div class="exr-currency-icon-name-container">
                                        <span class="exr-currency-icon" >${rate.symbol}</span>
                                        <span class="exr-currency-name" >${rate.name}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="exr-column" style="color:#000; font-size:20px;">
                                ${formatNumber(Number(rate.buying_rate))}
                            </div>
                            <div class="exr-column" style="color:#000;font-size:20px;">
                                ${formatNumber(Number(rate.selling_rate))}
                            </div>
                            <div class="exr-column" style="color:#000;font-size:20px;">
                                ${rate.avg_buying_rate ? formatNumber(Number(rate.avg_buying_rate)) : '--'}
                            </div>
                            <div class="exr-column" style="color:#000;font-size:20px;">
                                ${rate.avg_selling_rate ? formatNumber(Number(rate.avg_selling_rate)) : '--'}
                            </div>
                        </div>
                    `).join('');

                    $('#exr-rates-wrapper').html(header + rows);

                    // Update the "Select Currency" dropdown
                    const dropdownOptions = enrichedRates.map(rate => `
                        <option value="${rate.currency_code}">${rate.currency_code} (${rate.symbol})</option>
                    `).join('');
                    $('#currency').html(dropdownOptions);

                    // Trigger a calculation update (if needed)
                    calculateExchangeRate();
                } else {
                    $('#exr-rates-wrapper').html('<p style="color: gray; text-align: center;">No exchange rates available for the selected date.</p>');
                    $('#currency').html('<option value="">No currencies available</option>');
                }
            } catch (error) {
                console.error('Error parsing response:', error);
                $('#exr-rates-wrapper').html('<p>Error loading data.</p>');
                $('#currency').html('<option value="">Error loading currencies</option>');
            } finally {
                hideLoadingSpinner(); // Hide spinner after AJAX request completes
            }
        });
    }

    // Fetch and display specific exchange rate calculation
    function calculateExchangeRate() {
        const currency = $('#currency').val();
        const amount = parseFloat($('#amount').val());
        $('#amount-label').text(`Amount in ${currency}`);

        if (!currency || isNaN(amount) || amount <= 0) {
            $('#exr-cash-buying').text('Buying: --');
            $('#exr-cash-selling').text('Selling: --');
            return;
        }

        const rate = exchangeRates.find(r => r.currency_code === currency);
        if (!rate) {
            $('#exr-cash-buying').text('Buying: --');
            $('#exr-cash-selling').text('Selling: --');
            return;
        }

        const buyingRate = parseFloat(rate.buying_rate);
        const sellingRate = parseFloat(rate.selling_rate);

        const cashBuying = (amount * buyingRate).toFixed(4); // Amount in Birr
        const cashSelling = (amount * sellingRate).toFixed(4); // Amount in Birr

        $('#exr-cash-buying').html(`Buying: <h3 style="font-weight:600;">${formatNumber(cashBuying)} Br</h3>`);
        $('#exr-cash-selling').html(`Selling: <h3 style="font-weight:600;">${formatNumber(cashSelling)} Br</h3>`);
    }

    // Function to calculate exchange rate from Birr to selected currency
    function calculateExchangeRateFromBirr() {
        const currency = $('#currency').val();
        const brAmount = parseFloat($('#br-amount').val());

        if (!currency || isNaN(brAmount) || brAmount <= 0) {
            $('#exr-cash-buying-br').text('Buying: --');
            $('#exr-cash-selling-br').text('Selling: --');
            return;
        }

        const rate = exchangeRates.find(r => r.currency_code === currency);
        if (!rate) {
            $('#exr-cash-buying-br').text('Buying: --');
            $('#exr-cash-selling-br').text('Selling: --');
            return;
        }

        const buyingRate = parseFloat(rate.buying_rate);
        const sellingRate = parseFloat(rate.selling_rate);

        const buyingAmount = (brAmount / buyingRate).toFixed(4); // Convert Birr to foreign currency
        const sellingAmount = (brAmount / sellingRate).toFixed(4); // Convert Birr to foreign currency

        $('#exr-cash-buying-br').html(`Buying: <h3 style="font-weight:600;">${formatNumber(buyingAmount)} ${currency}</h3>`);
        $('#exr-cash-selling-br').html(`Selling: <h3 style="font-weight:600;">${formatNumber(sellingAmount)} ${currency}</h3>`);
    }

    // Bind actions
    $('#currency, #date, #amount').on('change keyup', calculateExchangeRate);
    $('#br-amount').on('change keyup', calculateExchangeRateFromBirr);
    $('#exr-search-date').on('click', function() {
        const date = $('#date').val();
        fetchExchangeRates(date);
    });

    // Initial load with today's rates
    fetchExchangeRates('<?php echo esc_js(current_time("Y-m-d")); ?>');
});
</script>