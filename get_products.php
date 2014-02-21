<?php
// no notice print
error_reporting(0);

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
$dir_w = './txt/write_into/';
$dir_unfinished = './txt/write_into/unfinished/';

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
		$csv_data = array('ASIN', 'Title', 'Binding', 'Creator', 'Edition','Author', 'ItemDimensionsHeight', 'ItemDimensionsLength', 'ItemDimensionsWidth', 'ItemDimensionsWeight', 'Label', 'LanguagesUnknown', 'LanguagesOriginal', 'LanguagesPublished', 'Amount', 'CurrencyCode', 'Manufacturer', 'NumberOfPages', 'PackageDimensionsHeight', 'PackageDimensionsLength', 'PackageDimensionsWidth', 'PackageDimensionsWeight', 'ProductGroup', 'ProductTypeName', 'PublicationDate', 'Publisher', 'SmallImageUrl', 'Studio', 'SalesRanking');
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
			
			if($i % 1 == 0) {
				echo $file_w."\n";
				$searchTerm = array();
				foreach($temp_arr as $key => $value) {
					$searchTerm['IdList.Id.'.($key+1)] = $value;
				}

				$products = getDetail($searchTerm);
				
				if (!empty($products)) {
				
					foreach ($products as $product) {
					
						try {
							$csv_data = array($product['asin'], $product['title'], $product['binding'], $product['creator'], $product['edition'],$product['author'], $product['item_dimensions_height'], $product['item_dimensions_length'], $product['item_dimensions_width'], $product['item_dimensions_weight'], $product['label'], $product['languages_unknown'], $product['languages_original'], $product['languages_published'], $product['amount'], $product['currency_code'], $product['manufacturer'], $product['number_of_pages'], $product['package_dimensions_height'], $product['package_dimensions_length'], $product['package_dimensions_width'], $product['package_dimensions_weight'], $product['product_group'], $product['product_type_name'], $product['publication_date'], $product['publisher'], $product['small_image'], $product['studio'], $product['sales_rank']);
							fputcsv($fh_w, $csv_data);
						} catch (Exception $e) {
					    	echo 'Caught exception: ',  $e->getMessage(), "\n";
						}
						
					}
				}
				$temp_arr = array();
			}
			if($i % 5 == 0)
			{
				delayTime(1);
			}
		}
		
		$searchTerm = array();
		foreach($temp_arr as $key => $value) {
			$searchTerm['IdList.Id.'.($key+1)] = $value;
		}
		
		$products = getDetail($searchTerm);
				
		if (!empty($products)) {
			foreach ($products as $product) {
				$csv_data = array($product['asin'], $product['title'], $product['binding'], $product['creator'], $product['edition'],$product['author'], $product['item_dimensions_height'], $product['item_dimensions_length'], $product['item_dimensions_width'], $product['item_dimensions_weight'], $product['label'], $product['languages_unknown'], $product['languages_original'], $product['languages_published'], $product['amount'], $product['currency_code'], $product['manufacturer'], $product['number_of_pages'], $product['package_dimensions_height'], $product['package_dimensions_length'], $product['package_dimensions_width'], $product['package_dimensions_weight'], $product['product_group'], $product['product_type_name'], $product['publication_date'], $product['publisher'], $product['small_image'], $product['studio'], $product['sales_rank']);
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


function getDetail($searchTerm) {

	$re_arr = array();
	$empty_time = 0;
	while ( empty($re_arr) && $empty_time < 3 )
	{
		$xml = getProductsById($searchTerm);
		$i = 0;
		
		$loop_status = false;
		
		if ( isset($xml->Error->Code) && ($xml->Error->Code == 'RequestThrottled') ) {
			$loop_status = true;
			echo "RequestThrottled\n";
		}
		while ($loop_status) {
			
			$xml = getProductsById($searchTerm);
			if ( !isset($xml->Error->Code) ) {
				$loop_status = false;
				break;
			}
			echo "RequestThrottled\n";
			delayTime(30);
		}
		
		foreach($xml as $xml2) {
			echo $xml2['status']."\n";
			if ($xml2['status'] != 'Success') {
				continue;
			}
			
			$asin = $xml2['Id'];
			echo $asin."\n";
			$re_arr[$i]['asin'] = $asin;
	
			if ( !isset( $xml2->Products->Product->AttributeSets ) ) {
				return $re_arr;
			}
			
	       // var_dump ($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes);
	        
			$binding = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Binding) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Binding : ' ';
			$re_arr[$i]['binding'] = $binding;
	
			$creator = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Creator) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Creator : ' ';
			$re_arr[$i]['creator'] = $creator;
	
			$edition = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Edition) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Edition : ' ';
			$re_arr[$i]['edition'] = $edition;
			
			$author = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Author) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Author : ' ';
			$re_arr[$i]['author'] = $author;
	
			$item_dimensions_height = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Height) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Height : ' ';
			$re_arr[$i]['item_dimensions_height'] = $item_dimensions_height;
	
			$item_dimensions_length = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Length) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Length : ' ';
			$re_arr[$i]['item_dimensions_length'] = $item_dimensions_length;
	
			$item_dimensions_width =  isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Width) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Width : ' ';
			$re_arr[$i]['item_dimensions_width'] = $item_dimensions_width;
	
			$item_dimensions_weight =  isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Width) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ItemDimensions->Width : ' ';
			$re_arr[$i]['item_dimensions_weight'] = $item_dimensions_weight;
	
			$label = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Label) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Label : ' ';
			$re_arr[$i]['label'] = $label;
			
			$languages_unknown = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[0]->Name) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[0]->Name : ' ';
			$re_arr[$i]['languages_unknown'] = $languages_unknown;
			
			$languages_original = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[1]->Name) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[1]->Name : ' ';
			$re_arr[$i]['languages_original'] = $languages_original;
			
			$languages_published = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[2]->Name) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Languages->Language[2]->Name : ' ';
			$re_arr[$i]['languages_published'] = $languages_published;
			
			$amount = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ListPrice->Amount) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ListPrice->Amount : ' ';
			$re_arr[$i]['amount'] = $amount;
			
			$currency_code = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ListPrice->CurrencyCode) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ListPrice->CurrencyCode : ' ';
			$re_arr[$i]['currency_code'] = $currency_code;
			
			$manufacturer = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Manufacturer) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Manufacturer : ' ';
			$re_arr[$i]['manufacturer'] = $manufacturer;
			
			$number_of_pages = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->NumberOfPages) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->NumberOfPages : ' ';
			$re_arr[$i]['number_of_pages'] = $number_of_pages;
			
			$package_dimensions_height = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Height) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Height : ' ';
			$re_arr[$i]['package_dimensions_height'] = $package_dimensions_height;
			
			$package_dimensions_length = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Length) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Length : ' ';
			$re_arr[$i]['package_dimensions_length'] = $package_dimensions_length;
			
			$package_dimensions_width =  isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Width) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Width : ' ';
			$re_arr[$i]['package_dimensions_width'] = $package_dimensions_width;
			
			$package_dimensions_weight = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Weight) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PackageDimensions->Weight : ' ';
			$re_arr[$i]['package_dimensions_weight'] = $package_dimensions_weight;
			
			$product_group = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ProductGroup) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ProductGroup : ' ';
			$re_arr[$i]['product_group'] = $product_group;
			
			$product_type_name = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ProductTypeName) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->ProductTypeName : ' ';
			$re_arr[$i]['product_type_name'] = $product_type_name;
			
			$publication_date = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PublicationDate) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->PublicationDate : ' ';
			$re_arr[$i]['publication_date'] = $publication_date;
			
			$publisher = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Publisher) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Publisher : ' ';
			$re_arr[$i]['publisher'] = $publisher;
			
			$small_image = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->SmallImage->URL) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->SmallImage->URL : ' ';
			$re_arr[$i]['small_image'] = $small_image;
			
			$studio = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Studio) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Studio : ' ';
			$re_arr[$i]['studio'] = $studio;
			
			$title = isset($xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Title) ? $xml2->Products->Product->AttributeSets->children('ns2',true)->ItemAttributes->Title : ' ';
			$re_arr[$i]['title'] = $title;
			
			$sales_rank = isset($xml2->Products->Product->SalesRankings->SalesRank->Rank) ? $xml2->Products->Product->SalesRankings->SalesRank->Rank : ' ';
			$re_arr[$i]['sales_rank'] = $sales_rank;
	
			$i++;
		}
		
		if (empty($re_arr))
		{
			echo "get an empty !!!!\n";
			$empty_time ++;
			delayTime(30);
		}
    }
 	   
	return $re_arr;
	
}
	

function getProductsById($searchTerm) {

	$params = array(
	    'AWSAccessKeyId' => AWS_ACCESS_KEY_ID,
	    'Action' => "GetMatchingProductForId",
	    'SellerId' => MERCHANT_ID,
	    'SignatureMethod' => "HmacSHA256",
	    'SignatureVersion' => "2",
	    'Timestamp'=> gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()),
	    'Version'=> "2011-10-01",
	    'MarketplaceId' => MARKETPLACE_ID,
	    'IdType' => "ASIN",
	    );

	$params = array_merge($params, $searchTerm);

	// Sort the URL parameters
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


