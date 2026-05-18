<?php 
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}

class User
{
	public function __construct() {
		$this->db = new Database();
 	}
	public function login($params = array()){
	 	$check_user = $this->db->fetch_data(DB_USER_TABLE,['user_name'=>$params['username'], 'password'=>$params['password']],1);
	 	return $check_user;
	}

	// public static function login($params = array()){
	//   $check_user = Database::fetch_data(DB_USER_TABLE,['user_name'=>$params['username']],1);
	//   return $check_user;
	// }
}

?>