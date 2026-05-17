<!doctype html>
<html lang="en">
   <head>
      <title>Login</title>
      <meta charset="utf-8">
	<link  rel="icon" type="image/x-icon" href="favicon.png" />
	<link  rel="shortcut icon" type="image/x-icon" href="favicon.png" />
	<link rel="apple-touch-icon" href="app-icon.png"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
      <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
      <link rel="stylesheet" href="assets/css/style.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">
      <!-- <link rel="stylesheet" href="assets/css/style-new.css"> -->
   </head>
   <body>
      <section class="ftco-section">
         <div class="container">
            <!--<div class="row justify-content-center">
               <div class="col-md-6 text-center mb-5">
                  <h2 class="heading-section">Login</h2>
               </div>
            </div>-->
            <div class="row justify-content-center">
               <div class="col-md-6 col-lg-5">
                  <div class="login-wrap p-4 p-md-5">
                     <div class="icon d-flex align-items-center justify-content-center">
                        <span class="fa fa-user-o"></span>
                     </div>
                     <h3 class="text-center mb-4">Login</h3>
                     <form action="ajax.php" class="login-form" method="post" name="memberForm">
                        <input type="hidden" name="requestMethod" value="login">
                        <div class="form-group">
                           <input type="text" name="username" class="form-control rounded-left required" placeholder="Email" data-error="Please enter your user name">
                        </div>
                        <div class="form-group d-flex">
                           <input type="password" name="password" class="form-control rounded-left required" placeholder="Password" data-error="Please enter your password">
                        </div>
                        <div class="form-group d-md-flex">
                           <!-- <div class="w-50">
                              <label class="checkbox-wrap checkbox-primary">Remember Me
                              <input type="checkbox" checked>
                              <span class="checkmark"></span>
                              </label>
                           </div> -->
                           <!-- <div class="w-50 text-md-right">
                              <a href="#">Forgot Password</a>
                           </div> -->
                        </div>
                        <div class="form-group">
                           <button type="button" onclick="return submitForm()" class="btn btn-primary rounded submit p-3 px-5">Sign In</button>
                        </div>
                     </form>
                  </div>
               </div>
            </div>
         </div>
      </section>

      <p id="loading-indicator" style="display:none;">Processing...</p>


      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      crossorigin="anonymous"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
      <script type="text/javascript">
               
               function submitForm(){
                  var error = '';
                  $('.modal-body').html('');
                  $('[name=memberForm] .required').each(function(){
                        if($(this).val() == ''){
                           error +='<br>'+$(this).data('error');
                        }
                  });
                     if(error){
                        $.alert({
                           title: 'Warning!',
                           content: error,
                        });
                     }
                     else{
                        $('#loading-indicator').fadeIn();
                        $.ajax({
                             url: "ajax.php",
                             type: "POST",
                             data: $('[name=memberForm]').serialize(),
                             dataType : 'json',
                             cache: false,
                             success: function(data){
                               if(data.response == true){
                                    window.location.href = "dashboard.php";
                                    console.log(data);
                                    return true;   
                               }
                               else{
                                 // error.push(data.message);
                                 // errorModal(error);
                                 $.alert({
                                    title: 'Warning!',
                                    content: data.message,
                                 });
                                 $('#loading-indicator').fadeOut();
                                 return false;
                               }
                         }
                     });
                  }
                  //return false;   
               }
      </script>
   </body>
</html>