<?php
// if (!defined('BASEPATH')){
//     exit('Direct script access is not allowed!');
// }

//echo 'in'; die();

class PostbackBeta
{
	public function __construct() {
		$this->db = new Database();
 	}
	public function rotateUrl($params = array()){

		//return $params;

		$sub_offer_url = [];
		$sub_offer_weight = [];
	 	$get_main_offer_id = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['slug_name'=>$params['oid']],1);
	 	$get_sub_offers = $this->db->fetch_data('tbl_sub_offer_url', ['main_offer_id'=>$get_main_offer_id[0]['id'], 'status'=>'yes']);

	 	foreach ($get_sub_offers as $key => $value) {
		    array_push($sub_offer_url, $value['sub_url']);
		    array_push($sub_offer_weight, $value['weight']);
		}

		$filter_arr = array_combine($sub_offer_url, $sub_offer_weight);
	 	$site = $this->get_link_to_display($filter_arr);

	 	$get_site_id = $this->db->fetch_data('tbl_sub_offer_url', ['sub_url'=>$site, 'status'=>'yes']);
	 	$get_main_id = $get_site_id[0]['main_offer_id'];
	 	$get_sub_id = $get_site_id[0]['id'];
	 	$click_record = array(
				'click_id'=>$params['click_id'],
				'offer_id'=> $get_main_id,
				'sub_offer_id'=> $get_sub_id,
				'ip_address'=> $params['ip'],
				'created_at'=> date('Y-m-d')
		);
	 	$add_click = $this->db->save_data('tbl_click',$click_record);

	 	if($add_click){
	 		return $site;
	 	}


	}

	public function get_link_to_display($sites){
			$rand = rand(0,array_sum($sites)-1);
			foreach($sites as $site=>$weight) {
			    $rand -= $weight;
			    if( $rand < 0) break;
			}
			return $site;
	}
}
?>