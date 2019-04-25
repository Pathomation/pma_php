<?php
/**
The file contains classes that wrap around various components of Pathomation's software platform for digital microscopy
More information about Pathomation's free software offering can be found at http://free.pathomation.com
Commercial applications and tools can be found at http://www.pathomation.com
*/

namespace Pathomation;

/**
Wrapper around PMA.UI JavaScript framework
*/
class UI {
	public static $_pma_start_ui_javascript_path = "http://localhost:54001/Scripts/pmaui/";
	public static $_pma_ui_javascript_path = "pma.ui/";
	private static $_pma_ui_framework_embedded = false;
	private static $_pma_ui_viewport_count = 0;
	private static $_pma_ui_viewports = [];
	private static $_pma_ui_gallery_count = 0;
	private static $_pma_ui_galleries = [];
	private static $_pma_ui_loader_count = 0;
	private static $_pma_ui_loaders = [];
	
	/** internal helper function to prevent PMA.UI framework from being loaded more than once */
	private static function _pma_embed_pma_ui_framework($sessionID) {
		if (!self::$_pma_ui_framework_embedded) {
			if (!pma::ends_with(self::$_pma_ui_javascript_path, "/")) {
				self::$_pma_ui_javascript_path .= "/";
			}
			echo "<!-- include PMA.UI script & css -->\n";
			echo "<script src='".self::$_pma_ui_javascript_path."pma.ui.view.min.js' type='text/javascript'></script>\n";
			echo "<link href='".self::$_pma_ui_javascript_path."pma.ui.view.min.css' type='text/css' rel='stylesheet'>\n";
			echo "<!-- include PMA.UI.components script & css -->\n";
			echo "<script src='".self::$_pma_ui_javascript_path."PMA.UI.components.all.min.js' type='text/javascript'></script>\n";
			echo "<link href='".self::$_pma_ui_javascript_path."PMA.UI.components.all.min.css' type='text/css' rel='stylesheet'>\n";
			echo "<script>var pma_ui_context = new PMA.UI.Components.Context({ caller: 'PMA.PHP UI class' });</script>";
			self::$_pma_ui_framework_embedded = true;
		}
	}	
	
	/** output HTML code to display a single slide through a PMA.UI viewport control
		authentication against PMA.core happens through a pre-established SessionID */
	public static function embedSlideBySessionID($server, $slideRef, $sessionID, $options = null) {
		self::_pma_embed_pma_ui_framework($sessionID);
		self::$_pma_ui_viewport_count++;
		$viewport_id = "pma_viewport".self::$_pma_ui_viewport_count;
		self::$_pma_ui_viewports[] = $viewport_id;
		?>
		<div id="<?php echo $viewport_id; ?>"></div>
		<script type="text/javascript">
			// initialize the viewport
			var <?php echo $viewport_id; ?> = new PMA.UI.View.Viewport({
				caller: "PMA.PHP UI class",
				element: "#<?php echo $viewport_id; ?>",
				image: "<?php echo $slideRef;?>",
				serverUrls: ["<?php echo $server;?>"],
				sessionID: "<?php echo $sessionID;?>",
				},
				function () {
					console.log("Success!");
				},
				function () {
					console.log("Error! Check the console for details.");
				});
		</script>
		<?php
		return $viewport_id;
	}

	/** output HTML code to display a single slide through a PMA.UI viewport control 
		authentication against PMA.core happens in real-time through the provided $username and $password credentials
		Note that the username and password and NOT rendered in the HTML output (authentication happens in PHP on the server-side).
	*/
	public static function embedSlideByUsername($server, $slideRef, $username, $password = "", $options = null) {
		$session = Core::connect($server, $username, $password);
		return self::embedSlideBySessionID($server, $slideRef, $session, $options);
	}

	/** output HTML code to display a gallery that shows all thumbnails that exist in a specific folder hosted by the specified PMA.core instance 
		authentication against PMA.core happens through a pre-established SessionID */
    public static function embedGalleryBySessionID($server, $path, $sessionID, $options = null) {
		self::_pma_embed_pma_ui_framework($sessionID);
		self::$_pma_ui_gallery_count++;
		$gallery_id = "pma_gallery".self::$_pma_ui_gallery_count;
		self::$_pma_ui_galleries[] = $gallery_id;
		?>
		<div id="<?php echo $gallery_id; ?>"></div>
		<script type="text/javascript">
			new PMA.UI.Authentication.SessionLogin(pma_ui_context, [{ serverUrl: "<?php echo $server; ?>", sessionId: "<?php echo $sessionID; ?>" }]);
			
			// create a gallery that will display the contents of a directory
			var <?php echo $gallery_id; ?> = new PMA.UI.Components.Gallery(pma_ui_context, {
				element: "#<?php echo $gallery_id; ?>",
				thumbnailWidth: 200,
				thumbnailHeight: 150,
				mode: "<?php echo (isset($options) && $options != null) ?  (isset($options["mode"]) ? $options["mode"]: "horizontal"): "horizontal"; ?>",
				showFileName: true,
				showBarcode: true,
				barcodeRotation: 180,
				filenameCallback: function (path) {
					// show the filename without extension
					return path.split('/').pop().split('.')[0];
				}
			});

			// load the contents of a directory
			<?php echo $gallery_id; ?>.loadDirectory("<?php echo $server; ?>", "<?php echo $path; ?>");
		</script>
		<?php
		return $gallery_id;
	}
	
	/** output HTML code to display a gallery that shows all thumbnails that exist in a specific folder hosted by the specified PMA.core instance 
		authentication against PMA.core happens in real-time through the provided $username and $password credentials
		Note that the username and password and NOT rendered in the HTML output (authentication happens in PHP on the server-side).
	*/
	public static function embedGalleryByUsername($server, $path, $username, $password = "", $options = null) {
		$session = Core::connect($server, $username, $password);
		return self::embedGalleryBySessionID($server, $path, $session, $options);
	}

	/** output HTML code to couple an earlier instantiated PMA.UI gallery to a PMA.UI viewport. The PMA.UI viewport can be instantiated earlier, or not at all */	
	public static function linkGalleryToViewport($galleryDiv, $viewportDiv) {
		// verify the validity of the $galleryDiv argument
		if (in_array($galleryDiv, self::$_pma_ui_viewports)) {
			throw new \BadMethodCallException("$galleryDiv is not a PMA.UI gallery (it's actually a viewport; did you switch the arguments up?)");
		}
		if (!in_array($galleryDiv, self::$_pma_ui_galleries)) {
			throw new \BadMethodCallException("$galleryDiv is not a valid PMA.UI gallery container");
		}

		// verify the validity of the $viewportDiv argument
		if (in_array($viewportDiv, self::$_pma_ui_galleries)) {
			throw new \BadMethodCallException("$viewportDiv is not a PMA.UI viewport (it's actually a gallery; did you switch the arguments up?)");
		}
		
		self::$_pma_ui_loader_count++;
		$loader_id = "pma_slideLoader".self::$_pma_ui_loader_count;
		self::$_pma_ui_loaders[] = $loader_id;
		
		if (!in_array($viewportDiv, self::$_pma_ui_viewports)) {
			// viewport container doesn't yet exist, but this doesn't have to be a showstopper; just create it on the fly
			self::$_pma_ui_viewports[] = $viewportDiv;
			self::$_pma_ui_viewport_count++;
			?>
			<div id="<?php echo $viewportDiv; ?>"></div>
		<?php
		}
		?>
		<script>
        // create an image loader that will allow us to load images easily
        var <?php echo $loader_id; ?> = new PMA.UI.Components.SlideLoader(pma_ui_context, {
            element: "#<?php echo $viewportDiv; ?>",
            theme: PMA.UI.View.Themes.Default,
            overview: {
                collapsed: false
            },
            // the channel selector is only displayed for images that have multiple channels
            channels: {
                collapsed: false
            },
            // the barcode is only displayed if the image actually contains one
            barcode: {
                collapsed: false,
                rotation: 180
            },
            loadingBar: true,
            snapshot: true,
            digitalZoomLevels: 2,
            scaleLine: true,
            filename: true
        });

        // listen for the slide selected event to load the selected image when clicked
        <?php echo $galleryDiv; ?>.listen(PMA.UI.Components.Events.SlideSelected, function (args) {
            // load the image with the image loader
            <?php echo $loader_id; ?>.load(args.serverUrl, args.path);
        });
		</script>
		<?php
	}

}
