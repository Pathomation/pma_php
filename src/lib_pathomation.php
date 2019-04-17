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
	public static $_pma_sessions = [];
	public static $_pma_slideinfos = [];
	public static $_pma_pmacoreliteURL = "http://localhost:54001/";
	public static $_pma_pmacoreliteSessionID = "SDK.PHP";
	private static $_pma_usecachewhenretrievingtiles = true;
	public static $_pma_amount_of_data_downloaded = array("SDK.PHP" => 0);

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
		
		$url = PMA::_pma_join($pmacoreURL, "api/json/IsLite");
		$contents = "";
		
		try {
			@$contents = file_get_contents($url);
		} catch (Exception $e) {
			// this happens when NO instance of PMA.core is detected
			echo "Unable to detect PMA.core(.lite)";
			return null;
		}
		
		if (strlen($contents) < 1) {
			//echo "Unable to detect PMA.core(.lite)";
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
		return PMA::_pma_join($url, "api/json/");
	}
    
	# end internal module helper variables and functions


	/**
	See if there's a PMA.core.lite or PMA.core instance running at $pmacoreURL
	*/
	public static function isLite($pmacoreURL = null)
	{
		if ($pmacoreURL == null) {
			$pmacoreURL = self::$_pma_pmacoreliteURL;
		}
		return self::_pma_is_lite($pmacoreURL);
	}

	/**
	Get version info from PMA.core instance running at $pmacoreURL
	*/
	public static function getVersionInfo($pmacoreURL = null)
	{
		if ($pmacoreURL == null) {
			$pmacoreURL = self::$_pma_pmacoreliteURL;
		}
		// purposefully DON'T use helper function _pma_api_url() here:
		// why? because GetVersionInfo can be invoked WITHOUT a valid SessionID; _pma_api_url() takes session information into account
		$url = PMA::_pma_join($pmacoreURL, "api/json/GetVersionInfo");
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
			if (self::_pma_is_lite()) {
				// no point authenticating localhost / PMA.core.lite
				return self::$_pma_pmacoreliteSessionID;
			} else {
				return null;
			}
		}
		
		// purposefully DON'T use helper function _pma_api_url() here:
		// why? Because_pma_api_url() takes session information into account (which we don't have yet)
		$url = PMA::_pma_join($pmacoreURL, "api/json/authenticate?caller=SDK.PHP");
		if ($pmacoreUsername != "") {
			$url .= "&username=".PMA::_pma_q($pmacoreUsername);
		}
		if ($pmacorePassword != "") {
			$url .= "&password=".PMA::_pma_q($pmacorePassword);
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
		
		$url = self::_pma_api_url($sessionID)."DeAuthenticate?sessionID=".PMA::_pma_q($sessionID);
		try {
			$contents = @file_get_contents($url);
		} catch (Exception $ex) {
			throw new Exception("Unable to disconnect");
			$contents = "";
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
		$url = self::_pma_api_url($sessionID)."GetRootDirectories?sessionID=".PMA::_pma_q($sessionID);
		try {
			$contents = @file_get_contents($url);
		} catch (Exception $ex) {
			throw new Exception("Unable to retrieve root-directories through $sessionID");
			$contents = "";
		}

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
		$url = self::_pma_api_url($sessionID)."GetDirectories?sessionID=".PMA::_pma_q($sessionID)."&path=".PMA::_pma_q($startDir);
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
			if ($startDir == "/") {
				foreach (self::getRootDirectories($sessionID) as $dir) {
					$nonEmtptyDir = self::getFirstNonEmptyDirectory($dir, $sessionID);
					if ($nonEmtptyDir !== null) {
						return $nonEmtptyDir;
					}
				}
			} else {
				foreach (self::getDirectories($startDir, $sessionID) as $dir) {
					$nonEmtptyDir = self::getFirstNonEmptyDirectory($dir, $sessionID);
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
	public static function getSlides($startDir, $sessionID = null, $recursive = false)
	{
		$sessionID = self::_pma_session_id($sessionID);
		if (pma::starts_with($startDir, "/")) {
			$startDir = substr($startDir, 1);
		}
		$url = self::_pma_api_url($sessionID)."GetFiles?sessionID=".PMA::_pma_q($sessionID)."&path=".PMA::_pma_q($startDir);
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
		
		if ( ((gettype($recursive) == "boolean") && ($recursive == True)) 
			|| ((gettype($recursive) == "integer") && ($recursive > 0)) ) {		
			foreach (self::getDirectories($startDir, $sessionID) as $dir) {
				if (gettype($recursive) == "boolean") {
					$new_slides = self::getSlides($dir, $sessionID, $recursive);
					if (is_array($slides)) {
						if (is_array($new_slides)) {
							$slides = array_merge($slides, $new_slides);
						}
					} else {
						if (is_array($new_slides)) {
							$slides = $new_slides;
						}
					}
				} elseif (gettype($recursive) == "integer") {
					$new_slides = self::getSlides($dir, $sessionID, $recursive - 1);
					if (is_array($slides)) {
						if (is_array($new_slides)) {
							$slides = array_merge($slides, $new_slides);
						}
					} else {
						if (is_array($new_slides)) {
							$slides = $new_slides;
						}
					}
				}
			}
		}
		
		return $slides;
	}

	/**
	Get the UID (unique identifier) for a specific slide 
	*/
	public static function getUID($slideRef, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		$url = self::_pma_api_url($sessionID)."GetUID?sessionID=".PMA::_pma_q($sessionID)."&path=".PMA::_pma_q($slideRef);
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

	/**
	Get the fingerprint for a specific slide 
	*/
	public static function getFingerprint($slideRef, $strict = false, $sessionID = null) 
	{
		$sessionID = self::_pma_session_id($sessionID);
		$url = self::_pma_api_url($sessionID)."GetFingerprint?sessionID=".PMA::_pma_q($sessionID)."&strict=".($strict ? "true": "false")."&pathOrUid=".PMA::_pma_q($slideRef);

		$contents= file_get_contents($url);
		
		$json = json_decode($contents, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}
		
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
		if (isset($json["Code"])) {
			throw new Exception("get_fingerprint on  " + $slideRef + " resulted in: " + $json["Message"] + " (keep in mind that slideRef is case sensitive!)");
		} else {
			$fingerprint = $json;
		}
		return $fingerprint;
	}
		
	/**
	Return raw image information in the form of nested dictionaries
	*/
	public static function getSlideInfo($slideRef, $sessionID = null)
	{
		$sessionID = self::_pma_session_id($sessionID);
		$infos = Core::GetSlidesInfo(array($slideRef), $sessionID);
		return $infos[$slideRef];
	}

	public static function GetSlidesInfo($slideRefs, $sessionID = null) {
		if (!is_array($slideRefs)) {
			$slideRefs = array($slideRefs);
		}
		$sessionID = self::_pma_session_id($sessionID);

		
		$slideRefs_new = array();
		foreach ($slideRefs as $sl) {
			$sl_new = $sl;
			if (PMA::starts_with($sl_new, "/")) {
				$sl_new = substr($sl_new, 1);
			}
			if (!isset(self::$_pma_slideinfos[$sessionID][$sl_new])) {
				array_push($slideRefs_new, $sl_new);
			}			
		}
		
		if (count($slideRefs_new) > 0) {
			$url = Core::_pma_url($sessionID)."api/json/GetImagesInfo";
			$jsonData = array(
				"sessionID"=> $sessionID,
				"pathOrUids"=> $slideRefs_new
			);
			$ret_val = PMA::_pma_send_post_request($url, $jsonData);

			$json = json_decode($ret_val, true);
			if (isset($json["d"])) {
				$json = $json["d"];
			}

			self::$_pma_amount_of_data_downloaded[$sessionID] += strlen($ret_val);
			if (isset($json["Code"])) {
				throw new Exception("ImageInfos to " + $slideRefs + " resulted in: " + $json["Message"] + " (keep in mind that slideRef is case sensitive!)");
			} else {
				if (count($json) > 0) {
					foreach ($json as $el) {
						self::$_pma_slideinfos[$sessionID][$el["Filename"]] = $el;
						self::$_pma_slideinfos[$sessionID][$el["UID"]] = $el;
					}
				}
			}
		}

		$ret_value = array();
		foreach ($slideRefs as $sl) {
			$ret_value[$sl] = self::$_pma_slideinfos[$sessionID][$sl];
		}
		
		return $ret_value;
	}
	/**
	Determine the maximum zoomlevel that still represents an optical magnification
	*/
	public static function getMaxZoomlevel($slideRef, $sessionID = null) {
		$info = self::getSlideInfo($slideRef, $sessionID);
		if ($info == null) {
			throw new Exception("Unable to get information for".$slideRef." from ".$sessionID);
			return 0;
		} else {
			/*if ("MaxZoomLevel" in info) {
				try:
					return int(info["MaxZoomLevel"])
				except:
					print("Something went wrong consulting the MaxZoomLevel key in info{} dictionary; value =", info["MaxZoomLevel"])
					return 0
			} else {
			try:
				return int(info["NumberOfZoomLevels"])
			except:
				print("Something went wrong consulting the NumberOfZoomLevels key in info{} dictionary; value =", info["NumberOfZoomLevels"])
				return 0
			}*/
		}
	}
	
	/**
	Obtain a list with all zoomlevels, starting with 0 and up to and including max_zoomlevel
	Use min_number_of_tiles argument to specify that you're only interested in zoomlevels that include at lease a given number of tiles
	*/
	public static function getZoomlevelsList($slideRef, $sessionID = None, $minNumberOfTiles = 0) {
		//return sorted(list(get_zoomlevels_dict($slideRef, $sessionID, $minNumberOfTiles).keys()))
	}
	
	/**
	Obtain a dictionary with the number of tiles per zoomlevel.
	Information is returned as (x, y, n) tupels per zoomlevel, with 
		x = number of horizontal tiles, 
		y = number of vertical tiles, 
		n = total number of tiles at specified zoomlevel (x * y)
	Use min_number_of_tiles argument to specify that you're only interested in zoomlevels that include at lease a given number of tiles
	*/
	public static function getZoomlevelsDict($slideRef, $sessionID = null, $minNumberOfTiles = 0) {
	/*zoomlevels = list(range(0, get_max_zoomlevel(slideRef, sessionID) + 1))
	dimensions = [ get_number_of_tiles(slideRef, z, sessionID) for z in zoomlevels if get_number_of_tiles(slideRef, z, sessionID)[2] > min_number_of_tiles]
	d = dict(zip(zoomlevels[-len(dimensions):], dimensions))
	
	
	return d*/
	}
	
	
	/**
	Retrieve the physical dimension in terms of pixels per micrometer.
	When zoomlevel is left to its default value of None, dimensions at the highest zoomlevel are returned 
	(in effect returning the "native" resolution at which the slide was registered)
	*/
	public static function getPixelsPerMicrometer($slideRef, $zoomlevel = null, $sessionID = null) {

		$maxZoomLevel = self::getMaxZoomlevel($slideRef, $sessionID);
		$info = self::getSlideInfo($slideRef, $sessionID);
		$xppm = $info["MicrometresPerPixelX"];
		$yppm = $info["MicrometresPerPixelY"];
	
	/*if (zoomlevel is None or zoomlevel == maxZoomLevel):
		return (float(xppm), float(yppm))
	else:
		factor = 2 ** (zoomlevel - maxZoomLevel)
		return (float(xppm) / factor, float(yppm) / factor)		*/
	}
	
	/**
	Get the total dimensions of a slide image at a given zoomlevel
	*/
	public static function getPixelDimensions($slideRef, $oomlevel = null, $sessionID = null) {
	
		$maxZoomLevel = self::getMaxZoomlevel($slideRef, $sessionID);
		$info = self::getSlideInfo($slideRef, $sessionID);
		/*if (zoomlevel is None or zoomlevel == maxZoomLevel) {
			return (int(info["Width"]), int(info["Height"]))
		} else {
		factor = 2 ** (zoomlevel - maxZoomLevel)
		}
		return (int(info["Width"]) * factor, int(info["Height"]) * factor)
		*/
	}
	
	/**
	Determine the number of tiles needed to reconstitute a slide at a given zoomlevel
	*/
	public static function getNumberOfTiles($slideRef, $zoomlevel = null, $sessionID = null) {
	
		$pixels = self::getPixelDimensions($slideRef, $zoomlevel, $sessionID);
		$sz = self::getTileSize($sessionID);
		$xtiles = int(ceil($pixels[0] / $sz[0]));
		$ytiles = int(ceil($pixels[1] / $sz[0]));
		$ntiles = $xtiles * $ytiles;
		return array(xtiles, ytiles, ntiles);
	}
	
	/**
	Determine the physical dimensions of the sample represented by the slide.
	This is independent of the zoomlevel: the physical properties don't change because the magnification changes
	*/
	public static function getPhysicalDimensions($slideRef, $sessionID = null) {
		$ppmData = self::getPixelsPerMicrometer($slideRef, $sessionID);
		$pixelSz = self::getPixelDimensions($slideRef, $sessionID);
		return array($pixelSz[0] * $ppmData[0], $pixelSz[1] * $ppmData[1]);
	}
	
	/**
	Number of fluorescent channels for a slide (when slide is brightfield, return is always 1)
	*/
	public static function getNumberOfChannels($slideRef, $sessionID = null) {
		$info = self::getSlideInfo($slideRef, $sessionID);
		$channels = $info["TimeFrames"][0]["Layers"][0]["Channels"];
		return count($channels);
	}

	/**	
	Number of (z-stacked) layers for a slide
	*/
	public static function getNumberOfLayers($slideRef, $sessionID = null) {
		$info = self::getSlideInfo($slideRef, $sessionID);
		$layers = $info["TimeFrames"][0]["Layers"];
		return count($layers);
	}
		
	public static function getNumberOfZStackLayers($slideRef, $sessionID = null) {
		return self::getNumberOfLayers($slideRef, $sessionID);
	}

	/** 
	Determine whether a slide is a fluorescent image or not
	*/
	public static function isFluorescent($slideRef, $sessionID = null) {
		return self::getNumberOfChannels($slideRef, $sessionID) > 1;
	}

	/**
	Determine whether a slide contains multiple (stacked) layers or not
	*/
	public static function isMultiLayer($slideRef, $sessionID = null) {
		
		return self::getNumberOfLayers($slideRef, $sessionID) > 1;
	}
	
	/** 
	Determine whether a slide is a z-stack or not
	*/
	public static function isZStack($slideRef, $sessionID = null) {
	
		return self::isMultiLayer($slideRef, $sessionID);
	}
	
	/**
	Get the magnification represented at a certain zoomlevel
	*/
	public static function getMagnification($slideRef, $zoomlevel = null, $exact = False, $sessionID = null) {
		$ppm = self::getPixelsPerMicrometer($slideRef, $zoomlevel, $sessionID)[0];
		if ($ppm > 0) {
			if ($exact === True) {
				return round(40 / ($ppm / 0.25));
			} else {
				return round(40 / round($ppm / 0.25));
			}
		} else {
			return 0;
		}
	}
	
	/**
	Get the URL that points to the barcode (alias for "label") for a slide
	*/
	public static function getBarcodeUrl($slideRef, $sessionID = null) {		
		$sessionID = Core::_pma_session_id($sessionID);
		$url = (Core::_pma_url($sessionID)."barcode"
			."?SessionID=".pma::_pma_q($sessionID)
			."&pathOrUid=".pma::_pma_q($slideRef));
		return $url;
	}

	/**
	Get the barcode (alias for "label") image for a slide
	*/
	public static function getBarcodeImage($slideRef, $sessionID = null) {
		$sessionID = Core::_pma_session_id($sessionID);
		$img = imagecreatefromjpeg(self::getBarcodeUrl($slideRef, $sessionID));
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
		return $img;
	}

	/**
	Get the URL that points to the label for a slide
	*/
	public static function getLabelUrl($slideRef, $sessionID = null) {
		
		return getBarcodeUrl($slideRef, $sessionID);
	}
	
	/**
	Get the label image for a slide
	*/
	public static function getLabelImage($slideRef, $sessionID = null) {
		$sessionID = pma::_pma_session_id($sessionID);
		$img = imagecreatefromjpeg(self::getLabelUrl($slideRef, $sessionID));
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
		return $img;
	}
	
	/**
	Get the URL that points to the thumbnail for a slide
	*/
	public static function getThumbnailUrl($slideRef, $sessionID = null) {
		$sessionID = Core::_pma_session_id($sessionID);
		$url = (Core::_pma_url($sessionID) . "thumbnail"
			. "?SessionID=" . pma::_pma_q($sessionID)
			. "&pathOrUid=" . pma::_pma_q($slideRef));
		return $url;
	}

	/**
	Get the thumbnail image for a slide
	*/
	public static function getThumbnailImage($slideRef, $sessionID = null) {		
		$sessionID = Core::_pma_session_id($sessionID);
		$img = imagecreatefromjpeg(self::getThumbnailUrl($slideRef, $sessionID));
		self::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
		return $img;
	}		
}

/**
Wrapper around PMA.UI JavaScript framework
*/
class UI {
	public static $_pma_start_ui_javascript_path = "http://localhost:54001/Scripts/pmaui/";
	public static $_pma_ui_javascript_path = "pma.ui/";
	private static $_pma_ui_framework_embedded = false;
	private static $_pma_ui_viewport_count = 0;
	private static $_pma_ui_viewports = [];
	private static $_pma_ui_gallery_count = 0;
	private static $_pma_ui_galleries = [];
	private static $_pma_ui_loader_count = 0;
	private static $_pma_ui_loaders = [];
	
	/** internal helper function to prevent PMA.UI framework from being loaded more than once */
	private static function _pma_embed_pma_ui_framework($sessionID) {
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
			echo "<script>var pma_ui_context = new PMA.UI.Components.Context({ caller: 'PMA.PHP UI class' });</script>";
			self::$_pma_ui_framework_embedded = true;
		}
	}	
	
	/** output HTML code to display a single slide through a PMA.UI viewport control
		authentication against PMA.core happens through a pre-established SessionID */
	public static function embedSlideBySessionID($server, $slideRef, $sessionID, $options = null) {
		self::_pma_embed_pma_ui_framework($sessionID);
		self::$_pma_ui_viewport_count++;
		$viewport_id = "pma_viewport".self::$_pma_ui_viewport_count;
		self::$_pma_ui_viewports[] = $viewport_id;
		?>
		<div id="<?php echo $viewport_id; ?>"></div>
		<script type="text/javascript">
			// initialize the viewport
			var <?php echo $viewport_id; ?> = new PMA.UI.View.Viewport({
				caller: "PMA.PHP UI class",
				element: "#<?php echo $viewport_id; ?>",
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
		<?php
		return $viewport_id;
	}

	/** output HTML code to display a single slide through a PMA.UI viewport control 
		authentication against PMA.core happens in real-time through the provided $username and $password credentials
		Note that the username and password and NOT rendered in the HTML output (authentication happens in PHP on the server-side).
	*/
	public static function embedSlideByUsername($server, $slideRef, $username, $password = "", $options = null) {
		$session = Core::connect($server, $username, $password);
		return self::embedSlideBySessionID($server, $slideRef, $session, $options);
	}

	/** output HTML code to display a gallery that shows all thumbnails that exist in a specific folder hosted by the specified PMA.core instance 
		authentication against PMA.core happens through a pre-established SessionID */
    public static function embedGalleryBySessionID($server, $path, $sessionID, $options = null) {
		self::_pma_embed_pma_ui_framework($sessionID);
		self::$_pma_ui_gallery_count++;
		$gallery_id = "pma_gallery".self::$_pma_ui_gallery_count;
		self::$_pma_ui_galleries[] = $gallery_id;
		?>
		<div id="<?php echo $gallery_id; ?>"></div>
		<script type="text/javascript">
			new PMA.UI.Authentication.SessionLogin(pma_ui_context, [{ serverUrl: "<?php echo $server; ?>", sessionId: "<?php echo $sessionID; ?>" }]);
			
			// create a gallery that will display the contents of a directory
			var <?php echo $gallery_id; ?> = new PMA.UI.Components.Gallery(pma_ui_context, {
				element: "#<?php echo $gallery_id; ?>",
				thumbnailWidth: 200,
				thumbnailHeight: 150,
				mode: "<?php echo (isset($options) && $options != null) ?  (isset($options["mode"]) ? $options["mode"]: "horizontal"): "horizontal"; ?>",
				showFileName: true,
				showBarcode: true,
				barcodeRotation: 180,
				filenameCallback: function (path) {
					// show the filename without extension
					return path.split('/').pop().split('.')[0];
				}
			});

			// load the contents of a directory
			<?php echo $gallery_id; ?>.loadDirectory("<?php echo $server; ?>", "<?php echo $path; ?>");
		</script>
		<?php
		return $gallery_id;
	}
	
	/** output HTML code to display a gallery that shows all thumbnails that exist in a specific folder hosted by the specified PMA.core instance 
		authentication against PMA.core happens in real-time through the provided $username and $password credentials
		Note that the username and password and NOT rendered in the HTML output (authentication happens in PHP on the server-side).
	*/
	public static function embedGalleryByUsername($server, $path, $username, $password = "", $options = null) {
		$session = Core::connect($server, $username, $password);
		return self::embedGalleryBySessionID($server, $path, $session, $options);
	}

	/** output HTML code to couple an earlier instantiated PMA.UI gallery to a PMA.UI viewport. The PMA.UI viewport can be instantiated earlier, or not at all */	
	public static function linkGalleryToViewport($galleryDiv, $viewportDiv) {
		// verify the validity of the $galleryDiv argument
		if (in_array($galleryDiv, self::$_pma_ui_viewports)) {
			throw new \BadMethodCallException("$galleryDiv is not a PMA.UI gallery (it's actually a viewport; did you switch the arguments up?)");
		}
		if (!in_array($galleryDiv, self::$_pma_ui_galleries)) {
			throw new \BadMethodCallException("$galleryDiv is not a valid PMA.UI gallery container");
		}

		// verify the validity of the $viewportDiv argument
		if (in_array($viewportDiv, self::$_pma_ui_galleries)) {
			throw new \BadMethodCallException("$viewportDiv is not a PMA.UI viewport (it's actually a gallery; did you switch the arguments up?)");
		}
		
		self::$_pma_ui_loader_count++;
		$loader_id = "pma_slideLoader".self::$_pma_ui_loader_count;
		self::$_pma_ui_loaders[] = $loader_id;
		
		if (!in_array($viewportDiv, self::$_pma_ui_viewports)) {
			// viewport container doesn't yet exist, but this doesn't have to be a showstopper; just create it on the fly
			self::$_pma_ui_viewports[] = $viewportDiv;
			self::$_pma_ui_viewport_count++;
			?>
			<div id="<?php echo $viewportDiv; ?>"></div>
		<?php
		}
		?>
		<script>
        // create an image loader that will allow us to load images easily
        var <?php echo $loader_id; ?> = new PMA.UI.Components.SlideLoader(pma_ui_context, {
            element: "#<?php echo $viewportDiv; ?>",
            theme: PMA.UI.View.Themes.Default,
            overview: {
                collapsed: false
            },
            // the channel selector is only displayed for images that have multiple channels
            channels: {
                collapsed: false
            },
            // the barcode is only displayed if the image actually contains one
            barcode: {
                collapsed: false,
                rotation: 180
            },
            loadingBar: true,
            snapshot: true,
            digitalZoomLevels: 2,
            scaleLine: true,
            filename: true
        });

        // listen for the slide selected event to load the selected image when clicked
        <?php echo $galleryDiv; ?>.listen(PMA.UI.Components.Events.SlideSelected, function (args) {
            // load the image with the image loader
            <?php echo $loader_id; ?>.load(args.serverUrl, args.path);
        });
		</script>
		<?php
	}

}

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
		print($url);
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
	
	public static function AddS3RootDirectory($AdmSessionID, $s3accessKey, $s3secretKey, $alias, $s3path, $description = "Root dir created through lib_php", $isPublic = False, $isOffline = False) {
		if (Core::$_pma_pmacoreliteSessionID == $AdmSessionID) {
			throw new \BadMethodCallException("PMA.start doesn't support AddS3RootDirectory()");
		}
		
		$url = Core::_pma_url($AdmSessionID)."admin/json/CreateAmazonS3RootDirectory";

		$jsonData = array(
		 "sessionID" => $AdmSessionID,
		  "rootDirectory"=> array(
			"AccessKey"=> $s3accessKey,
			"SecretKey"=> $s3secretKey,
			"Alias"=> $alias,
			"Description"=> $description,
			"Offline"=> $isOffline,
			"Public"=> $isPublic,
			"Path"=> $s3path
			)
		);
		 
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

/**
Wraps around PMA.control's API
*/
class Control {
	const version = PMA::version;
	
	const pma_session_role_supervisor = 1;
	const pma_session_role_trainee = 2;
	const pma_session_role_observer = 3;

	const pma_interaction_mode_locked = 0;
	const pma_interaction_mode_test_active = 1;
	const pma_interaction_mode_review = 2;
	const pma_interaction_mode_consensus_view = 3;
	const pma_interaction_mode_browse = 4;
	const pma_interaction_mode_board = 5;
	const pma_interaction_mode_consensus_score_edit = 6;
	const pma_interaction_mode_self_review = 7;
	const pma_interaction_mode_self_test = 8;
	const pma_interaction_mode_hidden = 9;
	const pma_interaction_mode_clinical_information_edit = 10;

	
	/**
	Get version info from PMA.control instance running at $pmacontrolURL
	*/
	public static function getVersionInfo($pmacontrolURL)
	{
		// purposefully DON'T use helper function _pma_api_url() here:
		// why? because GetVersionInfo can be invoked WITHOUT a valid SessionID; _pma_api_url() takes session information into account
		$url = PMA::_pma_join($pmacontrolURL, "api/version");
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
	Retrieve a list of currently defined training sessions in PMA.control
	*/
	private static function _pma_get_sessions($pmacontrolURL, $pmacoreSessionID) {
		$url = PMA::_pma_join($pmacontrolURL, "api/Sessions?sessionID=".PMA::_pma_q($pmacoreSessionID));
		try {
			@$r = file_get_contents($url);
			// r = pma._pma_http_get(url, headers)
		} catch (Exception $e) {
			echo "Something went wrong; could not get $url\n";
			return null;
		}
		
		$json = json_decode($r, true);
		if (isset($json["d"])) {
			$json = $json["d"];
		}

		return $json;
	}

	/**
	Helper method to convert a JSON representation of a PMA.control training session to a proper Python-esque structure
	*/
	private static function _pma_format_session_properly($sess) {
		$sess_data = array(
			"Id" => $sess["Id"],
			"Title" => $sess["Title"],
			"LogoPath" => $sess["LogoPath"],
			"StartsOn" => $sess["StartsOn"],
			"EndsOn" => $sess["EndsOn"],
			"ProjectId" => $sess["ProjectId"],
			"State" => $sess["State"],
			"CaseCollections" => array(),
			"NumberOfParticipants" => count($sess["Participants"])
		);
		foreach ($sess["CaseCollections"] as $coll) {
			$sess_data["CaseCollections"][$coll["Id"]] = $coll["Title"];
		}
		return $sess_data;
	}
	
	/**
	Get a list of all participants registered across all sessions, include the Role they play
	*/
	public static function getAllParticipants($pmacontrolURL, $pmacoreSessionID) 
	{
		$full_sessions = self::_pma_get_sessions($pmacontrolURL, $pmacoreSessionID);
		$user_dict = array();
		foreach ($full_sessions as $sess) {
			$s = self::_pma_format_session_properly($sess);
			foreach ($sess["Participants"] as $part) {
				if (!(isset($user_dict[$part["User"]]))) {
					$user_dict[$part["User"]] = array();
				}
				$user_dict[$part["User"]][$sess["Id"]] = $s;
				$user_dict[$part["User"]][$sess["Id"]]["Role"] = $part["Role"];
			}
		}
		return $user_dict;
	}
	public static function getSessionTitles($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID) {
		return array_values(self::getSessionTitlesAssoc($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID));
	}
	
	/**
	Retrieve (training) sessions (possibly filtered by project ID), return a dictionary of session IDs and titles
	*/
	public static function getSessionTitlesAssoc($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID) {
		$dct = array();
		$all = self::_pma_get_sessions($pmacontrolURL, $pmacoreSessionID);
		foreach ($all as $sess) {
			if ($pmacontrolProjectID == null) {
				$dct[$sess["Id"]] = $sess["Title"];
			} elseif ($pmacontrolProjectID == $sess["ProjectId"]) {
				$dct[$sess["Id"]] = $sess["Title"];
			}
		}
		return $dct;
	}
}

/**
Helper class. Developers should never access this class directly (but may recognize some helper functions they wrote themselves once upon a time)
*/
class PMA {
	/** returns the current version of the library (2.0.0.32) */
	const version = "2.0.0.32";

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
		//Execute the request
		$result = curl_exec($ch);
		
		if (curl_error($ch)) {
			trigger_error('Curl Error:' . curl_error($ch));
		}
		
		curl_close ($ch);
		
		return $result;		
	}
	
}
