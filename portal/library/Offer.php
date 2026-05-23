<?php 
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}
//echo "<pre>";
//class OfferBeta
class Offer
{
	private $db;

	public function __construct() {
		$this->db = new Database();
 	}

 	public function checkMainUrl($param){
 		// return $param;
 		$hasoffer = $this->db->fetch_data(DB_OFFER_TABLE,['offer'=>$param],1);
 		//$hasoffer = $this->db->fetch_data(DB_OFFER_TABLE,'offer like "%'.$this->get_domain($param).'%"',1);
 		return $hasoffer;
 	}

 	public function checkSlug($param){
 		// return $param;
 		//$hasoffer = $this->db->fetch_data(DB_OFFER_TABLE,['offer'=>$param],1);
 		// RI-HOTFIX-V1 M-01: exact match aligns with routing's = lookup; LIKE caused false-positive blocks
 		$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,['slug_name'=>strtolower($param)],1);
 		return $hasslug;
 	}

 	public function archiveOffer($param){
 		//return $param['oid'];
 		$offerStatus = array(
				'offer_status'=>2,
				'status_updated_at'=> date('Y-m-d')
			);
 		$update_archive_status = $this->db->update_data(DB_OFFER_TABLE,$offerStatus,['id'=>$param['oid']]);
 		return $update_archive_status;
 	}

 	public function deleteOffer($param){
 		//return $param['oid'];
 		$offerStatus = array(
				'offer_status'=>3,
				'status_updated_at'=> date('Y-m-d')
			);
 		$update_delete_status = $this->db->update_data(DB_OFFER_TABLE,$offerStatus,['id'=>$param['oid']]);
 		return $update_delete_status;
 	}
 	public function resetOffer($param){
 		// return $param;

 		// need to check the deleted slug name already in other active state or not
 		$getOfferName = $this->db->fetch_data_new(DB_OFFER_TABLE,'offer',['id'=>$param],1);
 		$getSlugName = $this->db->fetch_data_new(DB_OFFER_TABLE,'slug_name',['id'=>$param],1);

 		$checkActiveOffer = $this->db->fetch_data(DB_OFFER_TABLE,['offer'=>$getOfferName[0]['offer'], 'offer_status'=>1],1);
 		$checkActiveSlug = $this->db->fetch_data(DB_OFFER_TABLE,['slug_name'=>$getSlugName[0]['slug_name'], 'offer_status'=>1],1);

 		//print_r($getSlugName); die();

 		if(!empty($checkActiveOffer)){
			return array('status'=>false, 'message'=>'There is an active offer with the same name already exists!');
		}
		if(!empty($checkActiveSlug)){
			return array('status'=>false, 'message'=>'There is an active offer with the same slug name already exists!!');
		}

 		$offerStatus = array(
				'offer_status'=>1,
				'status_updated_at'=> date('Y-m-d')
			);
 		$update_active_status = $this->db->update_data(DB_OFFER_TABLE,$offerStatus,['id'=>$param]);
 		return array('status'=>true, 'message'=>'Offer moved to active!');
 	}

 	public function getSubOffers($params = array()){

		//return $params;
		$fetch_sub_urls =[];
		$deleted_states = 'no';
		$sub_offer_status = [
			    "yes"  => "Active",
			    "no" => "Deactive"
			];

		$fetch_sub_urls = $this->db->join_query('select a.*, b.offer from tbl_sub_offer_url a left join tbl_offer_url b on a.main_offer_id=b.id
							 where a.main_offer_id="'.$params['oid'].'" AND a.deleted_status="'.$deleted_states.'" GROUP BY a.id ORDER BY a.id ASC;');

		//return $fetch_sub_urls;
		
		$data = array();
	 	for ($i=0; $i < count($fetch_sub_urls); $i++) { 
		 	 $sub_array = array();
			 // $sub_array[] = $i+1;
			 $sub_array[] = $fetch_sub_urls[$i]['offer'];
			 $sub_array[] = $fetch_sub_urls[$i]['sub_url'];
			 $sub_array[] = $fetch_sub_urls[$i]['weight'];
			 $sub_array[] = $sub_offer_status[$fetch_sub_urls[$i]['status']];
			 $data[] = $sub_array;
	
	 	}

		 return $data;

	}

	// public function fetchAll(){
	// 	// $final_offer_list =[];
	//  	// $fetch_offer = $this->db->fetch_data(DB_OFFER_TABLE,['offer_status'=>1]);

	//  	// foreach ($fetch_offer as $value) {
	// 	//   $fetch_tag_row = $this->db->fetch_data('tbl_tag',['id'=>$value['tag_id']],1);
	// 	//   $fetch_tag_name = $fetch_tag_row[0]['tag_name'];

	//  	//   $fetch_network_row = $this->db->fetch_data('tbl_network',['id'=>$value['network_id']],1);
	//  	//   $fetch_network_name = $fetch_network_row[0]['network_name'];

	//  	//   $fetch_click = $this->db->fetch_clicks('tbl_click','COUNT(offer_id) clicks',['offer_id'=>$value['id']]);


	//  	//   $value['tag_id'] = $fetch_tag_name;
	//  	//   $value['network_id'] = $fetch_network_name;
	//  	//   $value['clicks'] = $fetch_click[0]['clicks'];

	//  	//   array_push($final_offer_list,$value); 
	// 	// }
	// 	$final_offer_list = $this->db->join_query("select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a 
	// 						left join tbl_tag b on a.tag_id=b.id
	// 						left join tbl_network c on a.network_id=c.id
	// 						left join tbl_click d on a.id=d.offer_id where a.offer_status=1 GROUP BY a.id ORDER BY a.id ASC");
	// 	$data = array();
	//  	for ($i=0; $i < count($final_offer_list); $i++) { 
	//  		$link = 'https://efbhalvbhdsurl.com/?oid='.$final_offer_list[$i]['slug_name'].'&tag='.$final_offer_list[$i]['tag_name'].'&affid='.$final_offer_list[$i]['network_name'];
	//  		if($final_offer_list[$i]['offer_status'] == 1){
	//  			$action = '<button data-tooltip="Settings" type="button" value="'.$final_offer_list[$i]["id"].'" class="sting_icon_btn setting cmn_icon" data-bs-toggle="modal"
  //                               data-bs-target="#exampleModal">
  //                 <span class="sting_icon">
  //           <img src="assets/images/setting.png" alt="">
            
  //         </span>
  //               </button>
  //               <button data-tooltip="View Report" type="button" value="'.$final_offer_list[$i]["id"].'" class="view_icon_btn btn_report cmn_icon" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
  //                 <span class="sting_icon">
  //           <img src="assets/images/view.png" alt="">
            
  //         </span>
  //               </button>
  //               <button data-tooltip="Archive" type="button" value="'.$final_offer_list[$i]["id"].'" value="'.$final_offer_list[$i]["id"].'" class="btn_archive cmn_icon">
  //                 <span class="sting_icon">
  //           <img src="assets/images/archive.png" alt="">
            
  //         </span>
  //               </button>

  //         <button data-tooltip="Delete" type="button" value="'.$final_offer_list[$i]["id"].'" value="'.$final_offer_list[$i]["id"].'" class="btn_delete cmn_icon">
  //                 <span class="sting_icon">
  //           <img src="assets/images/delete.png" alt="">
            
  //         </span>
  //         </button>';
	//  		}
	//  		else{
	//  			$action = '<td align="right"><button data-tooltip="Move to Active" style="border:none;" value="'.$final_offer_list[$i]["id"].'" class="btn_reset"><i class="fas fa-undo"></i></button></td>';
	//  		}
	// 	 	 $sub_array = array();
	// 		 $sub_array[] = $i+1;
	// 		 // $sub_array[] = wordwrap($final_offer_list[$i]['offer'], 10, '<br />', true);
	// 		 $sub_array[] = wordwrap($final_offer_list[$i]['offer'], 10, '<br />', true);
	// 		 $sub_array[] = wordwrap($final_offer_list[$i]['slug_name'], 10, '<br />', true);
	// 		 $sub_array[] = wordwrap($final_offer_list[$i]['tag_name'], 10, '<br />', true);
	// 		 $sub_array[] = wordwrap($final_offer_list[$i]['note'], 10, '<br />', true);
	// 		 $sub_array[] = wordwrap($final_offer_list[$i]['network_name'], 10, '<br />', true);
	// 		 $sub_array[] = $final_offer_list[$i]['clicks'];
	// 		 $sub_array[] = $action;
	// 		 $data[] = $sub_array;

	// 		 //$desc2 = strlen($row['product_2_description']) > 100 ? substr($row['product_2_description'],0,100)."..." : $row['product_2_description'];

	// 		 // $sub_array[] = strlen($final_offer_list[$i]['offer']) > 10 ? substr($final_offer_list[$i]['offer'],0,10)."..." : $final_offer_list[$i]['offer'];
	// 		 // $sub_array[] = strlen($final_offer_list[$i]['slug_name']) > 10 ? substr($final_offer_list[$i]['slug_name'],0,10)."..." : $final_offer_list[$i]['slug_name'];
	// 		 // $sub_array[] = strlen($final_offer_list[$i]['tag_name']) > 10 ? substr($final_offer_list[$i]['tag_name'],0,10)."..." : $final_offer_list[$i]['tag_name'];
	// 		 // $sub_array[] = strlen($final_offer_list[$i]['note']) > 10 ? substr($final_offer_list[$i]['note'],0,10)."..." : $final_offer_list[$i]['note'];
	// 		 // $sub_array[] = strlen($final_offer_list[$i]['network_name']) > 10 ? substr($final_offer_list[$i]['network_name'],0,10)."..." : $final_offer_list[$i]['network_name'];
	
	//  	}

	// 	 return $data;

	// }

	public function fetchAll($params = array()){
		//return $params;
		$final_offer_list =[];
		$searchQuery = "";
		$draw = $params['draw'];
		$start = $params['start'];
		$length = $params['length'];
		$searchValue = $params['search']['value']; // Search value
		$filterType = $params['filterType'] ?? '';

		if($filterType == 'Network'){
			$get_network_id = $this->db->fetch_data_new('tbl_network','id',['network_name'=>$params['filterValue']],1);

			$totalRecordsQuery = $this->db->filter_query('SELECT COUNT(*) AS total FROM tbl_offer_url WHERE offer_status=1 AND network_id='.$get_network_id[0]["id"].'');
			$totalRecords = $totalRecordsQuery[0]['total'];

			//return $totalRecordsQuery;
			
			if(!empty($searchValue)){
			    $searchQuery = " WHERE (a.offer LIKE '%" . $searchValue . "%' OR a.slug_name LIKE '%" . $searchValue . "%' OR b.tag_name LIKE '%" . $searchValue . "%' OR a.note LIKE '%" . $searchValue . "%' OR c.network_name LIKE '%" . $searchValue . "%') AND c.network_name='".$params['filterValue']."' AND a.offer_status=1";
			}
			else{
				$searchQuery = ' WHERE a.offer_status=1 AND a.network_id='.$get_network_id[0]["id"].'';
			}
			$totalFilteredRecordsQuery = $this->db->join_query('select COUNT(*) total from tbl_offer_url a 
								left join tbl_tag b on a.tag_id=b.id
								left join tbl_network c on a.network_id=c.id' . $searchQuery.'');
			
			$totalFilteredRecords = $totalFilteredRecordsQuery[0]['total'];

			//return $searchQuery;

			$final_offer_list = $this->db->join_query('select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a 
							left join tbl_tag b on a.tag_id=b.id
							left join tbl_network c on a.network_id=c.id
							left join tbl_click d on a.id=d.offer_id '.$searchQuery.' GROUP BY a.id ORDER BY a.id ASC LIMIT '.$start.', '.$length.'');


		}

		else if($filterType == 'Domain'){

			$get_offer_id = $this->db->filter_query('SELECT DISTINCT(main_offer_id) FROM `tbl_sub_offer_url` WHERE `sub_url` LIKE "%'.$params['filterValue'].'%" AND  deleted_status="no";');
			$offer_ids = array_column($get_offer_id, 'main_offer_id');
			$final_ids = implode(',', $offer_ids);

			$totalRecordsQuery = $this->db->filter_query('SELECT COUNT(*) AS total FROM tbl_offer_url WHERE offer_status=1 AND id in ('.$final_ids.')');
			$totalRecords = $totalRecordsQuery[0]['total'];

			//return $totalRecordsQuery;

			if(!empty($searchValue)){
			    $searchQuery = " WHERE (a.offer LIKE '%" . $searchValue . "%' OR a.slug_name LIKE '%" . $searchValue . "%' OR b.tag_name LIKE '%" . $searchValue . "%' OR a.note LIKE '%" . $searchValue . "%' OR c.network_name LIKE '%" . $searchValue . "%') AND a.id in (".$final_ids.") AND a.offer_status=1";
			}
			else{
				$searchQuery = ' WHERE a.offer_status=1 AND a.id in ('.$final_ids.')';
			}
			$totalFilteredRecordsQuery = $this->db->join_query('select COUNT(*) total from tbl_offer_url a 
								left join tbl_tag b on a.tag_id=b.id
								left join tbl_network c on a.network_id=c.id' . $searchQuery.'');
			
			$totalFilteredRecords = $totalFilteredRecordsQuery[0]['total'];

			//return $totalFilteredRecordsQuery;
			$final_offer_list = $this->db->join_query('select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a 
							left join tbl_tag b on a.tag_id=b.id
							left join tbl_network c on a.network_id=c.id
							left join tbl_click d on a.id=d.offer_id '.$searchQuery.' GROUP BY a.id ORDER BY a.id ASC LIMIT '.$start.', '.$length.'');

		}

		else if($filterType == 'Status'){

			$offer_status = [
			    "Active"  => 1,
			    "Archived" => 2,
			    "Deleted" => 3
			];

			// 'All' is not in $offer_status → null means no status restriction
			$status_id    = $offer_status[$params['filterValue']] ?? null;
			$status_where = ($status_id !== null) ? ' WHERE offer_status=' . $status_id : '';
			$status_and   = ($status_id !== null) ? " AND a.offer_status='" . $status_id . "'" : '';

			$totalRecordsQuery = $this->db->filter_query('SELECT COUNT(*) AS total FROM tbl_offer_url' . $status_where);
			$totalRecords = $totalRecordsQuery[0]['total'] ?? 0;

			//return $totalRecordsQuery;

			if(!empty($searchValue)){
			    $searchQuery = " WHERE (a.offer LIKE '%" . $searchValue . "%' OR a.slug_name LIKE '%" . $searchValue . "%' OR b.tag_name LIKE '%" . $searchValue . "%' OR a.note LIKE '%" . $searchValue . "%' OR c.network_name LIKE '%" . $searchValue . "%')" . $status_and;
			}
			else{
				$searchQuery = ($status_id !== null) ? ' WHERE a.offer_status=' . $status_id : '';
			}

			//return $searchQuery;

			$totalFilteredRecordsQuery = $this->db->join_query('select COUNT(*) total from tbl_offer_url a
								left join tbl_tag b on a.tag_id=b.id
								left join tbl_network c on a.network_id=c.id' . $searchQuery.'');

			$totalFilteredRecords = $totalFilteredRecordsQuery[0]['total'] ?? 0;

			// //return $totalFilteredRecordsQuery;
			$final_offer_list = $this->db->join_query('select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a
							left join tbl_tag b on a.tag_id=b.id
							left join tbl_network c on a.network_id=c.id
							left join tbl_click d on a.id=d.offer_id '.$searchQuery.' GROUP BY a.id ORDER BY a.id ASC LIMIT '.$start.', '.$length.'');

		}


		else{

			// Query to count total records without filtering
			//return 'in';
			$totalRecordsQuery = $this->db->join_query("SELECT COUNT(*) AS total FROM tbl_offer_url WHERE offer_status=1");
			$totalRecords = $totalRecordsQuery[0]['total'];

			//return $totalRecords;


			// Query to count total records with filtering (if search is applied)
			//$searchQuery = "";
			if(!empty($searchValue)){
			    $searchQuery = " WHERE (a.offer LIKE '%" . $searchValue . "%' OR a.slug_name LIKE '%" . $searchValue . "%' OR b.tag_name LIKE '%" . $searchValue . "%' OR a.note LIKE '%" . $searchValue . "%' OR c.network_name LIKE '%" . $searchValue . "%')  AND a.offer_status=1";
			}
			else{
				$searchQuery = " WHERE a.offer_status=1";
			}

			$totalFilteredRecordsQuery = $this->db->join_query('select COUNT(*) total from tbl_offer_url a 
								left join tbl_tag b on a.tag_id=b.id
								left join tbl_network c on a.network_id=c.id' . $searchQuery.'');

			// return 'select COUNT(*) total from tbl_offer_url a 
			// 					left join tbl_tag b on a.tag_id=b.id
			// 					left join tbl_network c on a.network_id=c.id' . $searchQuery.'';
			
			$totalFilteredRecords = $totalFilteredRecordsQuery[0]['total'];

			

			$final_offer_list = $this->db->join_query('select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a 
								left join tbl_tag b on a.tag_id=b.id
								left join tbl_network c on a.network_id=c.id
								left join tbl_click d on a.id=d.offer_id '.$searchQuery.' GROUP BY a.id ORDER BY a.id ASC LIMIT '.$start.', '.$length.'');

		}

		

		//return $final_offer_list;
		
		$data = array();
		for ($i=0; $i < count($final_offer_list); $i++) {
			// RI-HOTFIX-V1 AJ-02: ?? '' guards — tag_name/network_name can be NULL from LEFT JOIN miss
			// DU-01: BASE_URL replaces hardcoded domain — resolves to current host at runtime
			$link = BASE_URL.'/?oid='.($final_offer_list[$i]['slug_name'] ?? '').'&tag='.($final_offer_list[$i]['tag_name'] ?? '').'&affid='.($final_offer_list[$i]['network_name'] ?? '');
	 		if($final_offer_list[$i]['offer_status'] == 1){
	 			$action = '<span style="position:relative;" value="'.$final_offer_list[$i]["id"].'">
	 			<input id="hdn_'.$final_offer_list[$i]["id"].'" type="text" style="display:none;" name="hdn_'.$final_offer_list[$i]["id"].'" value="'.$link.'">
	        <button data-tooltip="Settings" type="button" value="'.$final_offer_list[$i]["id"].'" class="sting_icon_btn setting cmn_icon" data-bs-toggle="modal"
	        data-bs-target="#exampleModal">
	        <span class="sting_icon outer_btn">
	        <i class="fa fa-cog"></i>
	        </span>
				</button>

				<button type="button" data-tooltip="Clone" value="'.$final_offer_list[$i]["id"].'" class="sting_icon_btn clone cmn_icon" data-bs-toggle="modal" data-bs-target="#exampleModal">
				    <span class="sting_icon outer_btn">
				        <i class="fa fa-clone"></i>
				        </span>
				</button>

				<button type="button" data-tooltip="Get Link" value="'.$final_offer_list[$i]["id"].'" class="btn_copy link cmn_icon">
				    <span class="sting_icon outer_btn">
				        <i class="fas fa-link"></i></span>
				</button>
				<button onclick="myFunction('.$final_offer_list[$i]["id"].')" value="'.$final_offer_list[$i]["id"].'" class="dropbtn">
				</button>
				<div id="myDropdown_'.$final_offer_list[$i]["id"].'" class="dropdown-content">

				    <div class="sub_menu">

				        <ul>

				            <li>
				                <button type="button" value="'.$final_offer_list[$i]["id"].'" class="view_icon_btn btn_report cmn_icon" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
				                    <span class="sting_icon">
				                    <i class="fa fa-eye" ></i>
				                  </span> View Report
				                </button>
				            </li>

				            <li>
				                <button type="button" value="'.$final_offer_list[$i]["id"].'" class="btn_archive cmn_icon">
				                    <span class="sting_icon">
				                <i class="fa fa-archive"></i> 
				                </span> Archive
				                </button>
				            </li>

				            <li>
				                <button type="button" value="'.$final_offer_list[$i]["id"].'" class="btn_delete cmn_icon">
				                    <span class="sting_icon">
				                  <i class="fa fa-trash"></i></span> Delete
				                </button>
				            </li>
				        </ul>

				    </div>

				</div>
				</span>';
	 		}
	 		else{
	 			$action = '<td align="right"><button data-tooltip="Move to Active" style="border:none;" value="'.$final_offer_list[$i]["id"].'" class="btn_reset"><i class="fas fa-undo"></i></button></td>';
	 		} 
			$sub_array = array();
			$sub_array[] = $i+1;
			// $sub_array[] = $final_offer_list[$i]['offer'];
			// $sub_array[] = $final_offer_list[$i]['slug_name'];
			// $sub_array[] = $final_offer_list[$i]['tag_name'];
			// $sub_array[] = $final_offer_list[$i]['note'];
			// $sub_array[] = $final_offer_list[$i]['network_name'];
			// $sub_array[] = $final_offer_list[$i]['clicks'];
			// $sub_array[] = $action;

			// RI-HOTFIX-V1 AJ-03: ?? '' guards — PHP 8.2 Deprecated: strlen(null) corrupts JSON via ob buffer
			$sub_array[] = strlen($final_offer_list[$i]['offer'] ?? '') > 15 ? substr($final_offer_list[$i]['offer'],0,15)."..." : ($final_offer_list[$i]['offer'] ?? '');
			$sub_array[] = strlen($final_offer_list[$i]['slug_name'] ?? '') > 15 ? substr($final_offer_list[$i]['slug_name'],0,15)."..." : ($final_offer_list[$i]['slug_name'] ?? '');
			$sub_array[] = strlen($final_offer_list[$i]['tag_name'] ?? '') > 15 ? substr($final_offer_list[$i]['tag_name'],0,15)."..." : ($final_offer_list[$i]['tag_name'] ?? '');
			$sub_array[] = strlen($final_offer_list[$i]['note'] ?? '') > 15 ? substr($final_offer_list[$i]['note'],0,15)."..." : ($final_offer_list[$i]['note'] ?? '');
			$sub_array[] = strlen($final_offer_list[$i]['network_name'] ?? '') > 15 ? substr($final_offer_list[$i]['network_name'],0,15)."..." : ($final_offer_list[$i]['network_name'] ?? '');
			$sub_array[] = $final_offer_list[$i]['clicks'];
			$sub_array[] = $action;


			$data[] = $sub_array;
		}

		$response = array(
		    "draw" => intval($draw), // DataTables draw counter for every request
		    "recordsTotal" => intval($totalRecords), // Total records without filtering
		    "recordsFiltered" => intval($totalFilteredRecords), // Total records with filtering
		    "data" => $data // The actual data
		);

		return $response;

	}


	public function addNewOffer($param){

		// return $param;
		// echo "<pre>";
		// print_r($param); die();
		
		$check_tag = $this->db->fetch_data('tbl_tag',['tag_name'=>$param['tags']],1);
		$check_network = $this->db->fetch_data('tbl_network',['network_name'=>$param['network']]);
		//$check_slug = $this->db->fetch_data(DB_OFFER_TABLE,['slug_name'=>$param['slug']]);

		//$check_slug = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['slug_name'=>$param['slug']],1);
		$check_slug = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['slug_name'=>$param['slug'], 'offer_status'=>1],1);
		// print_r($check_slug);
		// die();
		if(!empty($check_slug) && ($param['offerId'] != $check_slug[0]['id'])){
			echo json_encode(array('status'=>false, 'message'=>'Slug already is in Use! Please use another slag name')); die();
		}

		//print_r($check_slug); die();

		// if(!empty($check_slug)){
		// 	//print_r('new'); die();
		// 	//return 0;
		// 	echo json_encode(array('status'=>false, 'message'=>'Slug already is in Use! Please use another slag name')); die();
		// }

		if(!empty($check_tag)){
			$param['tags'] = $check_tag[0]['id'];
		}
		else{
			$tag_record = array(
				'tag_name'=>$this->trimmString($param['tags']),
				'created_at'=> date('Y-m-d')
			);
			$add_tag = $this->db->save_data('tbl_tag',$tag_record);
			if($add_tag){
				$param['tags'] = $add_tag;
			}
		}


		if(!empty($check_network)){
			$param['network'] = $check_network[0]['id'];
		}
		else{
			$network_record = array(
				'network_name'=>$this->trimmString($param['network']),
				'created_at'=> date('Y-m-d')
			);
			$add_network = $this->db->save_data('tbl_network',$network_record);
			if($add_network){
				$param['network'] = $add_network;
			}
		}


		
		if(!empty($param['offerId'])){
			//print_r($param['offerId']); die();
			//print_r('new2'); die();
			//$param['tags'] = $check_tag[0]['id'];
			//$check_offer = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['offer'=>$param['offer']],1);
			$check_offer = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['offer'=>$param['offer'], 'offer_status'=>1],1);
			// print_r($check_offer);
			// die();
			if(!empty($check_offer) && ($param['offerId'] != $check_offer[0]['id'])){
				echo json_encode(array('status'=>false, 'message'=>'Offer Name is in Use, Please enter another offer name!'));
				die();
			}
			//die();
			$offerId = $param['offerId'];
			$main_url_record = array(
				'offer'=>$this->trimmString($param['offer']),
				'slug_name'=>$this->trimmString($param['slug']),
				'tag_id'=>$this->trimmString($param['tags']),
				'note'=>$this->trimmString($param['note']),
				'network_id'=>$this->trimmString($param['network']),
				'start_date'=>$param['startdate'] ? $param['startdate'] : date('Y-m-d'),
				'end_date'=>$param['enddate'] ? $param['enddate'] : date('Y-m-d')
			);
			//print_r($main_url_record); die();
			$update_offer = $this->db->update_data(DB_OFFER_TABLE,$main_url_record,['id'=>$offerId]);
			//print_r($update_offer); die();

			$check_sub_offer_del = $this->db->fetch_data_new('tbl_sub_offer_url','GROUP_CONCAT(sub_url,"") sub_url',[ 
					'main_offer_id'=>$offerId]);

			$prev_sub_url_del = explode(',', $check_sub_offer_del[0]['sub_url'] ?? '');
			$deleted_url = array_diff($prev_sub_url_del, $param['suburl']['url']);
			$update_sub = $this->db->update_data('tbl_sub_offer_url',['deleted_status'=>'yes', 'weight'=>0, 'status'=>'no'],'sub_url in ("'.implode('","', $deleted_url).'") AND main_offer_id = '.$offerId);

			$check_sub_offer = $this->db->fetch_data_new('tbl_sub_offer_url','GROUP_CONCAT(CONCAT(sub_url,"-",deleted_status),"") sub_url',[ 
					'main_offer_id'=>$offerId]); //'deleted_status'=>'no'
			
			$prev_sub_url = explode(',', $check_sub_offer[0]['sub_url'] ?? '');
			
			// echo "<pre>"; 
			// print_r($param); die();
			for ($x = 0; $x < count($param['suburl']['url']); $x++) {
				//print_r($param); //die();
				if(in_array($param['suburl']['url'][$x].'-yes', $prev_sub_url)){
					
					$sub_url_record = array(
							'main_offer_id'=>$offerId,
							'sub_url'=>$param['suburl']['url'][$x],
							'weight'=>$param['suburl']['weight'][$x],
							'status'=>$param['suburl']['status'][$x],
							'deleted_status'=>'no'
						);
					//echo 'in';
					//print_r($sub_url_record); die();
					$update_sub_offer = $this->db->update_data('tbl_sub_offer_url',$sub_url_record,['sub_url'=>$param['suburl']['url'][$x],'main_offer_id'=>$offerId]);
					//echo 'out';
				}
				else if(in_array($param['suburl']['url'][$x].'-no', $prev_sub_url)){
					//continue;
					$sub_url_record = array(
							'main_offer_id'=>$offerId,
							'sub_url'=>$param['suburl']['url'][$x],
							'weight'=>$param['suburl']['weight'][$x],
							'status'=>$param['suburl']['status'][$x],
						);
					//echo 'in';
					$update_sub_offer = $this->db->update_data('tbl_sub_offer_url',$sub_url_record,['sub_url'=>$param['suburl']['url'][$x],'main_offer_id'=>$offerId]);
				}

					else{
					// $check_sub_offer = $this->db->fetch_data('tbl_sub_offer_url',['sub_url'=>$param['suburl']['url'][$x], 
					// 'main_offer_id'=>$offerId],1);
					//if(!empty($check_sub_offer)){
						$sub_url_record = array(
								'main_offer_id'=>$offerId,
								'sub_url'=>$this->trimmString($param['suburl']['url'][$x]),
								'weight'=>$param['suburl']['weight'][$x],
								'status'=>$param['suburl']['status'][$x]
							);
						$add_sub_offer = $this->db->save_data('tbl_sub_offer_url',$sub_url_record);
					//}
				}
			}
			return $update_offer;
		}
		else{

			//$check_offer = $this->db->fetch_data(DB_OFFER_TABLE,['offer'=>$param['offer']],1);
			$check_offer = $this->db->fetch_data(DB_OFFER_TABLE,['offer'=>$param['offer'], 'offer_status'=>1],1);
			if(!empty($check_offer)){
				echo json_encode(array('status'=>false, 'message'=>'Offer Name is in Use, Please enter another offer name!'));
				die();
				//print_r('in'); die();
				//return json_encode(array('status'=>false, 'message'=>'Offer is in Use, Please enter another offer name!'));
				//die();
			}
			//print_r('in'); die();
			$main_url_record = array(
			'offer'=>$this->trimmString($param['offer']),
			'slug_name'=>$this->trimmString($param['slug']),
			'tag_id'=>$this->trimmString($param['tags']),
			'note'=>$this->trimmString($param['note']),
			'network_id'=>$this->trimmString($param['network']),
			'start_date'=>$param['startdate'] ? $param['startdate'] : date('Y-m-d'),
			'end_date'=>$param['enddate'] ? $param['enddate'] : date('Y-m-d')
			);
			//print_r($main_url_record); die();
			$add_offer = $this->db->save_data(DB_OFFER_TABLE,$main_url_record);

			if(!empty($add_offer)){
				for ($x = 0; $x < count($param['suburl']['url']); $x++) {
					$sub_url_record = array(
							'main_offer_id'=>$add_offer,
							'sub_url'=>$this->trimmString($param['suburl']['url'][$x]),
							'weight'=>$param['suburl']['weight'][$x],
							'status'=>$param['suburl']['status'][$x]
						);
					$add_sub_offer = $this->db->save_data('tbl_sub_offer_url',$sub_url_record);
				}
			}
			return $add_offer;
		}



	}

	public function editOffer($rowId){
		//return $rowId;
	 	$fetch_main_offer = $this->db->fetch_data(DB_OFFER_TABLE,['id'=>$rowId],1);
	 	$get_tag = $this->db->fetch_data('tbl_tag',['id'=>$fetch_main_offer[0]['tag_id']]);
		$get_network = $this->db->fetch_data('tbl_network',['id'=>$fetch_main_offer[0]['network_id']]);
		$get_sub_urls = $this->db->fetch_data('tbl_sub_offer_url',['main_offer_id'=>$fetch_main_offer[0]['id'], 'deleted_status'=>'no']);
		$fetch_main_offer[0]['tag_id'] = $get_tag[0]['tag_name'];
		$fetch_main_offer[0]['network_id'] = $get_network[0]['network_name'];
		$fetch_main_offer[0]['sub_url_details'] = $get_sub_urls;

		return $fetch_main_offer;
	}


	public function trimmString($string) {
		$trimmedString= preg_replace('/^\s+|\s+$/u', '', $string);
		return $trimmedString;
	}

	function get_domain($url)
	{
	  $pieces = parse_url($url);
	  $domain = isset($pieces['host']) ? $pieces['host'] : $pieces['path'];
	  if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
	    $url = $regs['domain'].(!isset($pieces['path']) ? '' : ($pieces['path']=="/" ? '' : $pieces['path']));
	    return $filte = rtrim($url,'/');
	  }
	  return false;
	}
	
}

?>