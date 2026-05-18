<?php 
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}
//echo "<pre>";
class Filter
{
	private $db;

	public function __construct() {
		$this->db = new Database();
 	}

 	public function filterType($param){
 		//return $param;

 		if($param == 'Status'){
 			// $getStatus = array(array('Status'=>'All'), array('Status'=>'Active'), array('Status'=>'Archived'), array('Status'=>'Deleted'));
 			$getStatus = array(array('Status'=>'Active'), array('Status'=>'Archived'), array('Status'=>'Deleted'));
 			return $getStatus;
 		}

 		if($param == 'Network'){
 			$getNetworkName = $this->db->join_query('SELECT DISTINCT(network_name) AS Network FROM `tbl_network`;');
 			return $getNetworkName;
 		}
 		if($param == 'Domain'){
 			$getNetworkName = $this->db->join_query("SELECT DISTINCT CASE  WHEN LEFT(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 4) = 'www.' THEN SUBSTRING(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 5) ELSE SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)) END AS Domain
				FROM tbl_sub_offer_url;");
 			return $getNetworkName;
 		}
 	}
}

// SELECT DISTINCT CASE  WHEN LEFT(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 4) = 'www.' THEN SUBSTRING(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 5) ELSE SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)) END AS DomainName
// FROM tbl_sub_offer_url;



// SELECT DISTINCT
//     CASE 
//         WHEN LEFT(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 4) = 'www.'
//         THEN SUBSTRING(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 5)
//         ELSE SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2))
//     END AS DomainName
// FROM 
//     tbl_sub_offer_url
// WHERE 
//     sub_url IS NOT NULL
//     AND sub_url != ''
//     AND LOCATE('//', sub_url) > 0;


// SELECT 
//     CASE 
//         -- If the domain starts with "www.", remove it
//         WHEN LEFT(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 4) = 'www.'
//         THEN SUBSTRING(SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2)), 5)
        
//         -- Otherwise, return the domain as is
//         ELSE SUBSTRING(sub_url, LOCATE('//', sub_url) + 2, LOCATE('/', sub_url, LOCATE('//', sub_url) + 2) - (LOCATE('//', sub_url) + 2))
//     END AS Domain,
//     GROUP_CONCAT(sub_url) AS UrlsWithSameDomain
// FROM 
//     tbl_sub_offer_url
// WHERE 
//     sub_url IS NOT NULL
//     AND sub_url != ''
//     AND LOCATE('//', sub_url) > 0
// GROUP BY 
//     Domain
// HAVING COUNT(*) > 1;

// SELECT * FROM `tbl_sub_offer_url` WHERE `sub_url` LIKE '%google.com%'











