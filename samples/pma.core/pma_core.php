<?php
/*
Setup
*/

// load pma_php library
require "../../src/lib_pathomation.php"; 	// PMA.php library
require "../lib_config.php"; 				// only needed for the purpose of these demos

use Pathomation\PmaPhp\Core;

echo "pma_php library loaded; version " . Core::$__version__ . $newline;

// connection parameters to be used throughout this notebook
$pma_core_server = "https://snapshot.pathomation.com/PMA.core_3.0.0.f47dcd30/";
$pma_core_user = "pma_admin";
$pma_core_pass = "P4th0-M4t!on";
$pma_core_slide_dir = "hgx_cases/bladder/10440001B";

if (!Core::isLite($pma_core_server)) {
    echo "PMA.core found. Good".$newline;
} else {
    throw new Exception("Unable to detect PMA.core! Please update configuration parameters in this block");
}

Core::setDebugFlag(true);
echo $newline;

echo Core::getVersionInfo($pma_core_server);
die();

/*
PMA.core examples
*/

/* Example 10: identifying PMA.core */

// testing actual "full" PMA.core instance that may or may not be out there
echo Core::isLite($pma_core_server);
echo "Are you running PMA.core  at $pma_core_server ? ".(Core::isLite($pma_core_server) !== null ? "yes": "no").$newline;
echo "Are you running PMA.start(PMA.core.lite) at http://nowhere ? ".( Core::isLite("http://nowhere") === true ? "yes": "no").$newline;
echo $newline;

/* Example 20: getting version information about PMA.core */
echo "Investigating " . $pma_core_server . $newline;
echo "You are running PMA.core version ".Core::getVersionInfo($pma_core_server)." at ".$pma_core_server.$newline;

// what happens when we run it against a bogus URL?
$version = Core::getVersionInfo("http://nowhere/");
if ($version == null) {
    echo "Unable to detect PMA.core at specified location (http://nowhere/)".$newline;
} else {
    echo "You are running PMA.core version ".$version.$newline;
}
echo $newline;

/* Example 30: connect to PMA.core */
$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

if ($sessionID == null) {
    echo "User [$pma_core_user] was unable to connect to PMA.core at specified location ($pma_core_server); password used = [$pma_core_pass]".PHP_EOL;
} else {
    echo "Successfully connected to PMA.core; sessionID = $sessionID";
}
echo $newline;

/* example 40: getting root-directories from PMA.core */
echo "You have the following root-directories on your system:".$newline;
$rootdirs = Core::getRootDirectories($sessionID);
foreach ($rootdirs as $rd) {
    echo "[$rd]".$newline;
}
echo $newline;

/* example 50: disconnect from PMA.core */

// Core::disconnect($sessionID);  // not always needed in a PHP context; depends on whether the client (e.g. browser) still needs to SessionID as well

$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

print_r(Core::whoAmI($sessionID));
echo $newline;

Core::Disconnect($sessionID);
try {
    print_r(Core::whoAmI($sessionID));
    echo $newline;
}
catch(exception $ex) {
    echo "Unable to identify you. Are you connected to the PMA.core instance? Is that a valid sessionID?";
}
echo $newline;

$sessionID = Core::Connect($pma_core_server, $pma_core_user, $pma_core_pass);

/* Example 60: getting directories from PMA.core */
$rootdirs = Core::getRootDirectories();
echo "Directories found in ".$rootdirs[0].":".$newline;

$dirs = Core::getDirectories($rootdirs[0], $sessionID);
foreach ($dirs as $d) {
    print($d.$newline);
}
echo $newline;

/* example 70: get first non empty directory PMA.core */
$slide_dir = Core::getFirstNonEmptyDirectory();
echo $slide_dir.$newline;
echo $newline;

/* example 80: getting slide PMA.core */
$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
echo "Looking for slides in ".$dir.":".$newline;
    echo "**Non-recursive:\n";
$slides = Core::getSlides($dir, $sessionID);
foreach ($slides as $slide) {
    echo $slide.$newline;
}

echo "\n**One-level deep recursion:".$newline;
print_r(Core::getSlides($dir, $sessionID, $recursive = 1));

echo "\n**Full recursion:".$newline;
print_r(Core::getSlides($dir, $sessionID, $recursive = True));

/* example 90: get UID for a slide in PMA.core */
$dir = Core::getFirstNonEmptyDirectory("/", $sessionID);
echo "Looking for slides in ".$dir.$newline;
foreach (Core::getSlides($dir, $sessionID) as $slide) {
    echo $slide." - ".Core::getUID($slide, $sessionID).$newline;
}

/* example 100: get fingerprint in PMA.core */

$slide_dir = $pma_core_slide_dir;
echo "Looking for slides in " . $slide_dir . $newline;
    
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo $slide." - ".Core::getFingerPrint($slide, $sessionID).$newline;
}

/* 	example 110: SlideInfo PMA.core */


$slide_dir = $pma_core_slide_dir;
echo "Looking for slides in " . $slide_dir . $newline;
    
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo "*** ". $slide;
    try {
        echo $slide." - ".print_r(Core::getSlideInfo($slide, $sessionID), true).$newline;
    }
    catch (exception $ex){
        echo "**Unable to get slide info from this one";
    }
}

print_r(Core::getTileSize($sessionID));
/* # example 120: slide dimensions PMA.core */

$slide_dir = $pma_core_slide_dir;
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo "[".$slide."]".$newline;
    try {
        $pixelSize = Core::getPixelDimensions($slide, null, $sessionID);
        $physicalSize = Core::getPhysicalDimensions($slide, null, $sessionID);
        echo "Pixel dimensions of slide: " . $newline;
        echo $pixelSize[0]."x".$pixelSize[1] . $newline;
        echo "Slide surface area represented by image: ".$newline;
        echo $physicalSize[0]."um x " . $physicalSize[1] . "um = ";
        echo $physicalSize[0] * $physicalSize[1] / 1E6. " mm2".$newline;
    }
    catch (exception $ex) {
        echo "**Unable to parse ". $slide;
    }
}

/* example 130: get all files that make up a particular slide */
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo $slide.$newline;
    // print_r(Core::getFilesForSlide($slide, $sessionID));
}

/* # example 140: who are you in PMA.core */
Core::whoAmI($sessionID);

/* # example 150: investigate zoomlevels PMA.core */
$slide_dir = $pma_core_slide_dir;
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo "*** " . $slide . $newline;
    echo "  max zoomlevel:". Core::GetMaxZoomlevel($slide, $sessionID) . $newline;
    echo "  zoomlevel dictionary:" . $newline;
    print_r(Core::getZoomlevelsDict($slide, $sessionID, 0));
}

/* example 160: investigate magnification and other characteristics PMA.core */

$slide_dir = $pma_core_slide_dir;
$slide_infos = [];

foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    $slide_infos[] = array(
    "slide" => $slide,
    "approx_mag" => Core::getMagnification($slide, null, false, $sessionID),
    "exact_mag" => Core::getMagnification($slide, null, true, $sessionID),
    "is_fluo" => Core::isFluorescent($slide, $sessionID),
    "is_zstack" => Core::isZStack($slide, $sessionID)
    );
}

print_r($slide_infos);

/* # example 170: get barcode from slide in PMA.core */
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo $slide.$newline;
    print_r(Core::getBarcodeText($slide, $sessionID));
}

/* example 190: slide label (URL) in PMA.core */

foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo Core::getLabelUrl($slide, $sessionID) . $newline;
}

/* example 200: slide label (URL) in PMA.core (using barcode alias methods) */
foreach (Core::getSlides($slide_dir, $sessionID) as $slide) {
    echo Core::getBarcodeUrl($slide, $sessionID) . $newline;
}

/* example 220: retrieving individual tiles in PMA.core */
$slide_dir = $pma_core_slide_dir;
$slide = Core::getSlides($slide_dir, $sessionID)[0];
for ($zl = 0; $zl < Core::getMaxZoomLevel($slide); $zl++) {
    $xyTotal = Core::getNumberOfTiles($slide, $zl, $sessionID);
    if ($xyTotal[2] > 16 && $xyTotal[0] >4 && $xyTotal[1] > 4) {
        break;
    }

    for ($i = 0;$i < 18;$i++) {
        $xr = 1 + ($i - 1) % 4;
        $yr = (int)(($i - 1) / 4) + 1;
        echo Core::getTile($slide, $xr, $yr, $zl, $sessionID) . $newline;
    }
}


/* example 230: searching for slides in PMA.core */
$slides = Core::searchSlides($pma_core_slide_dir, "mrxs", $sessionID);
print_r($slides);

/* example 240: search for folders in PMA.core */

$slides = Core::searchSlides($pma_core_slide_dir, "bladder", $sessionID);
print_r($slides);

?>