<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

// testing actual "full" PMA.core instance that may or may not be out there
echo "Are you running PMA.start(PMA.core.lite) at $pma_core_server ? ".( Core::isLite($pma_core_server) === TRUE ? "yes": "no").$newline;
echo "Are you running PMA.start(PMA.core.lite) at http://nowhere ? ".( Core::isLite("http://nowhere") === TRUE ? "yes": "no").$newline;

finish();
?>