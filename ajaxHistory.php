<?php
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');



if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $conf and classpathes
require_once(DOKU_INC.'inc/init.php');


// db access

$helper = new helper_plugin_owncloud(false);
if(isset($_POST['file']) && $_POST['file'] != ''){
echo $_POST['file'];
	echo $helper->mediaMetaAsList($_POST['file'],false);
}
else{
	echo "Nothing found";
}


?>




