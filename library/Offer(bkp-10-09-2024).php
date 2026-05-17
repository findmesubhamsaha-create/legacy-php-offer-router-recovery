<?php 
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}
//echo "<pre>";
class Offer
{
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
 		$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,'slug_name like "%'.strtolower($param).'%"',1);
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

	public function fetchAll(){
		// $final_offer_list =[];
	 	// $fetch_offer = $this->db->fetch_data(DB_OFFER_TABLE,['offer_status'=>1]);

	 	// foreach ($fetch_offer as $value) {
		//   $fetch_tag_row = $this->db->fetch_data('tbl_tag',['id'=>$value['tag_id']],1);
		//   $fetch_tag_name = $fetch_tag_row[0]['tag_name'];

	 	//   $fetch_network_row = $this->db->fetch_data('tbl_network',['id'=>$value['network_id']],1);
	 	//   $fetch_network_name = $fetch_network_row[0]['network_name'];

	 	//   $fetch_click = $this->db->fetch_clicks('tbl_click','COUNT(offer_id) clicks',['offer_id'=>$value['id']]);


	 	//   $value['tag_id'] = $fetch_tag_name;
	 	//   $value['network_id'] = $fetch_network_name;
	 	//   $value['clicks'] = $fetch_click[0]['clicks'];

	 	//   array_push($final_offer_list,$value); 
		// }
		$final_offer_list = $this->db->join_query("select a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks from tbl_offer_url a 
							left join tbl_tag b on a.tag_id=b.id
							left join tbl_network c on a.network_id=c.id
							left join tbl_click d on a.id=d.offer_id where a.offer_status=1 GROUP BY a.id ORDER BY a.id ASC");
		return $final_offer_list;
		// print_r($final_offer_list);
	 	//  die();

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

			$prev_sub_url_del = explode(',', $check_sub_offer_del[0]['sub_url']);
			$deleted_url = array_diff($prev_sub_url_del, $param['suburl']['url']);
			$update_sub = $this->db->update_data('tbl_sub_offer_url',['deleted_status'=>'yes', 'weight'=>0, 'status'=>'no'],'sub_url in ("'.implode('","', $deleted_url).'") AND main_offer_id = '.$offerId);

			$check_sub_offer = $this->db->fetch_data_new('tbl_sub_offer_url','GROUP_CONCAT(CONCAT(sub_url,"-",deleted_status),"") sub_url',[ 
					'main_offer_id'=>$offerId]); //'deleted_status'=>'no'
			
			$prev_sub_url = explode(',', $check_sub_offer[0]['sub_url']);
			
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