<?php
/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
*/

namespace Pathomation;

/**
Class that wraps around the free PMA.core.lite (the server component of PMA.start), as well as its commercial variant; the PMA.core product
*/
class Core {
	# internal module helper variables and functions
	private static $_pma_sessions = [];
	private static $_pma_slideinfos = [];
	private static $_pma_pmacoreliteURL = "http://localhost:54001/";
	private static $_pma_pmacoreliteSessionID = "SDK.PHP";
	private static $_pma_usecachewhenretrievingtiles = true;
	private static $_pma_amount_of_data_downloaded = array("SDK.PHP" => 0);

	/** Internal use only */
	private static function _pma_session_id($sessionID = null)
	{
		if ($sessionID === null) {
			// if the sessionID isn't specified, maybe we can still recover it somehow
			return self::_pma_first_session_id();
		} else {
			// nothing to do in this case; a SessionID WAS passed along, so just continue using it
			return $sessionID;
		}
	}

	/** Internal use only */
	private static function _pma_first_session_id()
	{
		// do we have any stored sessions from earlier login events?
		if (count(self::$_pma_sessions) > 0) {
			// yes we do! This means that when there's a PMA.core active session AND PMA.core.lite version running,
			// the PMA.core active will be selected and returned
			return current(array_keys(self::$_pma_sessions));
		} else {
			// ok, we don't have stored sessions; not a problem per se...
			if (self::_pma_is_lite()) {
				if (!isset(self::$_pma_slideinfos[self::$_pma_pmacoreliteSessionID])) {
					self::$_pma_slideinfos[self::$_pma_pmacoreliteSessionID] = [];
				}
				return self::$_pma_pmacoreliteSessionID;
			} else {
				// no stored PMA.core sessions found NOR PMA.core.lite
				return null;
			}
		}
	}
            
	/** Internal use only */
	public static function _pma_url($sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		
		if ($sessionID === null) {
			// sort of a hopeless situation; there is no URL to refer to
			return null;
		} elseif ($sessionID == self::$_pma_pmacoreliteSessionID) {
			return self::$_pma_pmacoreliteURL;
		} else {
			// assume sessionID is a valid session; otherwise the following will generate an error
			if (isset(self::$_pma_sessions[$sessionID])) {
				$url = self::$_pma_sessions[$sessionID];
				if (!PMA::ends_with($url, "/")) {
					$url .= "/";
				}
				return $url;
			} else {
				throw new Exception("Invalid sessionID: ".$sessionID);
			}
		}
	}
            
	/** Internal use only */
	private static function _pma_is_lite($pmacoreURL = null)
	{
		if ($pmacoreURL == null) {
			$pmacoreURL = self::$_pma_pmacoreliteURL;
		}
		
		$url = self::_pma_join($pmacoreURL, "api/json/IsLite");
		$contents = "";
		
		try {
			@$contents = file_get_contents($url);
		} catch (Exception $e) {
			// this happens when NO instance of PMA.core is detected
			echo "Unable to detect PMA.core(.lite)";
			return null;
		}
		
		if (strlen($contents) < 1) {
			echo "Unable to detect PMA.core(.lite)";
			return null;
		}
		
		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		
		return $json == 1; //return str(dom.firstChild.firstChild.nodeValue).lower() == "true";
	}

	/** Internal use only */
	private static function _pma_api_url($sessionID = None, $xml = false)
	{
		// let's get the base URL first for the specified session
		$url = self::_pma_url($sessionID);
		if ($url == null) {
			// sort of a hopeless situation; there is no URL to refer to
			return null;
		}
		// remember, _pma_url is guaranteed to return a URL that ends with "/"
		return self::_pma_join($url, "api/json/");
	}
    
	/** Internal use only */
	private static function _pma_join($dir1, $dir2)
	{
		$dir1 = str_replace("\\", "/", $dir1);
		$dir2 = str_replace("\\", "/", $dir2);
		if (PMA::ends_with($dir1, "/")) {
			$dir1 = substr($dir1, 0, strlen($dir1) - 1);
		}
		if (PMA::starts_with($dir2, "/")) {
			$dir2 = substr($dir2, 1);
		}
		return join("/", array($dir1, $dir2));
	}
    
	/** Internal use only */
	private static function _pma_q($arg)
	{
		if ($arg == null) {
			return "";
		} else {
			return urlencode($arg);
		}
	}
    
	# end internal module helper variables and functions


	/**
	See if there's a PMA.core.lite or PMA.core instance running at $pmacoreURL
	*/
	public static function isLite($pmacoreURL = null)
	{
		global $_pma_pmacoreliteURL;
		if ($pmacoreURL == null) {
			$pmacoreURL = $_pma_pmacoreliteURL;
		}
		return self::_pma_is_lite($pmacoreURL);
	}

	/**
	Get version info from PMA.core instance running at $pmacoreURL
	*/
	public static function getVersionInfo($pmacoreURL = null)
	{
		global $_pma_pmacoreliteURL;
		if ($pmacoreURL == null) {
			$pmacoreURL = $_pma_pmacoreliteURL;
		}
		// purposefully DON'T use helper function _pma_api_url() here:
		// why? because GetVersionInfo can be invoked WITHOUT a valid SessionID; _pma_api_url() takes session information into account
		$url = self::_pma_join($pmacoreURL, "api/json/GetVersionInfo");
		$contents = "";
		try {
			@$contents = file_get_contents($url);
		} catch (Exception $e) {
			return null;
		}

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		return $json;
	}

	/**
	Attempt to connect to PMA.core instance; success results in a SessionID
	*/
	public static function connect($pmacoreURL = null, $pmacoreUsername = "", $pmacorePassword = "")
	{
		if ($pmacoreURL == null) {
			$pmacoreURL = self::$_pma_pmacoreliteURL;
		}

		if ($pmacoreURL == self::$_pma_pmacoreliteURL) {
			if (is_lite()) {
				// no point authenticating localhost / PMA.core.lite
				return self::$_pma_pmacoreliteSessionID;
			} else {
				return null;
			}
		}
		
		// purposefully DON'T use helper function _pma_api_url() here:
		// why? Because_pma_api_url() takes session information into account (which we don't have yet)
		$url = self::_pma_join($pmacoreURL, "api/json/authenticate?caller=SDK.PHP");
		if ($pmacoreUsername != "") {
			$url .= "&username=".self::_pma_q($pmacoreUsername);
		}
		if ($pmacorePassword != "") {
			$url .= "&password=".self::_pma_q($pmacorePassword);
		}
		
		try {
			@$contents = file_get_contents($url);
		} catch (Exception $e) {
			//	 Something went wrong; unable to communicate with specified endpoint
			return null;
		}
		if (strlen($contents) < 1) {
			return null;
		}
		
		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		if ($json["Success"] != 1) {
			$sessionID = null;
		} else {
			$sessionID = $json["SessionId"];
			self::$_pma_sessions[$sessionID] = $pmacoreURL;
			self::$_pma_slideinfos[$sessionID] = [];
			self::$_pma_amount_of_data_downloaded[$sessionID] = strlen($contents);
		}
		return $sessionID;
	}

	/**
	Attempt to connect to PMA.core instance; success results in a SessionID
	*/
	public static function disconnect($sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		if ($sessionID == null) {
			return false;
		}
		
		$url = self::_pma_api_url($sessionID)."DeAuthenticate?sessionID=".self::_pma_q($sessionID);
		try {
			$contents = @file_get_contents($url);
		} catch (Exception $ex) {
			throw new Exception("Unable to disconnect");
		}
		
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
		
		if (count(self::$_pma_sessions) > 0) {
			unset(self::$_pma_sessions[$sessionID]);
			unset(self::$_pma_slideinfos[$sessionID]);
		}
		
		return true;
	}

	/**
	Return an array of root-directories available to $sessionID
	*/
	public static function getRootDirectories($sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		$url = self::_pma_api_url($sessionID)."GetRootDirectories?sessionID=".self::_pma_q($sessionID);
		$contents = file_get_contents($url);

		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		
		return $json;
	}

	/**
	Return an array of sub-directories available to $sessionID in the $startDir directory
	*/
	public static function getDirectories($startDir, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		$url = self::_pma_api_url($sessionID)."GetDirectories?sessionID=".self::_pma_q($sessionID)."&path=".self::_pma_q($startDir);
		$contents = file_get_contents($url);

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
		
		if (isset($json["Code"])) {
			throw new Exception("get_directories to $startDir resulted in: ".$json["Message"]." (keep in mind that startDir is case sensitive!)");
		}
		return $json;
	}

	/**
	Look for the first directory in a directory hierarchy that starts at $startDir that has at least one actual slide in it
	*/
	public static function getFirstNonEmptyDirectory($startDir = null, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);

		if (($startDir === null) || ($startDir == "")) {
			$startDir = "/";
		}
		
		$slides = self::getSlides($startDir, $sessionID);
		if (count($slides) > 0) {
			return $startDir;
		} else {
			if (startDir == "/") {
				foreach (self::get_root_directories($sessionID) as $dir) {
					$nonEmtptyDir = self::get_first_non_empty_directory($dir, $sessionID);
					if ($nonEmtptyDir !== null) {
						return $nonEmtptyDir;
					}
				}
			} else {
				foreach (self::get_directories($startDir, $sessionID) as $dir) {
					$nonEmtptyDir = self::get_first_non_empty_directory($dir, $sessionID);
					if ($nonEmtptyDir !== null) {
						return $nonEmtptyDir;
					}
				}
			}
		}
		return null;
	}

	/**
	Return an array of slides available to sessionID in the startDir directory
	*/
	public static function getSlides($startDir, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		if (starts_with($startDir, "/")) {
			$startDir = substr($startDir, 1);
		}
		$url = self::_pma_api_url($sessionID)."GetFiles?sessionID=".self::_pma_q($sessionID)."&path=".self::_pma_q($startDir);
		$contents = file_get_contents($url);

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
		
		if (isset($json["Code"])) {
			throw new Exception("get_slides from $startDir resulted in: ".$json["Message"]." (keep in mind that startDir is case sensitive!)");
		} else {
			$slides = $json;
		}
		return $slides;
	}

	/**
	Get the UID (unique identifier) for a specific slide 
	*/
	public static function getUID($slideRef, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		$url = self::_pma_api_url($sessionID)."GetUID?sessionID=".self::_pma_q($sessionID)."&path=".self::_pma_q($slideRef);
		$contents = file_get_contents($url);

		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

		if (isset($json["Code"])) {
			throw new Exception("get_uid for $slideRef resulted in: ".$json["Message"]." (keep in mind that slideRef is case sensitive!)");
		} else {
			$uid = $json;
		}
		return $uid;
	}	

}

/**
Wrapper around PMA.UI JavaScript framework
*/
class UI {
	public static $_pma_ui_javascript_path = "pma.ui/";
	private static $_pma_ui_framework_embedded = false;
	private static $_pma_ui_viewport_count = 0;
	private static $_pma_ui_gallery_count = 0;
	
	/** internal helper function to prevent PMA.UI framework from being loaded more than once */
	private static function _pma_embed_pma_ui_framework() {
		if (!self::$_pma_ui_framework_embedded) {
			if (!pma::ends_with(self::$_pma_ui_javascript_path, "/")) {
				self::$_pma_ui_javascript_path .= "/";
			}
			echo "<!-- include PMA.UI script & css -->\n";
			echo "<script src='".self::$_pma_ui_javascript_path."pma.ui.view.min.js' type='text/javascript'></script>\n";
			echo "<link href='".self::$_pma_ui_javascript_path."pma.ui.view.min.css' type='text/css' rel='stylesheet'>\n";
			echo "<!-- include PMA.UI.components script & css -->\n";
			echo "<script src='".self::$_pma_ui_javascript_path."PMA.UI.components.all.min.js' type='text/javascript'></script>\n";
			echo "<link href='".self::$_pma_ui_javascript_path."PMA.UI.components.all.min.css' type='text/css' rel='stylesheet'>\n";
			self::$_pma_ui_framework_embedded = true;
		}
	}	
	
	/** output HTML code to display a single slide through a PMA.UI viewport control
		authentication against PMA.core happens through a pre-established SessionID */
	public static function embed_slide_by_sessionID($server, $slideRef, $sessionID, $options = null) {
		self::_pma_embed_pma_ui_framework();
		self::$_pma_ui_viewport_count++;
		$div_id = "pma_viewport".self::$_pma_ui_viewport_count;
		?>
		<div id="<?php echo $div_id; ?>"></div>
		<script type="text/javascript">
			// initialize the viewport
			var viewport = new PMA.UI.View.Viewport({
				caller: "PMA.PHP UI class",
				element: "#<?php echo $div_id; ?>",
				image: "<?php echo $slideRef;?>",
				serverUrls: ["<?php echo $server;?>"],
				sessionID: "<?php echo $sessionID;?>",
				},
				function () {
					console.log("Success!");
				},
				function () {
					console.log("Error! Check the console for details.");
				});
		</script>
		<?
		return $div_id;
	}

	/** output HTML code to display a single slide through a PMA.UI viewport control 
		authentication against PMA.core happens in real-time through the provided $username and $password credentials
		Note that the username and password and NOT rendered in the HTML output (authentication happens in PHP on the server-side).
	*/
	public static function embed_slide_by_username($server, $slideRef, $username, $password = "", $options = null) {
		$session = Core::connect($server, $username, $password);
		return self::embed_slide_by_sessionID($server, $slideRef, $session, $options);
	}

}

/**
CoreAdmin class. Interface to PMA.core for administrative operations. Does NOT apply to PMA.start / PMA.core.lite
*/
class CoreAdmin {
	
	/**
	Define a new user in PMA.core
	*/
	public static function AddUser($ASessionID, $login, $firstName, $lastName, $email, $password, $canAnnotate = false, $isAdmin = false, $isSuspended = false) {
		$url = Core::_pma_url($ASessionID)."admin?singleWsdl";
		print($url);
		$client = new \SoapClient($url);

		$create_user = $client->CreateUser(
            array(
                "sessionID" => $ASessionID,
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
	}

}

/**
Helper class. Developers should never access this class directly (but may recognize some helper functions they wrote themselves once upon a time)
*/
class PMA {
	const version = "2.0.0.4";

	public static function ends_with($wholestring, $suffix)
	{
		return substr($wholestring, - strlen($suffix)) == $suffix ? true : false;
	}

	public static function starts_with($wholestring, $prefix)
	{
		return substr($wholestring, 0, strlen($prefix)) == $prefix ? true : false;
	}
}

