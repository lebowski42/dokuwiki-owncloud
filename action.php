<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */
//error_reporting (E_ALL | E_STRICT);  
//ini_set ('display_errors', 'On');


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
		global $ID;
		$helper = $this->loadHelper('owncloud',false);
		if(!$helper) return $rawdata;
		$parts = explode("|",$rawdata);
		$link = array_shift($parts);
		// Save alignment
		$ralign = (bool)preg_match('/^ /',$link);
		$lalign = (bool)preg_match('/ $/',$link);
		// delete whitespaces
		$link = trim($link);
		if($helper->isExternal($link)) return $rawdata;
		//split into src and parameters (using the very last questionmark) from /inc/parser/handler.php
		$pos = strrpos($link,'?');
		if($pos != false){
			$src   = substr($link,0,$pos);
			$param = substr($link,$pos+1);
		}else{
			$src   = $link;
			$param = '';
		}
		if(preg_match('#fileid=(\d+)?#i',$param,$match)){
			($match[1]) ? $fileid = intval($match[1]) : $fileid = 0;
		}else{
			$fileid = 0;
		}
		$desc = implode("|",$parts);
		// get fileID
		/*if(count($parts) > 1  ){ // We've a fileid
			$last = array_pop($parts);
			$fileid = intval($last); // Last element maybe fileid
			$desc = implode("|",$parts); // The rest is the description, can contain |
			if($fileid == 0 ) $desc .= "|".$last; // no fileid, is part of description
		}else{
			$fileid = 0;
			$desc = array_shift($parts);
		}*/
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
			$oldsrc = $src;
			resolve_mediaid(getNS($ID),$src, $exists);
			if($exists){
				$fileid = $helper->fileIDForWikiID($src);
			}else{// Maybe directory
				$fileid = $helper->fileIDForWikiID($oldsrc);
				if($fileid != '' && $fileid > 0) $src = $oldsrc;
			}
			
			if($fileid == '' || $fileid < 1) return $rawdata;
		}
		$param = preg_replace('#fileid=(\d+)?#i',"fileid=$fileid",$param,-1,$count);
		if($fileid!='' && $fileid > 0 && $count < 1) $param = (($param != "") ? "$param&fileid=$fileid":"fileid=$fileid");
		return (($ralign)?" ":"").":".$src.(($param != "") ? "?$param":"").(($lalign)?" ":"")."|".$desc;
		//return (($ralign)?" ":"").$src.(($param != "") ? "?$param":"").(($lalign)?" ":"")."|".$desc."|".$fileid;
	}
}
