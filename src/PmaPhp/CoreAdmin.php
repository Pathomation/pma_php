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
    
    public static function GetLicenseInfo($admSessionID) {
        if (Core::$_pma_pmacoreliteSessionID == $admSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support GetLicenseInfo()");
        }
        
        $url = Core::_pma_url($admSessionID)."admin/json/GetLicense"
        . "?SessionID=" . pma::_pma_q($admSessionID);
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            // this happens when NO instance of PMA.core is detected
            echo "Unable to fetch license information";
            return null;
        }
        
        if (strlen($contents) < 1) {
            return null;
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
        
        return $json;
    }

    public static function GetUsers($admSessionID, $includeSuspendedUsers = FALSE, $path = NULL) {
        if (Core::$_pma_pmacoreliteSessionID == $admSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support GetUsers()");
        }
        
        $url = Core::_pma_url($admSessionID)."admin/json/GetUsers"
        . "?sessionID=" . pma::_pma_q($admSessionID)
        . "&includeSuspendedUsers=" . pma::_pma_q($includeSuspendedUsers ? "true" : "false");
        
        if (!is_null($path)) {
            $url .= "&path=" . urlencode($path);
        }
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            // this happens when NO instance of PMA.core is detected
            echo "Unable to fetch instances information";
            return null;
        }
        
        if (strlen($contents) < 1) {
            return null;
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
        
        return $json;
    }
    
    public static function GetInstances($admSessionID) {
        if (Core::$_pma_pmacoreliteSessionID == $admSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support GetInstances()");
        }
        
        $url = Core::_pma_url($admSessionID)."admin/json/GetInstances"
        . "?SessionID=" . pma::_pma_q($admSessionID);
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            // this happens when NO instance of PMA.core is detected
            echo "Unable to fetch instances information";
            return null;
        }
        
        if (strlen($contents) < 1) {
            return null;
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
        
        return $json;
    }
    
    public static function GetCurrentInstance($admSessionID) {
        $instances = CoreAdmin::GetInstances($admSessionID);
        if (is_array($instances))
        {
            foreach ($instances as $i)
            {
                if ($i["IsCurrent"] == 1)
                {
                    return $i;
                }
            }
        }
        else if (!is_null($instances))
        {
            return $instances;
        }
        
        return null;
    }
    
    /**
    Send out an email reminder to the address associated with user login
    Returns true call to PMA.core was successful (doesn't guarantee an email was received!); false if not.
    */
    public static function SendEmailReminder($AdmSessionID, $login, $subject = "PMA.core password reminder") {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support SendEmailReminder()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/EmailPassword";
        
        $jsonData = [
        "sessionID" => $AdmSessionID,
        "username" => $login,
        "subject" => $subject,
        "messageTemplate" => ""
        ];
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        
        return true;
    }

    public static function ChangePassword($AdmSessionID, $oldPassword, $newPassword) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support ChangePassword()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/ChangePassword";
        $url .= "?SessionID=" . pma::_pma_q($AdmSessionID);
        $url .= "&oldpassword=" . pma::_pma_q($oldPassword);
        $url .= "&newpassword=" . pma::_pma_q($newPassword);
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            // this happens when NO instance of PMA.core is detected
            echo "Unable to change password";
            return null;
        }
        
        if (strlen($contents) < 1) {
            return null;
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
        
        return $json == TRUE;
    }
	
	public static function ResetPassword($AdmSessionID, $username, $newPassword) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support ChangePassword()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/ResetPassword";
        $url .= "?SessionID=" . pma::_pma_q($AdmSessionID);
        $url .= "&username=" . pma::_pma_q($username);
        $url .= "&newpassword=" . pma::_pma_q($newPassword);
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            // this happens when NO instance of PMA.core is detected
            echo "Unable to reset password";
            return null;
        }
        
        if (strlen($contents) < 1 && $contents !== "") {
            return false;
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
		
		if (isset($json["Code"])) {
            return null;
        }
        
        return true;
    }
    
    /**
    Define a new user in PMA.core
    Returns true if user creation is successful; false if not.
    */
    public static function AddUser($AdmSessionID, $login, $firstName, $lastName, $email, $password, $canAnnotate = false, $isAdmin = false, $isSuspended = false) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support AddUser()");
        }
        $url = Core::_pma_url($AdmSessionID)."admin/json/CreateUser";
        
        $jsonData = [
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
        ];
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
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
    
    function GetPmaCoreInstances($AdmSessionID) {
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
    function GetCurrentPmaCoreInstance($AdmSessionID) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support GetCurrentMountingPoint()");
        }
        
        $mps = CoreAdmin::GetPmaCoreInstances($AdmSessionID);
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
        $currentInstanceId = CoreAdmin::GetCurrentInstance($AdmSessionID)["InstanceID"];
        
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
        "InstanceId"=> ($instanceID == 0 ? $currentInstanceId : $instanceID)
        ])]
        ];
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function AddLocalRootDirectory($AdmSessionID, $alias, $localpath, $description = "Root dir created through lib_php", $instanceID = 0, $isPublic = False, $isOffline = False) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support AddLocalRootDirectory()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/CreateRootDirectory";
        $currentInstanceId = CoreAdmin::GetCurrentInstance($AdmSessionID)["InstanceID"];
        
        $jsonData = array(
        "sessionID" => $AdmSessionID,
        "rootDirectory"=> array(
        "Alias"=> $alias,
        "Description"=> $description,
        "Offline"=> $isOffline,
        "Public"=> $isPublic,
        "FileSystemMountingPoints" => array(array(
        "Path"=> $localpath,
        "InstanceId" => ($instanceID == 0 ? $currentInstanceId : $instanceID)
        ))
        )
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
	
	public static function CreateDirectory($AdmSessionID, $path) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support createDirectory()");
        }
		$url = Core::_pma_url($AdmSessionID)."admin/json/CreateDirectory";
        $jsonData = array(
			"sessionID" => $AdmSessionID,
			"path" 	  	=> $path
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
    
    public static function RenameSlide($AdmSessionID, $slide, $newName) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support RenameSlide()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/RenameSlide";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "path"=> $slide,
        "newName"=> $newName
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
	
	public static function MoveSlide($AdmSessionID, $slide, $newPath) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support RenameSlide()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/MoveSlide";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "sourcePath"=> $slide,
        "destinationPath"=> $newPath
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function DeleteSlide($AdmSessionID, $slide) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support DeleteSlide()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/DeleteSlide";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "path"=> $slide
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function DeleteDirectory($AdmSessionID, $directory) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support DeleteDirectory()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/DeleteDirectory";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "path"=> $directory
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function DeleteUser($AdmSessionID, $username) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support DeleteUser()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/DeleteUser";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "login"=> $username
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function DeleteRootDirectory($AdmSessionID, $alias) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support DeleteRootDirectory()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/DeleteRootDirectory";
        
        $jsonData = array(
        "sessionID"=> $AdmSessionID,
        "alias"=> $alias
        );
        
        $ret_val = PMA::_pma_send_post_request($url, $jsonData);
        return $ret_val;
    }
    
    public static function GetRootDirectories($AdmSessionID, $aliasFilter = null) {
        if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
            throw new \BadMethodCallException("PMA.start doesn't support DeleteRootDirectory()");
        }
        
        $url = Core::_pma_url($AdmSessionID)."admin/json/GetRootDirectories?sessionID=".PMA::_pma_q($AdmSessionID);
        if (isset($aliasFilter)) {
            $url .= "&aliasFilter=".PMA::_pma_q($aliasFilter);
        }
        
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $ex) {
            throw new Exception("Unable to retrieve root-directories through $sessionID");
            $contents = "";
        }
        
        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }
        
        return $json;
    }
}