<?php
/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
*/

namespace Pathomation\PmaPhp;

/**
CoreAdmin class. Interface to PMA.core for administrative operations. Does NOT apply to PMA.start / PMA.core.lite
*/
class CoreAdmin {

	/**
    Attempt to connect to PMA.core instance; success results in a SessionID
    only success if the user has administrative status
    */
	public static function AdminConnect($pmacoreURL, $pmacoreAdmUsername, $pmacoreAdmPassword){ 
		if ($pmacoreURL == Core::$_pma_pmacoreliteURL) {
			if (Core::is_lite()) {
				throw new \BadMethodCallException("PMA.core.lite found running, but doesn't support an administrative back-end");
			} else {
				throw new \BadMethodCallException("PMA.core.lite not found, and besides; it doesn't support an administrative back-end anyway");
			}
		}
		// purposefully DON'T use helper function _pma_admin_url() here:    
		// why? Because_pma_admin_url() takes session information into account (which we don't have yet)
		$url = PMA::_pma_join($pmacoreURL, "admin/json/AdminAuthenticate?caller=SDK.PHP");
		$url .= "&username=".PMA::_pma_q($pmacoreAdmUsername);
		$url .= "&password=".PMA::_pma_q($pmacoreAdmPassword);

		try {
			$contents = @file_get_contents($url);
		} catch (Exception $ex) {
			throw new Exception("Unable to login $pmacoreAdmUsername on $pmacoreURL");
			$contents = "";
		}

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		$loginresult = $json;
		
		if ($loginresult["Success"] != 1) {
			$admSessionID = null;
		} else {
			$admSessionID = $loginresult["SessionId"];
			
			Core::$_pma_sessions[$admSessionID] = $pmacoreURL;
			if (!isset(Core::$_pma_slideinfos[$admSessionID])) {
				Core::$_pma_slideinfos[$admSessionID] = array();
			}
			Core::$_pma_amount_of_data_downloaded[$admSessionID] = strlen($contents);
		}
		
		return ($admSessionID);
	}

	/**
	Attempt to disconnect from PMA.core instance; True if valid admSessionID was indeed disconnected
	*/
	public static function AdminDisconnect($admSessionID) {
		return Core::disconnect($admSessionID);
	}
	
	/**
	Define a new user in PMA.core
	Returns true if user creation is successful; false if not.
	*/
	public static function AddUser($AdmSessionID, $login, $firstName, $lastName, $email, $password, $canAnnotate = false, $isAdmin = false, $isSuspended = false) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support AddUser()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin?singleWsdl";
		$client = new \SoapClient($url);

		try {	
			$client->CreateUser(
				array(
					"sessionID" => $AdmSessionID,
					"user" => array(
							"Administrator" => $isAdmin,
							"CanAnnotate"   => $canAnnotate,
							"Email"         => $email,
							"FirstName"     => $firstName,
							"LastName"      => $lastName,
							"Local"         => true,
							"Login"         => $login,
							"Password"      => $password,
							"Suspended"     => $isSuspended,
					),
				)
			);	
		} catch (\SoapFault $e) {
			echo "\n<!-- Unable to create user -->\n";
			return false;
		}
		return true;
	}
	public static function UserExists($AdmSessionID, $login) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support UserExists()");
		}

		$url = Core::_pma_url($AdmSessionID)."admin/json/SearchUsers?sessionID=".PMA::_pma_q($AdmSessionID)."&source=local&query=".PMA::_pma_q($login);
		try {
			$contents = @file_get_contents($url);
		} catch (Exception $ex) {
			throw new Exception("Unable to determine if user exists");
			$contents = "";
		}

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		return count($json) > 0;
	}

	function GetMountingPoints($AdmSessionID) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support GetMountingPoints()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/GetInstances?sessionID=".PMA::_pma_q($AdmSessionID);
		
		$json = @file_get_contents($url);
		if ($json == "") return null;
		
		$obj = json_decode($json, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		
		return $obj;
	}
	function GetCurrentMountingPoint($AdmSessionID) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support GetCurrentMountingPoint()");
		}

		$mps = CoreAdmin::GetMountingPoints($AdmSessionID);
		foreach ($mps as $mp) {
			if ($mp["IsCurrent"] == 1) {
				return $mp;
			}
		}
		return null;
	}
	
	public static function AddS3RootDirectory($AdmSessionID, $s3accessKey, $s3secretKey, $alias, $s3path, $instanceID, $description = "Root dir created through lib_php", $isPublic = False, $isOffline = False) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support AddS3RootDirectory()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/CreateRootDirectory";

		$jsonData = [
		 "sessionID" => $AdmSessionID,
		  "rootDirectory"=> [
			"AccessKey"=> $s3accessKey,
			"SecretKey"=> $s3secretKey,
			"Alias"=> $alias,
			"Description"=> $description,
			"Offline"=> $isOffline,
			"Public"=> $isPublic,
			"AmazonS3MountingPoints"=> array(
			[
			"AccessKey"=> $s3accessKey,
			"SecretKey"=> $s3secretKey,
			"Path"=> $s3path,
			"InstanceId"=> $instanceID
			])]
		];
		 
		$ret_val = PMA::_pma_send_post_request($url, $jsonData);
		return $ret_val;
	}

	public static function AddLocalRootDirectory($AdmSessionID, $alias, $localpath, $description = "Root dir created through lib_php", $isPublic = False, $isOffline = False) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support AddLocalRootDirectory()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/CreateFileSystemRootDirectory";

		$jsonData = array(
		 "sessionID" => $AdmSessionID,
		  "rootDirectory"=> array(
			"Alias"=> $alias,
			"Description"=> $description,
			"Offline"=> $isOffline,
			"Public"=> $isPublic,
			"Path"=> $localpath
			)
		);
		 
		$ret_val = PMA::_pma_send_post_request($url, $jsonData);
		return $ret_val;
	}
	
	public static function GrantAccessToRootDirectory($AdmSessionID, $pmacoreUsername, $alias) {

		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support GrantAccessToRootDirectory()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/GrantRootDirAccess";

		$jsonData = array(
		 "sessionID"=> $AdmSessionID,
		 "usernames"=> array($pmacoreUsername),
		 "rootDirectories"=> array($alias)
		);
		 
		$ret_val = PMA::_pma_send_post_request($url, $jsonData);
		return $ret_val;
	}
	
	public static function DenyAccessToRootDirectory($AdmSessionID, $pmacoreUsername, $alias) {

		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support DenyAccessToRootDirectory()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/DenyRootDirAccess";

		$jsonData = array(
		 "sessionID"=> $AdmSessionID,
		 "usernames"=> array($pmacoreUsername),
		 "rootDirectories"=> array($alias)
		);
		 
		$ret_val = PMA::_pma_send_post_request($url, $jsonData);
		return $ret_val;
	}
}

