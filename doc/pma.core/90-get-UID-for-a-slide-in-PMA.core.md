# **Get UID for a slide**

- [Load library](#load-library)
- [Get UID for a slide](#get-uid-for-a-slide)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Get UID for a slide
```
$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
	echo "Unable to connect to PMA.core at specified location ($pma_core_server)";
} else {
	echo "Successfully connected to PMA.core; sessionID = $sessionID".$newline;

	$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);

	echo "Looking for slides in ".$dir.$newline;
	foreach (Core::getSlides($dir, $sessionID) as $slide) {
		echo $slide." - ".Core::getUID($slide, $sessionID).$newline;
	}
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}
```
