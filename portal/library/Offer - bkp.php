<?php 
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}

class Offer
{
	public function __construct() {
		$this->db = new Database();
 	}
	public function fetchAll(){
	 	$fetch_offer = $this->db->fetch_data(DB_OFFER_TABLE,1);
	 	return $fetch_offer;
	}
	public function addNewOffer($param){
		
		$check_tag = $this->db->fetch_data('tbl_tag',['tag_name'=>$param['tags']],1);
		$check_network = $this->db->fetch_data('tbl_network',['network_name'=>$param['network']]);

		if(!empty($check_tag)){
			$param['tags'] = $check_tag[0]['id'];
		}
		else{
			$tag_record = array(
				'tag_name'=>$param['tags'],
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
				'network_name'=>$param['network'],
				'created_at'=> date('Y-m-d')
			);
			$add_network = $this->db->save_data('tbl_network',$network_record);
			if($add_network){
				$param['network'] = $add_network;
			}
		}

		$main_url_record = array(
			'offer'=>$param['offer'],
			'tag_id'=>$param['tags'],
			'note'=>$param['note'],
			'network_id'=>$param['network'],
			'start_date'=>$param['startdate'],
			'end_date'=>$param['enddate']
		);
		$add_offer = $this->db->save_data(DB_OFFER_TABLE,$main_url_record);

		if(!empty($add_offer)){
			for ($x = 0; $x < count($param['suburl']['url']); $x++) {
				$sub_url_record = array(
						'main_offer_id'=>$add_offer,
						'sub_url'=>$param['suburl']['url'][$x],
						'weight'=>$param['suburl']['weight'][$x],
						'status'=>$param['suburl']['status'][$x]
					);
				$add_sub_offer = $this->db->save_data('tbl_sub_offer_url',$sub_url_record);
			}
		}
		return $add_offer;
	}

	public function editOffer($rowId){
	 	$fetch_main_offer = $this->db->fetch_data(DB_OFFER_TABLE,['id'=>$rowId],1);
	 	$get_tag = $this->db->fetch_data('tbl_tag',['id'=>$fetch_main_offer[0]['tag_id']]);
		$get_network = $this->db->fetch_data('tbl_network',['id'=>$fetch_main_offer[0]['network_id']]);
		$get_sub_urls = $this->db->fetch_data('tbl_sub_offer_url',['main_offer_id'=>$fetch_main_offer[0]['id']]);
		$fetch_main_offer[0]['tag_id'] = $get_tag[0]['tag_name'];
		$fetch_main_offer[0]['network_id'] = $get_network[0]['network_name'];
		$fetch_main_offer[0]['sub_url_details'] = $get_sub_urls;

		return $fetch_main_offer;
	}
	
}

?>