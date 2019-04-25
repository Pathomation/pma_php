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
 