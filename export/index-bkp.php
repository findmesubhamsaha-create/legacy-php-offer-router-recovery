<?php
// Database connection
$host = 'localhost'; // Replace with your host
//$db = 'staging_database'; // Replace with your database name
$db = 'efbhalvbhdsurl'; // Replace with your database name
$user = 'admin'; // Replace with your username
$pass = 'KDms@jY7Gw'; // Replace with your password

$mysqli = new mysqli($host, $user, $pass, $db);


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



echo "<h1>Download Links</h1>";
echo "<ul>";

for ($i = 0; $i < $total_batches; $i++) {
    $offset = $i * $batch_size;
    $batch_number = $i + 1;
    echo "<li><a href='download.php?offset=$offset&limit=$batch_size'>Download Batch $batch_number</a></li>";
}

echo "</ul>";

// Close the database connection
$mysqli->close();
?>
