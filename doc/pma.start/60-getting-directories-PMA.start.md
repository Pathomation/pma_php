# **Getting directories**


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Getting directories
```
$sessionID = Core::Connect();

if ($sessionID == null) {
	echo "Unable to connect to PMA.start";
} else {
	echo "Successfully connected to PMA.start".$newline;
	
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
