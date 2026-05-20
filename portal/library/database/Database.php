<?php

class Database{
    
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $conn;

    public function __construct(){
        $this->dbHost = DB_HOST;
        $this->dbUser = DB_USERNAME;
        $this->dbPass = DB_PASSWORD;
        $this->dbName = DB_NAME;

        $this->conn = new mysqli($this->dbHost,$this->dbUser,$this->dbPass,$this->dbName);
        if ($this->conn->connect_error) {
            if (ob_get_level() > 0) { ob_end_clean(); }
            echo json_encode(['response' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }

    public function save_data($table,$info){
        $insert_id = 0;
		$key = array_keys($info);
	    $val = array_values($info);
	    $sql = "INSERT INTO ".$table." (" . implode(', ', $key) . ") ". "VALUES ('" . implode("', '", $val) . "')";
	    if ($this->conn->query($sql) === TRUE)
            $insert_id = $this->conn->insert_id;
		return $insert_id ;
	}

    public function fetch_data($table,$cond="",$limit=0,$orderby='id ASC'){
		$return = array();

		$sql = "SELECT * FROM ".$table;

		if($cond!=""){
			$where = '';
			if(is_array($cond))
			{
				$where = array();
				foreach($cond as $key=>$val) {
					$where[] = "$key = '$val'";
				}
			}
			else
				$where = $cond;
		
			if(is_array($cond))
				$sql .= " WHERE " . implode(' AND ', $where);
			else
				$sql .= " WHERE ".$where;
		}

		$sql .= " ORDER BY ".$orderby;

		if($limit>0)
			$sql .= " LIMIT ".$limit;
		//echo $sql; die();
		$result = $this->conn->query($sql);

		if($result)
		{
			if($result->num_rows > 0) 
			{
				while($row = $result->fetch_assoc())
				{
					$return[] = $row;
				}
			}
		}
		return $return;
	}

	public function fetch_data_new($table,$select="*",$cond="",$limit=0,$orderby='id ASC'){
		$return = array();

		$sql = "SELECT ".$select." FROM ".$table;

		if($cond!=""){
			$where = '';
			if(is_array($cond))
			{
				$where = array();
				foreach($cond as $key=>$val) {
					$where[] = "$key = '$val'";
				}
			}
			else
				$where = $cond;
		
			if(is_array($cond))
				$sql .= " WHERE " . implode(' AND ', $where);
			else
				$sql .= " WHERE ".$where;
		}

		$sql .= " ORDER BY ".$orderby;

		if($limit>0)
			$sql .= " LIMIT ".$limit;

		//echo $sql; die();
		$result = $this->conn->query($sql);

		if($result)
		{
			if($result->num_rows > 0)
			{
				while($row = $result->fetch_assoc())
				{
					$return[] = $row;
				}
			}
		}
		return $return;
	}

	public function fetch_clicks($table,$select="*",$cond=""){
		$return = array();

		$sql = "SELECT ".$select." FROM ".$table;

		if($cond!=""){
			$where = '';
			if(is_array($cond))
			{
				$where = array();
				foreach($cond as $key=>$val) {
					$where[] = "$key = '$val'";
				}
			}
			else
				$where = $cond;
		
			if(is_array($cond))
				$sql .= " WHERE " . implode(' AND ', $where);
			else
				$sql .= " WHERE ".$where;
		}

		
		// echo $sql; die();
		$result = $this->conn->query($sql);


		if($result)
		{
			if($result->num_rows > 0) 
			{
				while($row = $result->fetch_assoc())
				{
					$return[] = $row;
				}
			}
		}
		//print_r($return); die();
		return $return;
	}

	public function update_data($table,$data,$cond){
		$cols = array();
		$where = array();
		$sql = "";

		foreach($data as $key=>$val) {
	        $cols[] = "$key = '$val'";
	    }

		$sql = "UPDATE ".$table." SET " . implode(', ', $cols);


		if($cond!=""){
			$where = '';
			if(is_array($cond))
			{
				$where = array();
				foreach($cond as $key=>$val) {
					$where[] = "$key = '$val'";
				}
			}
			else
				$where = $cond;
		
			if(is_array($cond))
				$sql .= " WHERE " . implode(' AND ', $where);
			else
				$sql .= " WHERE ".$where;
		}
		//echo $sql; die();
	    // foreach($data as $key=>$val) {
	    //     $cols[] = "$key = '$val'";
	    // }
	    // foreach($cond as $key=>$val) {
	    //     $where[] = "$key = '$val'";
	    // }

	    // $sql = "UPDATE ".$table." SET " . implode(', ', $cols) . " WHERE " . implode(' AND ', $where);

	    if($this->conn->query($sql) === TRUE) {
			return 1;
		} else {
			return 0;
		}

		//$this->conn->close();
	}

	public function join_query($sql){
		//echo $sql;
		$return = [];
		 $result = $this->conn->query($sql);

		if($result)
		{
			if($result->num_rows > 0)
			{
				while($row = $result->fetch_assoc())
				{
					$return[] = $row;
				}
			}
		}
		//print_r($return); die();
		return $return;
	}


	public function filter_query($sql){
		//echo $sql;  die();
		$return = [];
		 $result = $this->conn->query($sql);

		if($result)
		{
			if($result->num_rows > 0)
			{
				while($row = $result->fetch_assoc())
				{
					$return[] = $row;
				}
			}
		}
		//print_r($return); die();
		return $return;
	}

	// RC-05: transaction support for atomic multi-table imports
	public function begin_transaction() {
		$this->conn->begin_transaction();
	}

	public function commit() {
		$this->conn->commit();
	}

	public function rollback() {
		$this->conn->rollback();
	}

}
?>