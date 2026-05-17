<?php
require 'Settings.php';
$servername = DB_HOST;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

echo '<pre>';
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
//echo 'done';
$search_date = date('Y-m-d');
$exist = $conn->query("SELECT * FROM tbl_report WHERE report_date = '".$search_date."'");
if ($exist->num_rows > 0) {
  while($row = $exist->fetch_assoc()) {
    $curr_record =  $conn->query("SELECT COUNT(id) clicks, offer_id,created_at FROM tbl_click WHERE offer_id = ".$row['main_offer_id']." AND created_at =  '".$search_date."' GROUP BY offer_id");
    if ($curr_record->num_rows > 0){
      $new = $curr_record->fetch_assoc();
      //print_r($new);
      $upd_sql = "UPDATE tbl_report SET offer_clicks = ".$new['clicks']." WHERE main_offer_id = ".$new['offer_id']." AND report_date = '".$search_date."'";
      $result = $conn->query($upd_sql);
      if ($result){
        echo 'Old Row Updated';
      } else {
        echo "Old Row Not Updated";
      }
    }
  }
}
else{
    $ins_sql = "INSERT INTO tbl_report (main_offer_id,main_offer_url,offer_clicks,report_date)
SELECT C.offer_id,O.offer,COUNT(C.id) clicks,C.created_at FROM tbl_click C JOIN tbl_offer_url O ON O.id = C.offer_id WHERE created_at = '".$search_date."' GROUP BY offer_id ORDER BY offer";
    $result = $conn->query($ins_sql);
    if ($result) {
      echo 'New Row Inserted';
    } else {
      echo "New Row Not Inserted";
    }
}


/*$sql = "INSERT INTO `tbl_report` (main_offer_id,main_offer_url,sub_offer_id,sub_offer_url,sub_offer_clicks,report_date) SELECT O.id offer_id, O.offer offer_url, S.id sub_offer_id, S.sub_url,COUNT(C.id) clicks,C.created_at FROM tbl_click C JOIN tbl_offer_url O ON C.offer_id = O.id JOIN tbl_sub_offer_url S ON C.sub_offer_id = S.id GROUP BY C.offer_id,C.sub_offer_id;";*/
// echo $sql;
// die();
//$result = $conn->query($sql);
/*
if ($result) {
  // output data of each row
  echo 'done';
} else {
  echo "not done";
}
$conn->close();*/
?>