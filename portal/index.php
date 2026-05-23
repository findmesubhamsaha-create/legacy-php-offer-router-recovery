<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
   <title>Sign In — Traffic Router</title>
   <link rel="icon" type="image/x-icon" href="favicon.png">
   <link rel="shortcut icon" type="image/x-icon" href="favicon.png">
   <link rel="apple-touch-icon" href="app-icon.png">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.2/css/all.min.css" crossorigin="anonymous">
   <link rel="stylesheet" href="assets/css/design-system-v2.css">
   <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
   </style>
</head>
<body class="ds2-login-page">

   <div class="ds2-login-card">

      <!-- Brand -->
      <div class="ds2-login-brand">
         <div class="ds2-login-brand-icon">
            <i class="fas fa-route"></i>
         </div>
         <div class="ds2-login-brand-text">
            <span class="ds2-login-brand-name">Traffic Router</span>
            <span class="ds2-login-brand-sub">Intelligence Platform</span>
         </div>
      </div>

      <!-- Heading -->
      <h1 class="ds2-login-title">Welcome back</h1>
      <p class="ds2-login-subtitle">Sign in to your account to continue</p>

      <!-- Inline error -->
      <div id="ds2-login-error"></div>

      <!-- Form — action, method, name, field names all unchanged -->
      <form action="ajax.php" class="login-form" method="post" name="memberForm">
         <input type="hidden" name="requestMethod" value="login">

         <div class="ds2-login-field">
            <label for="login-username">Email / Username</label>
            <input type="text" id="login-username" name="username"
                   class="form-control required" placeholder="you@example.com"
                   data-error="Please enter your username" autocomplete="username">
         </div>

         <div class="ds2-login-field">
            <label for="login-password">Password</label>
            <input type="password" id="login-password" name="password"
                   class="form-control required" placeholder="••••••••"
                   data-error="Please enter your password" autocomplete="current-password">
         </div>

         <button type="button" id="login-submit-btn" onclick="return submitForm()"
                 class="btn btn-primary ds2-login-btn">
            Sign In
         </button>
      </form>

      <div class="ds2-login-footer">
         Traffic Routing Intelligence Platform
      </div>

   </div>

   <p id="loading-indicator" style="display:none;">Processing...</p>

   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

   <script type="text/javascript">
      /* ── Inline error helper (replaces $.alert on login page) ── */
      function showLoginError(msg) {
         var el = document.getElementById('ds2-login-error');
         el.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + msg.replace(/<br\s*\/?>/gi, ' ');
         el.classList.add('visible');
         el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function hideLoginError() {
         var el = document.getElementById('ds2-login-error');
         el.classList.remove('visible');
      }

      /* ── Submit function — AJAX logic preserved exactly ── */
      function submitForm() {
         var error = '';
         hideLoginError();

         $('[name=memberForm] .required').each(function () {
            if ($(this).val() == '') {
               error += (error ? ' ' : '') + $(this).data('error') + '.';
            }
         });

         if (error) {
            showLoginError(error);
         } else {
            var btn = document.getElementById('login-submit-btn');
            btn.classList.add('loading');
            btn.textContent = 'Signing in…';
            $('#loading-indicator').fadeIn();

            $.ajax({
               url: 'ajax.php',
               type: 'POST',
               data: $('[name=memberForm]').serialize(),
               dataType: 'json',
               cache: false,
               success: function (data) {
                  if (data.response == true) {
                     window.location.href = 'dashboard.php';
                     return true;
                  } else {
                     showLoginError(data.message);
                     btn.classList.remove('loading');
                     btn.textContent = 'Sign In';
                     $('#loading-indicator').fadeOut();
                     return false;
                  }
               },
               error: function () {
                  showLoginError('Network error — please try again.');
                  btn.classList.remove('loading');
                  btn.textContent = 'Sign In';
                  $('#loading-indicator').fadeOut();
               }
            });
         }
      }

      /* ── Clear error on input ── */
      document.querySelectorAll('[name=memberForm] input').forEach(function (el) {
         el.addEventListener('input', hideLoginError);
      });

      /* ── Allow Enter key to submit ── */
      document.querySelector('[name=memberForm]').addEventListener('keydown', function (e) {
         if (e.key === 'Enter') { e.preventDefault(); submitForm(); }
      });
   </script>
</body>
</html>
