<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "User [$pma_core_user] was unable to connect to PMA.core at specified location ($pma_core_server); password used = [$pma_core_pass]".PHP_EOL;
} else {
	echo "Successfully connected to PMA.core; sessionID = $sessionID";
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}

finish();
?>
