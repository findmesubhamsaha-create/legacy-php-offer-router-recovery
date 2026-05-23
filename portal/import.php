<?php
// SHV1-03: require authenticated session
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Upload CSV</title>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <!-- Bootstrap CSS -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Bootstrap JS (with Popper) -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <link rel="stylesheet" href="assets/css/dashboard_style.css?v=<?=time()?>">
      <link rel="stylesheet" href="assets/css/modern.css">
   </head>
   <body>
      <div class="main closeBar" id="mainArea">
         <div class="topBar">
            <button id="open_sidebar" class="IconClick">
            <img src="assets/images/menu.png" alt="">
            </button>
         </div>
         <div class="sidebar">
            <button id="close_sidebar" class="IconClick">
            <img src="assets/images/cancel.png" alt="">
            </button>
            <div class="sideMenu_ottr">
               <ul class="">
                  <li>
                     <a href="dashboard.php">
                     Offers
                     </a>
                  </li>
                  <li>
                     <a href="analytics.php">
                     Analytics
                     </a>
                  </li>
               </ul>
            </div>
         </div>
         <div class="mainArea">
            <div class="inner_main mt-2">
               <!--Page Content Here-->

               <div class="import_page">
               <form id="csvForm" enctype="multipart/form-data">
                  <label for="csvFile">Upload CSV:</label>
                  <input type="file" id="csvFile" name="csvFile" accept=".csv">
                  <button type="submit" class="btn btn-primary">Submit</button>
               </form>
               <a href="#" id="download-link" class="btn btn-primary">Download Sample CSV</a>
               <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                     <div class="modal-content">
                        <div class="modal-header">
                           <h5 class="modal-title" id="responseModalLabel">Response</h5>
                           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalBody">
                           <!-- Dynamic content will be injected here -->
                        </div>
                        <div class="modal-footer">
                           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                     </div>
                  </div>
               </div>
               </div>
               <p id="loading-indicator" style="display:none;">Processing...</p>
               <!--Page Content End-->
            </div>
         </div>
      </div>
      <!--Page Js Here-->
      <script>
         $(document).ready(function() {
             $('#csvForm').on('submit', function(e) {
                 e.preventDefault(); // Prevent form submission
         
                 var file = $('#csvFile')[0].files[0]; // Get the file
         
                 // Check if a file is selected
                 if (!file) {
                     alert('Please select a file.');
                     return;
                 }
         
                 // Validate file extension (CSV)
                 var fileName = file.name;
                 var fileExtension = fileName.split('.').pop().toLowerCase();
                 if (fileExtension !== 'csv') {
                     alert('Only CSV files are allowed.');
                     return;
                 }
         
                 // Validate MIME type (Optional but useful)
                 if (file.type !== 'text/csv' && file.type !== 'application/vnd.ms-excel') {
                     alert('Invalid file type. Please upload a CSV file.');
                     return;
                 }
         
                 var reader = new FileReader();
             reader.onload = function(e) {
                 var csvData = e.target.result;
         
                 // Optional: Parse CSV data (split by rows and columns)
                 var rows = csvData.split("\n").map(row => row.split(","));
                 var headers = rows[0];  // First row as header
                 var dataRows = rows.slice(1);
                 var csvObjects = dataRows.map(row => {
                     var obj = {};
                     headers.forEach((header, index) => {
                         obj[header.trim()] = (row[index] || '').trim();
                        //obj[header.trim()] = row[index].trim();  // Trim values to remove extra spaces
                     });
                     return obj;
                 });
         
                 if (rows.length > 100) {
                     alert('The file contains more than 100 rows. Please upload a file with a maximum of 100 rows.');
                     return; // Stop the execution if there are more than 100 rows
                 }
         
                 $('#loading-indicator').fadeIn();
                 // Send parsed CSV data to PHP via AJAX
                 $.ajax({
                     //url: 'upload.php', // PHP file to process data
                     url: 'ajax.php', // PHP file to process data
                     type: 'POST',
                     //data: { csvData: rows },
                     data: { csvData: JSON.stringify(csvObjects), requestMethod: 'importOffer' },
                     success: function(responseRaw) {
                         // jQuery auto-parses when Content-Type is application/json; handle both cases
                         var outerResponse = (typeof responseRaw === 'string') ? JSON.parse(responseRaw) : responseRaw;

                         // message field is a JSON string from uploadOffer() — parse it
                         var responseData;
                         try {
                             responseData = (typeof outerResponse.message === 'string')
                                 ? JSON.parse(outerResponse.message)
                                 : outerResponse.message;
                         } catch (e) {
                             console.error("Failed to parse nested JSON:", e, outerResponse);
                             showInModal("An unexpected error occurred parsing the server response.");
                             return;
                         }
         
                         // Handle different statuses (error, partial_success, success)
                         if (responseData.status === "error") {
                             // Show the error message inside the modal
                             showInModal(responseData.message);
                         } else {
                             // Build the content to show inserted and error rows
                             var modalContent = `<p><strong>Inserted Rows:</strong> ${responseData.inserted_rows || 0}</p>`;
         
                             if (responseData.error_rows && responseData.error_rows.length > 0) {
                                 modalContent += `<p><strong>Error Rows:</strong></p><ul>`;
                                 responseData.error_rows.forEach(errorRow => {
                                     modalContent += `<li><strong>Offer:</strong> ${errorRow.offer}, <strong>Error:</strong> ${errorRow.error}</li>`;
                                 });
                                 modalContent += `</ul>`;
                             } else {
                                 modalContent += `<p>All rows processed successfully.</p>`;
                             }
         
                             // Show the built content in the modal
                             showInModal(modalContent);
                         }
         
                         // Function to show content in the Bootstrap modal
                         function showInModal(content) {
                             $('#modalBody').html(content);  // Update the modal body with dynamic content
                             var responseModal = new bootstrap.Modal(document.getElementById('responseModal'));
                             responseModal.show();  // Show the modal
                         }
                         $('#loading-indicator').fadeOut();
                         
                     },
                     error: function(xhr, status, error) {
                         alert('Error: ' + error);
                     }
                 });
             };
             reader.readAsText(file);
         
                 // Prepare form data
                 // var formData = new FormData();
                 // formData.append('csvFile', file);
         
                 // // Send file through AJAX
                 // $.ajax({
                 //     url: 'upload.php',  // The PHP file to process the upload
                 //     type: 'POST',
                 //     data: formData,
                 //     processData: false, // Required for file upload
                 //     contentType: false, // Required for file upload
                 //     success: function(response) {
                 //         alert(response);  // Display response from PHP
                 //     },
                 //     error: function(jqXHR, textStatus, errorThrown) {
                 //         alert('Error: ' + textStatus + ' - ' + errorThrown);
                 //     }
                 // });
             });
         });
      </script>
      <script>
         document.getElementById('download-link').addEventListener('click', function (e) {
             e.preventDefault(); // Prevent the default link behavior
         
             // Sample CSV content
             const csvContent = 
                 "offer name,slug name,tag name,notes,network,start date,end date,url1,weight1,status1,url2,weight2,status2,url3,weight3,status3\n" +
                 "offer1,slug1,,Sample note,Network1,2024-10-14,,https://abc.com,10,yes,https://abcd.com,90,yes\n" +
                 "offer2,slug2,tag2,,Network2,,2024-12-31,https://xyz.com,90,yes,https://xyz.com/v1,10,yes,https://xyz.com/v2,90,no\n";
         
             // Create a Blob object for the CSV content
             const blob = new Blob([csvContent], { type: 'text/csv' });
         
             // Create a temporary URL for the Blob
             const url = URL.createObjectURL(blob);
         
             // Create a temporary <a> element for the download
             const a = document.createElement('a');
             a.href = url;
             a.download = 'sample.csv'; // Set the file name
             document.body.appendChild(a); // Append to the body
         
             a.click(); // Trigger the download
         
             // Clean up by revoking the Blob URL and removing the <a> element
             URL.revokeObjectURL(url);
             document.body.removeChild(a);
         });
      </script>
      <!--Page Js End-->
      <!--Js for Sidebar Function 17-10-24 Adding Class of Elements-->
      <script>
         const openBtn = document.getElementById('open_sidebar');
         const closeBtn = document.getElementById('close_sidebar');
         const mainArea = document.getElementById('mainArea');
         
         openBtn.addEventListener('click', () => {
           mainArea.classList.add('openBar');
           mainArea.classList.remove('closeBar');
         });
         
         closeBtn.addEventListener('click', () => {
               mainArea.classList.add('closeBar');
              mainArea.classList.remove('openBar');
         });
      </script>
      <!--Js for Sidebar Function 17-10-24-->
   </body>
</html>
