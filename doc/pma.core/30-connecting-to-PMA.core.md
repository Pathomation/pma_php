# **Connecting to PMA.core**

- [Load library](#load-library)
- [Connecting to PMA.core](#connecting-to-pma.core)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Connecting to PMA.core
```
$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "Unable to connect to PMA.core at specified location ($pma_core_server)";
} else {
	echo "Successfully connected to PMA.core; sessionID = $sessionID";
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}

finish();
```