<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\Core;

if (!Core::isLite()) {
	// don't bother running this script if PMA.start isn't active
	echo "PMA.start is not running. Please start PMA.start first";
} else {
	// assuming we have PMA.start running; what's the version number?
	echo "You are running PMA.start version ".Core::getVersionInfo();
}

finish();
?>
