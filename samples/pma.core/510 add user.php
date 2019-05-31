<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;
use Pathomation\PmaPhp\CoreAdmin;

$sessionID = CoreAdmin::AdminConnect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "Unable to connect to PMA.core at specified location ($pma_core_server)";
	die();
}

$new_user = "user".rand();
$new_pass = "pass".rand();

$user = CoreAdmin::AddUser($sessionID, $new_user, "John", "Doe", $new_user."@doe.family", $new_pass);

$new_session = Core::Connect($pma_core_server, $new_user, $new_pass);
echo "SessionID obtained for $new_user : $new_session".PHP_EOL;
Core::Disconnect($new_session);
	
CoreAdmin::AdminDisconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well

?>
