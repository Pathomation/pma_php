# **Get version information**

- [Load library](#load-library)
- [Get version information](#get-version-information)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Get version information
```
if (!Core::isLite()) {
	// don't bother running this script if PMA.start isn't active
	echo "PMA.start is not running. Please start PMA.start first";
} else {
	// assuming we have PMA.start running; what's the version number?
	echo "You are running PMA.start version ".Core::getVersionInfo();
}

finish();
```
