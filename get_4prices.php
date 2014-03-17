<?php
include 'config.inc.php';

ini_set('memory_limit','-1');

function findImportedData($file) {
	$products_temp = array();
	$file_r = $file;
	if (file_exists($file_r)) {
		$row = 1;
		if (($handle = fopen($file_r, "r")) !== FALSE) {
			while (($data = fgetcsv($handle)) !== FALSE) {
				if ($data[0] == 'ASIN') {
					continue;
				}
				$products_temp[$data[0]] = $data;
				$row++;
			}
			fclose($handle);
		}
	}
	return $products_temp;
}

$dir_r = './txt/read_from/';
$dir_w = './txt/write_into/price/';
$dir_unfinished = './txt/write_into/unfinished/price/';

if (!is_dir($dir_w)) {
	$old_umask = umask(0);
	mkdir($dir_w, 0777, true);
	umask($old_umask);
}
if (!is_dir($dir_unfinished)) {
	$old_umask = umask(0);
	mkdir($dir_unfinished, 0777, true);
	umask($old_umask);
}


if ( is_dir($dir_r) ) {
	if ($dh = opendir($dir_r) ) {
		while (($file = readdir($dh)) !== FALSE) {
			if (filetype($dir_r.$file) == 'dir') {
				continue;
			}
			$path_info = pathinfo($file);
			if ($path_info['extension'] != 'csv') {
				continue;
			}
			
			if (file_exists($dir_w.$file)) {
				continue;
			}

			$products_temp = findImportedData($dir_unfinished.$file);
			//$products_temp = array();
			run($dir_r, $dir_w, $file, $products_temp);
		}

	} 
	closedir($dh);
}

function run($dir_r, $dir_w, $file, $products_temp) {

	$i = 0;
	$temp_arr = array();
	if ( ($file_r = fopen($dir_r.$file, 'r')) !== FALSE ) {
		$file_w = $dir_w.$file;
		$fh_w = fopen($file_w, 'w') or die("can't open file");
		$csv_data = array('ASIN',  'landed_amount', 'landed_amount2');
		fputcsv($fh_w, $csv_data);

		while ( ($data = fgetcsv($file_r)) !== FALSE ) {
			
			$item_txt = $data[0];
			if ($item_txt == 'ASIN') {
				continue;
			}
			if (!empty($products_temp[$item_txt])) {
				fputcsv($fh_w, $products_temp[$item_txt]);
				continue;
			}
			$temp_arr[] = $item_txt;
			
			$i++;
			//if ($i == 1440) break;
			
			if($i % 5 == 0) {
				echo $file_w."\n";
				$searchTerm = array();
				foreach($temp_arr as $key => $value) {
                                        while (strlen($value)<10)
				       {
                                          $value = "0".$value;
					}
					$searchTerm['ASINList.ASIN.'.($key+1)] = $value;
				}
                
				$xml = getPriceById($searchTerm);   

				var_dump($xml);            
				$products1 = getDetail_new($xml);
				$products2 = getDetail2_new($xml);
				//$products = $products1;
				var_dump($products1);

				if (!empty($products1)) {
					for ( $i=0; $i< count($products1); $i++ ) {
						$csv_data = array($products1[$i]['asin'], $products1[$i]['landed_amount'],$products2[$i]['landed_amount2']);
						fputcsv($fh_w, $csv_data);
					}
				}
				$temp_arr = array();

				delayTime(1);

			}
		}
		$searchTerm = array();
		foreach($temp_arr as $key => $value) {
			$searchTerm['ASINList.ASIN.'.($key+1)] = $value;
		}

		$xml = getPriceById($searchTerm);
		var_dump($xml);   
		$products1 = getDetail_new($xml);
		$products2 = getDetail2_new($xml);
		var_dump($products2);
		if (!empty($products1)) {
					for ( $i=0; $i< count($products1); $i++ ) {
						$csv_data = array($products1[$i]['asin'], $products1[$i]['landed_amount'],$products2[$i]['landed_amount2']);
						fputcsv($fh_w, $csv_data);
					}
		}
		$temp_arr = array();
		fclose($fh_w);	
	}

}


function delayTime($seconds) {
	// And this is the best:
	$nano = time_nanosleep($seconds, 500000000);

	if ($nano === true) {
		echo "Slept for $seconds seconds, 0.5 microseconds.\n";
	} elseif ($nano === false) {
		echo "Sleeping failed.\n";
	} elseif (is_array($nano)) {
		$seconds = $nano['seconds'];
		$nanoseconds = $nano['nanoseconds'];
		echo "Interrupted by a signal.\n";
		echo "Time remaining: $seconds seconds, $nanoseconds nanoseconds.";
	}
}

function getDetail0_new($xml) {
	$re_arr = array();
	$i = 0;

	$loop_status = false;
	if ( isset($xml->Error->Code) && ($xml->Error->Code == 'RequestThrottled') ) {
		$loop_status = true;
		echo "RequestThrottled\n";
	}
	while ($loop_status) {
		delayTime(2);
		$xml = getProductsById($searchTerm);
		if ( !isset($xml->Error->Code) ) {
			$loop_status = false;
			break;
		}
		echo "RequestThrottled\n";
	}
	foreach($xml as $xml2) {
		echo $xml2['status']."\n";
		if ($xml2['status'] != 'Success') {
			continue;
		}
		
		$asin = $xml2['ASIN'];
		echo $asin."\n";
		
		foreach ($xml2->Product->LowestOfferListings->LowestOfferListing as $xml3) {

			
			 if (preg_match("/^9.*/", $xml3->Qualifiers->SellerPositiveFeedbackRating) != 1) {
				continue;
			}
			if ($xml3->SellerFeedbackCount <= 100) {
				continue;
			}
			
           		if ($xml3->Qualifiers->ItemCondition!='New') {
				continue;
			}
			 if ($xml3->Qualifiers->ShipsDomestically != 'True') {
				continue;
			}
 			if ($xml3->Qualifiers->ShippingTime->Max != '0-2 days') {
				continue;
			}
			$re_arr[$i]['asin'] = $asin;
			$landed_amount = $xml3->Price->ListingPrice->Amount;
			$re_arr[$i]['landed_amount'] = $landed_amount;
			$i++;
		}

		
	}
	var_dump($re_arr);
	return $re_arr;

}
function getDetail_new($xml) {
	$re_arr = array();
	$i = 0;

	$loop_status = false;
	if ( isset($xml->Error->Code) && ($xml->Error->Code == 'RequestThrottled') ) {
		$loop_status = true;
		echo "RequestThrottled\n";
	}
	while ($loop_status) {
		delayTime(2);
		$xml = getProductsById($searchTerm);
		if ( !isset($xml->Error->Code) ) {
			$loop_status = false;
			break;
		}
		echo "RequestThrottled\n";
	}
       
          $a=array(0=>0,1=>0,2=>0,3=>0,4=>0);
	foreach($xml as $xml2) {
		if ($xml2['status'] != 'Success') {
			continue;
		}

		$asin = $xml2['ASIN'];
                echo $asin."\n";
//               echo "i:".$i."\t";
//               echo $a[$i]."\n";
        
		foreach ($xml2->Product->LowestOfferListings->LowestOfferListing as $xml3) {
		//var_dump($xml3);
                    
            if (preg_match("/^9.*/", $xml3->Qualifiers->SellerPositiveFeedbackRating) != 1) {
				continue;
			}
			if ($xml3->SellerFeedbackCount <= 100) {
				continue;
			}
			
           	if ($xml3->Qualifiers->ItemCondition!='New' ) {
				continue;
			}
			 if ($xml3->Qualifiers->ShipsDomestically != 'True') {
				continue;
			}
 			if ($xml3->Qualifiers->ShippingTime->Max != '0-2 days') {
				continue;
			}
//			if ($xml3->Qualifiers->FulfillmentChannel != 'Amazon') {
//				continue;
//			}
                        $a[$i]++;
                        //echo "j=".$a[$i]."\n";
                        if($a[$i]==1){
			$re_arr[$i]['asin'] = $asin;
			$landed_amount = $xml3->Price->ListingPrice->Amount;
			$re_arr[$i]['landed_amount'] = $landed_amount;
                        break;
                        }
		}
		$i++;
	}
	var_dump($re_arr);
	return $re_arr;
        
}

function getDetail2_new($xml) {
	$re_arr = array();
	$i = 0;

	$loop_status = false;
	if ( isset($xml->Error->Code) && ($xml->Error->Code == 'RequestThrottled') ) {
		$loop_status = true;
		echo "RequestThrottled\n";
	}
	while ($loop_status) {
		delayTime(2);
		$xml = getProductsById($searchTerm);
		if ( !isset($xml->Error->Code) ) {
			$loop_status = false;
			break;
		}
		echo "RequestThrottled\n";
	}
       
          $a=array(0=>0,1=>0,2=>0,3=>0,4=>0);
	foreach($xml as $xml2) {
		if ($xml2['status'] != 'Success') {
			continue;
		}

		$asin = $xml2['ASIN'];
                echo $asin."\n";
//               echo "i:".$i."\t";
//               echo $a[$i]."\n";
        
		foreach ($xml2->Product->LowestOfferListings->LowestOfferListing as $xml3) {
		//var_dump($xml3);
                    
            if (preg_match("/^9.*/", $xml3->Qualifiers->SellerPositiveFeedbackRating) != 1) {
				continue;
			}
			if ($xml3->SellerFeedbackCount <= 100) {
				continue;
			}
			
           	if ($xml3->Qualifiers->ItemCondition!='New' ) {
				continue;
			}
			 if ($xml3->Qualifiers->ShipsDomestically != 'True') {
				continue;
			}
 			if ($xml3->Qualifiers->ShippingTime->Max != '0-2 days') {
				continue;
			}
//			if ($xml3->Qualifiers->FulfillmentChannel != 'Amazon') {
//				continue;
//			}
                        $a[$i]++;
                        if($re_arr[$i]['new_2lowest']==null){
                                    if($xml3->Qualifiers->FulfillmentChannel == 'Amazon') {
                                    $landed_amount = $xml3->Price->ListingPrice->Amount;
                                    $re_arr[$i]['new_2lowest'] = $landed_amount;
                                    }else if($a[$i]==2){
                                      $landed_amount = $xml3->Price->ListingPrice->Amount;
                                    $re_arr[$i]['new_2lowest'] = $landed_amount;
                                    }
                        }
		}
		$i++;
	}
	var_dump($re_arr);
	return $re_arr;
        
}



function getPriceById($searchTerm) {
	$params = array(
	    'AWSAccessKeyId' => AWS_ACCESS_KEY_ID,
	    'Action' => "GetLowestOfferListingsForASIN",
	    'SellerId' => MERCHANT_ID,
	    'SignatureMethod' => "HmacSHA256",
	    'SignatureVersion' => "2",
	    'Timestamp'=> gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()),
	    'Version'=> "2011-10-01",
	    'MarketplaceId' => MARKETPLACE_ID,
	    );
            
	$params = array_merge($params, $searchTerm);

	$url_parts = array();
	foreach($params as $key => $value) {
	    $url_parts[] = $key . "=" . str_replace('%7E', '~', rawurlencode($value));
	}
	sort($url_parts);
       
	// Construct the string to sign
	$url_string = implode("&", $url_parts);
	$string_to_sign = "GET\nmws.amazonservices.com\n/Products/2011-10-01\n" . $url_string;

	// Sign the request
	$signature = hash_hmac("sha256", $string_to_sign, AWS_SECRET_ACCESS_KEY, TRUE);

	// Base64 encode the signature and make it URL safe
	$signature = urlencode(base64_encode($signature));

	$url = "https://mws.amazonservices.com/Products/2011-10-01" . '?' . $url_string . "&Signature=" . $signature;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
	//var_dump($response);
	$parsed_xml = simplexml_load_string($response);
	return ($parsed_xml);
}
