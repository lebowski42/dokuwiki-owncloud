<?php
/**
 * This class is a backend to use the ownCloud database while running DokuWiki
 * You can have a look to <ownCloud-Path>/lib/db.php for functions provided.
 * Here is a description for developer to using the ownCloud database
 * http://doc.owncloud.org/server/5.0/developer_manual/app/appframework/database.html
 * 
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

//constants
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
if(!defined('DOKU_CHANGE_TYPE_MOVE')) define('DOKU_CHANGE_TYPE_MOVE','M');
if(!defined('DOKU_CHANGE_TYPE_REPLACE')) define('DOKU_CHANGE_TYPE_REPLACE','R');


class helper_plugin_owncloud extends DokuWiki_Plugin 
{
	// The last query of type PDOStatementWrapper (see <ownCloud-Path>/lib/db.php)
	protected $lastQuery;
	protected $storageNr;
	protected $lastfileid;
	public $wiki = 'wiki';
	
	// id => filename
	protected $fileIDCache = array();
	// filename => id
	protected $filenameCache = array();
    
    /**
     * Constructor to connect to db and check if ownCloud is installed 
     */
    public function helper_plugin_owncloud($db=true) {
		if($db){
			global $conf;
			include_once($this->getConf('pathtoowncloud').'/lib/base.php');
			// Check if ownCloud is installed or in maintenance (update) mode
			if (!OC_Config::getValue('installed', false)) {
				global $conf;
				require_once('lang/'.$conf['lang'].'/settings.php');
				echo $lang['owncloudNotInstalled'];
				exit();
			}
			// Find Storage ID
			$this->dbQuery('SELECT numeric_id FROM `*PREFIX*storages` WHERE `id` LIKE ?', array('%'.$conf['mediadir'].'/'));
			$this->storageNr = $this->lastQuery->fetchOne();
		}
    }
    
    /**
    * Prepares and executes a SQL query
    *
    * @param $sql String The SQL-Query (for syntax see http://doc.owncloud.org/server/5.0/developer_manual/app/appframework/database.html)
    * @param $params array The parameters should be used in the sql-query
    */
    public function dbQuery($sql, $params){
		$db = \OC_DB::prepare($sql);
		$this->lastQuery = $db->execute($params);
				
	}
	
	
	// Returns the last query (last call of dbQuery)
	public function getLastQuery(){
		return $this->lastQuery;
	}
	
	// Returns the last fileid
	public function getLastfileid(){
		return $this->lastfileid;
	}
	
	/**
    * Returns the path for the correspondig fileID (from table OC_filecache)
    *
    * @param $id integer The fileID
    * @param $wikiID bool If true, slashes (/) will be replaced by colons (:)
    */
	public function getFilenameForID($id, $wikiID=false){
		if(isset($this->fileidCache[$id])){// save db query
			 $path = $this->fileidCache[$id];
		}else{
			$this->dbQuery('SELECT `path` FROM `*PREFIX*filecache` WHERE fileid = ? AND storage = ?', array($id, $this->storageNr));
			if($this->lastQuery->numRows() == 0) return NULL;
			$path = $this->lastQuery->fetchOne();
		}
		$this->fileidCache[$id] = $path;
		if($wikiID) return $this->pathToWikiID($path);
		return $path;
	}
	
	/** replace / with / */
	public function pathToWikiID($path){
		return str_replace('/',':',$path);
	}
	
	/** replace : with / */
	public function wikiIDToPath($id){
		return str_replace(':','/',$id);
	}
	
	/** Returns true if the given path is a directory */
	public function isMediaDir($path){
		global $conf;
		return is_dir($conf['mediadir'].'/'.trim($path,'/'));
	}

	
	/**
    * Returns the fileid for the correspondig path (from table OC_filecache)
    *
    * @param $file string The path
    * @return $id the fileID
    */
	public function getIDForFilename($file){
		$file = trim($file,'/'); //Remove slashes at the beginning
		// save db query
		if(isset($this->filenameCache[$file])) return $this->filenameCache[$file];
		$this->dbQuery('SELECT `fileid` FROM `*PREFIX*filecache` WHERE path = ? AND storage = ?', array($file, $this->storageNr));
		$id = $this->lastQuery->fetchOne();
		$this->filenameCache[$file] = $id;
		$this->lastfileid = $id;
		return $id;
	}
	
	/**
    * Returns the mimetype for the correspondig id (from table OC_filecache)
    *
    * @param $file string The path
    */
	public function getMimetypeForID($id){
		$this->dbQuery('SELECT *PREFIX*mimetypes.mimetype FROM *PREFIX*filecache JOIN *PREFIX*mimetypes ON *PREFIX*filecache.mimetype = *PREFIX*mimetypes.id WHERE fileid = ? AND storage = ?', array($id, $this->storageNr));
		return $this->lastQuery->fetchOne();
	}
	
	/**
    * Returns the content of a folder specified by its id (from oc database)
    *
    * @param $id string the folderID
    * @return @folderAndFiles folderID and filesID in the given dir
    */
	public function getFolderContent($id){
		$this->dbQuery('SELECT `fileid`, `path`,*PREFIX*mimetypes.mimetype FROM *PREFIX*filecache  JOIN *PREFIX*mimetypes ON *PREFIX*filecache.mimetype = *PREFIX*mimetypes.id WHERE parent=? AND storage = ? ORDER BY *PREFIX*filecache.path ASC', array($id, $this->storageNr));
		$rows = $this->lastQuery->numRows();
		$files = array();
		$folders = array();
		for($i = 1; $i <= $rows; $i++){
				$row = $this->lastQuery->fetchRow();
				if($row['mimetype'] == 'httpd/unix-directory'){
					array_push($folders, $row);
				}else{
					array_push($files, $row);
				}	
		}
		return array($folders,$files);
	}
	
	/**
    * Returns the media used on the page specified by $wikiid (from 
    * table OC_dokuwiki_media_used). It will be rendered as a orderd list
    *
    * @param $file string wikiid
    * @return $string orderd list of media used on the page
    */
	public function getMediaOfThisPage($wikiid){
		$this->dbQuery('SELECT `fileid`,`path` FROM `*PREFIX*dokuwiki_media_use` JOIN `*PREFIX*filecache` USING(`fileid`) WHERE `wikipage_hash` = ? ORDER BY `fileid` ASC', array(md5($wikiid)));
		$rows = $this->lastQuery->numRows();
		if(empty($rows) || $rows == 0) return false;
		$ret = '<p style="float:right;"><a href="#headingusedmedia" id="usemediadetail">'.$this->getLang('showfileinfo').'</a></p>';
		$ret .= DOKU_LF.'<ol id="usedmedia">'.DOKU_LF;
		$ids = $this->lastQuery;
		for($i = 1; $i <= $rows; $i++){
			$row = $ids->fetchRow();
			$ret .= DOKU_TAB.'<li class="mediaitem" fileid="'.$row['fileid'].'">';
			$ret .= $this->internalmedia($row['fileid'],"",(($row['path']!='')?$row['path']:'/'),NULL,16,NULL,NULL,'linkonly');
			$ret .= '</li>'.DOKU_LF;
		}
		$ret .= '</ol>'.DOKU_LF;
		return $ret;
		
	}
	
	/**
    * Returns formatted informations about the given $file or $id. The
    * informations contain the time, the number of versions, the authors
    * and the description. 
    *
    * @param $file full filename or id
    * @param $isid choose true, if the first parameter is a fileid
    * @return $string html with file informations
    */
	public function fileInfoToString($file, $isID = false){
		global $conf;
		if($isID) $file = $this->getFilenameForID($file);
		if($this->isMediaDir($file)) return '';
		list($authorsString,$desc,$count,$time) = $this->getAuthorsAndDescOfMediafile($file);
		if(empty($count)) $count = 0;
		return '<span class="filedesc" style=" font-size:90%;">'.
			   '<p style="margin-bottom:0px;padding-left:16px;"><span><b>'.$this->getLang('historyVersion').':</b>&nbsp;'.strftime($conf['dformat'],intval($time)).'&nbsp;('.$count.'&nbsp;'.($count == 1?$this->getLang('version'):$this->getLang('versions')).')</span></b>'.	
			   '<p style="margin-bottom:0px;padding-left:16px;"><span><b>'.$this->getLang('filelistAuthor').'</b>:&nbsp;'.$authorsString.'</span></p>'.
		       (($desc != '' && $desc != $lang['created'])?'<p style="margin-bottom:2px;padding-left:16px;"><b>'.$this->getLang('historyComment').'</b>:&nbsp;'.$desc.'</p>':'').'</span>';
	}
	
	
	
	
	/** Returns the authors of the given mediafile as string. If plugin authorlist is enabled,
	 *  authors will be linked as configured in this plugin. 
	 */
	public function getAuthorsAndDescOfMediafile($file){
		global $ID;
		if($this->getConf('linkAuthor') && !plugin_isdisabled('authorlist')){
			$authorlist = $this->loadHelper('authorlist',true);
			$authorlist->setOptions($ID,array('displayaslist'=>false));
		}
		$meta = $this->getMediaMeta($file);
		if(!empty($meta)){
			$authors = array(); 
			$line =array();
			foreach($meta as $onemeta){
				$line = explode("\t", $onemeta);
				if($line[4] != "" && !in_array($line[4],$authors)) array_push($authors,$line[4]);
			}
			if($authorlist){
				foreach($authors as &$author){
					$author=$authorlist->renderOneAuthor($author,$authorlist->getFullname($author));
				}
			}
			return array(implode(", ", $authors),$line[5],count($meta),$line[0]); // $line[5] is the latest filedescription
		}
		return '';
	}
	
	/** Returns the meta data of the given mediafile */
	public function getMediaMeta($file){
		global $conf;
		if(file_exists($conf['mediametadir'].'/'.$file.'.changes')) return file($conf['mediametadir'].'/'.$file.'.changes');
		return array();
	}
	
	/**
    * Builds a table for the mediameta information. The rest is done by
    * jQuery
    *
    * @param $file file to find the metadata for
    * @return $ret htmltable with included javascript
    */
	public function mediaMetaStart($file){
		global $lang;
		$ret = '<div class="historyOC">'.DOKU_LF;
		$ret .= DOKU_TAB.'<div class="table"><table  width="100%" class="inline">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr class="row0">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.DOKU_TAB.'<th class="col0" width="20%" >'.($this->getLang('historyVersion')).'</th><th  width="20%" class="col1">'.($this->getLang('historyAuthor')).'</th><th width="10%" class="col2">'.($this->getLang('filelistSize')).'</th><th class="col3" width="45%">'.($this->getLang('historyComment')).'</th>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'</tr>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr><td colspan="4" class="load"></td></tr>'.DOKU_LF;
		$ret .= DOKU_TAB.'</table></div></div>'.DOKU_LF;
		// To run javascript only if filelist is on this side
		$ret .= DOKU_TAB.'<script type="text/javascript">filehistory.start("'.$file.'");</script>'.DOKU_LF;
		$wikiid = $this->pathToWikiID($file);
		$ns = getNS($wikiid);
		$mediamanager = '<a class="mmLink" href="'.DOKU_URL.'doku.php?ns='.$ns.'&image='.$this->pathToWikiID($file).'&do=media&tab_details=history">'.$this->getLang('compare').'</a>';
		$ret .= '<p>'.$this->getLang('mediamanager_info').' '.$mediamanager.'</p>';
		return $ret;
		
		
	}
	
	/**
    * Builds the innards for the table generated by mediaMetaStart()
    * is called via jQuery.
    *
    * @param $file file to find the metadata for
    * @return $ret html-table-rows with media meta
    */
	public function mediaMetaAsTable($file){
		$ret = "";
		global $conf;
		global $ID;
		global $lang;
		$meta = $this->getMediaMeta($file);
		if(empty($meta)) return '<tr><td colspan="4" align="center">'.($this->getLang('noVersion')).'</td></tr>';
		$meta =  array_reverse($meta); // Newest first.
		$authorlist = false;
		if($this->getConf('linkAuthor') && !plugin_isdisabled('authorlist')){
			$authorlist = $this->loadHelper('authorlist',true);
			$authorlist->setOptions($ID,array('displayaslist'=>false));
		}
		$oldmedia = $conf['mediaolddir'];
		$nr = 1;
		$fetch = DOKU_BASE.'lib/exe/fetch.php';
		foreach($meta as $onemeta){
			$line = explode("\t", $onemeta);
			$time = strftime($conf['dformat'],intval($line[0]));
			if($nr > 1){ // in attic
				list($name, $ext) =  $this->filenameAndExtension($file);
				$path = $oldmedia.'/'.$name.'.'.$line[0].'.'.$ext;
				$link = '<a title="'.$time.'" href="'.$fetch.'?media='.($this->pathToWikiID($file)).'&rev='.$line[0].'" target="_blank">'.$time.'</a>';
			}else{
				$path = $conf['mediadir'].'/'.$file;
				$link = $time.' <small>('.$lang['current'].')<small>';;
				
			}
			$size = (file_exists($path)) ? filesize_h(filesize($path)) : '--';
			if(empty($line[4]))	$author = $line[1]; // IP if no author
			else $author = ($authorlist)?$authorlist->renderOneAuthor($line[4],$authorlist->getFullname($line[4])) : $line[4];
			//$author .= '<span class="ip">('.$line[1].')</span>';
			if($line[2] == DOKU_CHANGE_TYPE_MOVE) $extra = ' ('.$this->getLang('movedfrom').' '.hsc($line[6]).')';
			else $extra = '';
			
			
			$ret .= '<tr class=" row'.$nr.'">';
			$ret .= '<td class="col0" > '.$link.' </td><td class="col1">'.$author.'</td><td class="col2">'.$size.'</td><td class="col3">'.$line[5].$extra.'</td>';
			$ret .= '</tr>';
			$nr++;
		}
		return $ret;
		
		
	}
	
	/**
    * Returns the filename and extension (Can be replaced by php nativ
    * function pathinfo, only for compatibility
    *
    * @param $file file
    * @return @array filename and extension
    */
	public function filenameAndExtension($file){
			$extPos  = strrpos($file, '.');
			$ext = substr($file, $extPos + 1);
			$name = substr($file, 0, $extPos);
			return array($name, $ext);
	}
        
        
    /**
    * Returns true, if the given source starts with http, https or ftp
    * otherwise false 
    *
    * @param $src string
    * @return bool true if http(s)/ftp otherwise false
    */
    public function isExternal($src){
		if ( preg_match('#^(https?|ftp)#i',$src) ) {
			return  true;
		}
		return false;
	}
	
	/**
    * Tests if an url match the the urls or pattern specified in the
    * plugin configuration.
    *
    * @param $url URL
    * @return bool true if allowed
    */
	public function isAllowedExternalImage($url){
		if($this->getConf('allowExternalImages') == 1) return true; // all external images are allowed
		$allowedImagesURL = explode(',',$this->getConf('allowedImagesURL'));
		$match = false;
		foreach($allowedImagesURL as $allowedURL){
			$allowedURL = trim($allowedURL);
			if(empty($allowedURL)) continue;
			if(strpos($url, $allowedURL) === 0 ){
				$match = true;
				break;
			}
		}
		if($match) return true; // save some time, if we have a match until here
		$allowedImagesURLregexp = explode(',',$this->getConf('allowedImagesURLregexp'));
		foreach($allowedImagesURLregexp as $pattern){
			$pattern = trim($pattern);
			if(empty($pattern)) continue;
			if(preg_match('#'.str_replace('#','\\#',$pattern).'#i',$url)){
				$match = true;
				break;
			}
		}
		return $match;	
	}
   
    /** 
     * Renders an internal media link. This is nearly the same function as internalmedia(...)
     *  in inc/parser/xhtml.php extended to getting the filename from fileid. 
     * 
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
     */   
    public function internalmedia($fileid, $src, $title=NULL, $align=NULL, $width=NULL,$height=NULL, $cache=NULL, $linking=NULL) {
        global $ID;
        $filelist = false;
        list($src,$hash) = explode('#',$src,2);
        if($fileid != '' && $fileid > 0){
				$res = $this -> getFilenameForID($fileid, true);
		}
		if(isset($res)){
				$src = $res;
				if($src == ''){ // we are at the top
					$src = '/';
					if(empty($title)) $title = '/';
				}
				$exists = true;
		}else{
			$oldsrc = $src;
			resolve_mediaid(getNS($ID),$src, $exists);
			if($exists){
				$fileid = $this->fileIDForWikiID($src);
			}else{// Maybe directory
				$fileid = $this->fileIDForWikiID($oldsrc);
				if($fileid != '' && $fileid > 0){
					$src = $oldsrc;
					$exists = true;
				}
			}
		}
        $noLink = false;
        $render = ($linking == 'linkonly') ? false : true;
        // + fileid first parameter
        $link = $this->_getMediaLinkConf($fileid,$src, $title, $align, $width, $height, $cache, $render);

        list($ext,$mime,$dl) = mimetype($src,false);
        if(substr($mime,0,5) == 'image' && $render){
			// + fileid  & $this->
            $link['url'] = $this->ml($src,array('id'=>$ID,'cache'=>$cache,'fileid'=>$fileid),($linking=='direct'));
        }elseif($mime == 'application/x-shockwave-flash' && $render){
            // don't link flash movies
            $noLink = true;
        }else{
            // add file icons
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
            // + mimetype folder
            if(empty($ext)){
					if($this->getMimetypeForID($fileid) == 'httpd/unix-directory') {
							$class = 'folder';
							if($linking =='direct') $filelist = true;
					}
			}
            $link['class'] .= ' mediafile mf_'.$class;
            // + fileid  & $this->
            $link['url'] = $this->ml($src,array('id'=>$ID,'cache'=>$cache,'fileid'=>$fileid),($linking=='direct'));
            // + no size if directory
            if ($exists && $class != 'folder') $link['title'] .= ' (' . filesize_h(filesize(mediaFN($src))).')';
        }

        if($hash) $link['url'] .= '#'.$hash;

        //markup non existing files
        if (!$exists) {
            $link['class'] .= ' wikilink2';
        }
        //output formatted
        // + return instead of .=
        if ($linking == 'nolink' || $noLink){
			 return $link['name'];
		}else{
			if(!$filelist){
				return $this->_formatLink($link);
			}else{
				$link['name'] = $this->wikiIDToPath($src);
				return $this->filelist($fileid);
			}
		}
        
        
	}
	
	/** 
     * Renders an external media link. This is the same function as externalmedia(...)
     * in inc/parser/xhtml.php, here it returns the link and don't add it to the render doc
     * 
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @author Andreas Gohr <andi@splitbrain.org>
     */   
	function externalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
                            $height=NULL, $cache=NULL, $linking=NULL) {
        list($src,$hash) = explode('#',$src,2);
        $noLink = false;
        $render = ($linking == 'linkonly') ? false : true;
        // + -1 as fileid
        $link = $this->_getMediaLinkConf(-1,$src, $title, $align, $width, $height, $cache, $render);
		
		// use the originally ml function
        $link['url'] = ml($src,array('cache'=>$cache));

        list($ext,$mime,$dl) = mimetype($src,false);
        if(substr($mime,0,5) == 'image' && $render){
            // link only jpeg images
            // if ($ext != 'jpg' && $ext != 'jpeg') $noLink = true;
        }elseif($mime == 'application/x-shockwave-flash' && $render){
            // don't link flash movies
            $noLink = true;
        }else{
            // add file icons
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
            $link['class'] .= ' mediafile mf_'.$class;
        }

        if($hash) $link['url'] .= '#'.$hash;

        //output formatted
        if ($linking == 'nolink' || $noLink) return  $link['name'];
        else return  $this->_formatLink($link);
    }
    
    /** Renders a list with all files of the given folder. Using jQuery (see script.js).
     */ 
    public function filelist($folderid, $link){
		if(!isset($link)) $link = '<div class="filelistheader">'.($this->getLang('filelistHeader')).' '.$this->internalmedia($folderid,NULL,NULL, NULL, NULL,NULL, NULL, 'details').'</div>';
		$ret = '<div class="filelistOC fileid'.$folderid.'">'.$link.DOKU_LF;
		$ret .= DOKU_TAB.'<div class="table"><table  width="100%" class="inline">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr class="row0">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.DOKU_TAB.'<th class="col0">'.($this->getLang('filelistName')).'</th><th class="col1">'.($this->getLang('filelistAuthor')).'</th><th class="col2">'.($this->getLang('filelistDate')).'</th><th class="col3">'.($this->getLang('filelistSize')).'</th><th class="col4"></th>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'</tr>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr><td colspan="5" class="load"></td></tr>'.DOKU_LF;
		$ret .= DOKU_TAB.'</table></div></div>'.DOKU_LF;
		// To run javascript only if filelist is on this side
		$ret .= DOKU_TAB.'<script type="text/javascript"> window.filelistOnThisSide = true;</script>'.DOKU_LF;
		return $ret;
	}
	
	
	/**
	 * This is nearly the same function as ml(...) in inc/common.php 
	 * extended to getting the filename from fileid. 
	 * 
	 * Build a link to a media file
	 *
	 * Will return a link to the detail page if $direct is false
	 *
	 * The $more parameter should always be given as array, the function then
	 * will strip default parameters to produce even cleaner URLs
	 * 
	 * This is nearly the code from inc/common.php. Replaced lib/exe/fetch.php 
	 * with lib/plugins/owncloud/exe/fetch.php.
	 * 
	 * @author Andreas Gohr <andi@splitbrain.org>
	 * @author Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
	 *
	 * @param string  $id     the media file id or URL
	 * @param mixed   $more   string or array with additional parameters
	 * @param bool    $direct link to detail page if false
	 * @param string  $sep    URL parameter separator
	 * @param bool    $abs    Create an absolute URL
	 * @return string
	 */
	function ml($id = '', $more = '', $direct = true, $sep = '&amp;', $abs = false) {

		global $conf;
		if(is_array($more)) {
			// add token for resized images
			if($more['w'] || $more['h']){
				$more['tok'] = media_get_token($id,$more['w'],$more['h']);
			}
			// strip defaults for shorter URLs
			if(isset($more['cache']) && $more['cache'] == 'cache') unset($more['cache']);
			if(!$more['w']) unset($more['w']);
			if(!$more['h']) unset($more['h']);
			//+ fileid
			if($more['fileid'] <1 || $more['fileid'] == '') unset($more['fileid']);
			if(isset($more['id']) && $direct) unset($more['id']);
			$more = buildURLparams($more, $sep);
		} else {
			$more = str_replace('cache=cache', '', $more); //skip default
			$more = str_replace(',,', ',', $more);
			$more = str_replace(',', $sep, $more);
		}

		if($abs) {
			$xlink = DOKU_URL;
		} else {
			$xlink = DOKU_BASE;
		}

		// external URLs are always direct without rewriting
		if(preg_match('#^(https?|ftp)://#i', $id)) {
			// + changed lib/exe to lib/plugins/owncloud/
			$xlink .= 'lib/plugins/owncloud/exe/fetch.php';
			// add hash:
			$xlink .= '?hash='.substr(md5(auth_cookiesalt().$id), 0, 6);
			if($more) {
				$xlink .= $sep.$more;
				$xlink .= $sep.'media='.rawurlencode($id);
			} else {
				$xlink .= $sep.'media='.rawurlencode($id);
			}
			return $xlink;
		}

		$id = idfilter($id);

		// decide on scriptname
		if($direct) {
			if($conf['userewrite'] == 1) {
				$script = '_media';
			} else {
				// + changed lib/exe to lib/plugins/owncloud/
				$script = 'lib/plugins/owncloud/exe/fetch.php';
			}
		} else {
			if($conf['userewrite'] == 1) {
				$script = '_detail';
			} else {
				// + changed lib/exe to lib/plugins/owncloud/
				$script = 'lib/plugins/owncloud/exe/detail.php';
			}
		}

		// build URL based on rewrite mode
		if($conf['userewrite']) {
			$xlink .= $script.'/'.$id;
			if($more) $xlink .= '?'.$more;
		} else {
			if($more) {
				$xlink .= $script.'?'.$more;
				$xlink .= $sep.'media='.$id;
			} else {
				$xlink .= $script.'?media='.$id;
			}
		}

		return $xlink;
	}
	
	
	/**
     * _getMediaLinkConf is a helperfunction to internalmedia() and externalmedia()
     * which returns a basic link to a media (from inc/parser/xhtml.php).
     *
     * + fileid as first parameter
     * 
     * @author Pierre Spring <pierre.spring@liip.ch>
     * @param string $src
     * @param string $title
     * @param string $align
     * @param string $width
     * @param string $height
     * @param string $cache
     * @param string $render
     * @access protected
     * @return array
     */
	function _getMediaLinkConf($fileid,$src, $title, $align, $width, $height, $cache, $render)
    {
        global $conf;

        $link = array();
        $link['class']  = 'media';
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['target'] = $conf['target']['media'];
        $link['title']  = $this->_xmlEntities($src);
        // + fileid first parameter
        $link['name']   = $this->_media($fileid, $src, $title, $align, $width, $height, $cache, $render);

        return $link;
    }
    
    /**
     * Renders internal and external media (from inc/parser/xhtml.php)
     * 
     * add fileid as first parameter
     * 
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _media($fileid, $src, $title=NULL, $align=NULL, $width=NULL,
                      $height=NULL, $cache=NULL, $render = true) {

        $ret = '';

        list($ext,$mime,$dl) = mimetype($src);
        if(substr($mime,0,5) == 'image'){
            // first get the $title
            if (!is_null($title)) {
                $title  = $this->_xmlEntities($title);
            }elseif($ext == 'jpg' || $ext == 'jpeg'){
                //try to use the caption from IPTC/EXIF
                require_once(DOKU_INC.'inc/JpegMeta.php');
                $jpeg =new JpegMeta(mediaFN($src));
                if($jpeg !== false) $cap = $jpeg->getTitle();
                if($cap){
                    $title = $this->_xmlEntities($cap);
                }
            }
            if (!$render) {
                // if the picture is not supposed to be rendered
                // return the title of the picture
                if (!$title) {
                    // just show the sourcename
                    $title = $this->_xmlEntities(utf8_basename(noNS($src)));
                }
                return $title;
            }
            //add image tag
            // + $this
            $ret .= '<img src="'.($this->ml($src,array('fileid'=>$fileid,'w'=>$width,'h'=>$height,'cache'=>$cache))).'"';
            $ret .= ' class="media'.$align.'"';

            if ($title) {
                $ret .= ' title="' . $title . '"';
                $ret .= ' alt="'   . $title .'"';
            }else{
                $ret .= ' alt=""';
            }

            if ( !is_null($width) )
                $ret .= ' width="'.$this->_xmlEntities($width).'"';

            if ( !is_null($height) )
                $ret .= ' height="'.$this->_xmlEntities($height).'"';

            $ret .= ' />';

        }elseif($mime == 'application/x-shockwave-flash'){
            if (!$render) {
                // if the flash is not supposed to be rendered
                // return the title of the flash
                if (!$title) {
                    // just show the sourcename
                    $title = utf8_basename(noNS($src));
                }
                return $this->_xmlEntities($title);
            }

            $att = array();
            $att['class'] = "media$align";
            if($align == 'right') $att['align'] = 'right';
            if($align == 'left')  $att['align'] = 'left';
            $ret .= html_flashobject(ml($src,array('cache'=>$cache),true,'&'),$width,$height,
                                     array('quality' => 'high'),
                                     null,
                                     $att,
                                     $this->_xmlEntities($title));
        }elseif($title){
            // well at least we have a title to display
            $ret .= $this->_xmlEntities($title);
        }else{
            // just show the sourcename
            $ret .= $this->_xmlEntities(utf8_basename(noNS($src)));
        }

        return $ret;
    }

	
	
	
	 /**
     * (from inc/parser/xhtml.php)
     */
    function _xmlEntities($string) {
        return htmlspecialchars($string,ENT_QUOTES,'UTF-8');
    }
    
    /**
     * Build a link (from inc/xhtml)
     *
     * Assembles all parts defined in $link returns HTML for the link
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _formatLink($link){
        //make sure the url is XHTML compliant (skip mailto)
        if(substr($link['url'],0,7) != 'mailto:'){
            $link['url'] = str_replace('&','&amp;',$link['url']);
            $link['url'] = str_replace('&amp;amp;','&amp;',$link['url']);
        }
        //remove double encodings in titles
        $link['title'] = str_replace('&amp;amp;','&amp;',$link['title']);

        // be sure there are no bad chars in url or title
        // (we can't do this for name because it can contain an img tag)
        $link['url']   = strtr($link['url'],array('>'=>'%3E','<'=>'%3C','"'=>'%22'));
        $link['title'] = strtr($link['title'],array('>'=>'&gt;','<'=>'&lt;','"'=>'&quot;'));

        $ret  = '';
        $ret .= $link['pre'];
        $ret .= '<a href="'.$link['url'].'"';
        if(!empty($link['class']))  $ret .= ' class="'.$link['class'].'"';
        if(!empty($link['target'])) $ret .= ' target="'.$link['target'].'"';
        if(!empty($link['title']))  $ret .= ' title="'.$link['title'].'"';
        if(!empty($link['style']))  $ret .= ' style="'.$link['style'].'"';
        if(!empty($link['rel']))    $ret .= ' rel="'.$link['rel'].'"';
        if(!empty($link['more']))   $ret .= ' '.$link['more'];
        $ret .= '>';
        $ret .= $link['name'];
        $ret .= '</a>';
        $ret .= $link['suf'];
        return $ret;
    }
    
	/* Looking for a fileid when a wikiid is given
	 *
	 * @param String wikiid of the mediafile 
	 * @return fileid for a wikiid
	 */
	public function fileIDForWikiID($src){
			$path = str_replace(':','/',$src);
			return $this->getIDForFilename($path);
	}
	
	
	/* Returns a list with all pages using the given media
	 *
	 * @param String fileid of the mediafile 
	 * @return String List with all pages using the specified media.
	 */
	public function mediaInUse($fileID){
		$this->dbQuery('SELECT `firstheading`,`wikipage` FROM `*PREFIX*dokuwiki_media_use` WHERE fileid = ?', array($fileID));
		$rows = $this->lastQuery->numRows();
		$ret = '<h3 class="sectionedit3">'.$this->getLang('mediaUsedIn').'</h3>';
		if(empty($rows) || $rows == 0) return '<h3 class="sectionedit3">'.$this->getLang('noUsage').'</h3>';
		$ret .= '<ul>'.DOKU_TAB;
		for($i = 1; $i <= $rows; $i++){
				$row = $this->lastQuery->fetchRow();
				$title = ($row['firstheading']!='')?$row['firstheading'].' ('.$row['wikipage'].')':$row['wikipage'];
				$ret .= DOKU_TAB.'<li><span class="curid"><a href="'.wl($row['wikipage']).'" class="wikilink1" title="'.$title.'">'.$title.'</a></span></li>'.DOKU_LF;
		}
		$ret .= '</ul>'.DOKU_LF;
		return $ret;
	}
    
   
}
