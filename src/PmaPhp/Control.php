<?php
/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
*/

namespace Pathomation\PmaPhp;

/**
Wraps around PMA.control's API
*/
class Control {
	const version = PMA::version;
	
	const pma_training_session_role_supervisor = 1;
	const pma_training_session_role_trainee = 2;
	const pma_training_session_role_observer = 3;

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

	/**
	Retrieve a list of currently defined training sessions in PMA.control
	*/
	private static function _pma_get_training_sessions($pmacontrolURL, $pmacoreSessionID) {
		$url = PMA::_pma_join($pmacontrolURL, "api/Sessions?sessionID=".PMA::_pma_q($pmacoreSessionID));
		try {
			$r = @file_get_contents($url);
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
	private static function _pma_format_training_session_properly($sess) {
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
		$full_sessions = self::_pma_get_training_sessions($pmacontrolURL, $pmacoreSessionID);
		$user_dict = array();
		foreach ($full_sessions as $sess) {
			$s = self::_pma_format_training_session_properly($sess);
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
	public static function getTrainingSessionTitles($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID) {
		return array_values(self::getTrainingSessionTitlesAssoc($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID));
	}
	
	/**
	Retrieve (training) sessions (possibly filtered by project ID), return a dictionary of session IDs and titles
	*/
	public static function getTrainingSessionTitlesAssoc($pmacontrolURL, $pmacontrolProjectID, $pmacoreSessionID) {
		$dct = array();
		$all = self::_pma_get_training_sessions($pmacontrolURL, $pmacoreSessionID);
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
