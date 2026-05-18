<?php
//echo 'in'; die();
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


// Get offset and limit from query parameters
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// The SQL query for the current batch
// $query = "
//     SELECT 
//         SerialNumber,
//         offer AS 'Offer Name', 
//         slug_name AS 'Slug Name',
//         note AS 'Notes',
//         tag_name AS 'Tag Name',
//         network_name AS 'Network Name',
//         clicks,
//         URLs,
//         weight AS 'Weight',
//         status AS 'Is Active'
//     FROM (
//         SELECT 
//             ROW_NUMBER() OVER (ORDER BY t1.offer) AS SerialNumber,
//             t1.offer, 
//             t1.slug_name, 
//             t1.note, 
//             t2.tag_name,
//             t3.network_name,
//             COUNT(t4.offer_id) AS clicks,
//             GROUP_CONCAT(t5.sub_url) AS URLs,
//             t5.weight,
//             t5.status
//         FROM 
//             tbl_offer_url t1
//         JOIN 
//             tbl_tag t2 ON t1.tag_id = t2.id
//         JOIN 
//             tbl_network t3 ON t1.network_id = t3.id
//         LEFT JOIN 
//             tbl_click t4 ON t1.id = t4.offer_id
//         JOIN 
//             tbl_sub_offer_url t5 ON t1.id = t5.main_offer_id
//         WHERE 
//             t1.offer_status = 1
//             AND t5.deleted_status = 'no'
//         GROUP BY 
//             t1.offer, 
//             t1.slug_name, 
//             t1.note, 
//             t2.tag_name, 
//             t3.network_name, 
//             t5.sub_url, 
//             t5.weight, 
//             t5.status
//     ) AS batch
//     LIMIT $limit OFFSET $offset;
// ";

$query = "SELECT * FROM ( SELECT ROW_NUMBER() OVER (ORDER BY t1.offer) AS 'Serial Number', t1.offer AS 'Offer Name', t1.slug_name AS 'Slug Name', t1.note AS 'Notes', t2.tag_name AS 'Tag Name', t3.network_name AS 'Network Name', t5.sub_url AS 'URLs', t5.weight AS 'Weight', t5.status AS 'Is Active' FROM tbl_offer_url t1 JOIN tbl_tag t2 ON t1.tag_id = t2.id JOIN tbl_network t3 ON t1.network_id = t3.id JOIN tbl_sub_offer_url t5 ON t1.id = t5.main_offer_id WHERE t1.offer_status = 1 AND t5.deleted_status = 'no' GROUP BY t1.offer, t1.slug_name, t1.note, t2.tag_name, t3.network_name, t5.sub_url, t5.weight, t5.status ) AS subquery LIMIT ? OFFSET ?";


// echo $query;
// die();

$stmt = $mysqli->prepare($query);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Set headers to force download
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="exported_data_batch_' . ($offset / $limit + 1) . '.csv"');

// Open PHP output stream for writing
$output = fopen('php://output', 'w');

// Write the header row
$fields = array('Serial Number', 'Offer Name', 'Slug Name', 'Notes', 'Tag Name', 'Network Name', 'URLs', 'Weight', 'Is Active');
fputcsv($output, $fields);

// Write each row from the query result
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

// Close the output stream
fclose($output);

// Close the database connection
$mysqli->close();
?>
