<?php
/*
Setup
*/

// load pma_php library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

//[ERROR] echo("pma_php library loaded; version" + Core::$__version__);

// connection parameters to be used throughout this notebook
$pma_core_server = "http://host.pathomation.com/sandbox/2/PMA.core";
$pma_core_user = "user1";
$pma_core_pass = "Pathomation";
$pma_core_slide_dir = "hgx_cases";

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

/* Example 10: identifying PMA.core */

// testing actual "full" PMA.core instance that may or may not be out there
echo "Are you running PMA.core  at $pma_core_server ? ".(Core::isLite($pma_core_server) === TRUE ? "yes": "no").$newline;
echo "Are you running PMA.start(PMA.core.lite) at http://nowhere ? ".( Core::isLite("http://nowhere") === TRUE ? "yes": "no").$newline;
echo $newline;

/* Example 20: getting version information about PMA.core */

echo "You are running PMA.core version ".Core::getVersionInfo($pma_core_server)." at ".$pma_core_server.$newline;

// what happens when we run it against a bogus URL?
$version = Core::getVersionInfo("http://nowhere/");
if ($version == null) {
	echo "Unable to detect PMA.core at specified location (http://nowhere/)".$newline;
} else {
	echo "You are running PMA.core version ".$version.$newline;
}
echo $newline;

/* Example 30: connect to PMA.core */
$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "User [$pma_core_user] was unable to connect to PMA.core at specified location ($pma_core_server); password used = [$pma_core_pass]".PHP_EOL;
} else {
	echo "Successfully connected to PMA.core; sessionID = $sessionID";
}
echo $newline;

/* example 40: getting root-directories from PMA.core */
echo "You have the following root-directories on your system:".$newline;
$rootdirs = Core::getRootDirectories($sessionID);
foreach ($rootdirs as $rd) {
	echo "[$rd]".$newline;
}
echo $newline;

/* example 50: disconnect from PMA.core */

	// Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
	
/* Example 60: getting directories from PMA.core */
$rootdirs = Core::getRootDirectories();
echo "Directories found in ".$rootdirs[0].":".$newline;

$dirs = Core::getDirectories($rootdirs[0], $sessionID);
foreach ($dirs as $d) {
	print($d.$newline);
}
echo $newline;

/* example 70: get first non empty directory PMA.core */
$slide_dir = Core::getFirstNonEmptyDirectory();
echo $slide_dir.$newline;
echo $newline;

/* example 80: getting slide PMA.core */
$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
echo "Looking for slides in ".$dir.":".$newline;
echo "**Non-recursive:\n";
$slides = Core::getSlides($dir, $sessionID);
foreach ($slides as $slide) {
	echo $slide.$newline;
}
// [ERROR] Need to add recursive example here
echo $newline;

/* example 90: get UID for a slide in PMA.core */
$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
echo "Looking for slides in ".$dir.$newline;
foreach (Core::getSlides($dir, $sessionID) as $slide) {
	echo $slide." - ".Core::getUID($slide, $sessionID).$newline;
}



?>