# **Getting root directories**

- [Load library](#load-library)
- [Getting directories](#getting-directories)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Getting directories
```
$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "Unable to connect to PMA.core at specified location ($pma_core_server)";
} else {
	echo "Successfully connected to PMA.core; sessionID = $sessionID".$newline;
	
	$rootdirs = Core::getRootDirectories();
	echo "Directories found in ".$rootdirs[0].":".$newline;
	
	$dirs = Core::getDirectories($rootdirs[0], $sessionID);
	foreach ($dirs as $d) {
		print($d.$newline);
	}
	
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}

finish();
```
