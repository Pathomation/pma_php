# **Getting slides**

- [Load library](#load-library)
- [Getting slides](#getting-slides)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Getting slides
```
$sessionID = Core::Connect();

if ($sessionID == null) {
	echo "Unable to connect to PMA.start";
} else {
	echo "Successfully connected to PMA.start".$newline;
	// for this demo, we don't know where we can expect to find actual slides
	// the getFirstNonEmptyDirectory() method wraps around recursive calls to getDirectories() and is useful to "just" find a bunch of slides in "just" any folder
	$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
	echo "Looking for slides in ".$dir.":".$newline;
	$slides = Core::getSlides($dir, $sessionID);
	foreach ($slides as $slide) {
		echo $slide.$newline;
	}
	Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well
}

finish();
```
