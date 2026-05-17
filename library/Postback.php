<?php
class Postback
{
	public function __construct() {
		$this->db = new Database();
 	}
	public function rotateUrl($params = array()){

		//return $params;

		$sub_offer_url = [];
		$sub_offer_weight = [];
	 	$get_sub_offers = $this->db->join_query("SELECT s.sub_url,s.weight FROM tbl_sub_offer_url s JOIN tbl_offer_url m ON s.main_offer_id = m.id WHERE m.slug_name = '".$params['oid']."' AND m.offer_status = '1' AND s.status = 'yes' ORDER BY s.id ASC");

	 	if(!empty($get_sub_offers)){
	 		$site = $this->get_link_to_display($get_sub_offers);
		 	// return $site;
		 	// die();
		 	//$get_site_id = $this->db->fetch_data('tbl_sub_offer_url', ['sub_url'=>$site, 'status'=>'yes']);
		 	$get_site_id = $this->db->join_query("SELECT s.main_offer_id,s.id FROM tbl_sub_offer_url s JOIN tbl_offer_url m ON s.main_offer_id = m.id WHERE s.sub_url= '".$site."' AND m.offer_status = '1' AND s.status = 'yes' ORDER BY s.id ASC;");

		 	//print_r($get_site_id); die();
		 	
		 	$get_main_id = $get_site_id[0]['main_offer_id'];
		 	$get_sub_id = $get_site_id[0]['id'];
		 	$click_record = array(
					'click_id'=>$params['click_id'],
					'offer_id'=> $get_main_id,
					'sub_offer_id'=> $get_sub_id,
					'ip_address'=> $params['ip'],
					'created_at'=> date('Y-m-d')
			);
			//print_r($click_record); die();
		 	$add_click = $this->db->save_data('tbl_click',$click_record);

		 	if($add_click){
		 		return $site;
		 	}
	 	}
	 	
	}

	public function get_link_to_display($sites){
			$rand = rand(0,100-1);
			foreach($sites as $site=>$weight) {
			    $rand -= $weight['weight'];
			    if( $rand < 0) break;
			}
			return $weight['sub_url'];
	}
}
?>