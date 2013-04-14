<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');


// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_owncloud extends DokuWiki_Action_Plugin{

	
	function register(&$contr) {
		$contr->register_hook('IO_WIKIPAGE_WRITE','BEFORE',$this,'write');
		$contr->register_hook('PARSER_WIKITEXT_PREPROCESS','BEFORE',$this,'preprocess');
		
	}
    
	function write(&$event, $param){
		$text = $event->data[0][1];
		$event->data[0][1] = preg_replace('#\{\{(.+)\}\}#Uise', "'{{'.action_plugin_owncloud::buildLink('\\1').'}}'",$text);
	}
	
	function preprocess(&$event, $param){
		$text = $event->data;
		$event->data = preg_replace('#\{\{(.+)\}\}#Uise', "'{{'.action_plugin_owncloud::buildLink('\\1').'}}'",$text);
	}

	
	function buildLink($rawdata){
		$helper = $this->loadHelper('owncloud',false);
		if(!$helper) return $rawdata;
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
			$path = trim($path,'/'); //Remove slashes at the beginning
			$fileid = $helper->getIDForFilename($path);
		}
		
		return $src.(($param != "") ? "?$param":"")."|".$desc."|".$fileid;
	}

}
