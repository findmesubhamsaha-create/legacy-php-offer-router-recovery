<?php
// SHV1-03: require authenticated session
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: ../index.php');
    exit;
}
// DU-01: load BASE_URL before HTML output so template can use it
require dirname(__FILE__) . '/../library/Settings.php';
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
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.2/css/all.min.css" crossorigin="anonymous">
      <link rel="stylesheet" href="../assets/css/dashboard_style.css?v=<?=time()?>">
      <link rel="stylesheet" href="../assets/css/modern.css">
      <link rel="stylesheet" href="../assets/css/design-system-v2.css">
   </head>
   <body>
      <div class="main closeBar" id="mainArea">
         <div class="topBar">
            <button id="open_sidebar" class="IconClick">
            <img src="../assets/images/menu.png" alt="">
            </button>
         </div>
         <div class="sidebar">
            <button id="close_sidebar" class="IconClick">
            <img src="../assets/images/cancel.png" alt="">
            </button>
            <div class="sideMenu_ottr">
               <ul class="">
                  <li>
                     <a href="<?= BASE_URL ?>/portal/dashboard.php">
                        <i class="fas fa-th-list ds2-nav-icon"></i> Offers
                     </a>
                  </li>
                  <li>
                     <a href="<?= BASE_URL ?>/portal/analytics.php">
                        <i class="fas fa-chart-bar ds2-nav-icon"></i> Analytics
                     </a>
                  </li>
                  <li>
                     <a href="<?= BASE_URL ?>/portal/import.php">
                        <i class="fas fa-file-import ds2-nav-icon"></i> Import
                     </a>
                  </li>
                  <li>
                     <a href="<?= BASE_URL ?>/portal/export/">
                        <i class="fas fa-file-export ds2-nav-icon"></i> Export
                     </a>
                  </li>
               </ul>
            </div>
         </div>
         <div class="mainArea">
            <div class="inner_main mt-2">
               <!--Page Content Here-->
             


<?php
$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);


//echo 'in'; die();

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Determine the total number of records
$query_count = "SELECT COUNT(*) AS total_count FROM ( SELECT ROW_NUMBER() OVER (ORDER BY t1.offer) AS SerialNumber FROM tbl_offer_url t1 JOIN tbl_tag t2 ON t1.tag_id = t2.id JOIN tbl_network t3 ON t1.network_id = t3.id JOIN tbl_sub_offer_url t5 ON t1.id = t5.main_offer_id WHERE t1.offer_status = 1 AND t5.deleted_status = 'no' GROUP BY t1.offer, t1.slug_name, t1.note, t2.tag_name, t3.network_name, t5.sub_url, t5.weight, t5.status ) AS derived_table;";

//echo $result_count; die();

$result_count = $mysqli->query($query_count);
$total_records = $result_count->fetch_assoc()['total_count'];
//echo $total_records; die();
$batch_size = 100;
$total_batches = ceil($total_records / $batch_size);

echo "<div class='export_ottr'>";
echo "<h1>Download Links</h1>";
echo "<ul>";

for ($i = 0; $i < $total_batches; $i++) {
    $offset = $i * $batch_size;
    $batch_number = $i + 1;
    echo "<li><a href='download.php?offset=$offset&limit=$batch_size'>Download Batch $batch_number</a></li>";
}

echo "</ul>";
echo "</div>";
// Close the database connection
$mysqli->close();
?>

               <!--Page Content End-->
            </div>
         </div>
      </div>
      <!--Page Js Here-->
    
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
      <script>
      (function () {
         var path = window.location.pathname;
         document.querySelectorAll('.sideMenu_ottr a').forEach(function (a) {
            try {
               var ap = new URL(a.href).pathname;
               if (path === ap || path.endsWith(ap.replace(/^.*\/portal\//, '/portal/'))) {
                  a.classList.add('ds2-active');
               }
            } catch (e) {}
         });
      })();
      </script>
   </body>
</html>
