<?php
// modify the following three lines for your specific circumstances:
$pma_core_server = "http://server/core";
$pma_core_user = "user";
$pma_core_pass = "secret";

$aws_key = "";
$aws_secret = "";

// stay away from the lines below; they include helper function to allow sample code to run in CLI as well as web-mode.
if (!defined('STDIN')) {
	$parts = explode(DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']);
	
	$more_parts = explode(" ", end($parts));
	array_shift($more_parts);
	
	$even_more_parts = explode(".", implode(" ", $more_parts));
	array_pop($even_more_parts);
		
	$title = implode(".", $even_more_parts);
	?>
<html>
	<head>
		<title><?php echo $title;?></title>
		<link rel="icon" type="image/png" sizes="32x32" href="http://free.pathomation.com/Content/img/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="http://free.pathomation.com/Content/img/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="http://free.pathomation.com/Content/img/favicon-16x16.png">
	</head>
	<body>
	
<?php	
}

$newline = (defined('STDIN') ? "\n": "<br />");

function href($href) {
	return defined('STDIN') ?
		$href:
		"<a href='$href'>$href</a>";
}

function finish() {
	if (!defined('STDIN')) {
		echo "
		<br/><br/>
        <a href='../index.htm'>Back to PMA.php sample overview</a>
	</body>
</html>";
	} else {
		global $newline;
		echo $newline;
	}
}
?>