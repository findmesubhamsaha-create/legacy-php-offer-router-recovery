<?php
require dirname(__FILE__) . '/portal/library/Settings.php';
require dirname(__FILE__) . '/portal/library/database/Database.php';
require dirname(__FILE__) . '/portal/library/Postback.php';

//print_r('test'); die();
$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$parsed = parse_url($actual_link);
$query = $parsed['query'];
parse_str($query, $params);
unset($params['oid']);
$final_query = http_build_query($params);


$getoffer = postBack($params = array('oid'=>$_REQUEST['oid'], 'ip'=>$_SERVER['REMOTE_ADDR'], 'click_id'=>$_REQUEST['click_id'] ? $_REQUEST['click_id'] : '0'));

//print_r($getoffer); die();

if(isset($getoffer)){
	header("Location: ".$getoffer.'?'.$final_query);
}

else{
	//header("Location: 404.php".$_SERVER['REQUEST_URI']);	
	header("Location: 404.php?".$final_query);	
}

function postBack($param = array()){
	$postback = new Postback();
	$check_rotate = $postback->rotateUrl($param);
	return $check_rotate;
}



?>