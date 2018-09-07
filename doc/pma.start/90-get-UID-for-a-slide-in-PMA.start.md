# **Get UID for a slide**


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Get UID for a slide
```
$sessionID = Core::Connect();

if ($sessionID == null) {
	echo "Unable to connect to PMA.start";
} else {
	echo "Successfully connected to PMA.start".$newline;

	$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
	echo "Looking for slides in ".$dir.$newline;

	foreach (Core::getSlides($dir, $sessionID) as $slide) {
		echo $slide." - ".Core::getUID($slide, $sessionID).$newline;
	}
	
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}
```
