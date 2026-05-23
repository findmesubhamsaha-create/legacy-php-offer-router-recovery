<?php
session_start();
// SHV1-03: require authenticated session
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: index.php');
    exit;
}
// DU-01: load BASE_URL for dynamic link generation
require dirname(__FILE__) . '/library/Settings.php';
?>

<!doctype html>
<html lang="en">
   <head>
      <title>Portal</title>
      <meta charset="utf-8">
     <link  rel="icon" type="image/x-icon" href="favicon.png" />
   <link  rel="shortcut icon" type="image/x-icon" href="favicon.png" />
   <link rel="apple-touch-icon" href="app-icon.png"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
      <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"> -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
      <link rel="stylesheet" href="assets/css/dashboard_style.css">
      <link rel="stylesheet" href="assets/css/modern.css">
      <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">

      <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.2/css/all.min.css" integrity="sha512-u7ppO4TLg4v6EY8yQ6T6d66inT0daGyTodAi6ycbw9+/AU8KMLAF7Z7YGKPMRA96v7t+c7O1s6YCTGkok6p9ZA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

      <style>

         /*.blurred-background {
            filter: blur(5px);
            pointer-events: none;
            opacity: 0.5;
        }*/

         .offer_name {
             cursor: pointer; /* This will use the hand pointer */
         }

         .select2-container--default .select2-selection--single {
           height: 38px!important;
           border-radius: var(--bs-border-radius-sm);
           border: var(--bs-border-width) solid var(--bs-border-color);
         }
         .select2-container--default .select2-selection--single .select2-selection__clear {
           height: 35px!important;;
         }
         .select2-container--default .select2-selection--single .select2-selection__rendered {
           line-height: 38px !important;
           color: var(--bs-body-color)!important;;
           font-size: .875rem;
         }
         .select2-container--default .select2-selection--single .select2-selection__arrow {
           top: 7px!important;;
         }
     
         @media only screen and (min-width:1024px) {
            td span.brk , th span.brk {
               max-width: 300px;
               width:100%;
               display: table;
               word-break: break-all;
            }
         }
         button.view_icon_btn{
           margin-left: 10px;
         }
         button.view_icon_btn.btn_report {
            border: none;
            background:none;
         }
         .dropbtn {
             border: none;
             cursor: pointer;
             background: url(assets/images/three_dot_new.png);
             text-align: center;
             padding: 0 0;
             width: 5px;
             height: 22px;
             background-size: contain;
             background-repeat: no-repeat;
            position:relative;
            top:6px;
         }

         .dropbtn:hover, .dropbtn:focus {
            border: none;
             cursor: pointer;
             background: url(assets/images/three_dot_new.png);
             text-align: center;
             padding: 0 0;
             width: 5px;
             height: 22px;
             background-size: contain;
             background-repeat: no-repeat;
         }
         .dropdown {
           position: relative;
           display: inline-block;
         }
         .dropdown-content {
             display: none;
             position: absolute;
             background-color: #f1f1f1;
             min-width: 160px;
             overflow: auto;
             box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
             z-index: 1;
             right: 0;
         }
         .dropdown a:hover {background-color: #ddd;}
         .show {display: block;}
         .sub_menu {background: #c5dcff;}
         .sub_menu ul{list-style:none; margin:0 0; padding:0 0;}
         .sub_menu ul li{list-style:none; margin:0 0; padding:0 0;}
         .sub_menu ul li {
             border-bottom: 1px solid #c5c5c5;
            background: #c5dcff;
            text-align: left;
         }
         .sub_menu ul li:hover{background:#fff;}
         .sub_menu ul li:last-child{border:none;}
         .sub_menu ul li button{width:auto; text-align: left; font-size: 14px;}
         .sub_menu ul li button span.sting_icon {
             margin-right: 5px;
         }

         @media only screen and (max-width:540px) {
            button.setting, button.clone, button.link {
               margin:0 0!important;
               padding:0 0!important;
            }
            button.setting span i, button.clone span i, button.link span i{font-size:12px;}
            .dropbtn {
               padding: 0 0;
               width: 5px;
               height: 15px;
               background-size: contain;
               background-repeat: no-repeat;
               top: 3px;
            }

         .dropbtn:hover, .dropbtn:focus {
            padding: 0 0;
               width: 5px;
               height: 15px;
               background-size: contain;
               background-repeat: no-repeat;
               top: 3px;
         }
         }
      </style>

      <style>
button.view_icon_btn{
  margin-left: 10px;
}
button.view_icon_btn.btn_report {
    border: none;
  background:none;
}
</style>

   </head>
   <body>
   <div class="main closeBar" id="mainArea">

      <div class="topBar">
         <button id="open_sidebar" class="IconClick">
            <img src="assets/images/menu.png" alt="">
         </button>

          <div class="hdFlex">
               <select style="max-width:300px;" class="form-select form-select-sm" name="offer_filter" id="filter_menu">
                 <option value="" disabled>Filter By</option>
                 <option value="Status" selected>Status</option>
                 <option value="Network">Network</option>
                 <option value="Domain">Domain</option>
               </select>
               <select style="max-width:300px;" class="form-select form-select-sm" name="offer_status" id="status_menu">
                 <option value="All">All</option>
                 <option value="Active" selected>Active</option>
                 <option value="Archived">Archived</option>
                 <option value="Deleted">Deleted</option>
               </select>
               <div class="AddRecord">
                  <button type="button" class="btn btn-primary " data-bs-toggle="modal" id="addNewRecord"
                              data-bs-target="#exampleModal">Add Record</button>
               </div>
         </div>


      </div>
      <div class="sidebar">

         <button id="close_sidebar" class="IconClick">
            <img src="assets/images/cancel.png" alt="">
         </button>

         <div class="sideMenu_ottr">
            <ul class="">
               <li>
                  <a href="<?= BASE_URL ?>/portal/import.php">
                     Import
                  </a>
               </li>
               <li>
                  <a href="<?= BASE_URL ?>/portal/export/">
                     Export
                  </a>
               </li>
               <li>
                  <a href="<?= BASE_URL ?>/portal/analytics.php">
                     Analytics
                  </a>
               </li>

            </ul>

         </div>



      </div>
      <!-- <div class="topBar">
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
                  <a href="#">
                     Home
                  </a>
               </li>
               <li>
                  <a href="#">
                     Dashboard
                  </a>
               </li>
               <li>
                  <a href="#">
                     Orders
                  </a>
               </li>
               <li>
                  <a href="#">
                     Products
                  </a>
               </li>
               <li>
                  <a href="#">
                     Customers
                  </a>
               </li>
            </ul>

         </div>



      </div> -->
      <div class="mainArea">
         <div class="inner_main">

            <div class="hdFlex d-flex">

             <!--  <select style="max-width:300px;" class="form-select form-select-sm" name="offer_filter" id="filter_menu">
                 <option value="" disabled>Filter By</option>
                 <option value="Status" selected>Status</option>
                 <option value="Network">Network</option>
                 <option value="Domain">Domain</option>
               </select>
               &nbsp; &nbsp; 
               <select style="max-width:300px;" class="form-select form-select-sm" name="offer_status" id="status_menu">
                 <<option value="All">All</option> 
                 <option value="Active" selected>Active</option>
                 <option value="Archived">Archived</option>
                 <option value="Deleted">Deleted</option>
               </select>

               &nbsp; &nbsp; 
               <div class="AddRecord">
                  <button type="button" class="btn btn-primary " data-bs-toggle="modal" id="addNewRecord"
                              data-bs-target="#exampleModal">Add Record</button>
               </div> -->
               <!-- <div class="d-flex inputarae ">
                  <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                  <button class="btn btn-outline-success" type="submit">Search</button>
               </div> -->
            </div>
            
            <div class="table-responsive">
               <table id="example" class="table table-bordered table-striped">
                 <thead>
                   <tr role="row">
                     <th>Sl No.</th>
                     <th>Offer Name</th>
                     <th>Slug Name</th>
                     <th>Tag</th>
                     <th>Note</th>
                     <th>Networks</th>
                     <th class="sorting_desc" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" aria-sort="descending">Clicks</th>
                     <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Salary: activate to sort column ascending">Actions</th>
                   </tr>
                 </thead>
                 <tbody>
                 </tbody>
               </table>
            </div>



         </div>
      </div>
   </div>

      <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script> -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      crossorigin="anonymous"></script>
   
   <!-- Modal -->
   <div class="modal fade Step4From" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
      aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h1 class="modal-title fs-5" id="exampleModalLabel">Add Offer</h1>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body form-modal-body">
               <form action="ajax.php" method="post" name="offerForm">
                  <input type="hidden" name="requestMethod" value="addNewOffer">
                  <input type="hidden" name="offerId" value="">
                  <input type="hidden" name="checkoffer" value="0">
                  <div class="row">
                     <div class="col-xs-12">
                        <div class="form-group">
                           <label for="name">Offer Name</label>
                           <input type="text" name="offer" class="form-control required" id="mainoffer" placeholder="Enter offer name" data-error="Please enter offer name">
                        </div>
                     </div>
                  </div>


                  <div class="row">
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="note">Slug</label>
                           <input type="text" name="slug" class="form-control cls_slug required" id="slug" placeholder="slug" data-error="Please enter slug">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="note">Tags</label>
                           <input type="text" name="tags" class="form-control" id="Tags" placeholder="Tags" data-error="Please enter tag">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="Note">Notes</label>
                           <textarea name="note" class="form-control" placeholder="Add Note" id="Note"  data-error="Please enter note"></textarea>
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="network">Network</label>
                           <input type="text" name="network" class="form-control required" id="network" placeholder="Network" data-error="Please enter network">
                        </div>
                     </div>
                     
                  </div>


                  <div class="row">
                     <div class="col-xs-12 col-sm-6">
                        <div class="form-group">
                           <label for="start-date">Start Date</label>
                           <input type="date" name="startdate" class="form-control" id="start-date" data-error="Please enter start-date">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-6">
                        <div class="form-group">
                           <label for="end-date">End Date</label>
                           <input type="date" name="enddate" class="form-control" id="end-date" data-error="Please enter end-date">
                        </div>
                     </div>
                  </div>

                  <div class="row mb-2 mt-2">
                     <div class="col-xs-12 col-sm-12">
                        <button class="btn btn-primary" type="button" id="equalDistribution">Distribute Weightage Equally</button>
                     </div>
                  </div>

                  <div class="suburl_main">
                     <div class="row suburl">
                        <div class="col-xs-12 col-sm-6">
                           <div class="form-group">
                              <label for="url" class="subUrlLabel">URL-1</label>
                              <input type="url" name="url[]" class="form-control required sublink" id="url1" placeholder="Enter URL" data-error="Please enter sub url">
                              <input type="hidden" name="sub_url_id[]" id="subUrlId1" value="" />
                           </div>
                        </div>
                        <div class="col-xs-12 col-sm-3">
                           <div class="form-group">
                              <label for="weight">Weight</label>
                              <input type="number" name="weight[]" class="form-control required weights" id="weight1" placeholder="Weight" data-error="Please select weight" oninput="javascript: this.value = this.value.replace(/[^0-9]/g, '');">
                           </div>
                        </div>
                        <div class="col-xs-12 col-sm-3">
                           <div class="form-group checkbox_ottr">
                              <div class="checkbox">
                                 <label>
                                    <input type="checkbox" class="isActive" name="check_1" value="yes" id="checkbox1">
                                 </label>
                                 
                                    <button type="button" class="add_sub_url">
                                    <img src="assets/images/plus.png" alt="">
                                    </button>
                                 
                              </div>
                           </div>
                        </div>
                        
                     </div>
                  </div>

                  <div class="row mt-3" style="text-align: right;">
                     <div class="col-xs-12 text-right">
                        <button type="button" onclick="return submitForm()" id="save_btn" class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-cls-default">Cancel</button>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </div>
   <!-- Modal End -->






   <!-- Modal -->
   <div class="modal fade Step4From" id="clickexampleModal" tabindex="-1" aria-labelledby="clickexampleModalLabel"
      aria-hidden="true">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h1 class="modal-title fs-5" id="clickexampleModalLabel">Click</h1>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body click-modal-body report-body">
               <!--  report table goes here   -->
               <table id="report-tbl" class="table table-striped" cellspacing="0" width="100%">
                  <thead>
                     <tr>
                        <th>Offer ID</th>
                        <th>Main Offer URL</th>
                        <th>Clicks</th>
                        <th>Report Date</th>
                     </tr>
                  </thead>
                 
                  <tbody>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
   <!-- Modal End -->


   <!-- Offer Hover Modal -->
   <div class="modal fade Step4From" id="offerhovereModal" tabindex="-1" aria-labelledby="offerhoverModalLabel"
      aria-hidden="true">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h1 class="modal-title fs-5" id="offerhoverModalLabel">SUB OFFER</h1>
               <button type="button" class="btn-close close-hover-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body offerhover-modal-body offerhover-body">
               <table id="offerhover-tbl" class="table table-striped" cellspacing="0" width="100%">
                  <thead>
                     <tr>
                        <th>OFFER NAME</th>
                        <th>OFFER URL</th>
                        <th>WEIGHT</th>
                        <th>Status</th>
                     </tr>
                  </thead>
                 
                  <tbody>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
   <!-- Offer Hover Modal End -->

   <div id="m-toast-container" aria-live="polite" aria-atomic="true"></div>
   <p id="loading-indicator" style="display:none;">Processing...</p>

   <script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
   <script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
   <script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>

   
   <script type="text/javascript">
      // $(document).ready(function() {
      //    $('#example').DataTable();
      // } );
   </script>


<script>
   function showToast(type, title, body) {
      var icons = {
         success: 'fa-check-circle',
         warning: 'fa-exclamation-circle',
         danger:  'fa-times-circle',
         info:    'fa-info-circle'
      };
      var id = 'toast_' + Date.now();
      var html = '<div id="' + id + '" class="toast m-toast m-toast-' + type + '" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500">'
         + '<div class="toast-header">'
         + '<i class="fas ' + (icons[type] || icons.info) + ' me-2"></i>'
         + '<strong class="me-auto">' + title + '</strong>'
         + '<button type="button" class="btn-close btn-close-sm ms-2" data-bs-dismiss="toast" aria-label="Close"></button>'
         + '</div>'
         + (body ? '<div class="toast-body">' + body + '</div>' : '')
         + '</div>';
      $('#m-toast-container').append(html);
      var toastEl = document.getElementById(id);
      bootstrap.Toast.getOrCreateInstance(toastEl).show();
      $(toastEl).on('hidden.bs.toast', function () { $(this).remove(); });
   }

   $('.btn-close').on('click',function(e) {
      if ($(e.target).attr("class") != "addeddom") $(".addeddom").remove();
      $('[name=checkoffer]').val(0);
      $("#save_btn").val('');
      $("#mainoffer").val('');
      $("#Note").val('');
      $("#Tags").val('');
      $("#network").val('');
      $("#start-date").val('');
      $("#end-date").val('');
      $("#url1").val('');
      $("#weight1").val('');
      $("#checkbox1").prop('checked', false);
      $('.form-modal-body').load(location.href + ' .form-modal-body');
   });
   // $('.btn-default').on('click',function(e) {
   //    $(".btn-close").click();
   // });
   $("body").on("click", ".btn-cls-default", function () {
      $(".btn-close").click();
   });
/*======================================= Fetch Data ========================================== */

      // $( document ).ready(function() {
      //    $('#loading-indicator').fadeIn();
      //     $.ajax({
      //       url: "ajax.php",
      //       type: "POST",
      //       data: {requestMethod: 'fetchAll'},
      //       dataType : 'json',
      //       cache: false,
      //       success: function(data){
      //          $.ajax({
      //             url: "table.php",
      //             type: "POST",
      //             //data: {data: data},
      //             data: {data: JSON.stringify(data)},
      //             dataType : 'html',
      //             cache: false,
      //             success: function(data2){
      //                //console.log(data2);
      //                $(".table-responsive").html(data2);
      //                $('#loading-indicator').fadeOut();
      //             }
      //          });
      //       }
      //    });
      // });

      var dataTable;
      var status = 1;

      $(document).ready(function(){

         // dataTable = $("#example").DataTable({
         //    "processing":true,
         //    "serverSide":false,
         //    "order":[],
         //    "ajax":{
         //       url:"ajax.php",
         //       method:"POST",
         //       data: {requestMethod: 'fetchAll', offerStatus: status},
         //    },
         //    "coloumnDefs":[{
         //       "target":[7],
         //       "orderable":false
         //    }],
         //    "pageLength": 100,
         //    "autoWidth": false,
         // });
         dataTable = $("#example").DataTable({
            "processing":true,
            "serverSide":true,
            "order":[],
            "ajax":{
               "url":"ajax.php",
               "type":"POST",
               "data": function (d) {
                     d.requestMethod = "fetchAll";  // Add static custom data
                     d.offerStatus = status;  // Add dynamic data from an input field
               },
               //data: {requestMethod: 'fetchAll', offerStatus: status},
               //dataType : 'json',
               // cache: false,
               // success: function(data){
               //    console.log(data);
               // }
            },
            "createdRow": function(row, data, dataIndex) {
                 // Add a class to the first <td> element (you can adjust the index)
                  $('td:eq(1)', row).addClass('offer_name');
                  $('td:eq(1)', row).attr('data-bs-toggle', 'modal');
                  $('td:eq(1)', row).attr('data-bs-target', '#offerhovereModal');
             },
             // Listen to the processing event
            // "columns": [
            //    { "data": "Sl No." },
            //    { "data": "Offer Name" },
            //    { "data": "Slug Name" },
            //    { "data": "Tag" },
            //    { "data": "Note" },
            //    { "data": "Networks" },
            //    { "data": "Clicks" },
            //    { "data": "Actions" }
            // ],
            "coloumnDefs":[{
               "target":[7],
               "orderable":true
            }],
            "pageLength": 50,
            "lengthMenu": [50, 100, 250, 500, 1000],
            "autoWidth": false,
         });
         // dataTable.on('processing.dt', function(e, settings, processing) {
         //        if (processing) {
         //            $('body').addClass('blurred-background');
         //        } else {
         //            $('body').removeClass('blurred-background');
         //        }
         //    });
      });

/*======================================= End Of Fetch Data ========================================== */


/*======================================= Fetch Report Data ========================================== */

      $("body").on("click", ".btn_report", function () {
         $("#report-tbl").DataTable().destroy();

         var reportTable = $("#report-tbl").DataTable({
            "processing":true,
            "serverSide":false,
            "order":[],
            "ajax":{
               url:"ajax.php",
               method:"POST",
               data: {requestMethod: 'fetchReport', oid: $(this).val()},
            },
            // "coloumnDefs":[{
            //    "target":[7],
            //    "orderable":false
            // }],
            "pageLength": 100,
            "autoWidth": false,
         });
      });

      // $("body").on("click", ".btn_report", function () {
      //     $.ajax({
      //       url: "ajax.php",
      //       type: "POST",
      //       data: {requestMethod: 'fetchReport', oid: $(this).val()},
      //       dataType : 'json',
      //       cache: false,
      //       success: function(data){
      //          //console.log(data);
      //          $.ajax({
      //             url: "report-table.php",
      //             type: "POST",
      //             data: {data: data},
      //             dataType : 'html',
      //             cache: false,
      //             success: function(data2){
      //                //console.log(data2);
      //                $(".report-body").html(data2);
      //             }
      //          });
      //       }
      //    });
      // });

/*======================================= End Of Fetch Report Data ========================================== */









/*======================================= Add New Data =============================================== */
      //var error = '';
      var sublink = [];
      $("#addNewRecord").on('click', function(){
         $('[name=offerForm]')[0].reset();
         $('[name=requestMethod]').val('addNewOffer');
         $('[name=offerId]').val('');
         $('.form-modal-body').load(location.href + ' .form-modal-body');
      });



      $("body").on("change", ".sublink", function () {
         if(!isUrlValid($(this).val())){
            showToast('warning', 'Warning', 'Enter a valid sub URL');
            $(this).val('');
         }
         else{
            var reportRecipients = $("input[name='url[]']").map(function(){return $(this).val();}).get();
            var recipientsArray = reportRecipients.sort(); 
            var reportRecipientsDuplicate = [];
            for (var i = 0; i < recipientsArray.length - 1; i++) {
                if (recipientsArray[i + 1] == recipientsArray[i] && recipientsArray[i + 1] != '') {
                     // $(this).parents(".suburl").remove();
                     $(this).val('');
                     showToast('warning', 'Warning', 'Sub URL already added!');
                }
                else{
                  console.log('out');
                }
            }
         }
      });

      //$('.cls_slug').keypress(function( e ) {
      $("body").on("keypress", ".cls_slug", function (e) {
          if(e.which === 32) 
              return false;
      })

      
      function submitForm(){
         //$('#loading-indicator').fadeIn();
         var error = '';
         var totweight=0;
         //console.log('in');
         $('[name=offerForm] .required').each(function(){
               if($(this).val() == '' || !$(this).val().replace(/\s/g, '').length){
                  //console.log('[name=offer]').val();
                  error +='<br>'+$(this).data('error');
               }
               // if(!$(this).val().replace(/\s/g, '').length){
               //    error +='<br>'+'field can not be blank!';
               // }
         });

         // if($('[name=checkoffer]').val() == 1){
         //    error +='<br>'+'Offer Name is in use!';
         // }
   
         $('input[type=checkbox]').each(function () {
             if(this.checked){
               var checkid = $(this).attr('id');
               //var getnum = checkid[checkid.length -1];
               var getnum = checkid.match(/\d+$/);
               totweight = parseInt(parseInt($("#weight"+getnum).val())+parseInt(totweight));
             }
         });

         if(totweight != 100){
            error +='<br>'+'Weight must be 100 in total!';
         }
      
            if(error){
               //$('#loading-indicator').fadeOut();
               showToast('warning', 'Validation Error', error);
            }
            else{
               $('#loading-indicator').fadeIn();
               $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: $('[name=offerForm]').serialize(),
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        //dataTable.ajax.reload();
                        if(data.response == true){
                           dataTable.ajax.reload(function() {
                              showToast('success', 'Success', 'Data added successfully!');
                           $("#exampleModal").modal('hide');
                           $(".btn-close").click();
                           $('#loading-indicator').fadeOut();
                     
                           }, false);
                           
                        }
                        else{
                           $('#loading-indicator').fadeOut();
                           showToast('warning', 'Warning', data.message);
                        }
                        //console.log(data);
                  }
            });
         }
      }

      var max_ln=100;
      const weightArrayDB = [];
      
      $("body").on("click", ".add_sub_url", function () {
         add_row();
      });

      function add_row()
      {
         //$('.suburl').length;
         if(($('.suburl').length) < max_ln){
            var offerLength = ($(".suburl").length)+1;
            var subUrl = `<div class="row suburl addeddom">
                        <div class="col-xs-12 col-sm-6">
                           <div class="form-group">
                              <label for="url" class="subUrlLabel">URL-${offerLength}</label>
                              <input type="url" name="url[]" class="form-control required sublink" id="url${offerLength}" placeholder="Enter URL" data-error="Please enter sub url">
                           </div>
                        </div>
                        <div class="col-xs-12 col-sm-3">
                           <div class="form-group">
                              <label for="weight">Weight</label>
                              <input type="number" name="weight[]" class="form-control required weights" id="weight${offerLength}" placeholder="Weight" data-error="Please select weight" oninput="javascript: this.value = this.value.replace(/[^0-9]/g, '');">
                           </div>
                        </div>
                        <div class="col-xs-12 col-sm-3">
                           <div class="form-group checkbox_ottr">
                              <div class="checkbox">
                                 <label>
                                    <input type="checkbox" class="isActive" name="check_${offerLength}" value="yes" id="checkbox${offerLength}">
                                 </label>

                                 <button type="button" class="del_sub_url" onclick="remove_row(${offerLength})">
                                    <img src="assets/images/minus.png" alt="">
                                    </button>
                                 
                              </div>
                           </div>
                        </div>
                        
                     </div>`;
            // var subUrl= '<div class="suburl">Test</div>';
          $('.suburl_main').append(subUrl);

         // for()
         // {

         // }


            // append - in 1st child
            $('.remove_minus').remove();
            // $('.suburl:first').after('<button class="remove_minus">minus</button>');
            $('.checkbox:first').append(`<button type="button" class="del_sub_url remove_minus" onclick="remove_row(1)">
                                       <img src="assets/images/minus.png" alt="">
                                       </button>`);
            // remove + from 1st child
            $('.add_sub_url').remove();

            // last child e append
            $('.add_plus').remove();
            $('.checkbox:last').after(`<button type="button" onclick="add_row()" class="add_sub_url add_plus">
                                    <img src="assets/images/plus.png" alt="">
                                    </button>`);
         }

      }

      function remove_row(id)
      {
         //console.log(id);
         var offer_length = 1;
         $($("#url"+id)).parents(".suburl").remove();
         $('.add_plus').remove();
         $('.checkbox:last').append(`<button type="button" onclick="add_row()" class="add_sub_url add_plus">
                                    <img src="assets/images/plus.png" alt="">
                                    </button>`);

         if(($('.suburl').length) == 1){
            //offerLength = 0;
            $('.del_sub_url').remove();
            $('.add_plus').remove();
            $('.checkbox:first').append(`<button type="button" onclick="add_row()" class="add_sub_url add_plus">
                                    <img src="assets/images/plus.png" alt="">
                                    </button>`);
            // $(this).find('.subUrlLabel').html(`URL-1`);
            // $(this).find('.sublink').attr('id', `url1`);
            // $(this).find('.weights').attr('id', `weight1`);
            // $(this).find('.isActive').attr('id', `checkbox1`);

         }
            $(".suburl").each(function(){
                 //console.log(i);
                 $(this).find('.subUrlLabel').html(`URL-${offer_length}`);
                 $(this).find('.sublink').attr('id', `url${offer_length}`);
                 $(this).find('.weights').attr('id', `weight${offer_length}`);
                 $(this).find('.isActive').attr('id', `checkbox${offer_length}`);
                 $(this).find('.isActive').attr('name', `check_${offer_length}`);
                 //console.log($(this).find('.sublink').attr('id'));
                 offer_length = offer_length+1;
             });
         // when ln 1
         // append + in 1st child
         // remove - from 1st child
      }

/*======================================= End Of Add New Data =============================================== */





/*======================================= Edit Data ========================================================= */

      $("body").on("click", ".setting", function () {
         
         //console.log($(this).val());
         //$('[name=offer]').prop("readonly",true);
         //$('[name=requestMethod]').val('editInOffer');
         $('[name=offerId]').val($(this).val());

         var newSubUrl='';
         var sub_url_id=''
         var ischeck = '';

         $.ajax({
            url: "ajax.php",
            type: "POST",
            data: {requestMethod: 'editOffer', row: $(this).val()},
            dataType : 'json',
            cache: false,
            success: function(data){
               //console.log(data);
               var obj = data.message;
               var arr = Object.keys(obj).map(function (key) { return obj[key]; });
               //console.log(arr[0]);
               $("#save_btn").val(arr[0]['id']);
               $("#mainoffer").val(arr[0]['offer']);
               $("#slug").val(arr[0]['slug_name']);
               $("#Note").val(arr[0]['note']);
               $("#Tags").val(arr[0]['tag_id']);
               $("#network").val(arr[0]['network_id']);
               $("#start-date").val(arr[0]['start_date']);
               $("#end-date").val(arr[0]['end_date']);
               

               $.each(arr[0]['sub_url_details'], function(i) {

                  //console.log(arr[0]['sub_url_details'][i]);
                  ischeck = (arr[0]['sub_url_details'][i]['status']=='yes') ? 'checked' : '';
                  //sub_url_id += `<input type="hidden" name="sub_url_id[]" value="${arr[0]['sub_url_details'][i]['id']}" />`;

                  if(i==0){
                     $("#url1").val(arr[0]['sub_url_details'][i]['sub_url']);
                     $("#subUrlId1").val(arr[0]['sub_url_details'][i]['id']);
                     $("#weight1").val(arr[0]['sub_url_details'][i]['weight']);
                     if(arr[0]['sub_url_details'][0]['status']=='yes'){
                        $("#checkbox1").prop('checked', true);
                     }
                     if(arr[0]['sub_url_details'][0]['status']=='no'){
                        $("#checkbox1").prop('checked', false);
                     }
                  }
                  else{
                   newSubUrl += `<div class="row suburl addeddom">
                     <div class="col-xs-12 col-sm-6">
                        <div class="form-group">
                           <label for="url" class="subUrlLabel">URL-${i+1}</label>
                           <input type="url" name="url[]" class="form-control required sublink" value="${arr[0]['sub_url_details'][i]['sub_url']}" id="url${i+1}" placeholder="Enter URL" data-error="Please enter sub url">
                           <input type="hidden" name="sub_url_id[]" value="${arr[0]['sub_url_details'][i]['id']}" />
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="weight">Weight</label>
                           <input type="number" name="weight[]" class="form-control required weights" value="${arr[0]['sub_url_details'][i]['weight']}" id="weight${i+1}" placeholder="Weight${i+1}" data-error="Please select weight" oninput="javascript: this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group checkbox_ottr">
                           <div class="checkbox">
                              <label>
                                 <input type="checkbox" ${ischeck} class="isActive" name="check_${i+1}" value="yes" id="checkbox${i+1}">
                              </label>
                                 
                                 <button type="button" onclick="remove_row(${i+1})" class="del_sub_url">
                                 <img src="assets/images/minus.png" alt="">
                                 </button>
                           </div>
                        </div>
                     </div>
                     
                  </div>`;
                  }   
               });
               $('.suburl_main').append(newSubUrl);
               // $('[name="offerId"]').after(sub_url_id);
               if(($('.suburl').length) > 1){
                  // append - in 1st child
                  $('.remove_minus').remove();
                  // $('.suburl:first').after('<button class="remove_minus">minus</button>');
                  $('.checkbox:first').append(`<button type="button" class="del_sub_url remove_minus" onclick="remove_row(1)">
                                             <img src="assets/images/minus.png" alt="">
                                             </button>`);
                  // remove + from 1st child
                  $('.add_sub_url').remove();

                  // last child e append
                  $('.add_plus').remove();
                  $('.checkbox:last').after(`<button type="button" onclick="add_row()" class="add_sub_url add_plus">
                                          <img src="assets/images/plus.png" alt="">
                                          </button>`);

               }

            }
         });
      });



/*======================================= End Of Edit Data ========================================================= */




/*======================================= Clone Data ========================================================= */

      $("body").on("click", ".clone", function () {
         
         //console.log($(this).val());
         //$('[name=offer]').prop("readonly",true);
         //$('[name=requestMethod]').val('editInOffer');
         //$('[name=offerId]').val($(this).val());

         var newSubUrl='';
         var sub_url_id=''
         var ischeck = '';
         weightArrayDB.length = 0;

         $.ajax({
            url: "ajax.php",
            type: "POST",
            data: {requestMethod: 'editOffer', row: $(this).val()},
            dataType : 'json',
            cache: false,
            success: function(data){
               //console.log(data);
               var obj = data.message;
               var arr = Object.keys(obj).map(function (key) { return obj[key]; });
               //console.log(arr[0]);
               $("#save_btn").val(arr[0]['id']);
               $("#mainoffer").val(arr[0]['offer']+'-copy');
               $("#slug").val(arr[0]['slug_name']+'-copy');
               $("#Note").val(arr[0]['note']);
               $("#Tags").val(arr[0]['tag_id']);
               $("#network").val(arr[0]['network_id']);
               $("#start-date").val(arr[0]['start_date']);
               $("#end-date").val(arr[0]['end_date']);
               

               $.each(arr[0]['sub_url_details'], function(i) {

                  //console.log(arr[0]['sub_url_details'][i]);
                  ischeck = (arr[0]['sub_url_details'][i]['status']=='yes') ? 'checked' : '';
                  //sub_url_id += `<input type="hidden" name="sub_url_id[]" value="${arr[0]['sub_url_details'][i]['id']}" />`;
                  weightArrayDB.push(arr[0]['sub_url_details'][i]['weight']);
                  if(i==0){
                     $("#url1").val(arr[0]['sub_url_details'][i]['sub_url']);
                     $("#subUrlId1").val(arr[0]['sub_url_details'][i]['id']);
                     $("#weight1").val(arr[0]['sub_url_details'][i]['weight']);
                     if(arr[0]['sub_url_details'][0]['status']=='yes'){
                        $("#checkbox1").prop('checked', true);
                     }
                     if(arr[0]['sub_url_details'][0]['status']=='no'){
                        $("#checkbox1").prop('checked', false);
                     }
                  }
                  else{
                   newSubUrl += `<div class="row suburl addeddom">
                     <div class="col-xs-12 col-sm-6">
                        <div class="form-group">
                           <label for="url" class="subUrlLabel">URL-${i+1}</label>
                           <input type="url" name="url[]" class="form-control required sublink" value="${arr[0]['sub_url_details'][i]['sub_url']}" id="url${i+1}" placeholder="Enter URL" data-error="Please enter sub url">
                           <input type="hidden" name="sub_url_id[]" value="${arr[0]['sub_url_details'][i]['id']}" />
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="weight">Weight</label>
                           <input type="number" name="weight[]" class="form-control required weights" value="${arr[0]['sub_url_details'][i]['weight']}" id="weight${i+1}" placeholder="Weight${i+1}" data-error="Please select weight" oninput="javascript: this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group checkbox_ottr">
                           <div class="checkbox">
                              <label>
                                 <input type="checkbox" ${ischeck} class="isActive" name="check_${i+1}" value="yes" id="checkbox${i+1}">
                              </label>
                                 
                                 <button type="button" onclick="remove_row(${i+1})" class="del_sub_url">
                                 <img src="assets/images/minus.png" alt="">
                                 </button>
                           </div>
                        </div>
                     </div>
                     
                  </div>`;
                  }   
               });
               console.log('WeightageArray',weightArrayDB);
               $('.suburl_main').append(newSubUrl);
               // $('[name="offerId"]').after(sub_url_id);
               if(($('.suburl').length) > 1){
                  // append - in 1st child
                  $('.remove_minus').remove();
                  // $('.suburl:first').after('<button class="remove_minus">minus</button>');
                  $('.checkbox:first').append(`<button type="button" class="del_sub_url remove_minus" onclick="remove_row(1)">
                                             <img src="assets/images/minus.png" alt="">
                                             </button>`);
                  // remove + from 1st child
                  $('.add_sub_url').remove();

                  // last child e append
                  $('.add_plus').remove();
                  $('.checkbox:last').after(`<button type="button" onclick="add_row()" class="add_sub_url add_plus">
                                          <img src="assets/images/plus.png" alt="">
                                          </button>`);

               }

            }
         });
      });


/*======================================= Clone Data ========================================================= */


/*======================================= Filter Type Prepair ========================================================= */

   $("body").on("change", "#filter_menu", function () {
         //$("#example").DataTable().destroy();
         //console.log($(this).val());
         $("#status_menu").prop("disabled", true);
         var filter_option = $(this).val();
         $.ajax({
            url: "ajax.php",
               type: "POST",
               data: {requestMethod: 'getFilterType', filterType: filter_option},
               dataType : 'json',
               cache: false,
               success: function(data){
                  var obj = data.message;
                  var arr = Object.keys(obj).map(function (key) { return obj[key]; });
                  //console.log(arr);

                  var s = '<option value="-1" disabled selected>Please Select an Option</option>';
                  for (var i = 0; i < arr.length; i++) {
                     //console.log(arr[i][filter_option]);
                     if(arr[i][filter_option] != ''){
                      s += '<option value="' + arr[i][filter_option] + '">' + arr[i][filter_option] + '</option>';
                     }
                  }
                  $("#status_menu").html(s);
                  $("#status_menu").prop("disabled", false);


               }
            });
      });

      $('#status_menu').select2({
                placeholder: 'Search for an option',
                allowClear: true // Optional: allows a clear (reset) option
      });


/*======================================= Filter Type Prepair ========================================================= */





/*======================================= Filter Offer ========================================================= */

   $("body").on("change", "#status_menu", function () {

         var filter_type = $("#filter_menu").val();
         var filter_value = $(this).val();
         $("#example").DataTable().destroy();
         //console.log($(this).val());
         dataTable = $("#example").DataTable({
            "processing":true,
            "serverSide":true,
            "order":[],
            "ajax":{
               url:"ajax.php",
               method:"POST",
               "data": function (d) {
                     d.requestMethod = "fetchAll";  // Add static custom data
                     d.offerStatus = status;  // Add dynamic data from an input field
                     d.filterType = filter_type;  
                     d.filterValue = filter_value;  
               },
               //data: {requestMethod: 'fetchAll', filterType: filter_type, filterValue: filter_value},
               //dataType : 'json',
               // cache: false,
               // success: function(data){
               //    console.log(data);
               // }
            },
            "createdRow": function(row, data, dataIndex) {
                 // Add a class to the first <td> element (you can adjust the index)
                  $('td:eq(1)', row).addClass('offer_name');
                  $('td:eq(1)', row).attr('data-bs-toggle', 'modal');
                  $('td:eq(1)', row).attr('data-bs-target', '#offerhovereModal');
             },
            "coloumnDefs":[{
               "target":[7],
               "orderable":false
            }],
            "pageLength": 50,
            "lengthMenu": [50, 100, 250, 500, 1000],
            "autoWidth": false,
         });
      });


/*======================================= Filter Offer ========================================================= */




/*======================================= Sub URL Weight Distribution =========================================== */


   $("body").on("click", "#equalDistribution", function () {
         weightDistribution();
      });

      function weightDistribution(){
         countChecked = $('input:checkbox.isActive:checked').length;
         var equalWeight = Math.floor(100/countChecked);
         var remainWeight = Math.floor(100%countChecked);
         $('.isActive').each(function(i,obj) {
            if($(this).is(':checked')){
               var id = (i+1);
               if(remainWeight >0 && i == 0){
                  $('#weight'+id).val(equalWeight+remainWeight)
               }else{
                  $('#weight'+id).val(equalWeight)
               }
            }
         });
      }

/*======================================= Sub URL Weight Distribution =========================================== */



/*================================================ Sub-Offer Modal (click-triggered) =========================================== */

   /* data-bs-toggle="modal" on .offer_name cells (set in createdRow) handles click-to-open.
      Bootstrap fires show.bs.modal with e.relatedTarget = the clicked td. */
   $('#offerhovereModal').on('show.bs.modal', function (e) {
      var triggerEl = $(e.relatedTarget);
      var spanVal   = triggerEl.nextAll('td').find('span').attr('value');

      if ($.fn.DataTable.isDataTable('#offerhover-tbl')) {
         $('#offerhover-tbl').DataTable().destroy();
      }

      $('#offerhover-tbl').DataTable({
         "processing":  true,
         "serverSide":  false,
         "order":       [],
         "ajax": {
            url:    "ajax.php",
            method: "POST",
            data:   { requestMethod: 'getSubOffers', oid: spanVal },
         },
         "pageLength": 100,
         "autoWidth":  false,
      });
   });

   $('#offerhovereModal').on('hidden.bs.modal', function () {
      if ($.fn.DataTable.isDataTable('#offerhover-tbl')) {
         $('#offerhover-tbl').DataTable().destroy();
      }
   });

/*================================================ Sub-Offer Modal End =========================================== */





/*================================================ Copy Button Feature =========================================== */


   $("body").on("click", ".btn_copy", function () {
         var id = $(this).val();
         var copyText = document.getElementById("hdn_"+id);
         copyText.select();
         copyText.setSelectionRange(0, 99999);
         navigator.clipboard.writeText(copyText.value);
         showToast('success', 'Copied!', 'URL copied to clipboard');
   });


/*================================================ Copy Button Feature =========================================== */




/*======================================= Delete / Archive Offer ========================================================= */


   $("body").on("click", ".btn_archive", function () {
      var oid = $(this).val();
      $.confirm({
          title: 'Warning!',
          content: 'Are you sure? You want to archive this offer ?',
          buttons: {
              confirm: function () {
               $('#loading-indicator').fadeIn();
                  $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: {requestMethod: 'archiveOffer', oid: oid},
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        //console.log(data);
                        dataTable.ajax.reload(function() {
                           showToast('success', 'Archived', 'Offer archived successfully');
                           $(".btn-close").click();
                           $('#loading-indicator').fadeOut();
                        }, false);
                        
                     }
                  });
              },
              cancel: function () {
                  // $.alert('Canceled!');
                  // console.log('no ajax');
              }
          }
      });
   });


   $("body").on("click", ".btn_delete", function () {
      //console.log('in');
      var oid = $(this).val();
      $.confirm({
          title: 'Warning!',
          content: 'Are you sure? You want to delete this offer ?',
          buttons: {
              confirm: function () {
               $('#loading-indicator').fadeIn();
                  $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: {requestMethod: 'deleteOffer', oid: oid},
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        //console.log(data);
                         dataTable.ajax.reload(function() {
                           showToast('success', 'Deleted', 'Offer deleted successfully');
                           $(".btn-close").click();
                           $('#loading-indicator').fadeOut();
                        }, false);
                     }
                  });
              },
              cancel: function () {
                  // $.alert('Canceled!');
                  // console.log('no ajax');
              }
          }
      });
   });


/*======================================= Delete / Archive Offer ========================================================= */






/*======================================= Reset Offer ========================================================= */

   $("body").on("click", ".btn_reset", function () {

         //console.log($(this).val());
         //var offer_status = $(this).val();
         $('#loading-indicator').fadeIn();
          $.ajax({
            url: "ajax.php",
            type: "POST",
            data: {requestMethod: 'resetOffer', oid: $(this).val()},
            dataType : 'json',
            cache: false,
            success: function(data){
               dataTable.ajax.reload(function() {
                  //$(".table-responsive").html(data3);
                  $('#loading-indicator').fadeOut();
                  showToast('info', 'Reset', data.message);
               }, false);
               //console.log(data.message);
            }
         });
      });


/*======================================= Reset Offer ========================================================= */



   function isUrlValid(url) {
    return /^(https?|s?ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(url);
}









      
      function addClassIfNeeded() {

         // When DesktopReady Its Come
         if (window.innerWidth > 991) {
            mainArea.classList.remove('openBar');
            mainArea.classList.add('closeBar');
         }
         else {
            mainArea.classList.remove('openBar');
            mainArea.classList.add('closeBar');
         }
      }
      document.addEventListener('DOMContentLoaded', function () {
         const mainArea = document.getElementById('mainArea');
         const close_sidebar = document.getElementById('close_sidebar');
         const open_sidebar = document.getElementById('open_sidebar');

         close_sidebar.addEventListener('click', function () {
            mainArea.classList.remove('openBar');
            mainArea.classList.add('closeBar');
         });

         open_sidebar.addEventListener('click', function () {
            mainArea.classList.add('openBar');
            mainArea.classList.remove('closeBar');
         });
      });

      window.addEventListener('resize', function () {
         addClassIfNeeded();
      });

      addClassIfNeeded();



      

   </script>

   <script>
   function myFunction(oid) {
     document.getElementById("myDropdown_"+oid).classList.toggle("show");
   }

   window.onclick = function(event) {
     if (!event.target.matches('.dropbtn')) {
       var dropdowns = document.getElementsByClassName("dropdown-content");
       var i;
       for (i = 0; i < dropdowns.length; i++) {
         var openDropdown = dropdowns[i];
         if (openDropdown.classList.contains('show')) {
           openDropdown.classList.remove('show');
         }
       }
     }
   }
</script>

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