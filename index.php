<?php
// RI-HOTFIX-V1 RG-01: buffer output so PHP 8.2 warnings cannot appear before Location header
ob_start();
require dirname(__FILE__) . '/portal/library/Settings.php';
require dirname(__FILE__) . '/portal/library/database/Database.php';
require dirname(__FILE__) . '/portal/library/Postback.php';

//print_r('test'); die();
$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$parsed = parse_url($actual_link);
// RI-HOTFIX-V1 RG-01: null guard — parse_url returns no 'query' key when URL has no query string
$query = $parsed['query'] ?? '';
parse_str($query, $params);
unset($params['oid']);
$final_query = http_build_query($params);


// RI-HOTFIX-V1 L-01: ?? avoids PHP 8 Undefined index notices; fixes falsy '0' ternary
$getoffer = postBack($params = array('oid'=>$_REQUEST['oid'] ?? '', 'ip'=>$_SERVER['REMOTE_ADDR'], 'click_id'=>$_REQUEST['click_id'] ?? '0'));

//print_r($getoffer); die();

if(isset($getoffer)){
	header("Location: ".$getoffer.'?'.$final_query);
	// RI-HOTFIX-V1 M-04: exit prevents post-redirect code execution
	exit;
}

else{
	//header("Location: 404.php".$_SERVER['REQUEST_URI']);
	header("Location: 404.php?".$final_query);
	// RI-HOTFIX-V1 M-04: exit prevents post-redirect code execution
	exit;
}

function postBack($param = array()){
	$postback = new Postback();
	$check_rotate = $postback->rotateUrl($param);
	return $check_rotate;
}



?>