<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

// establish a default connection to PMA.start
if (Core::Connect()) {
	echo "The following drives were found on your system:".$newline;
	foreach (Core::getRootDirectories() as $rd) {
		echo $rd.$newline;
	}
	echo $newline."Can't find all the drives you're expecting? For network-connectivity (e.g. mapped drive access) you need PMA.core instead of PMA.start";
} else {
	echo "Unable to find PMA.start";
}

finish();
?>
