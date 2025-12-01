<h3>Upload CSV Data</h3>
<p id="show_upload_message"></p>
<form id="frm-csv-upload" method="post" enctype="multipart/form-data">
   <div>
      <label for="csv_data_file">Upload CSV File</label>
      <input type="file" name="csv_data_file" id="csv_data_file" accept=".csv" required>
      <input type="hidden" name="action" value="cdu_submit_form_data">
   </div>
   <input type="submit" value="Upload CSV">
</form>
<script>
   jQuery(document).ready(function($) {
       $('#frm-csv-upload').submit(function(e) {
           e.preventDefault();
   
           var formData = new FormData(this);
   
           $.ajax({
               url: '<?php echo admin_url(
      "admin-ajax.php"
      ); ?>', // Ensure to use admin-ajax.php to handle the AJAX request
               type: 'POST',
               data: formData,
               contentType: false,
               processData: false,
               success: function(response) {
                   var result = JSON.parse(response);
                   $('#show_upload_message').html(result.message);
                   if (result.status === 'success') {
                       $('#frm-csv-upload')[0].reset();
                   }
               },
               error: function(xhr, status, error) {
                   $('#show_upload_message').html('An error occurred while uploading the file.');
               }
           });
       });
   });
</script>