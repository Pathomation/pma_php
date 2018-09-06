# **Connecting to PMA.start**

- [Load library](#load-library)
- [Connecting to PMA.start](#connecting-to-pma.start)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Connecting to PMA.start
```
$sessionID = Core::Connect();  // no parameters needed for PMA.start

if ($sessionID == null) {
	echo "Unable to connect to PMA.start";
} else {
	echo "Successfully connected to PMA.start; sessionID = $sessionID";
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}

finish();
```
