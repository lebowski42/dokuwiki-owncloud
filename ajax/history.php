<?php
/**
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */

if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $conf and classpathes
require_once(DOKU_INC.'inc/init.php');


// db access

$helper = new helper_plugin_owncloud(false);
if(isset($_POST['file']) && $_POST['file'] != ''){
	echo $helper->mediaMetaAsTable($_POST['file'],true);
}
else{
	echo $helper->getLang('nothingfound');
}


?>




