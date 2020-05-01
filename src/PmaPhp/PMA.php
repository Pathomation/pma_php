<?php
/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
*/

namespace Pathomation\PmaPhp;

/**
Helper class. Developers should never access this class directly (but may recognize some helper functions they wrote themselves once upon a time)
*/
class PMA {
	/** returns the current version of the library (2.0.0.88) */
	const version = "2.0.0.88";

	public static $_pma_debug = False;

	/** Internal use only */
	public static function ends_with($wholestring, $suffix)
	{
		return substr($wholestring, - strlen($suffix)) == $suffix ? true : false;
	}

	/** Internal use only */
	public static function starts_with($wholestring, $prefix)
	{
		return substr($wholestring, 0, strlen($prefix)) == $prefix ? true : false;
	}

	/** Internal use only */
	public static function _pma_join($dir1, $dir2)
	{
		$dir1 = str_replace("\\", "/", $dir1);
		$dir2 = str_replace("\\", "/", $dir2);
		if (self::ends_with($dir1, "/")) {
			$dir1 = substr($dir1, 0, strlen($dir1) - 1);
		}
		if (self::starts_with($dir2, "/")) {
			$dir2 = substr($dir2, 1);
		}
		return join("/", array($dir1, $dir2));
	}

	/** Internal use only */
	public static function _pma_q($arg)
	{
		if ($arg == null) {
			return "";
		} else {
			return urlencode($arg);
		}
	}	

	/** Internal use only */
	public static function _pma_send_post_request($url, $jsonData) {
		// echo "URL: " . $url . " <br> \n";
		//Initiate cURL.
		$ch = curl_init($url);
		//Encode the array into JSON.
		$jsonDataEncoded = json_encode($jsonData);
		
		//print_r($jsonDataEncoded);
		
		//Tell cURL that we want to send a POST request.
		curl_setopt($ch, CURLOPT_POST, 1);
		//Attach our encoded JSON string to the POST fields.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
		//Set the content type to application/json
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json')); 
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		//Execute the request
		$result = curl_exec($ch);
		
		if (curl_error($ch)) {
			trigger_error('Curl Error:' . curl_error($ch));
		}
		
		curl_close ($ch);
		
		return $result;		
	}
	
}
