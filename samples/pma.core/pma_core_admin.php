<?php
/*
Setup
*/

// load pma_php library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;
use Pathomation\PmaPhp\CoreAdmin;

//[ERROR] echo("pma_php library loaded; version" + Core::$__version__);

// connection parameters to be used throughout this notebook
$pma_core_server = "http://host.pathomation.com/sandbox/2/PMA.core";
$pma_core_user = "user1";
$pma_core_pass = "Pathomation";
$pma_core_slide_dir = "hgx_cases";

$local_path = "";
$s3_key = "";
$s3_secret = "";
$s3_path = "";

if (!Core::isLite($pma_core_server)) {   //[ERROR] Doesn't return the same values as PMA_python
    echo "PMA.core found. Good".$newline;
} else {
    throw new Exception("Unable to detect PMA.core! Please update configuration parameters in this block");
}

//[ERROR] Core::setDebugFlag(true);
echo $newline;

/*
PMA.core examples
*/

/* Example 500: admin connect */

$sessionID = CoreAdmin::AdminConnect($pma_core_server, $pma_core_user, $pma_core_pass);
echo "Administrative SessionID: ".$sessionID.$newline;
echo $newline;

/* Example 510: add user */

$new_user = "user".rand();
$new_pass = "pass".rand();

$user = CoreAdmin::AddUser($sessionID, $new_user, "John", "Doe", $new_user."@doe.family", $new_pass);

$new_session = Core::Connect($pma_core_server, $new_user, $new_pass);
echo "SessionID obtained for $new_user : $new_session".PHP_EOL;
echo $newline;

/* Example 520: user exists */

echo "Does user [admin] exist? ";
echo CoreAdmin::UserExists($sessionID, "admin") === TRUE ? "Yes": "No";
echo $newline;

$rand_user = rand();
echo "Does user [$rand_user] exist? ";
echo CoreAdmin::UserExists($sessionID, $rand_user) === TRUE ? "Yes": "No";
echo $newline;

echo $newline;

/* Example 530: */
echo "Example 530 reserved for future user interaction samples".$newline;
echo $newline;

/* Example 540: */
echo "Example 540 reserved for future user interaction samples".$newline;
echo $newline;

/* Example 550: get all PMA.core instances */
echo "Available PMA.core instances associated with the current installation of PMA.core:".$newline;
$mps = CoreAdmin::GetPmaCoreInstances($sessionID);
print_r($mps);
echo $newline;

/* Example 560: get current mounting point */
echo "Current PMA.core instance information:".$newline;
$mp = CoreAdmin::GetCurrentPmaCoreInstance($sessionID);
print_r($mp);
echo $newline;


?>