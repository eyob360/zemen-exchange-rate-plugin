<div class="wrap">

    <!-- Add New Exchange Rate Button -->
    <button id="addNewExchangeRateBtn" class="button-primary" style="margin-bottom: 20px;">Add New Exchange Rate</button>

    <!-- Search Bar -->
    <input type="text" id="exchangeRateSearch" placeholder="Search exchange rates..." class="regular-text" style="width: 300px; margin-bottom: 20px;">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;">No.</th>
                <th>Currency Code</th>
                <th>Buying Rate</th>
                <th>Selling Rate</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="exchangeRateTable">
            <!-- Data will be dynamically inserted here by jQuery -->
        </tbody>
    </table>
</div>

<!-- Modal for Add/Edit Exchange Rate -->
<div id="exchangeRateModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Add New Exchange Rate</h2>
        <form id="exchangeRateForm">
            <input type="hidden" name="exchange_rate_id" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="currency_code">Currency Code</label></th>
                    <td><input type="text" name="currency_code" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="buying_rate">Buying Rate</label></th>
                    <td><input type="text" name="buying_rate" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="selling_rate">Selling Rate</label></th>
                    <td><input type="text" name="selling_rate" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_date">Date</label></th>
                    <td><input type="date" name="post_date" required /></td>
                </tr>
            </table>
            <input type="submit" class="button-primary" value="Save Exchange Rate" />
        </form>
    </div>
</div>

<style>
    /* Button Styling */
    button.editExchangeRateButton, button.deleteExchangeRateButton {
        padding: 6px 12px;
        margin-right: 5px;
        font-size: 14px;
        border-radius: 4px;
        cursor: pointer;
        border: none;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Edit Button Styling */
    button.editExchangeRateButton {
        background-color: #0073aa; /* WordPress blue color */
        color: white;
    }

    button.editExchangeRateButton:hover {
        background-color: #005a8d; /* Darker blue */
        color: white;
    }

    /* Delete Button Styling */
    button.deleteExchangeRateButton {
        background-color: #d9534f; /* Red for Delete */
        color: white;
    }

    button.deleteExchangeRateButton:hover {
        background-color: #c9302c; /* Darker red */
        color: white;
    }

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
        margin: 0 auto;  /* Center the modal horizontally */
    }

    .close {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 24px;
        color: #000;
        cursor: pointer;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // Initially, make sure the modal is hidden when the page loads
    $('#exchangeRateModal').hide();

    // Open the Add New Exchange Rate Modal
    $('#addNewExchangeRateBtn').on('click', function() {
        $('#exchangeRateForm')[0].reset(); // Clear the form
        $('#modalTitle').text('Add New Exchange Rate');
        $('#exchangeRateModal').show(); // Open the modal
    });

    // Close the modal when the close button is clicked
    $('.close').on('click', function() {
        $('#exchangeRateModal').hide(); // Hide the modal
    });

    // Close the modal when clicking outside the modal content
    $(window).on('click', function(event) {
        if ($(event.target).is('#exchangeRateModal')) {
            $('#exchangeRateModal').hide();
        }
    });

    // Edit Exchange Rate functionality
    $(document).on('click', '.editExchangeRateButton', function() {
        var exchange_rate_id = $(this).data('id');
        
        // Fetch exchange rate data using AJAX to pre-fill the form
        $.ajax({
            url: ajaxurl,  // WordPress provides this variable
            type: 'GET',
            data: { action: 'get_exchange_rate_data', exchange_rate_id: exchange_rate_id },
            success: function(response) {
                var exchangeRate = JSON.parse(response);
                if (exchangeRate.error) {
                    alert(exchangeRate.error);  // If there's an error, alert the user
                } else {
                    $('#exchangeRateForm input[name="exchange_rate_id"]').val(exchangeRate.exchange_id);
                    $('#exchangeRateForm input[name="currency_code"]').val(exchangeRate.currency_code);
                    $('#exchangeRateForm input[name="buying_rate"]').val(exchangeRate.buying_rate);
                    $('#exchangeRateForm input[name="selling_rate"]').val(exchangeRate.selling_rate);
                    $('#exchangeRateForm input[name="post_date"]').val(exchangeRate.post_date);
                    
                    // Change modal title to Edit Exchange Rate
                    $('#modalTitle').text('Edit Exchange Rate');
                    $('#exchangeRateModal').show(); // Show the modal
                }
            }
        });
    });

    // Handle Add/Edit Exchange Rate form submission
    $('#exchangeRateForm').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.post(ajaxurl, { action: 'save_exchange_rate', formData: formData }, function(response) {
            alert(response.message);
            location.reload(); // Reload the page after adding/updating
        });
    });
});
</script>

