<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\Core;

// test for PMA.core.lite (PMA.start)
echo "Are you running PMA.core.lite? ".(Core::isLite() ? "Yes!": "no :-(").$newline;

echo "Seeing 'no' and want to see 'yes'? Make sure PMA.start is running on your system or download it from ".href('http://free.pathomation.com').$newline;

finish();
?>