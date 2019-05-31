<?php
// load library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\CoreAdmin;

$sessionID = CoreAdmin::AdminConnect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "Unable to connect to PMA.core at specified location ($pma_core_server)";
	die();
} 

echo "Does user [admin] exist? ";
echo CoreAdmin::UserExists($sessionID, "admin") === TRUE ? "Yes": "No";
echo PHP_EOL;

$rand_user = rand();

echo "Does user [$rand_user] exist? ";
echo CoreAdmin::UserExists($sessionID, $rand_user) === TRUE ? "Yes": "No";
echo PHP_EOL;

CoreAdmin::AdminDisConnect($sessionID);
?>
