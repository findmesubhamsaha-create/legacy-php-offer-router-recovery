<?php
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}

class Report
{
	private $db;

	public function __construct() {
		$this->db = new Database();
 	}
	public function getReport($params = array()){

		$get_report = $this->db->fetch_data_new('tbl_report','main_offer_id, main_offer_url, offer_clicks, report_date','main_offer_id="'.$params['oid'].'"');

		$data = array();
	 	for ($i=0; $i < count($get_report); $i++) { 
	 		$sub_array = array();
			$sub_array[] = $get_report[$i]['main_offer_id'];
			$sub_array[] = $get_report[$i]['main_offer_url'];
			$sub_array[] = $get_report[$i]['offer_clicks'];
			$sub_array[] = $get_report[$i]['report_date'];
			$data[] = $sub_array;
	 	}


	 	return $data;



		//$get_report = $this->db->fetch_data_new('tbl_report','main_offer_id, main_offer_url, offer_clicks, report_date','main_offer_id="'.$params['oid'].'"');
		//echo $get_report;
		//return $get_report;
		// SELECT main_offer_url, SUM(sub_offer_clicks) sub_offer_clicks, report_date FROM `tbl_report` WHERE main_offer_id=27 GROUP BY report_date ORDER BY report_date DESC;
		// SELECT main_offer_url, SUM(sub_offer_clicks) sub_offer_clicks, report_date FROM tbl_report WHERE main_offer_id = '14' ORDER BY report_date DESC
	}
}
?>