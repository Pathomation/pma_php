<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

$sessionID = Core::Connect();

if ($sessionID == null) {
	echo "Unable to connect to PMA.start";
} else {
	echo "Successfully connected to PMA.start".$newline;

	$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
	echo "Looking for slides in ".$dir.$newline;

	foreach (Core::getSlides($dir, $sessionID) as $slide) {
		try {
			echo $slide." - ".Core::getUID($slide, $sessionID).$newline;
		} catch (Exception $e) {
			echo $e.PHP_EOL;
			echo "Seeing an exception? PMA.start doesn't offer advanced slide anonymization. You need PMA.core for that".PHP_EOL;
		}
	}
	
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}
?>
