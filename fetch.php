<?php
/**
 * Reads the path for a mediafile from the owncloud database identified by the fileID 
 * submitted by &fileid=... . Then redirects to fetch.php
 *
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */

//error_reporting (E_ALL | E_STRICT);  
//ini_set ('display_errors', 'On');

// Prepare
if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $INPUT and $conf

require_once(DOKU_INC.'inc/init.php');

$xlink = '';

// Get parameters from url
$fileID = $INPUT->int('fileid');
$media = stripctl(getID('media', false));


// db access
$helper = new helper_plugin_owncloud();
$realmedia = $helper->getFilenameForID($fileID,true);

/*
echo "<hr>";
echo '$fileID:'.$fileID."<br>";
echo '$media:'.$media."<br>";
echo '$realmedia:'.$realmedia."<br>";
echo '$queryString:'.$queryString."<br>";

echo "<hr>"; 
*/

// rebuild the given url-query
$queryString = $_SERVER['QUERY_STRING'];
// if there is an entry in the database for this id, try the given path
if(!empty($realmedia)) {
	$queryString = str_replace($media,$realmedia,$queryString);
}else{
	$realmedia = $media;
}


   

// follow the rewrite-mode (from function ml(...), see /inc/common.php)
// Webserver- or dokuwiki-/no rewrite? 
if($conf['userewrite'] == 1) {
	$script = '_media';
} else {
	$script = 'lib/exe/fetch.php';
}
// build URL based on rewrite mode
if($conf['userewrite']) {
	$xlink .= $script.'/'.$realmedia;
	if($queryString) $xlink .= '?'.$queryString;
} else {
	if($queryString) {
		$xlink .= $script.'?'.$queryString;
	} else {
		$xlink .= $script.'?media='.$realmedia;
	}
}
 
/*
echo "<hr>";
echo '$script:'.$script."<br>";
echo '$xlink:'.$xlink."<br>";
echo '$queryString:'.$queryString."<br>";
echo '$realmedia:'.$realmedia."<br>";
echo "<hr>";
*/
session_write_close(); //close session, we want use header()

//redirect to original fetch.php
header("Location: ".DOKU_URL.$xlink);

    

