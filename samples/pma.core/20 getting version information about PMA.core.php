<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

echo "You are running PMA.core version ".Core::getVersionInfo($pma_core_server)." at ".$pma_core_server.$newline;

// what happens when we run it against a bogus URL?
$version = Core::getVersionInfo("http://nowhere/");

if ($version == null) {
	echo "Unable to detect PMA.core at specified location (http://nowhere/)";
} else {
	echo "You are running PMA.core version ".$version;
}

finish();
?>
