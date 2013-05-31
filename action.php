<?php
/**
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
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

	// constants
	const FILEUPDATE = 1;
	const FILEREMOVE = 2;
	const NSREMOVE = 3;
	const WIKISTORAGE = 'wiki';
	

	/**
	 * Register all hooks
	*/
	function register(&$contr) {
		$contr->register_hook('IO_WIKIPAGE_WRITE','BEFORE',$this,'write');
		//$contr->register_hook('PARSER_WIKITEXT_PREPROCESS','BEFORE',$this,'preprocess');
		$contr->register_hook('MEDIA_UPLOAD_FINISH','AFTER',$this,'filecache',self::FILEUPDATE);
		$contr->register_hook('MEDIA_DELETE_FILE','AFTER',$this,'filecache',self::FILEREMOVE);
		$contr->register_hook('IO_NAMESPACE_DELETED','AFTER',$this,'filecache',self::NSREMOVE);
		$contr->register_hook('RENDERER_CONTENT_POSTPROCESS','AFTER',$this,'mediaOnThisPage');
		$contr->register_hook('TPL_ACT_RENDER','AFTER',$this,'mediaOnThisPageREV');
		

		
	}
    
    /**
    * Returns the path for the correspondig fileID (from table OC_filecache)
    * Adds a list with all media used on this page to the end of the page
    * Skips older revisions.
    */
    function mediaOnThisPage(&$event, $param){
		global $ID;
		global $ACT;
		global $REV;
		$d = $event->data[1];
		//$event->data[1] .="<h1>Dies ist noch zu sehend</h1>";
		if($ACT != 'show') return false;
		if($REV != 0) return false; // TPL_ACT_RENDER for older revisions
		if(!page_exists($ID)) return false;		
		
		$helper = $this->loadHelper('owncloud',false);
		$list = $helper->getMediaOfThisPage($ID);
		if($list == 0) return false;
		$event->data[1] .= '<h1 id="headingusedmedia">'.$this->getLang('filesOnThisSide').'</h1>';
		$event->data[1] .= $list;
		
	}
	
	/**
    * Returns the path for the correspondig fileID (from table OC_filecache)
    * Adds a list with all media used on this page to the end of the page
    * Skips older revisions.
    */
	function mediaOnThisPageREV(&$event, $param){
		global $REV;
		global $ID;
		if($REV == 0) return false; // see above
		/* TODO this may not be the real media used on this page. Effectively
		   this are the used media of the current page*/
		echo '<h1 id="headingusedmedia">'.$this->getLang('filesOnThisSide').'</h1>';
		$helper = $this->loadHelper('owncloud',false);
		echo $helper->getMediaOfThisPage($ID);
	}

    /*
     * Makes sure, that files uploaded or delete using the DokuWiki mediamanager 
     * are updated in the ownCloud database.
     */
    function filecache(&$event, $param){
		global $conf;
		global $INFO;
		require_once($this->getConf('pathtoowncloud').'/lib/base.php');
		$file = str_replace($conf['mediadir'],'/'.self::WIKISTORAGE,$event->data[1]);
		OC\Files\Filesystem::init($_SERVER['REMOTE_USER'],'/'.$_SERVER['REMOTE_USER'].'/files');

		switch($param){
				case self::FILEUPDATE:	$file = str_replace($conf['mediadir'],'/'.self::WIKISTORAGE,$event->data[1]);
										OC\Files\Cache\Updater::writeUpdate($file);
										$this->fixDescription(str_replace($conf['mediadir'],'',$event->data[1]),2);// 2, because the medialog entry is already written
										//OCA\DokuWiki\Storage::dokuwikiUploadFinish($file,$_SERVER['REMOTE_USER'],!$event->data[4]);
										break;
				case self::FILEREMOVE:	$file = str_replace($conf['mediadir'],'/'.self::WIKISTORAGE,$event->data['path']);
										OC\Files\Cache\Updater::deleteUpdate($file);
										break;
				case self::NSREMOVE:	if($event->data[1] == 'media'){
											$dir = '/'.self::WIKISTORAGE.'/'.str_replace(':','/',$event->data[0]);
											OC\Files\Cache\Updater::deleteUpdate($dir);
										}
										break;
											
				
										
		}
		 
	}
    
    /**
     * Add fileid or update filepath
     *
     * If file exists in the ownCloud database, the fileid will be add to the file parameters,
     * if fileid exists as parameter, the path will be updated (if necessary)
     * Changes will be write to disk
     * 
     */
	function write(&$event, $param){
		
		$text = $event->data[0][1];
		$helper = $this->loadHelper('owncloud',false);
		global $ID;
		$helper->dbQuery('DELETE FROM `*PREFIX*dokuwiki_media_use` WHERE `wikipage_hash` = ?', array(md5($ID)));
		$event->data[0][1] = preg_replace('#\{\{(.+)\}\}#Uise', "'{{'.action_plugin_owncloud::buildLink('\\1',true).'}}'",$text);
	}
	
	/**
     * The same as write, but only for preprocess, e. g. when choose preview.
     * 
     */
	function preprocess(&$event, $param){
		$text = $event->data;
		global $ACT;
		echo "<h1>$ID</h1>";
		$event->data = preg_replace('#\{\{(.+)\}\}#Uise', "'{{'.action_plugin_owncloud::buildLink('\\1',false).'}}'",$text);
	}

	
	
	/**
     * Build the link with fileid or update the filepath if fileid is given.
     * @param string $rawdata the wikitext
     * 
     */
	function buildLink($rawdata, $write = false){
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
		
		if($fileid > 0){ // Then find filename from id
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
		
		if($write) $this -> mediaUse($fileid,&$helper);
		$param = preg_replace('#fileid=(\d+)?#i',"fileid=$fileid",$param,-1,$count);
		if($fileid!='' && $fileid > 0 && $count < 1) $param = (($param != "") ? "$param&fileid=$fileid":"fileid=$fileid");
		return (($ralign)?" ":"").":".$src.(($param != "") ? "?$param":"").(($lalign)?" ":"")."|".$desc;
		//return (($ralign)?" ":"").$src.(($param != "") ? "?$param":"").(($lalign)?" ":"")."|".$desc."|".$fileid;
	}
	
	/**
	 * Adds a fileid to and the wikiid to table dokuwiki_media_use
	 */
	function mediaUse($fileid, $helper){
		global $ID;
		global $INFO;
		$heading = (is_array($INFO['meta']['description']['tableofcontents']))?$INFO['meta']['description']['tableofcontents'][0]['title']:'';
		$helper->dbQuery('INSERT IGNORE INTO `*PREFIX*dokuwiki_media_use` (`fileid`, `wikipage`,`wikipage_hash`,`firstheading`) VALUES (?,?,?,?)', array($fileid, $ID, md5($ID), $heading));
	}
	
	/**
     * Put the last description/summary as new description (if it is not 'created' or 'deleted')
     */
	function fixDescription($file,$x=1){
		global $conf;
		global $lang;
		$file = $conf['mediametadir'].'/'.$file.'.changes';
		if(file_exists($file)){
			$meta = file($file);
			$desc = '';
			$lines = 0;
			if(!empty($meta)) $lines = count($meta);
			if($lines >= $x ){
				$xLine = $meta[$lines-$x];
				$line = explode("\t", $xLine);
				$desc = (isset($line[5]))?trim($line[5]):'';
				if($desc == $lang['created'] || $desc == $lang['deleted'] ) $desc='';
				// Set desc for the last line
				$strip = array("\t", "\n");
				$lastLine = array_pop($meta);
				$line = explode("\t", $lastLine);
				$line[5] = utf8_substr(str_replace($strip, ' ',htmlspecialchars($desc)),0,255);
				array_push($meta,trim(implode("\t", $line))."\n");
				io_saveFile($file,implode("", $meta));
				return true;
			}
		}
		return false;
	}
}
