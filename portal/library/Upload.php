<?php
if (!defined('BASEPATH')){
    exit('Direct script access is not allowed!');
}

class Upload
{
	private $db;

	public function __construct() {
		$this->db = new Database();
 	}
	public function uploadOffer($params = array()){
	 	//return $params;
	 	if (isset($params)) {
		    $csvData = json_decode($params, true);

		    if ($csvData && is_array($csvData)) {
		        // Extract headers from the first row
		        $headers = array_keys($csvData[0]);

		        // Identify relevant columns dynamically
		        $url_columns = [];
		        $weight_columns = [];
		        $status_columns = [];
		        $offer_name_column = '';
		        $slug_name_column = '';
		        $start_date_column = '';
		        $end_date_column = '';

		        foreach ($headers as $header) {
		            if (stripos($header, 'url') !== false) {
		                $url_columns[] = $header;
		            } elseif (stripos($header, 'weight') !== false) {
		                $weight_columns[] = $header;
		            } elseif (stripos($header, 'status') !== false) {
		                $status_columns[] = $header;
		            } elseif (stripos($header, 'offer name') !== false) {
		                $offer_name_column = $header;
		            }elseif (stripos($header, 'slug name') !== false) {
		                $slug_name_column = $header;
		            } elseif (stripos($header, 'start date') !== false) {
		                $start_date_column = $header;
		            } elseif (stripos($header, 'end date') !== false) {
		                $end_date_column = $header;
		            }
		        }

		        // Check if essential columns are present
		        if (empty($url_columns) || empty($weight_columns) || empty($status_columns) || empty($offer_name_column) || empty($slug_name_column)) {
		            return json_encode(['status' => 'error', 'message' => 'Missing required columns: URL, Weight, Status, Slug Name or Offer Name.']);
		            exit;
		        }

		        $errors = [];  // Store error rows with reasons
		        $inserted_rows = 0;  // Track successfully inserted rows

		        // Group rows by offer for validations
		        $offer_data = [];
		        foreach ($csvData as $row_number => $row) {

		            $offer_name = $row[$offer_name_column] ?? 'Unknown Offer';
		            if (!isset($offer_data[$offer_name])) {
		                $offer_data[$offer_name] = [];
		            }
		            $offer_data[$offer_name][] = ['row_number' => $row_number + 1, 'data' => $row];
		        }

		        // Process each offer's rows
		        foreach ($offer_data as $offer_name => $offer_rows) {
		        	//echo "<pre>";
		        	// print_r($offer_rows[$offer_name]['data']['offer name']);
		        	// die();
		        	$offer_urls = [];
		            $offer_weight_total = 0;
		            $offer_correct_rows = []; // Temporarily store valid rows for this offer
		            $row_has_error = false;
		        	$error_reason = [];
		            //$outputArray = [];
		            // RC-07: extract offer-level dates from first row only — prevents last-row value leakage
		            $first_row_data = $offer_rows[0]['data'] ?? [];
		            $start_date     = $first_row_data['start date'] ?? '';
		            $end_date       = $first_row_data['end date'] ?? '';
		            $current_date   = date('Y-m-d');
		            if (empty($start_date)) {
		                $start_date = $current_date;
		            } elseif (!$this->validateDate($start_date)) {
		                $errors[] = ['row' => 'Offer: ' . $offer_name, 'offer' => $offer_name, 'error' => "Invalid Start Date: $start_date."];
		            }
		            if (empty($end_date)) {
		                $end_date = $current_date;
		            } elseif (!$this->validateDate($end_date)) {
		                $errors[] = ['row' => 'Offer: ' . $offer_name, 'offer' => $offer_name, 'error' => "Invalid End Date: $end_date."];
		            }


		        	foreach ($offer_rows as $key => $values) {

		        		$offer_name = trim($values['data']['offer name'] ?? '');
		            	$slug_name = trim($values['data']['slug name'] ?? '');

		            	//Validate Offer Name and Slug Name
			            if (empty($offer_name)) {
			                $error_reason[] = "Offer Name cannot be empty.";
			                $row_has_error = true;
			            }
			            if (empty($slug_name)) {
			                $error_reason[] = "Slug Name cannot be empty.";
			                $row_has_error = true;
			            }

				        
				        //print_r($values);  // Prints the array of row data
				    }


		            
		            foreach ($offer_rows as $row_info) {
		                $row = $row_info['data'];
		                $row_number = $row_info['row_number'];
		                $row_has_error = false;  // RC-06: reset per-row to prevent error state leakage
		                $error_reason = [];

		                // Loop through URL, Weight, and Status columns
		                for ($i = 0; $i < count($url_columns); $i++) {
		                    $url    = trim((string)($row[$url_columns[$i]] ?? ''));
		                    $weight = trim((string)($row[$weight_columns[$i]] ?? ''));
		                    $status = trim((string)($row[$status_columns[$i]] ?? ''));

		                    // Skip optional URL slots where all three fields are absent
		                    if ($url === '' && $weight === '' && $status === '') {
		                        continue;
		                    }

		                    // Validate the URL
		                    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
		                        $error_reason[] = "Invalid URL: $url";
		                        $row_has_error = true;
		                    }

		                    // Check for duplicate URLs within the same offer
		                    if ($url && in_array($url, $offer_urls)) {
		                        $error_reason[] = "Duplicate URL within the same offer: $url";
		                        $row_has_error = true;
		                    }
		                    if ($url !== '') {
		                        $offer_urls[] = $url;
		                    }

		                    // Add weight only if the status is 'yes'
		                    if (strtolower($status) === 'yes') {
		                        $offer_weight_total += (float)$weight;
		                    }
		                }

		                // If the row has errors, store it in the errors array
		                if ($row_has_error) {
		                    $errors[] = [
		                        'row' => $row_number,
		                        'offer' => $offer_name,
		                        'error' => implode("; ", $error_reason),
		                        'data' => $row
		                    ];
		                } else {
		                    // Store the valid row temporarily
		                    $offer_correct_rows[] = $row;
		                }
		            }

		            // After processing all rows for this offer, validate the weight
		            if (abs($offer_weight_total - 100) > 0.0001) {  // Use precision check for floating-point comparison
		                $errors[] = [
		                    'row' => 'Offer: ' . $offer_name,
		                    'offer' => $offer_name,
		                    'error' => "Total weight for 'yes' status must equal 100. Current total: $offer_weight_total"
		                ];
		            } else {
		            	//$offer_correct_rows[] = $row;
		                //Insert all valid rows for this offer into the database
		                foreach ($offer_correct_rows as $valid_row) {
		                	
		                    $check_offer = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['offer'=>$valid_row['offer name'], 'offer_status'=>1],1);
		                    $check_slug = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['slug_name'=>$valid_row['slug name'], 'offer_status'=>1],1);

		                    $check_tag = $this->db->fetch_data('tbl_tag',['tag_name'=>$valid_row['tag name']],1);
							$check_network = $this->db->fetch_data('tbl_network',['network_name'=>$valid_row['network']]);
							if(!empty($check_tag)){
								$valid_row['tag name'] = $check_tag[0]['id'];
							}
							else{
								$tag_record = array(
									'tag_name'=>$this->trimmString($valid_row['tag name']),
									'created_at'=> date('Y-m-d')
								);
								$add_tag = $this->db->save_data('tbl_tag',$tag_record);
								if($add_tag){
									$valid_row['tag name'] = $add_tag;
								}
							}


							if(!empty($check_network)){
								$valid_row['network'] = $check_network[0]['id'];
							}
							else{
								$network_record = array(
									'network_name'=>$this->trimmString($valid_row['network']),
									'created_at'=> date('Y-m-d')
								);
								$add_network = $this->db->save_data('tbl_network',$network_record);
								if($add_network){
									$valid_row['network'] = $add_network;
								}
							}
							// print_r($check_offer);
							// die();
							if(!empty($check_offer) || !empty($check_slug)){
								$errors[] = [
				                    'row' => 'Offer: ' . $offer_name,
				                    'offer' => $offer_name,
				                    'error' => "Offer/Slug Name is in Use, Please enter another offer/slug name!"
				                ];
							}
							else{
								$urls = [];
								$weights = [];
								$statuses = [];
								$outputArray = [];
								foreach ($valid_row as $key => $value) {
								    if (strpos($key, 'url') === 0) {
								        $urls[] = $value; // Collect URLs
								    } elseif (strpos($key, 'weight') === 0) {
								        $weights[] = (float) $value; // Collect weights and convert to float
								    } elseif (strpos($key, 'status') === 0) {
								        $statuses[] = $value; // Collect statuses
								    } else {
								        // Copy the rest of the fields as-is
								        $outputArray[$key] = $value;
								    }
								}

								// Add the collected URLs, weights, and statuses to the output array
								$outputArray['url'] = $urls;
								$outputArray['weight'] = $weights;
								$outputArray['status'] = $statuses;

								$main_url_record = array(
									'offer'=>$this->trimmString($outputArray['offer name']),
									'slug_name'=>$this->trimmString($outputArray['slug name']),
									'tag_id'=>$this->trimmString($outputArray['tag name']),
									'note'=>$this->trimmString($outputArray['notes']),
									'network_id'=>$this->trimmString($outputArray['network']),
									'start_date'=>$start_date,
									'end_date'=>$end_date
								);
								//print_r($main_url_record); die();
								// RC-05: atomic transaction for main + sub-offer inserts
								$this->db->begin_transaction();
								try {
									$add_offer = $this->db->save_data(DB_OFFER_TABLE, $main_url_record);
									if (empty($add_offer)) {
										throw new \RuntimeException('Main offer insert failed');
									}
									for ($x = 0; $x < count($outputArray['url']); $x++) {
										$u = trim((string)($outputArray['url'][$x] ?? ''));
										$s = trim((string)($outputArray['status'][$x] ?? ''));
										$w = trim((string)($outputArray['weight'][$x] ?? ''));
										// Skip optional empty URL slots — prevents ENUM/NOT NULL constraint errors
										if ($u === '' || $s === '' || $w === '') {
										    continue;
										}
										$sub_url_record = array(
										    'main_offer_id' => $add_offer,
										    'sub_url'       => $u,
										    'weight'        => (float)$w,
										    'status'        => $s
										);
										$add_sub_offer = $this->db->save_data('tbl_sub_offer_url', $sub_url_record);
										if (empty($add_sub_offer)) {
										    throw new \RuntimeException('Sub-offer insert failed at index ' . $x);
										}
									}
									$this->db->commit();
									$inserted_rows++;
								} catch (\Exception $e) {
									$this->db->rollback();
									$errors[] = [
										'row'   => 'Offer: ' . $offer_name,
										'offer' => $offer_name,
										'error' => 'Database error: ' . $e->getMessage()
									];
								}
							}
		                }
		            }
		        }

		        // Return the response with inserted row count and errors
		        if(count($errors) === 0){

		        	// foreach ($offer_correct_rows as $valid_row) {

		        	// 	echo "<pre>";
		        	// 	print_r($valid_row);
		        	// 	die();
		                    
		            //         $check_offer = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['offer'=>$valid_row['offer name'], 'offer_status'=>1],1);
		            //         $check_slug = $this->db->fetch_data_new(DB_OFFER_TABLE,'id',['slug_name'=>$valid_row['slug name'], 'offer_status'=>1],1);

		            //         $check_tag = $this->db->fetch_data('tbl_tag',['tag_name'=>$valid_row['tag name']],1);
					// 		$check_network = $this->db->fetch_data('tbl_network',['network_name'=>$valid_row['network']]);
					// 		if(!empty($check_tag)){
					// 			$valid_row['tag name'] = $check_tag[0]['id'];
					// 		}
					// 		else{
					// 			$tag_record = array(
					// 				'tag_name'=>$this->trimmString($valid_row['tag name']),
					// 				'created_at'=> date('Y-m-d')
					// 			);
					// 			$add_tag = $this->db->save_data('tbl_tag',$tag_record);
					// 			if($add_tag){
					// 				$valid_row['tag name'] = $add_tag;
					// 			}
					// 		}


					// 		if(!empty($check_network)){
					// 			$valid_row['network'] = $check_network[0]['id'];
					// 		}
					// 		else{
					// 			$network_record = array(
					// 				'network_name'=>$this->trimmString($valid_row['network']),
					// 				'created_at'=> date('Y-m-d')
					// 			);
					// 			$add_network = $this->db->save_data('tbl_network',$network_record);
					// 			if($add_network){
					// 				$valid_row['network'] = $add_network;
					// 			}
					// 		}
					// 		// print_r($check_offer);
					// 		// die();
					// 		if(!empty($check_offer) || !empty($check_slug)){
					// 			$errors[] = [
				    //                 'row' => 'Offer: ' . $offer_name,
				    //                 'offer' => $offer_name,
				    //                 'error' => "Offer/Slug Name is in Use, Please enter another offer/slug name!"
				    //             ];
					// 		}
					// 		else{
					// 			foreach ($valid_row as $key => $value) {
					// 			    if (strpos($key, 'url') === 0) {
					// 			        $urls[] = $value; // Collect URLs
					// 			    } elseif (strpos($key, 'weight') === 0) {
					// 			        $weights[] = (float) $value; // Collect weights and convert to float
					// 			    } elseif (strpos($key, 'status') === 0) {
					// 			        $statuses[] = $value; // Collect statuses
					// 			    } else {
					// 			        // Copy the rest of the fields as-is
					// 			        $outputArray[$key] = $value;
					// 			    }
					// 			}

					// 			// Add the collected URLs, weights, and statuses to the output array
					// 			$outputArray['url'] = $urls;
					// 			$outputArray['weight'] = $weights;
					// 			$outputArray['status'] = $statuses;

					// 			$main_url_record = array(
					// 				'offer'=>$this->trimmString($outputArray['offer name']),
					// 				'slug_name'=>$this->trimmString($outputArray['slug name']),
					// 				'tag_id'=>$this->trimmString($outputArray['tag name']),
					// 				'note'=>$this->trimmString($outputArray['notes']),
					// 				'network_id'=>$this->trimmString($outputArray['network']),
					// 				'start_date'=>$start_date,
					// 				'end_date'=>$end_date
					// 			);
					// 			//print_r($main_url_record); die();
					// 			$add_offer = $this->db->save_data(DB_OFFER_TABLE,$main_url_record);
					// 			if(!empty($add_offer)){
					// 				for ($x = 0; $x < count($outputArray['url']); $x++) {
					// 					$sub_url_record = array(
					// 							'main_offer_id'=>$add_offer,
					// 							'sub_url'=>$this->trimmString($outputArray['url'][$x]),
					// 							'weight'=>$outputArray['weight'][$x],
					// 							'status'=>$outputArray['status'][$x]
					// 						);
					// 					$add_sub_offer = $this->db->save_data('tbl_sub_offer_url',$sub_url_record);
					// 				}
					// 				$inserted_rows++;
					// 			}
					// 		}
		            //     }

		            return json_encode([
		            'status' => 'success',
		            'message' => 'All Success.',
		            'inserted_rows' => $inserted_rows
		            ]);
		            
		        }
		        else{
		            return json_encode([
		            'status' => 'partial_success',
		            'message' => 'Processed with some errors.',
		            'inserted_rows' => $inserted_rows,
		            'error_rows' => $errors
		            ]);
		        }
		        
		    } else {
		        return json_encode(['status' => 'error', 'message' => 'Invalid CSV data format.']);
		    }
		} else {
		    return json_encode(['status' => 'error', 'message' => 'Invalid request or missing CSV data.']);
		}

	}


	public function trimmString($string) {
		$trimmedString= preg_replace('/^\s+|\s+$/u', '', $string);
		return $trimmedString;
	}

	// Helper function to validate dates
	public function validateDate($date, $format = 'Y-m-d') {
		    $d = DateTime::createFromFormat($format, $date);
		    return $d && $d->format($format) === $date;
		}
}
?>
