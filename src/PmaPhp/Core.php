<?php

/**
 * @package Pathomation\PmaPhp
 **/

/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
 */

namespace Pathomation\PmaPhp;

use \Exception as Exception;

/**
Class that wraps around the free PMA.core.lite (the server component of PMA.start), as well as its commercial variant; the PMA.core product
 */
class Core
{
    public static $__version__ = PMA::version;


	public static $annotation_source_format_pathomation = 0;
	public static $annotation_source_format_native = 1;
	public static $annotation_source_format_visiopharm = 2;
	public static $annotation_source_format_indicalabs = 3;
	public static $annotation_source_format_aperio = 4;
	public static $annotation_source_format_definiens = 4;
	public static $annotation_source_format_xml = 4;

	public static $annotation_target_format_pathomation = 2;
	public static $annotation_target_format_visiopharm = 0;
	public static $annotation_target_format_indicalabs = 1;
	public static $annotation_target_format_aperio = 3;
	public static $annotation_target_format_definiens = 3;
	public static $annotation_target_format_xml = 3;
	public static $annotation_target_format_csv = 4;

    # internal module helper variables and functions
    public static $_pma_sessions = [];
    public static $_pma_usernames = [];
    public static $_pma_slideinfos = [];
    public static $_pma_pmacoreliteURL = "http://localhost:54001/";
    public static $_pma_pmacoreliteSessionID = "SDK.PHP";
    private static $_pma_usecachewhenretrievingtiles = true;
    public static $_pma_debug = false;
    private static $_previous_downloaded = -1;
    public static $_pma_amount_of_data_downloaded = array("SDK.PHP" => 0);


    /** Internal use only */
    public static function _pma_session_id($sessionID = null)
    {
        if ($sessionID === null) {
            // if the sessionID isn't specified, maybe we can still recover it somehow
            return Core::_pma_first_session_id();
        } else {
            // nothing to do in this case; a SessionID WAS passed along, so just continue using it
            return $sessionID;
        }
    }

    /** Internal use only */
    private static function _pma_first_session_id()
    {
        // do we have any stored sessions from earlier login events?
        if (count(Core::$_pma_sessions) > 0) {
            // yes we do! This means that when there's a PMA.core active session AND PMA.core.lite version running,
            // the PMA.core active will be selected and returned
            return current(array_keys(Core::$_pma_sessions));
        } else {
            // ok, we don't have stored sessions; not a problem per se...
            if (Core::_pma_is_lite()) {
                if (!isset(Core::$_pma_slideinfos[Core::$_pma_pmacoreliteSessionID])) {
                    Core::$_pma_slideinfos[Core::$_pma_pmacoreliteSessionID] = [];
                }
                return Core::$_pma_pmacoreliteSessionID;
            } else {
                // no stored PMA.core sessions found NOR PMA.core.lite
                return null;
            }
        }
    }

    /**
    returns the value of $_pma_pmacoreliteSessionID
     */
    public static function getPmacoreliteSessionID()
    {
        return Core::$_pma_pmacoreliteSessionID;
    }

    public static function setDebugFlag($flag)
    {
        Core::$_pma_debug = $flag === true;
        if (Core::$_pma_debug === true) {
            echo "Debug flag enabled. You will receive extra feedback and messages from pma_php (like this one)";
        }
    }

    /** Internal use only */
    public static function _pma_url($sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);

        if ($sessionID === null) {
            // sort of a hopeless situation; there is no URL to refer to
            return null;
        } elseif ($sessionID == Core::$_pma_pmacoreliteSessionID) {
            return Core::$_pma_pmacoreliteURL;
        } else {
            // assume sessionID is a valid session; otherwise the following will generate an error
            if (isset(Core::$_pma_sessions[$sessionID])) {
                $url = Core::$_pma_sessions[$sessionID];
                if (!PMA::ends_with($url, "/")) {
                    $url .= "/";
                }
                return $url;
            } else {
                throw new Exception("Invalid sessionID: " . $sessionID);
            }
        }
    }

    /**
	Set pma_host for sessionID
     */
    public static function storePmaHostForSessionID($pma_host, $sessionID)
    {
        if (!isset(Core::$_pma_sessions[$sessionID])) {
            Core::$_pma_sessions[$sessionID] = $pma_host;
            Core::$_pma_usernames[$sessionID] = '';
            Core::$_pma_slideinfos[$sessionID] = [];
            Core::$_pma_amount_of_data_downloaded[$sessionID] = 0;
        }
    }

    /** Internal use only */
    private static function _pma_is_lite($pmacoreURL = null)
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }

        $url = PMA::_pma_join($pmacoreURL, "api/json/IsLite");
        $contents = "";

        try {
            $contents = @file_get_contents($url);
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

        return $json == true;
    }

    /** Internal use only */
    private static function _pma_api_url($sessionID = None)
    {
        // let's get the base URL first for the specified session
        $url = Core::_pma_url($sessionID);
        if ($url == null) {
            // sort of a hopeless situation; there is no URL to refer to
            return null;
        }
        // remember, _pma_url is guaranteed to return a URL that ends with "/"
        return PMA::_pma_join($url, "api/json/");
    }

    /** Internal use only */
    private static function _pma_query_url($sessionID = None)
    {
        // let's get the base URL first for the specified session
        $url = Core::_pma_url($sessionID);
        if ($url == null) {
            // sort of a hopeless situation; there is no URL to refer to
            return null;
        }
        // remember, _pma_url is guaranteed to return a URL that ends with "/"
        return PMA::_pma_join($url, "query/json/");
    }

    /** Internal use only */
    private static function _pma_transfer_url($sessionID = None)
    {
        // let's get the base URL first for the specified session
        $url = Core::_pma_url($sessionID);
        if ($url == null) {
            // sort of a hopeless situation; there is no URL to refer to
            return null;
        }
        // remember, _pma_url is guaranteed to return a URL that ends with "/"
        return PMA::_pma_join($url, "transfer/");
    }

    # end internal module helper variables and functions


    /**
    See if there's a PMA.core.lite or PMA.core instance running at $pmacoreURL
     */
    public static function isLite($pmacoreURL = null)
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }
        return Core::_pma_is_lite($pmacoreURL);
    }

    /**
    Get version info from PMA.core instance running at $pmacoreURL
     */
    public static function getVersionInfo($pmacoreURL = null)
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }
        // purposefully DON'T use helper function _pma_api_url() here:
        // why? because GetVersionInfo can be invoked WITHOUT a valid SessionID; _pma_api_url() takes session information into account
        $url = PMA::_pma_join($pmacoreURL, "api/json/GetVersionInfo");
        if (Core::$_pma_debug == true) {
            echo $url . PHP_EOL;
        }
        $contents = "";
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            return null;
        }

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        if (substr($json, 0, strlen("3.0")) === "3.0") {
            $revision = Core::getBuildRevision($pmacoreURL);
            if (isset($revision)) {
                $json .= "." . $revision;
            }
        }

        return $json;
    }

    /**
    Get build revision info from PMA.core instance running at $pmacoreURL
     */
    public static function getBuildRevision($pmacoreURL = null)
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }

        $url = PMA::_pma_join($pmacoreURL, "api/json/GetBuildRevision");
        if (Core::$_pma_debug == true) {
            echo $url . PHP_EOL;
        }
        $contents = "";
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            return null;
        }

        $json = json_decode($contents, true);
        return $json;
    }

    public static function getAPIVersion($pmacoreURL = null)
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }
        $url = PMA::_pma_join($pmacoreURL, "api/json/GetAPIVersion");
        if (Core::$_pma_debug == true) {
            echo $url . PHP_EOL;
        }
        $contents = "";
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $e) {
            return null;
        }

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        return $json;
    }

    public static function getAPIVersionString($pmacoreURL = null)
    {
        $apiVersion = Core::getAPIVersion($pmacoreURL);
        if (!is_null($apiVersion) && is_array($apiVersion)) {
            return implode(".", $apiVersion);
        }

        return null;
    }

	public static function registerSession($pmacoreURL, $sessionID) {
            Core::$_pma_sessions[$sessionID] = $pmacoreURL;
            Core::$_pma_usernames[$sessionID] = "";
            Core::$_pma_slideinfos[$sessionID] = [];
	}
	
    /**
    Attempt to connect to PMA.core instance; success results in a SessionID
     */
    public static function connect($pmacoreURL = null, $pmacoreUsername = "", $pmacorePassword = "")
    {
        if ($pmacoreURL == null) {
            $pmacoreURL = Core::$_pma_pmacoreliteURL;
        }

        if ($pmacoreURL == Core::$_pma_pmacoreliteURL) {
            if (Core::_pma_is_lite()) {
                Core::$_pma_sessions[Core::$_pma_pmacoreliteSessionID] = $pmacoreURL;
                Core::$_pma_slideinfos[Core::$_pma_pmacoreliteSessionID] = [];
                Core::$_pma_amount_of_data_downloaded[Core::$_pma_pmacoreliteSessionID] = 0;
                // no point authenticating localhost / PMA.core.lite
                return Core::$_pma_pmacoreliteSessionID;
            } else {
                return null;
            }
        }

        // purposefully DON'T use helper function _pma_api_url() here:
        // why? Because_pma_api_url() takes session information into account (which we don't have yet)
        $url = PMA::_pma_join($pmacoreURL, "api/json/authenticate?caller=SDK.PHP");
        if ($pmacoreUsername != "") {
            $url .= "&username=" . PMA::_pma_q($pmacoreUsername);
        }
        if ($pmacorePassword != "") {
            $url .= "&password=" . PMA::_pma_q($pmacorePassword);
        }

        $json = array();
        $contents = "";
        try {
            $jsonData =  array(
                "username" => $pmacoreUsername,
                "password" => $pmacorePassword,
                "caller" => "SDK.PHP"
            );

            $ret_val = PMA::_pma_send_post_request_with_statuscode($url, $jsonData);
            if ($ret_val['statusCode'] != 200) {
                throw new Exception("No post authentication request");
            }

            $contents = $ret_val['resp'];
            $json = json_decode($ret_val['resp'], true);
        } catch (Exception $e) {

            try {
                $contents = @file_get_contents($url);
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
        }

        if ($json["Success"] != 1) {
            $sessionID = null;
        } else {
            $sessionID = $json["SessionId"];
            Core::$_pma_sessions[$sessionID] = $pmacoreURL;
            Core::$_pma_usernames[$sessionID] = $pmacoreUsername;
            Core::$_pma_slideinfos[$sessionID] = [];
            Core::$_pma_amount_of_data_downloaded[$sessionID] = strlen($contents);
        }
        return $sessionID;
    }

    /**
	Validates a sessionID
     */
    public static function ping($pmacoreURL, $sessionID)
    {
        $url = PMA::_pma_join($pmacoreURL, "/api/json/Ping") . "?sessionID=" . PMA::_pma_q($sessionID);
        $contents = "";
        try {
            $contents = @file_get_contents($url);
            return $contents == "true";
        } catch (Exception $e) {
            return false;
        }
    }

    /**
    Attempt to connect to PMA.core instance; success results in a SessionID
     */
    public static function disconnect($sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        if ($sessionID == null) {
            return false;
        }

        $url = Core::_pma_api_url($sessionID) . "DeAuthenticate?sessionID=" . PMA::_pma_q($sessionID);
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $ex) {
            throw new Exception("Unable to disconnect");
            $contents = "";
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        if (count(Core::$_pma_sessions) > 0) {
            unset(Core::$_pma_sessions[$sessionID]);
            unset(Core::$_pma_slideinfos[$sessionID]);
        }

        return true;
    }

    /**
    Return an array of root-directories available to $sessionID
     */
    public static function getRootDirectories($sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID) . "GetRootDirectories?sessionID=" . PMA::_pma_q($sessionID);
        try {
            $contents = @file_get_contents($url);
        } catch (Exception $ex) {
            throw new Exception("Unable to retrieve root-directories through $sessionID");
            $contents = "";
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        return $json;
    }

    /**
    Return an array of sub-directories available to $sessionID in the $startDir directory
     */
    public static function getDirectories($startDir, $sessionID = null, $recursive = False)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID) . "GetDirectories?sessionID=" . PMA::_pma_q($sessionID) . "&path=" . PMA::_pma_q($startDir);
        if (Core::$_pma_debug === true) {
            echo $url . PHP_EOL;
        }
        $contents = @file_get_contents($url);

        if (Core::$_pma_debug === true) {
            echo "<pre>".$contents . "</pre><br>" . PHP_EOL;
        }


        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        if (isset($json["Code"])) {
            throw new Exception("get_directories to $startDir resulted in: " . $json["Message"] . " (keep in mind that startDir is case sensitive!)");
        }

        // handle recursion, if so desired
        if ( (is_bool($recursive) && $recursive == True) || (is_numeric($recursive) && $recursive > 0) ) {
            //echo "RECURSION DETECTED!<br>";
            foreach (Core::getDirectories($startDir, $sessionID) as $dir) {
                if (is_bool($recursive))
                    $json = $json + Core::getDirectories($dir, $sessionID, $recursive);
                elseif (is_numeric($recursive))
                    $json = $json + Core::getDirectories($dir, $sessionID, $recursive - 1);
            }
        }

        return $json;
    }

    /**
    Look for the first directory in a directory hierarchy that starts at $startDir that has at least one actual slide in it
     */
    public static function getFirstNonEmptyDirectory($startDir = null, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);

        if (($startDir === null) || ($startDir == "")) {
            $startDir = "/";
        }

        $slides = Core::getSlides($startDir, $sessionID);
        if (count($slides) > 0) {
            return $startDir;
        } else {
            if ($startDir == "/") {
                foreach (Core::getRootDirectories($sessionID) as $dir) {
                    $nonEmtptyDir = Core::getFirstNonEmptyDirectory($dir, $sessionID);
                    if ($nonEmtptyDir !== null) {
                        return $nonEmtptyDir;
                    }
                }
            } else {
                foreach (Core::getDirectories($startDir, $sessionID) as $dir) {
                    $nonEmtptyDir = Core::getFirstNonEmptyDirectory($dir, $sessionID);
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
        $sessionID = Core::_pma_session_id($sessionID);
        if (pma::starts_with($startDir, "/")) {
            $startDir = substr($startDir, 1);
        }
        $url = Core::_pma_api_url($sessionID) . "GetFiles?sessionID=" . PMA::_pma_q($sessionID) . "&path=" . PMA::_pma_q($startDir);
        $contents = @file_get_contents($url);
        if ($contents === FALSE) {
            throw new Exception("get_slides wasn't able to get any content from $startDir (keep in mind that startDir is case sensitive!)");
        }

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        if (isset($json["Code"])) {
            throw new Exception("get_slides from $startDir resulted in: " . $json["Message"] . " (keep in mind that startDir is case sensitive!)");
        } else {
            $slides = $json;
        }

        if (((gettype($recursive) == "boolean") && ($recursive == True))
            || ((gettype($recursive) == "integer") && ($recursive > 0))
        ) {
            foreach (Core::getDirectories($startDir, $sessionID) as $dir) {
                if (gettype($recursive) == "boolean") {
                    $new_slides = Core::getSlides($dir, $sessionID, $recursive);
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
                    $new_slides = Core::getSlides($dir, $sessionID, $recursive - 1);
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
        $sessionID = Core::_pma_session_id($sessionID);
        if ($sessionID == Core::$_pma_pmacoreliteSessionID) {
            if (Core::isLite()) {
                throw new \BadMethodCallException("PMA.core.lite found running, but it doesn't slide UID generation");
            } else {
                throw new \BadMethodCallException("PMA.core.lite not found, and besides; it doesn't support slide UID generation anyway");
            }
        }

        $url = Core::_pma_api_url($sessionID) . "GetUID?sessionID=" . PMA::_pma_q($sessionID) . "&path=" . PMA::_pma_q($slideRef);

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        if (isset($json["Code"])) {
            throw new Exception("get_uid for $slideRef resulted in: " . $json["Message"] . " (keep in mind that slideRef is case sensitive!)");
        } else {
            $uid = $json;
        }
        return $uid;
    }

    /**
    Get the fingerprint for a specific slide
     */
    public static function getFingerprint($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID) . "GetFingerprint?sessionID=" . PMA::_pma_q($sessionID) . "&pathOrUid=" . PMA::_pma_q($slideRef);

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
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
        $sessionID = Core::_pma_session_id($sessionID);
        $infos = Core::GetSlidesInfo(array($slideRef), $sessionID);
        return $infos[$slideRef];
    }

    public static function GetSlidesInfo($slideRefs, $sessionID = null)
    {
        if (!is_array($slideRefs)) {
            $slideRefs = array($slideRefs);
        }
        $sessionID = Core::_pma_session_id($sessionID);


        $slideRefs_new = array();
        foreach ($slideRefs as $sl) {
            $sl_new = $sl;
            if (PMA::starts_with($sl_new, "/")) {
                $sl_new = substr($sl_new, 1);
            }
            if (!isset(Core::$_pma_slideinfos[$sessionID][$sl_new])) {
                array_push($slideRefs_new, $sl_new);
            }
        }

        if (count($slideRefs_new) > 0) {
            $url = Core::_pma_url($sessionID) . "api/json/GetImagesInfo";
            $jsonData = array(
                "sessionID" => $sessionID,
                "pathOrUids" => $slideRefs_new
            );
            $ret_val = PMA::_pma_send_post_request($url, $jsonData);
            $json = json_decode($ret_val, true);
            if (isset($json["d"])) {
                $json = $json["d"];
            }

            Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($ret_val);
            if (isset($json["Code"])) {
                throw new Exception("ImageInfos to " + $slideRefs + " resulted in: " + $json["Message"] + " (keep in mind that slideRef is case sensitive!)");
            } else {
                if (count($json) > 0) {
                    foreach ($json as $el) {
                        Core::$_pma_slideinfos[$sessionID][$el["Filename"]] = $el;
                        // UID is meaningless in PMA.start context
                        if ($sessionID != Core::$_pma_pmacoreliteSessionID) {
                            Core::$_pma_slideinfos[$sessionID][$el["UID"]] = $el;
                        }
                    }
                }
            }
        }

        $ret_value = array();
        foreach ($slideRefs as $sl) {
            $ret_value[$sl] = Core::$_pma_slideinfos[$sessionID][$sl];
        }

        return $ret_value;
    }
    /**
    Determine the maximum zoomlevel that still represents an optical magnification
     */
    public static function getMaxZoomlevel($slideRef, $sessionID = null)
    {
        $info = Core::getSlideInfo($slideRef, $sessionID);
        if ($info == null) {
            throw new Exception("Unable to get information for" . $slideRef . " from " . $sessionID);
            return 0;
        } else {
            if (array_key_exists("MaxZoomLevel", $info)) {
                try {
                    return (int)$info["MaxZoomLevel"];
                } catch (exception $ex) {
                    echo "Something went wrong consulting the MaxZoomLevel key in info{}" . PHP_EOL;
                    return 0;
                }
            } else {
                try {
                    return (int)$info["NumberOfZoomLevels"];
                } catch (exception $ex) {
                    echo "Something went wrong consulting the NumberOfZoomLevels key in info{}" . PHP_EOL;
                    return 0;
                }
            }
        }
    }

    /**
    Obtain a dictionary with the number of tiles per zoomlevel.
    Information is returned as (x, y, n) tupels per zoomlevel, with
    x = number of horizontal tiles,
    y = number of vertical tiles,
    n = total number of tiles at specified zoomlevel (x * y)
    Use min_number_of_tiles argument to specify that you're only interested in zoomlevels that include at lease a given number of tiles
     */
    public static function getZoomlevelsDict($slideRef, $sessionID = null, $minNumberOfTiles = 0)
    {
        $maxZoomLevel = Core::getMaxZoomlevel($slideRef, $sessionID);
        $d = array();
        for ($i = 0; $i <= $maxZoomLevel; $i++) {
            $tiles = Core::getNumberOfTiles($slideRef, $i, $sessionID);
            if ($tiles > $minNumberOfTiles) {
                $d[$i] =  $tiles;
            }
        }

        return $d;
    }


    /**
    Retrieve the physical dimension in terms of pixels per micrometer.
    When zoomlevel is left to its default value of None, dimensions at the highest zoomlevel are returned
    (in effect returning the "native" resolution at which the slide was registered)
     */
    public static function getPixelsPerMicrometer($slideRef, $zoomlevel = null, $sessionID = null)
    {
        $maxZoomLevel = Core::getMaxZoomlevel($slideRef, $sessionID);
        $info = Core::getSlideInfo($slideRef, $sessionID);
        $xppm = $info["MicrometresPerPixelX"];
        $yppm = $info["MicrometresPerPixelY"];

        if (is_null($zoomlevel) || $zoomlevel == $maxZoomLevel) {
            return array((float)$xppm, (float)$yppm);
        } else {
            $factor = pow(2, $zoomlevel - $maxZoomLevel);
            return array((float)$xppm / $factor, (float)$yppm / $factor);
        }
    }

    /**
    Get the total dimensions of a slide image at a given zoomlevel
     */
    public static function getPixelDimensions($slideRef, $zoomlevel = null, $sessionID = null)
    {
        $maxZoomLevel = Core::getMaxZoomlevel($slideRef, $sessionID);
        $info = Core::getSlideInfo($slideRef, $sessionID);
        if (is_null($zoomlevel) || $zoomlevel == $maxZoomLevel) {
            return array((int)$info["Width"], (int)$info["Height"]);
        } else {
            $factor = pow(2, $zoomlevel - $maxZoomLevel);
        }
        return array((int)$info["Width"] * $factor, (int)$info["Height"] * $factor);
    }

    /**
    Determine the number of tiles needed to reconstitute a slide at a given zoomlevel
     */
    public static function getNumberOfTiles($slideRef, $zoomlevel = null, $sessionID = null)
    {
        $pixels = Core::getPixelDimensions($slideRef, $zoomlevel, $sessionID);
        $sz = Core::getTileSize($sessionID);
        $xtiles = (int)ceil($pixels[0] / $sz[0]);
        $ytiles = (int)ceil($pixels[1] / $sz[1]);
        $ntiles = $xtiles * $ytiles;
        return array($xtiles, $ytiles, $ntiles);
    }

    /**
    Determine the physical dimensions of the sample represented by the slide.
    This is independent of the zoomlevel: the physical properties don't change because the magnification changes
     */
    public static function getPhysicalDimensions($slideRef, $sessionID = null)
    {
        $ppmData = Core::getPixelsPerMicrometer($slideRef, $sessionID);
        $pixelSz = Core::getPixelDimensions($slideRef, null, $sessionID);
        return array($pixelSz[0] * $ppmData[0], $pixelSz[1] * $ppmData[1]);
    }

    /**
    Number of fluorescent channels for a slide (when slide is brightfield, return is always 1)
     */
    public static function getNumberOfChannels($slideRef, $sessionID = null)
    {
        $info = Core::getSlideInfo($slideRef, $sessionID);
        $channels = $info["TimeFrames"][0]["Layers"][0]["Channels"];
        return count($channels);
    }

    /**
    Number of (z-stacked) layers for a slide
     */
    public static function getNumberOfLayers($slideRef, $sessionID = null)
    {
        $info = Core::getSlideInfo($slideRef, $sessionID);
        $layers = $info["TimeFrames"][0]["Layers"];
        return count($layers);
    }

    public static function getNumberOfZStackLayers($slideRef, $sessionID = null)
    {
        return Core::getNumberOfLayers($slideRef, $sessionID);
    }

    /**
    Determine whether a slide is a fluorescent image or not
     */
    public static function isFluorescent($slideRef, $sessionID = null)
    {
        return Core::getNumberOfChannels($slideRef, $sessionID) > 1;
    }

    /**
    Determine whether a slide contains multiple (stacked) layers or not
     */
    public static function isMultiLayer($slideRef, $sessionID = null)
    {
        return Core::getNumberOfLayers($slideRef, $sessionID) > 1;
    }

    /**
    Determine whether a slide is a z-stack or not
     */
    public static function isZStack($slideRef, $sessionID = null)
    {
        return Core::isMultiLayer($slideRef, $sessionID);
    }

    /**
    Get the magnification represented at a certain zoomlevel
     */
    public static function getMagnification($slideRef, $zoomlevel = null, $exact = False, $sessionID = null)
    {
        $ppm = Core::getPixelsPerMicrometer($slideRef, $zoomlevel, $sessionID)[0];
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
    Return the list of images type associated with a slide
     */
    public static function getAssociatedImageTypes($slideRef, $sessionID = null)
    {
        $info = Core::getSlideInfo($slideRef, $sessionID);
        if ($info == null) {
            return null;
        } else {
            if (array_key_exists("AssociatedImageTypes", $info)) {
                return $info["AssociatedImageTypes"];
            } else {
                return null;
            }
        }
    }

    /**
    Get the URL that points to the barcode (alias for "label") for a slide
     */
    public static function getBarcodeUrl($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = (Core::_pma_url($sessionID) . "barcode"
            . "?SessionID=" . pma::_pma_q($sessionID)
            . "&pathOrUid=" . pma::_pma_q($slideRef));
        return $url;
    }

    /**
    Get the barcode (alias for "label") image for a slide
     */
    public static function getBarcodeImage($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $img = imagecreatefromjpeg(Core::getBarcodeUrl($slideRef, $sessionID));
        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

    /**
    Get the URL that points to the label for a slide
     */
    public static function getLabelUrl($slideRef, $sessionID = null)
    {

        return Core::getBarcodeUrl($slideRef, $sessionID);
    }

    /**
    Get the label image for a slide
     */
    public static function getLabelImage($slideRef, $sessionID = null)
    {
        $sessionID = pma::_pma_session_id($sessionID);
        $img = imagecreatefromjpeg(Core::getLabelUrl($slideRef, $sessionID));
        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

    /**
    Get the URL that points to the thumbnail for a slide
     */
    public static function getThumbnailUrl($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = (Core::_pma_url($sessionID) . "thumbnail"
            . "?SessionID=" . pma::_pma_q($sessionID)
            . "&pathOrUid=" . pma::_pma_q($slideRef));
        return $url;
    }

    /**
    Get the thumbnail image for a slide
     */
    public static function getThumbnailImage($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $img = imagecreatefromjpeg(Core::getThumbnailUrl($slideRef, $sessionID));
        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

    /**
	Get a composite thumbnail for any number of slides
	The function results thumbnails for up to the first 4 slides in an array; if there are more than 4 slides, then a message is included in the resulting composite
     */
    public static function getCompositeThumbnailImage($slides, $width = 1200, $height = 630, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $canvas = imagecreatetruecolor($width, $height);
        switch (count($slides)) {
            case 0:
                $white = imagecolorallocate($canvas, 255, 255, 255);
                $textcolor = imagecolorallocate($canvas, 0, 0, 0);
                imagestring($canvas, 5, $width / 2, $height / 2, 'No slides selected', $textcolor);
                break;
            case 1:
                $t = Core::getThumbnailImage($slides[0], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, 0, 0, 0, $width, $height, $thumb_w, $thumb_h);
                break;
            case 2:
                $black = imagecolorallocate($canvas, 0, 0, 0);
                $t = Core::getThumbnailImage($slides[0], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, 0, 0, 0, $width / 2 - 2, $height, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[1], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, 0, 0, 0, $width / 2 - 2, $height, $thumb_w, $thumb_h);
                break;
            case 3:
                $black = imagecolorallocate($canvas, 0, 0, 0);

                $t = Core::getThumbnailImage($slides[0], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, 0, 0, 0, $width / 2 - 2, $height, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[1], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, 0, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[2], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, $height / 2, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                break;
            case 4:
                $black = imagecolorallocate($canvas, 0, 0, 0);

                $t = Core::getThumbnailImage($slides[0], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, 0, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[1], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, $height / 2, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[2], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, 0, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[3], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, $height / 2, 0, 0, $width / 2 - 2, $height / 2 - 2, $thumb_w, $thumb_h);

                break;
            default:
                $black = imagecolorallocate($canvas, 0, 0, 0);

                $textcolor = imagecolorallocate($canvas, 255, 255, 255);
                imagestring($canvas, 5, 5, $height - 30, "And " . (count($slides) - 4) . " more (" . count($slides) . " total)", $textcolor);

                $t = Core::getThumbnailImage($slides[0], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, 0, 0, 0, $width / 2 - 2, $height / 2 - 2 - 15, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[1], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, 0, $height / 2 - 15, 0, 0, $width / 2 - 2, $height / 2 - 2 - 15, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[2], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, 0, 0, 0, $width / 2 - 2, $height / 2 - 2 - 15, $thumb_w, $thumb_h);

                $t = Core::getThumbnailImage($slides[3], $sessionID);
                $thumb_w = imagesx($t);
                $thumb_h = imagesy($t);
                imagecopyresampled($canvas, $t, $width / 2, $height / 2 - 15, 0, 0, $width / 2 - 2, $height / 2 - 2 - 15, $thumb_w, $thumb_h);
        }
        return $canvas;
    }

    /**
    Get the URL that points to the macro image for a slide (a low resolution image of the slide overview as well as the barcode; essentially )
     */
    public static function getMacroUrl($slideRef, $width = null, $height = null, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = (Core::_pma_url($sessionID) . "macro"
            . "?SessionID=" . pma::_pma_q($sessionID)
            . "&pathOrUid=" . pma::_pma_q($slideRef));
		if (!is_null($width) && is_int($width)) {
			$url .= "&w=".$width;			
		}
		if (!is_null($height) && is_int($height)) {
			$url .= "&h=".$height;			
		}
        return $url;
    }

    /**
    Get the macro image for a slide
     */
    public static function getMacroImage($slideRef, $width = null, $height = null, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $img = imagecreatefromjpeg(Core::getThumbnailUrl($slideRef, $width, $height, $sessionID));
        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

    /**
    Obtain all files actually associated with a specific slide
    This is most relevant with slides that are defined by multiple files, like MRXS or VSI
     */
    public static function getFilesForSlide($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);

        $slideRef = ltrim($slideRef, "/");

        if ($sessionID == Core::$_pma_pmacoreliteSessionID) {
            $url = Core::_pma_api_url($sessionID, False) . "EnumerateAllFilesForSlide?sessionID=" . PMA::_pma_q($sessionID) . "&pathOrUid=" . PMA::_pma_q($slideRef);
        } else {
            $url = Core::_pma_api_url($sessionID, False) . "getfilenames?sessionID=" . PMA::_pma_q($sessionID) . "&pathOrUid=" . PMA::_pma_q($slideRef);
        }

        if (Core::$_pma_debug == true) {
            echo "url = " . $url . "\n";
        }

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
        if (isset($json["Code"])) {
            throw new Exception("enumerate_files_for_slide on  " . $slideRef . " resulted in: " . $json["Message"] . " (keep in mind that slideRef is case sensitive!)");
        } else {
            $files = $json;
        }

        return $files;
    }

    /**
     * Gets the tile size for the server
     */
    public static function getTileSize($sessionID = null)
    {
        $sessionID = CORE::_pma_session_id($sessionID);
        if (sizeof(Core::$_pma_slideinfos[$sessionID]) == 0) {
            $dir = Core::getFirstNonEmptyDirectory(null, $sessionID);
            $slides = Core::getSlides($dir, $sessionID);
            $info = Core::getSlideInfo($slides[0], $sessionID);
        } else {
            foreach (Core::$_pma_slideinfos[$sessionID] as $key => $info) {
                break;
            }
        }

        return array((int)$info["TileSize"], (int)$info["TileSize"]);
    }

    /**
    Get the text encoded by the barcode (if there IS a barcode on the slide to begin with)
     */
    public static function getBarcodeText($slideRef, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $slideRef = ltrim($slideRef, "/");

        $url = Core::_pma_api_url($sessionID, False) . "GetBarcodeText?sessionID=" . PMA::_pma_q($sessionID) . "&pathOrUid=" . PMA::_pma_q($slideRef);

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
        if (isset($json["Code"])) {
            throw new Exception("getBarcodeText on  " . $slideRef . " resulted in: " . $json["Message"] . " (keep in mind that slideRef is case sensitive!)");
        }

        return $json;
    }

    /**
		return information about the current user (associated with the PMA.core SessionID)
	*/
	public static function whoAmI($sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        if ($sessionID == Core::$_pma_pmacoreliteSessionID) {
            return array(
                "sessionID" => Core::$_pma_pmacoreliteSessionID,
                "username" => null,
                "url" => Core::$_pma_pmacoreliteURL,
                "amountOfDataDownloaded" => Core::$_pma_amount_of_data_downloaded[Core::$_pma_pmacoreliteSessionID]
            );
        } else if ($sessionID != null) {
            return array(
                "sessionID" => $sessionID,
                "username" => Core::$_pma_usernames[$sessionID],
                "url" => Core::_pma_url($sessionID),
                "amountOfDataDownloaded" => Core::$_pma_amount_of_data_downloaded[$sessionID]
            );
        }
    }

    /**
	    Built the URL for a single tile at position (x, y)
	    Format can be 'jpg' or 'png'
	    Quality is an integer value and varies from 0 (as much compression as possible; not recommended) to 100 (100%, no compression)
	*/
    public static function getTileUrl($slide, $x = 0, $y = 0, $zoomlevel = null, $zstack = 0, $sessionID = null, $format = "jpg", $quality = 100)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $slide = ltrim($slide, "/");

        if (is_null($zoomlevel)) {
            $zoomlevel = 0;
        }

        $url = Core::_pma_url($sessionID);

        if (is_null($url)) {
            throw new Exception("Unable to determine the PMA.core instance belonging to " . $sessionID);
        }

        $url .= "tile"
            . "?SessionID=" . PMA::_pma_q($sessionID)
            . "&channels=" . PMA::_pma_q("0")
            . "&timeframe=" . PMA::_pma_q("0")
            . "&layer=" . (int)round($zstack)
            . "&pathOrUid=" . PMA::_pma_q($slide)
            . "&x=" . (int)round($x)
            . "&y=" . (int)round($y)
            . "&z=" . (int)round($zoomlevel)
            . "&format=" . PMA::_pma_q($format)
            . "&quality=" . PMA::_pma_q($quality)
            . "&cache=" . strtolower(Core::$_pma_usecachewhenretrievingtiles);

        return $url;
    }

	/**
	    Get a single tile at position (x, y)
		Format can be 'jpg' or 'png'
		Quality is an integer value and varies from 0 (as much compression as possible; not recommended) to 100 (100%, no compression)
	*/
    public static function getTile($slide, $x = 0, $y = 0, $zoomlevel = null, $zstack = 0, $sessionID = null, $format = "jpg", $quality = 100)
    {
        $url = Core::getTileUrl($slide, $x, $y, $zoomlevel, $zstack, $sessionID, $format, $quality);
        if (is_null($url)) {
            throw new Exception("Unable to determine the PMA.core instance belonging to " . $sessionID);
        }

        if (strtolower($format) == "png") {
            $img = imagecreatefrompng($url);
        } else {
            $img = imagecreatefromjpeg($url);
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

	public static function getRegionURL($slideRef, $x = 0, $y = 0, $width = 0, $height = 0, $scale = 1, $zstack = 0, $sessionID = null, $format = "jpg", $quality = 100, $rotation=0, $contrast = null, $brightness = null, $postGamma = null, $dpi = 300, $flipVertical = false, $flipHorizontal = false, $annotationsLayerType = null, $drawFilename = 0, $downloadInsteadOfDisplay = false, $drawScaleBar = false) {
        $sessionID = Core::_pma_session_id($sessionID);
        $slideRef = ltrim($slideRef, "/");

        /*if (is_null($zoomlevel)) {
            $zoomlevel = 0;
        }*/

        $url = Core::_pma_url($sessionID)."/Region?";

        if (is_null($url)) {
            throw new Exception("Unable to determine the PMA.core instance belonging to " . $sessionID);
        }

		$regionParams = array(
				"sessionID" => $sessionID,
				"channels" => 0,
				"timeframe" => 0,
				"layer" => 0,
				"pathOrUid" => $slideRef,
				"x" => $x,
				"y" => $y,
				"width" => $width,
				"height" => $height,
				"scale" => $scale,
				"format" => $format,
				"quality" => $quality,
				"rotation" => $rotation,
				"drawFilename" => $drawFilename,
				"drawScaleBar" => $drawScaleBar
			);
        
        if (Core::$_pma_debug == true) {
            echo "url =" . $url;
        }

        return $url.http_build_query($regionParams);
		
	}
	
    public static function getRegion($slideRef, $x = 0, $y = 0, $width = 0, $height = 0, $scale = 1, $zstack = 0, $sessionID = null, $format = "jpg", $quality = 100, $rotation=0, $contrast = null, $brightness = null, $postGamma = null, $dpi = 300, $flipVertical = false, $flipHorizontal = false, $annotationsLayerType = null, $drawFilename = 0, $downloadInsteadOfDisplay = false, $drawScaleBar = false)
    {

        $url = Core::_pma_query_url($sessionID) . "Filename?sessionID=" . PMA::_pma_q($sessionID) . "&path=" . PMA::_pma_q($startDir) . "&pattern=" . PMA::_pma_q($pattern);
        if (Core::$_pma_debug == true) {
            echo "url =" . $url;
        }


        $url = Core::getRegionUrl($slideRef, $x, $y, $width, $height, $scale, $zstack, $sessionID, $format, $quality, $rotation, $contrast, $brightness, $postGamma, $dpi, $flipVertical, $flipHorizontal, $annotationsLayerType, $drawFilename, $downloadInsteadOfDisplay, $drawScaleBar);
        if (is_null($url)) {
            throw new Exception("Unable to determine the PMA.core instance belonging to " . $sessionID);
        }

        if (strtolower($format) == "png") {
            $img = imagecreatefrompng($url);
        } else {
            $img = imagecreatefromjpeg($url);
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen(serialize($img));
        return $img;
    }

    /**
    See what forms are available to fill out, either system-wide (leave slideref to None), or for a particular slide
    */
	public static function getAvailableForms($slideref = null, $sessionID = null) {

        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID)."/GetForms?sessionID=".PMA::_pma_q($sessionID);

/*
    if (slideRef is not None):
        if (slideRef.startswith("/")):
            slideRef = slideRef[1:]
        dir = os.path.split(slideRef)[0]
        url = _pma_api_url(sessionID) + "GetForms?sessionID=" + pma._pma_q(sessionID) + "&path=" + pma._pma_q(dir)
    else:
        url = _pma_api_url(sessionID) + "GetForms?sessionID=" + pma._pma_q(sessionID)
*/
        if (Core::$_pma_debug == true) {
            echo "url =" . $url;
        }

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

		$forms = [];
        if (isset($json["Code"])) {
            throw new Exception("get_uid for $slideRef resulted in: " . $json["Message"] . " (keep in mind that slideRef is case sensitive!)");
        } else {
			foreach ($json as $formdef) {
				$forms[$formdef["Key"]] = $formdef["Value"];
			}
        }
        return $forms;
	}
	
	/**
	Get a list of attached external annotation files (Visiopharm MLD, Indica Labs ANNOTATION...)
	*/
	public static function getAnnotationFiles($slideRef, $sessionID = null) {
        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID) . "GetExternalAnnotationFiles"
			."?sessionID=" . PMA::_pma_q($sessionID) 
			."&pathOrUid=" . PMA::_pma_q($slideRef);

        if (Core::$_pma_debug == true) {
            echo $url . PHP_EOL;
        }

        try {
            $contents = @file_get_contents($url);
        } catch (Exception $ex) {
            throw new Exception("Unable to retrieve annotations through $sessionID");
            $contents = "";
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        return $json;
	}
	
	/**
    Retrieve the annotations for slide slideRef
    */
    public static function getAnnotations($slideRef, $sessionID = null) {

        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID) . "GetAnnotations"
			."?sessionID=" . PMA::_pma_q($sessionID) 
			."&pathOrUid=" . PMA::_pma_q($slideRef);

        if (Core::$_pma_debug == true) {
            echo $url . PHP_EOL;
        }

        try {
            $contents = @file_get_contents($url);
        } catch (Exception $ex) {
            throw new Exception("Unable to retrieve annotations through $sessionID");
            $contents = "";
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        return $json;
	}

	/**
	Retrieve one particular annotation, based on its ID
	*/
	public static function getAnnotationByID($slideref, $annID, $sessionID = null) {
		$anns = Core::getAnnotations($slideref, $sessionID);
		foreach ($anns as $ann) {
			if ($ann["AnnotationID"] == $annID) {
				return $ann;
			}
		}
		return null;
	}

    /**
		Search for slides or directories using the pattern
    */
    public static function searchSlides($startDir, $pattern, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        if ($sessionID == Core::$_pma_pmacoreliteSessionID) {
            if (Core::isLite()) {
                throw new \BadMethodCallException("PMA.core.lite found running, but it doesn't support searching");
            } else {
                throw new \BadMethodCallException("PMA.core.lite not found, and besides; it doesn't support searching anyway");
            }
        }

        $startDir = ltrim($startDir, "/");

        $url = Core::_pma_query_url($sessionID) . "Filename?sessionID=" . PMA::_pma_q($sessionID) . "&path=" . PMA::_pma_q($startDir) . "&pattern=" . PMA::_pma_q($pattern);
        if (Core::$_pma_debug == true) {
            echo "url =" . $url;
        }

        $contents = @file_get_contents($url);

        $json = json_decode($contents, true);
        if (isset($json["d"])) {
            $json = $json["d"];
        }

        Core::$_pma_amount_of_data_downloaded[$sessionID] += strlen($contents);
        if (isset($json["Code"])) {
            throw new Exception("searchSlides on  " . $startDir . " resulted in: " . $json["Message"]);
        } else {
            $files = $json;
        }

        return $files;
    }

    /**
        Downloads a slide
    */
    public static function download($slide, $saveDirectory, $sessionID = null)
    {
        $sessionID = Core::_pma_session_id($sessionID);
        $slide = ltrim($slide, "/");

        $files = CORE::getFilesForSlide($slide, $sessionID);

        if (empty($files)) {
            throw new Exception("Slide not found" . "\n");
            return;
        }

        $mainDirectory = substr($slide, 0, strripos($slide, "/"));

        foreach ($files as $file) {
            $f = $file["Path"];
            $relativePath = str_replace($mainDirectory, "", $f);
            $relativePath = trim($relativePath, '\\');
            $relativePath = trim($relativePath, "/");
            $pmaCoreDownloadUrl = Core::_pma_transfer_url($sessionID) . "Download";

            if (Core::$_pma_debug == true) {
                echo "Downloading file " . $relativePath  . " for slide " . $slide . "\n";
            }

            $params = array(
                "sessionId" => $sessionID,
                "image" => $slide,
                "path" => $relativePath
            );

            $filePath = PMA::os_path_join($saveDirectory, $relativePath);

            $dir = dirname($filePath);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $fp = fopen($filePath, 'w');
            $url = $pmaCoreDownloadUrl . "?" . http_build_query($params);
            if (Core::$_pma_debug == true) {
                echo $url . "\n";
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array('self', 'progress'));
            CORE::$_previous_downloaded = -1;
            $data = curl_exec($ch);

            curl_close($ch);
            fclose($fp);
        }
    }

    /**
        Upload a slide to a PMA.core server
    */
    public static function upload($localSlide, $targetFolder, $targetPmaCoresessionID)
    {
        if (!Core::_pma_is_lite()) {
            throw new Exception("No PMA.start found on localhost. Are you sure it is running?");
            return;
        }

        if (strpos($targetFolder, '/') === 0) {
            $targetFolder = substr($targetFolder, 1);
        }

        $files = Core::getFilesForSlide($localSlide, Core::$_pma_pmacoreliteSessionID);
        $url = Core::_pma_transfer_url($targetPmaCoresessionID) . "Upload?sessionID=" . PMA::_pma_q($targetPmaCoresessionID);

        $mainDirectory = dirname($files[0]);
        foreach ($files as $file) {
            $md = dirname($file);
            if (strlen($md) < strlen($mainDirectory)) {
                $mainDirectory = $md;
            }
        }

        $uploadFiles = array();
        foreach ($files as $file) {
            $s = filesize($file);
            if (!$s) {
                continue;
            }

            $relativePath = str_replace($mainDirectory, "", $file);
            $relativePath = trim($relativePath, '\\');
            $relativePath = trim($relativePath, "/");

            array_push($uploadFiles, array(
                "Path" => $relativePath,
                "Length" => $s,
                "IsMain" => $file == $files[count($files) - 1],
                "FullPath" => $file
            ));
        }

        $data = array("Path" => $targetFolder, "Files" => $uploadFiles);
        $uploadHeaderResponse = PMA::_pma_send_post_request_with_statuscode($url, $data);

        if (!$uploadHeaderResponse["statusCode"] == 200) {
            throw new Exception("Error uploading to server" . $uploadHeaderResponse["resp"]);
        }

        $uploadHeader = json_decode($uploadHeaderResponse["resp"], true);

        $pmaCoreUploadUrl = Core::_pma_transfer_url($targetPmaCoresessionID) . "Upload/" . $uploadHeader["Id"] . "?sessionID=" . PMA::_pma_q($targetPmaCoresessionID) . "&path={0}";
        $i = 0;
        foreach ($uploadFiles as $file) {
            echo "Uploading file " . $file["Path"] . "\n";

            $fields = [
                'name' => new \CurlFile($file["FullPath"], 'application/octet-stream', basename($file["Path"]))
            ];

            $curl = curl_init();

            $url = str_replace('{0}', $file["Path"], $pmaCoreUploadUrl);
            $header = array('Content-Type: multipart/form-data', 'Content-Length: ' . $file["Length"]);
            if ($uploadHeader["UploadType"] == 1) {
                // Amazon upload
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                $url = $uploadHeader["Urls"][$i];
            } else if ($uploadHeader["UploadType"] == 2) {
                //Azure upload
                array_push($header, 'x-ms-blob-type: BlockBlob');
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                $url = $uploadHeader["Urls"][$i];
            } else {
                curl_setopt($curl, CURLOPT_POST, true);
            }

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_NOPROGRESS, false);
            curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, array('self', 'progressUpload'));
            CORE::$_previous_downloaded = -1;

            $r = curl_exec($curl);
            curl_close($curl);
            $i += 1;
        }

        $finalizeUrl = Core::_pma_transfer_url($targetPmaCoresessionID) . "Upload/" . $uploadHeader["Id"] . "?sessionID=" . PMA::_pma_q($targetPmaCoresessionID);

        $ch = curl_init($finalizeUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            trigger_error('Curl Error:' . curl_error($ch));
        }

        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exception("Error uploading" . $result);
        }
    }

    private static function progress($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {
        $dl = -1;
        if ($download_size > 0) {
            $dl = round($downloaded / $download_size  * 100, 1);
            if (CORE::$_previous_downloaded == -1 || ($dl - CORE::$_previous_downloaded) > 5 || (($dl - CORE::$_previous_downloaded) > 0 && $downloaded == $download_size)) {
                echo  $dl . " %\n";
                CORE::$_previous_downloaded = $dl;
            }
        }
    }

    private static function progressUpload($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {
        $dl = -1;
        if ($upload_size > 0) {
            $dl = round($uploaded / $upload_size  * 100, 1);
            if (CORE::$_previous_downloaded == -1 || ($dl - CORE::$_previous_downloaded) > 5 || (($dl - CORE::$_previous_downloaded) > 0 && $uploaded == $upload_size)) {
                echo  $dl . " %\n";
                CORE::$_previous_downloaded = $dl;
            }
        }
    }
	
	/** 
	Adds an anotation (in WKT format) to a slide with the specified parameters 
	*/
	public static function addAnnotation($slideRef, $classification, $notes, $geometry, $color = "#000000", $layerID = 0, $sessionID = null) {
		
        $sessionID = Core::_pma_session_id($sessionID);
        $url = Core::_pma_api_url($sessionID)."AddAnnotation";
    
		$jsonData =  array(
			"sessionID" => $sessionID,
			"pathOrUid" => $slideRef,
			"classification" => $classification,
			"layerID" => $layerID,
			"notes" => $notes,
			"geometry" => $geometry,
			"color" => $color
		);

        try {

            $ret_val = PMA::_pma_send_post_request_with_statuscode($url, $jsonData);
            if ($ret_val['statusCode'] != 200) {
                throw new Exception("No post authentication request<br/>\n");
            }

            $contents = $ret_val['resp'];
            $json = json_decode($ret_val['resp'], true);
			return $json;
        } catch (Exception $e) {
			echo "Unable to add annotation<br/>\n";
        }
	}

    public static function addAnnotationFile($slideRef, $annotationFileRef, $sessionID = null) {

        $sessionID = Core::_pma_session_id($sessionID);

		$annParams = array(
            "sessionID" => $sessionID,
            "pathOrUid" => $slideRef
        );

        $url = Core::_pma_api_url($sessionID)."SetExternalAnnotationFiles?".http_build_query($annParams);        

        $jsonData = [];
        $currentAnns = Core::getAnnotationFiles($slideRef);
        foreach ($currentAnns as $currentAnn) {
            if ($currentAnn["Path"] != $annotationFileRef)
                array_push($jsonData, $currentAnn);            
        }

        array_push($jsonData,
                    ["Name" => "Sliani ".sprintf('%06d', mt_rand(100000, 999989)),
                     "Path" => $annotationFileRef]);

        // echo "<br>$url<br>\n";
        // echo "<pre>"; print_r($jsonData); echo "</pre>";

        try {

            $ret_val = PMA::_pma_send_post_request_with_statuscode($url, $jsonData);
            // echo "HTTP statuscode: ".$ret_val['statusCode']."<br>";
            if ($ret_val['statusCode'] == 404) {
                throw new Exception("Endpoint <tt>$url</tt> not found<br/>\n");                
            } elseif ($ret_val['statusCode'] != 200) {
                echo "<pre>"; print_r($ret_val); echo "</pre>"; 
                throw new Exception("No post authentication request (status ".$ret_val['statusCode']." received)<br/>\n");                
            }

            $contents = $ret_val['resp'];
            $json = json_decode($ret_val['resp'], true);
			return $json;
        } catch (Exception $e) {
			echo "Unable to attach $annotationFileRef to $slideRef<br/>\n";
            echo "<pre>"; print_r($e); echo "</pre>"; 
        }

    }

    public static function detachAnnotationFile($slideRef, $annotationFileRef, $sessionID = null) {

        $sessionID = Core::_pma_session_id($sessionID);

		$annParams = array(
            "sessionID" => $sessionID,
            "pathOrUid" => $slideRef
        );

        $url = Core::_pma_api_url($sessionID)."SetExternalAnnotationFiles?".http_build_query($annParams);        

        $currentAnns = Core::getAnnotationFiles($slideRef);

        /*echo "<pre><b>Original annotation files:</b><br>\n";
        print_r($currentAnns);
        echo "</pre>";
        */
        $jsonData = [];

        foreach ($currentAnns as $currentAnn) {
            if ($currentAnn["Path"] != $annotationFileRef) {
                array_push($jsonData, $currentAnn); 
            }
        }
        
        /*echo "<pre><b>Updated annotation files:</b><br>\n";
        print_r($jsonData);
        echo "</pre>";
        */
        if (count($jsonData) == count($currentAnns)) {
            echo "No change; nothing to do; existing function";
            return;            
        }

        try {

            $ret_val = PMA::_pma_send_post_request_with_statuscode($url, $jsonData);
            echo "HTTP statuscode: ".$ret_val['statusCode']."<br>";
            if ($ret_val['statusCode'] == 404) {
                throw new Exception("Endpoint <tt>$url</tt> not found<br/>\n");                
            } elseif ($ret_val['statusCode'] != 200) {
                echo "<pre>"; print_r($ret_val); echo "</pre>"; 
                throw new Exception("No post authentication request (status ".$ret_val['statusCode']." received)<br/>\n");                
            }

            $contents = $ret_val['resp'];
            $json = json_decode($ret_val['resp'], true);
			return $json;
        } catch (Exception $e) {
			echo "Unable to attach $annotationFileRef to $slideRef<br/>\n";
            echo "<pre>"; print_r($e); echo "</pre>"; 
        }

    }

}
