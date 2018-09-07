# **Test for PMA.core.lite (PMA.start)**


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Test for PMA.core.lite (PMA.start)
```
echo "Are you running PMA.core.lite? ".(Core::isLite() ? "Yes!": "no :-(").$newline;

echo "Seeing 'no' and want to see 'yes'? Make sure PMA.start is running on your system or download it from ".href('http://free.pathomation.com').$newline;
```
