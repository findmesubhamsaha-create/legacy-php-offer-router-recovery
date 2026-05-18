<?php
session_start();
if(!isset($_SESSION["is_login"])){
   header("Location: index.php");
}
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
      <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
      <link rel="stylesheet" href="assets/css/dashboard_style.css">
      <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">

   </head>
   <body>
   <div class="main closeBar" id="mainArea">
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
               <div class="AddRecord">
                  <button type="button" class="btn btn-primary " data-bs-toggle="modal" id="addNewRecord"
                              data-bs-target="#exampleModal">Add Record</button>
               </div>
               <!-- <div class="d-flex inputarae ">
                  <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                  <button class="btn btn-outline-success" type="submit">Search</button>
               </div> -->
            </div>
            
            <div class="table-responsive">
               
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
                           <input type="text" name="tags" class="form-control required" id="Tags" placeholder="Tags" data-error="Please enter tag">
                        </div>
                     </div>
                     <div class="col-xs-12 col-sm-3">
                        <div class="form-group">
                           <label for="Note">Note</label>
                           <textarea style="" name="note" class="form-control required" placeholder="Add Note" id="Note"  data-error="Please enter note">
                           </textarea>
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
   <div class="modal Step4From" id="clickexampleModal" tabindex="-1" aria-labelledby="clickexampleModalLabel"
      aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h1 class="modal-title fs-5" id="clickexampleModalLabel">Click</h1>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body click-modal-body report-body">
               <!--  report table goes here   -->
            </div>
         </div>
      </div>
   </div>
   <!-- Modal End -->

   <p id="loading-indicator" style="display:none;">Processing...</p>

   <script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
   <script type="text/javascript" src="https://cdn.datatables.net/1.10.8/js/jquery.dataTables.min.js"></script>

   <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
   <script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
   <script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>


   <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
   <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js"></script>

   
   <script type="text/javascript">
      $(document).ready(function() {
         $('#example').DataTable();
      } );
   </script>


<script>
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

      $( document ).ready(function() {
          $.ajax({
            url: "ajax.php",
            type: "POST",
            data: {requestMethod: 'fetchAll'},
            dataType : 'json',
            cache: false,
            success: function(data){
               $.ajax({
                  url: "table.php",
                  type: "POST",
                  data: {data: data},
                  dataType : 'html',
                  cache: false,
                  success: function(data2){
                     //console.log(data2);
                     $(".table-responsive").html(data2);
                  }
               });
            }
         });
      });

/*======================================= End Of Fetch Data ========================================== */


/*======================================= Fetch Report Data ========================================== */

      $("body").on("click", ".btn_report", function () {
          $.ajax({
            url: "ajax.php",
            type: "POST",
            data: {requestMethod: 'fetchReport', oid: $(this).val()},
            dataType : 'json',
            cache: false,
            success: function(data){
               //console.log(data);
               $.ajax({
                  url: "report-table.php",
                  type: "POST",
                  data: {data: data},
                  dataType : 'html',
                  cache: false,
                  success: function(data2){
                     //console.log(data2);
                     $(".report-body").html(data2);
                  }
               });
            }
         });
      });

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

      // $("body").on("blur", "#mainoffer", function () {
      //    // if(!isUrlValid($(this).val())){
      //    //    $.alert({
      //    //       title: 'Warning!',
      //    //       content: 'enter valid URL',
      //    //    });
      //    //     $(this).val('');
      //    // }
      //    // else{

      //   $.ajax({
      //       url: "ajax.php",
      //       type: "POST",
      //       data: {url: $(this).val(), requestMethod: 'checkMainOffer'},
      //       dataType : 'json',
      //       cache: false,
      //       success: function(data){
      //          if(data.message == 'success'){
      //             $('[name=checkoffer]').val(1);
      //             //console.log('in');
      //             // $.alert({
      //             //    title: 'Warning!',
      //             //    content: 'offer already exixts!',
      //             // });
      //             //$('[name=offerForm]')[0].reset();
      //             //error +='<br>'+'offer name already exixts!';
      //             //$('#mainoffer').val('');
      //             //$("#exampleModal").modal('hide');
      //          }
      //          else{
      //             $('[name=checkoffer]').val(0);
      //          }
      //       }
      //    });
      //   //}
      // });


      $("body").on("change", ".sublink", function () {
         if(!isUrlValid($(this).val())){
            $.alert({
               title: 'Warning!',
               content: 'enter valid sub URL',
            });
            $(this).val('');
         }
         else{
            var reportRecipients = $("input[name='url[]']").map(function(){return $(this).val();}).get();
            var recipientsArray = reportRecipients.sort(); 
            var reportRecipientsDuplicate = [];
            for (var i = 0; i < recipientsArray.length - 1; i++) {
                if (recipientsArray[i + 1] == recipientsArray[i]) {
                     // $(this).parents(".suburl").remove();
                     $(this).val('');
                     $.alert({
                        title: 'Warning!',
                        content: 'Sub Url already added!',
                     });
                }
                else{
                  console.log('out');
                }
            }
         }
      });

      $('.cls_slug').keypress(function( e ) {
          if(e.which === 32) 
              return false;
      })

      // $("body").on("change", "#slug", function () {
      //    $.ajax({
      //       url: "ajax.php",
      //       type: "POST",
      //       data: {slug: $(this).val(), requestMethod: 'checkSlug'},
      //       dataType : 'json',
      //       cache: false,
      //       success: function(data){
      //          if(data.message == 'success'){
      //             //console.log('in');
      //             $.alert({
      //                title: 'Warning!',
      //                content: 'Slug Name already exixts!',
      //             });
      //             //$('[name=offerForm]')[0].reset();
      //             //$("#exampleModal").modal('hide');
      //             $('#slug').val('');
      //          }
      //       }
      //    });
      // });

      function submitForm(){
         $('#loading-indicator').fadeIn();
         var error = '';
         var totweight=0;
         //console.log('in');
         $('[name=offerForm] .required').each(function(){
               if($(this).val() == ''){
                  //console.log('[name=offer]').val();
                  error +='<br>'+$(this).data('error');
               }
               if(!$(this).val().replace(/\s/g, '').length){
                  error +='<br>'+'field can not be blank!';
               }
         });

         // if($('[name=checkoffer]').val() == 1){
         //    error +='<br>'+'Offer Name is in use!';
         // }
   
         $('input[type=checkbox]').each(function () {
             if(this.checked){
               var checkid = $(this).attr('id');
               var getnum = checkid[checkid.length -1];
               totweight = parseInt(parseInt($("#weight"+getnum).val())+parseInt(totweight));
             }
         });

         if(totweight != 100){
            error +='<br>'+'Weight must be 100 in total!';
         }
      
            if(error){
               $('#loading-indicator').fadeOut();
               $.alert({
                  title: 'Warning!',
                  content: error,
               });
            }
            else{
               //$('#loading-indicator').fadeIn();
               $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: $('[name=offerForm]').serialize(),
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        if(data.response == true){
                           $('#loading-indicator').fadeOut();
                           //$("body").removeClass("modal-open");
                           // $.alert({
                           //    title: 'Success!',
                           //    content: 'Data added successfully!',
                           // });
                            //location.reload();
                           $.ajax({
                              url: "ajax.php",
                              type: "POST",
                              data: {requestMethod: 'fetchAll'},
                              dataType : 'json',
                              cache: false,
                              success: function(data2){
                                 //console.log(data);
                                 $.ajax({
                                    //url: "datatable.php",
                                    url: "table.php",
                                    type: "POST",
                                    data: {data: data2},
                                    dataType : 'html',
                                    cache: false,
                                    success: function(data3){
                                       //console.log(data2);
                                       //$('[name=offerForm]')[0].reset();
                                       $('.form-modal-body').load(location.href + ' .form-modal-body');
                                       $(".table-responsive").html(data3);
                                       $.alert({
                                          title: 'Success!',
                                          content: 'Data added successfully!',
                                       });
                                       //$("#exampleModal").modal('hide');
                                       $(".btn-close").click();
                                    }
                                 });
                              }
                           });
                        }
                        else{
                           $('#loading-indicator').fadeOut();
                           $.alert({
                              title: 'Warning!',
                              content: data.message,
                           });
                        }
                        //console.log(data);
                  }
            });
         }
      }


     //  $("body").on("click", ".add_sub_url", function () {
     //     var offerLength = ($(".suburl").length)+1;
     //     //var newName = 'check_' + offerLength;
     //     var subUrl = `<div class="row suburl addeddom">
     //                 <div class="col-xs-12 col-sm-6">
     //                    <div class="form-group">
     //                       <label for="url">URL-${offerLength}</label>
     //                       <input type="url" name="url[]" class="form-control required sublink" id="url${offerLength}" placeholder="Enter URL" data-error="Please enter sub url">
     //                    </div>
     //                 </div>
     //                 <div class="col-xs-12 col-sm-3">
     //                    <div class="form-group">
     //                       <label for="weight">Weight</label>
     //                       <input type="number" name="weight[]" class="form-control required weights" id="weight${offerLength}" placeholder="Weight" data-error="Please select weight">
     //                    </div>
     //                 </div>
     //                 <div class="col-xs-12 col-sm-3">
     //                    <div class="form-group checkbox_ottr">
     //                       <div class="checkbox">
     //                          <label>
     //                             <input type="checkbox" class="isActive" name="check_${offerLength}" value="yes" id="checkbox${offerLength}">
     //                          </label>
                                 
     //                             <button type="button" class="del_sub_url">
     //                             <img src="assets/images/minus.png" alt="">
     //                             </button>
                              
     //                       </div>
     //                    </div>
     //                 </div>
                     
     //              </div>`;
     //   $(this).closest('.suburl').after(subUrl);
     // });

     //  $("body").on("click", ".del_sub_url", function () {
     //        $(this).parents(".addeddom").remove();
     //  });

      var max_ln=100;
      
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



/*======================================= Delete / Archive Offer ========================================================= */


   $("body").on("click", ".btn_archive", function () {
      var oid = $(this).val();
      $.confirm({
          title: 'Warning!',
          content: 'Are you sure? You want to archive this offer ?',
          buttons: {
              confirm: function () {
                  $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: {requestMethod: 'archiveOffer', oid: oid},
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        //console.log(data);
                        $.ajax({
                              url: "ajax.php",
                              type: "POST",
                              data: {requestMethod: 'fetchAll'},
                              dataType : 'json',
                              cache: false,
                              success: function(data2){
                                 $.ajax({
                                    url: "table.php",
                                    type: "POST",
                                    data: {data: data2},
                                    dataType : 'html',
                                    cache: false,
                                    success: function(data3){
                                       $('.form-modal-body').load(location.href + ' .form-modal-body');
                                       $(".table-responsive").html(data3);
                                       $.alert('Offer Arichived!');
                                       $(".btn-close").click();
                                    }
                                 });
                              }
                           });
                        //$.alert('Offer Arichived!');
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
      console.log('in');
      var oid = $(this).val();
      $.confirm({
          title: 'Warning!',
          content: 'Are you sure? You want to delete this offer ?',
          buttons: {
              confirm: function () {
                  $.ajax({
                     url: "ajax.php",
                     type: "POST",
                     data: {requestMethod: 'deleteOffer', oid: oid},
                     dataType : 'json',
                     cache: false,
                     success: function(data){
                        console.log(data);
                        $.ajax({
                              url: "ajax.php",
                              type: "POST",
                              data: {requestMethod: 'fetchAll'},
                              dataType : 'json',
                              cache: false,
                              success: function(data2){
                                 $.ajax({
                                    url: "table.php",
                                    type: "POST",
                                    data: {data: data2},
                                    dataType : 'html',
                                    cache: false,
                                    success: function(data3){
                                       $('.form-modal-body').load(location.href + ' .form-modal-body');
                                       $(".table-responsive").html(data3);
                                       $.alert('Offer Deleted!');
                                       $(".btn-close").click();
                                    }
                                 });
                              }
                           });
                        //$.alert('Offer Deleted!');
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
   </body>
</html>