<?php
//echo DB_HOST;
/**DB Cred  **/
$servername = DB_HOST; 
$username = DB_USERNAME;
$password = DB_PASSWORD;
$database = DB_NAME;
// $servername = 'localhost'; 
// $username = 'root';
// $password = '';
// $database = 'affiliate_portal_database';

$conn = new mysqli($servername,$username,$password,$database);

// Check connection
if ($conn -> connect_errno) {
  echo "Failed to connect to MySQL: " . $conn -> connect_error;
  die();
}
else
{
    echo "connection success";
}
/** DB Cred Ends **/



