<?php
// error_reporting(E_ALL);
// ini_set('display_errors', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require dirname(__FILE__) . '/library/Settings.php';
require dirname(__FILE__) . '/library/database/Database.php';
require dirname(__FILE__) . '/library/User.php';
require dirname(__FILE__) . '/library/Offer.php';
//require dirname(__FILE__) . '/library/OfferBeta.php';
require dirname(__FILE__) . '/library/Postback.php';
require dirname(__FILE__) . '/library/Report.php';
//require dirname(__FILE__) . '/library/ReportBeta.php';
require dirname(__FILE__) . '/library/Filter.php';
require dirname(__FILE__) . '/library/Upload.php';

//print_r($_REQUEST); die();

$requestMethod = $_REQUEST['requestMethod'];
$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
// $username = $_REQUEST['username'];
// $password = $_REQUEST['password'];

//echo $requestMethod; die();

switch ($requestMethod) {
	case 'login':
	 userLogin($params = array('method'=>$requestMethod, 'username'=>$username, 'password'=>$password));
		break;
	case 'resetPassword':
	 resetUserPassword($params = array('method'=>$requestMethod, 'username'=>$username, 'password'=>$password));
		break;
	// case 'fetchAll':
	//  fetchAllData($params = array('status'=>$_REQUEST['offerStatus']));
	// 	break;
	 case 'fetchAll':
	 	fetchAllData($_REQUEST);
	 break;
	case 'addNewOffer':
	 addNewOffer($_REQUEST);
		break;
	case 'editOffer':
	 editOffer($_REQUEST);
		break;
	case 'checkMainOffer':
	 checkMainOffer($_REQUEST);
		break;
	case 'checkSlug':
	 checkSlug($_REQUEST);
		break;
	case 'postBack':
	 postBack($params = array('oid'=>$_REQUEST['oid'], 'ip'=>$_REQUEST['ip'], 'click_id'=>$_REQUEST['click_id']));
		break;
	case 'fetchReport':
	 fetchReport($params = array('oid'=>$_REQUEST['oid']));
		break;
	case 'archiveOffer':
	 archiveOffer($params = array('oid'=>$_REQUEST['oid']));
		break;
	case 'deleteOffer':
	 deleteOffer($params = array('oid'=>$_REQUEST['oid']));
		break;
	case 'getFilterType':
	 getFilterType($params = array('filter_type'=>$_REQUEST['filterType']));
		break;
	case 'resetOffer':
	 resetOffer($params = array('oid'=>$_REQUEST['oid']));
		break;
	case 'getSubOffers':
	 getSubOffers($params = array('oid'=>$_REQUEST['oid']));
		break;
	case 'importOffer':
	 importOffer($params = array('csvData'=>$_REQUEST['csvData']));
		break;
	default:
		break;
}
function userLogin($params = array())
{
	$user = new User();
	$validate_user = $user->login($data = array('username'=>$params['username'],'password'=>$params['password']));
	// $validate_user = User::login($data = array('username'=>$params['username'],'password'=>$params['password']));
	// print_r($validate_user);
	
	if($validate_user){
		$_SESSION["is_login"] = true;
		echo json_encode(array('response'=>true, 'message'=>'Successfully Login!.'));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'Please check once your provided information!.'));
	}
}

// function fetchAllData(){
// 	$offer = new Offer();
// 	$fetch_data = $offer->fetchAll();
// 	if($fetch_data){
// 		echo json_encode(array('response'=>true, 'message'=>$fetch_data));
// 	}
// 	else{
// 		echo json_encode(array('response'=>false, 'message'=>''));
// 	}
// 	//print_r($fetch_data);
// }

	// function fetchAllData($params = array()){
	// 	$offer = new Offer();
	// 	$fetch_data = $offer->fetchAll($params);
	// 	if($fetch_data){
	// 		// echo json_encode($fetch_data);
	// 		$output = array(
	// 			"data"    => $fetch_data
	// 		);
	// 		echo json_encode($output);

	// 		//echo json_encode(array('response'=>false, 'message'=>$fetch_data));
	// 	}
	// 	else{
	// 		$output = array(
	// 			"data"    => ''
	// 		);
	// 		echo json_encode($output);
	// 	}
	// 	//print_r($fetch_data);
	// }

function fetchAllData($params = array()){
	//return $params;
	//$offer = new OfferBeta();
	$offer = new Offer();
	$fetch_data = $offer->fetchAll($params);
	//echo "<pre>"; print_r($fetch_data); die();
	if($fetch_data){
		// echo json_encode($fetch_data);
		$output = array(
			"draw" => $fetch_data['draw'], // DataTables draw counter for every request
    		"recordsTotal" => $fetch_data['recordsTotal'], // Total records without filtering
    		"recordsFiltered" => $fetch_data['recordsFiltered'], // Total records with filtering
			"data"    => $fetch_data['data']
		);
		//echo "<pre>"; print_r($output); die();
		echo json_encode($output);

		//echo json_encode(array('response'=>false, 'message'=>$fetch_data));
	}
	else{
		$output = array(
			"data"    => ''
		);
		echo json_encode($output);
	}
	//print_r($fetch_data);
}


function addNewOffer($param = array()){
	//echo "<pre>";
	//print_r($param); //die();
	// $pattern = '/check/i';
	$isActive = []; 
	$suburl = [];
	for ($x = 1; $x <= count($param['url']); $x++) {
		if(array_key_exists("check_$x",$param)){
			array_push($isActive,"yes");
		}
		else{
			array_push($isActive,"no");
		}
	}
	foreach($param as $key => $value) {
		$suburl += ['url' =>$param['url']];
		$suburl += ['weight' =>$param['weight']];
		$suburl += ['status' =>$isActive];
		$suburl += ['sub_url_id' =>$param['sub_url_id']];
	}



	$filter_params = array('offer'=>$param['offer'], 'offerId'=>$param['offerId'], 'slug'=>strtolower($param['slug']), 'tags'=>$param['tags'], 'note'=>$param['note'], 'network'=>$param['network'], 'startdate'=>$param['startdate'], 'enddate'=>$param['enddate'], 'suburl'=>$suburl);
	//print_r($filter_params); die();
	$offer = new Offer();
	$add_data = $offer->addNewOffer($filter_params);
	//print_r($add_data); die();

	if($add_data){
		echo json_encode(array('response'=>true, 'message'=>$add_data));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'something went wrong!'));
	}
}


function editOffer($param = array()){
	//print_r($param); die();
	// $offer = new OfferBeta();
	$offer = new Offer();
	$edit_offer = $offer->editOffer($param['row']);
	//print_r($edit_offer); die();
	if($edit_offer){
		echo json_encode(array('response'=>true, 'message'=>$edit_offer));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'something went wrong!'));
	}
}


function checkMainOffer($param = array()){
	// print_r($param);
	$offer = new Offer();
	$check_main_offer = $offer->checkMainUrl($param['url']);
	//print_r($check_main_offer);	
	if(!empty($check_main_offer)){
		echo json_encode(array('response'=>true, 'message'=>'success'));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'not found!'));
	}
}


function postBack($param = array()){
	//echo "<pre>";
	//print_r($param); die();
	$postback = new Postback();
	$check_rotate = $postback->rotateUrl($param);
	if($check_rotate){
		echo json_encode(array('response'=>true, 'message'=>$check_rotate));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'something went wrong!'));
	}
	//print_r($check_main_offer);
}

// function fetchReport($params = array()){
// 	// print_r($params);
// 	// die();
// 	$report = new Report();
// 	$fetch_report = $report->getReport($params);
// 	// print_r($fetch_report);
// 	// die();
// 	if($fetch_report){
// 		echo json_encode(array('response'=>true, 'message'=>$fetch_report));
// 	}
// 	else{
// 		echo json_encode(array('response'=>false, 'message'=>''));
// 	}
// 	//print_r($fetch_report);
// }

function fetchReport($params = array()){
	// print_r($params);
	// die();
	$report = new Report();
	$fetch_report = $report->getReport($params);
	// print_r($fetch_report);
	// die();
	if($fetch_report){
		$output = array(
			"data"    => $fetch_report
		);
		echo json_encode($output);
		//echo json_encode(array('response'=>true, 'message'=>$fetch_report));
	}
	else{
		$output = array(
			"data"    => ''
		);
		echo json_encode($output);
	}
	//print_r($fetch_report);
}



function archiveOffer($params = array()){
	$offer = new Offer();
	$archive_status = $offer->archiveOffer($params);

	if(!empty($archive_status)){
		echo json_encode(array('response'=>true, 'message'=>'Offer Archived !'));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'Something went wrong!'));
	}
}


function deleteOffer($params = array()){
	$offer = new Offer();
	$delete_status = $offer->deleteOffer($params);

	if(!empty($delete_status)){
		echo json_encode(array('response'=>true, 'message'=>'Offer Deleted'));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'Something went wrong!'));
	}
}




function checkSlug($param = array()){
	// print_r($param);
	$offer = new Offer();
	$check_slug = $offer->checkSlug($param['slug']);
	//print_r($check_main_offer);	
	if(!empty($check_slug)){
		echo json_encode(array('response'=>true, 'message'=>'success'));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>'not found!'));
	}
}

function getFilterType($params = array()){

	//print_r($params); die();

	$filter = new Filter();
	$filter_type = $filter->filterType($params['filter_type']);

	//echo "<pre>"; print_r($filter_type); die();
	
	if(!empty($filter_type) == true){
		echo json_encode(array('response'=>true, 'message'=>$filter_type));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>''));
	}
}

function resetOffer($param = array()){
	//print_r($param);
	// $offer = new OfferBeta();
	$offer = new Offer();
	$reset_offer = $offer->resetOffer($param['oid']);
	//print_r($reset_offer);	die();
	//$reset_resp = json_decode($reset_offer); 
	//print_r($reset_offer); die();
	if($reset_offer['status'] == true){
		echo json_encode(array('response'=>true, 'message'=>$reset_offer['message']));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>$reset_offer['message']));
	}
}

function getSubOffers($params = array()){
	//$offer = new OfferBeta();
	$offer = new Offer();
	$fetch_sub_url_data = $offer->getSubOffers($params);
	//echo "<pre>"; print_r($fetch_sub_url_data); die();
	if($fetch_sub_url_data){
		// echo json_encode($fetch_data);
		$output = array(
			"data"    => $fetch_sub_url_data
		);
		echo json_encode($output);

		//echo json_encode(array('response'=>false, 'message'=>$fetch_data));
	}
	else{
		$output = array(
			"data"    => ''
		);
		echo json_encode($output);
	}
	//print_r($fetch_data);
}




function importOffer($params = array()){
	$upload = new Upload();
	$upload_offer = $upload->uploadOffer($params['csvData']);
	//echo "<pre>"; print_r($params); die();
	//echo "<pre>";
	if(!empty($upload_offer) == true){
		//print_r($upload_offer);
		echo json_encode(array('response'=>true, 'message'=>$upload_offer));
	}
	else{
		echo json_encode(array('response'=>false, 'message'=>''));
	}
	//print_r($fetch_data);
}




















































function resetUserPassword($params = array())
{
	$resetpass = User::resetPassword($data = array('username'=>$params['username'],'password'=>$params['password']));

	if(strtolower($resetpass) == 'ok' ){
     echo json_encode(array('response'=>true,'message'=>'password updated successfully!!'));
    }
    else{
	 echo json_encode(array('response'=>false, 'message'=>'Please check once your provided information!.'));
    }
}