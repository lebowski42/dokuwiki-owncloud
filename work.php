<?php
/**
 * Reads the path for a mediafile from the owncloud database identified by the fileID 
 * submitted by &fileid=... . Then redirects to fetch.php
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */



error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');

// Prepare
if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $INPUT and $conf
require_once(DOKU_INC.'inc/init.php');



$text = "sdfsdf{{wiki:dokuwiki-128.png|beschreibung|6}}sdfsd{{wiki:dokuwiki-128.png|beschreibung|5}}f";

 //$inhalt = preg_replace('#\[url=(.*)\](.*)\[/url\]#Uis', '<a href="\1">\2</a>', $text);
 $inhalt = preg_replace('#\{\{(.+)\}\}#Uise', "'{{'.buildLink('\\1').'}}'", $text);

echo $inhalt;
//echo "<h1>".buildLink("wiki:dokuwiki-128.png?200&direcrt&nocache|Beschreibung|6")."</h1>";

function buildLiwnk($rawdata){
	$parts = explode("|",$rawdata);
	$link = array_shift($parts);
	//split into src and parameters (using the very last questionmark) from /inc/parser/handler.php
    $pos = strrpos($link,'?');
    if($pos != false){
        $src   = substr($link,0,$pos);
        $param = substr($link,$pos+1);
    }else{
        $src   = $link;
        $param = '';
    }
	// get fileID
	if(count($parts) > 1  ){ // We've a fileid
		$last = array_pop($parts);
		$fileid = intval($last); // Last element maybe fileid
		$desc = implode("|",$parts); // The rest is the description, can contain |
		if($fileid == 0 ) $desc .= "|".$last; // no fileid, is part of description
	}else{
		$fileid = 0;
		$desc = array_shift($parts);
	}
	// db access
	$helper = new helper_plugin_cloud();
    if($fileid > 0){ // Then find source from id
		$path = $helper->getFilenameForID($fileid);
		if($path != ""){
			$src = str_replace('/',':',$path);
			$notfound = false;
	    }else{
			$notfound = true;
		}
	}else{
		$notfound = true;
	}
	// We have no file from id, look for id using source
	if($notfound){ // Try to find ID from source
		$path = str_replace(':','/',$src);
		$fileid = $helper->getIDForFilename($path);
	}
    
	return $src.(($param != "") ? "?$param":"")."|".$desc."|".$fileid;
}

