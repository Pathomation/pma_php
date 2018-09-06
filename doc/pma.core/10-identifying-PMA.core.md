# **Identifying PMA.core**

- [Load library](#load-library)
- [Identifying PMA.core](#identifying-pma.core)


## Load library
```
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php";

use Pathomation\Core;
```


## Identifying PMA.core
```
// testing actual "full" PMA.core instance that may or may not be out there
echo "Are you running PMA.start(PMA.core.lite) at $pma_core_server ? ".( Core::isLite($pma_core_server) === TRUE ? "yes": "no").$newline;
echo "Are you running PMA.start(PMA.core.lite) at http://nowhere ? ".( Core::isLite("http://nowhere") === TRUE ? "yes": "no").$newline;

finish();
```
