<?php
/**
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');



if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $conf and classpathes
require_once(DOKU_INC.'inc/init.php');


// db access

$helper = new helper_plugin_owncloud(true);
if(isset($_POST['fileid']) && !empty($_POST['fileid'])){
	echo $helper->fileInfoToString($_POST['fileid'],true);
}
else{
	echo $helper->getLang('nothingfound');
}


?>




