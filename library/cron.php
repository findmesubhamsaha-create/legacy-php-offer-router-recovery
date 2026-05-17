<?php
require 'Settings.php';
$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

echo '<pre>';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$search_date = date('Y-m-d');
$clicks =  $conn->query("SELECT C.offer_id,O.offer,COUNT(C.id) clicks,C.created_at FROM tbl_click C JOIN tbl_offer_url O ON O.id = C.offer_id WHERE created_at = '".$search_date."' GROUP BY created_at,offer_id");

if ($clicks->num_rows > 0){
  while($click = $clicks->fetch_assoc()){
    $exist = $conn->query("SELECT * FROM tbl_report WHERE report_date = '".$search_date."' AND main_offer_id = ".$click['offer_id']);
    if ($exist->num_rows > 0){
      $upd_sql = "UPDATE tbl_report SET offer_clicks = ".$click['clicks']." WHERE main_offer_id = ".$click['offer_id']." AND report_date = '".$search_date."'";
      $result = $conn->query($upd_sql);
      if($result){
        echo 'Old Row Updated';
      }else{
        echo "Old Row Not Updated";
      }
    }
    else{
      $ins_sql = "INSERT INTO tbl_report (main_offer_id,main_offer_url,offer_clicks,report_date) VALUES (".$click['offer_id'].",'".$click['offer']."',".$click['clicks'].",'".$search_date."')";
      $result = $conn->query($ins_sql);
      if ($result) {
        echo 'New Row Inserted';
      } else {
        echo "New Row Not Inserted";
      }
    }
    echo '<br>';
  }
}

?>