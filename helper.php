<?php
/**
 * This class is a backend to use the ownCloud database while running Dokuwiki
 * You can have a look to <ownCloud-Path>/lib/db.php for functions provided.
 * Here is a description for developer to using the ownCloud database
 * http://doc.owncloud.org/server/5.0/developer_manual/app/appframework/database.html
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

//constants
if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');


class helper_plugin_owncloud extends DokuWiki_Plugin 
{
	// The last query of type PDOStatementWrapper (see <ownCloud-Path>/lib/db.php)
	protected $lastQuery;
	protected $storageNr;
	protected $lastfileid;
    
    /**
     * Constructor to connect to db and check if ownCloud is installed 
     */
    public function helper_plugin_owncloud($db=true) {
		if($db){
			global $conf;
			require_once($this->getConf('pathtoowncloud').'/lib/base.php');
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
		$this->dbQuery('SELECT `path` FROM `*PREFIX*filecache` WHERE fileid = ? AND storage = ?', array($id, $this->storageNr));
		$path = $this->lastQuery->fetchOne();
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
		return is_dir($conf['mediametadir'].'/'.trim($path,'/'));
	}

	
	/**
    * Returns the fileid for the correspondig path (from table OC_filecache)
    *
    * @param $file string The path
    */
	public function getIDForFilename($file){
		$this->dbQuery('SELECT `fileid` FROM `*PREFIX*filecache` WHERE path = ? AND storage = ?', array($file, $this->storageNr));
		$id = $this->lastQuery->fetchOne();
		$this->lastfileid = $id;
		return $id;
	}
	
	/**
    * Returns the fileid for the correspondig path (from table OC_filecache)
    *
    * @param $file string The path
    */
	public function getMimetypeForID($id){
		$this->dbQuery('SELECT *PREFIX*mimetypes.mimetype FROM *PREFIX*filecache JOIN *PREFIX*mimetypes ON *PREFIX*filecache.mimetype = *PREFIX*mimetypes.id WHERE fileid = ? AND storage = ?', array($id, $this->storageNr));
		return $this->lastQuery->fetchOne();
	}
	
	public function getFolderContent($id){
		$this->dbQuery('SELECT fileid, path,*PREFIX*mimetypes.mimetype FROM *PREFIX*filecache  JOIN *PREFIX*mimetypes ON *PREFIX*filecache.mimetype = *PREFIX*mimetypes.id WHERE parent=? AND storage = ? ORDER BY *PREFIX*filecache.path ASC', array($id, $this->storageNr));
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
	
	/** Returns the authors of the given mediafile as String. If plugin authorlist is enabled,
	 *  authors will be linked as configured in this plugin. 
	 */
	public function getAuthorsOfMediafile($file){
		global $ID;
		if($this->getConf('linkAuthor') && !plugin_isdisabled('authorlist')){
			$authorlist = $this->loadHelper('authorlist',true);
			$authorlist->setOptions($ID,array('displayaslist'=>false));
		}
		$meta = $this->getMediaMeta($file);
		if(!empty($meta)){
			$authors = array(); 
			foreach($meta as $onemeta){
				$line = explode("\t", $onemeta);
				if($line[4] != "" && !in_array($line[4],$authors)) array_push($authors,$line[4]);
			}
			if($authorlist){
				foreach($authors as &$author){
					$author=$authorlist->renderOneAuthor($author,$authorlist->getFullname($author));
				}
			}
			return implode(", ", $authors);
		}
		return '';
	}
	
	/** Returns the meta data of the given mediafile */
	public function getMediaMeta($file){
		global $conf;
		if(file_exists($conf['mediametadir'].'/'.$file.'.changes')) return file($conf['mediametadir'].'/'.$file.'.changes');
		return array();
	}
	
	public function mediaMetaStart($file){
		global $lang;
		$ret = '<div class="historyOC">'.DOKU_LF;
		$ret .= DOKU_TAB.'<div class="table"><table  width="100%" class="inline">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr class="row0">'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.DOKU_TAB.'<th class="col0" width="20%" >'.($this->getLang(historyVersion)).'</th><th  width="20%" class="col1">'.($this->getLang(historyAuthor)).'</th><th width="10%" class="col2">'.($this->getLang(filelistSize)).'</th><th class="col3" width="45%">'.($this->getLang(historyComment)).'</th>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'</tr>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr><td colspan="4" class="load"></td></tr>'.DOKU_LF;
		$ret .= DOKU_TAB.'</table></div></div>'.DOKU_LF;
		// To run javascript only if filelist is on this side
		$ret .= DOKU_TAB.'<script type="text/javascript">filehistory.start("'.$file.'");</script>'.DOKU_LF;
		$wikiid = $this->pathToWikiID($file);
		$ns = getNS($wikiid);
		$mediamanager = '<a class="mmLink" href="'.DOKU_URL.'doku.php?ns='.$ns.'&image='.$this->pathToWikiID($file).'&do=media&tab_details=history">'.$this->getLang('compare').'</a>';
		$ret .= $this->getLang('mediamanager_info').' '.$mediamanager;
		return $ret;
		
		
	}
	
	
	public function mediaMetaAsList($file){
		$ret = "";
		global $conf;
		$meta = $this->getMediaMeta($file);
		if(empty($meta)) return '';
		$meta =  array_reverse($meta); // Newest first.
		if($this->getConf('linkAuthor') && !plugin_isdisabled('authorlist')){
			$authorlist = $this->loadHelper('authorlist',true);
			$authorlist->setOptions($ID,array('displayaslist'=>false));
		}
		$oldmedia = $conf['mediaolddir'];
		$nr = 1;
		foreach($meta as $onemeta){
			$line = explode("\t", $onemeta);
			$time = strftime($conf['dformat'],intval($line[0]));
			if($nr > 1){ // in attic
				list($name, $ext) =  $this->filenameAndExtension($file);
				$path = $oldmedia.'/'.$name.'.'.$line[0].'.'.$ext;
			}else{
				$path = $conf['mediadir'].'/'.$file;
			}
			$size = (file_exists($path)) ? filesize_h(filesize($path)) : '--';
			$author = ($authorlist)?$authorlist->renderOneAuthor($line[4],$authorlist->getFullname($line[4])) : $line[4];
			$author .= '<span class="ip">('.$line[1].')</span>';
			
			$ret .= '<tr class=" row'.$nr.'">';
			$ret .= '<td class="col0" > '.$time.' </td><td class="col1">'.$author.'</td><td class="col2">'.$size.'</td><td class="col3">'.$line[5].'</td>';
			$ret .= '</tr>';
			$nr++;
		}
		return $ret;
		
		
	}
	
	public function filenameAndExtension($file){
			$extPos  = strrpos($file, '.');
			$ext = substr($file, $extPos + 1);
			$name = substr($file, 0, $extPos);
			return array($name, $ext);
	}
        
        
        
    public function isExternal($src){
		if ( preg_match('#^(https?|ftp)#i',$src) ) {
			return  true;
		}
	}
	   
   
    /** Renders an internal media link. This is nearly the same function as internalmedia(...)
     *  in inc/xhtml.php extended to getting the filename from fileid. 
     */   
    public function internalmedia ($fileid, $src, $title=NULL, $align=NULL, $width=NULL,
                            $height=NULL, $cache=NULL, $linking=NULL) {
        global $ID;
        $filelist = false;
        list($src,$hash) = explode('#',$src,2);
        if($fileid != '' && $fileid > 0){
				$res = $this -> getFilenameForID($fileid, true);
		}
		if($res != ''){
				$src = $res;
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
		$ret .= DOKU_TAB.DOKU_TAB.DOKU_TAB.'<th class="col0">'.($this->getLang(filelistName)).'</th><th class="col1">'.($this->getLang(filelistAuthor)).'</th><th class="col2">'.($this->getLang(filelistDate)).'</th><th class="col3">'.($this->getLang(filelistSize)).'</th><th class="col4"></th>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'</tr>'.DOKU_LF;
		$ret .= DOKU_TAB.DOKU_TAB.'<tr><td colspan="5" class="load"></td></tr>'.DOKU_LF;
		$ret .= DOKU_TAB.'</table></div></div>'.DOKU_LF;
		// To run javascript only if filelist is on this side
		$ret .= DOKU_TAB.'<script type="text/javascript"> window.filelistOnThisSide = true;</script>'.DOKU_LF;
		return $ret;
	}
	
	
	/**
 * Build a link to a media file
 *
 * Will return a link to the detail page if $direct is false
 *
 * The $more parameter should always be given as array, the function then
 * will strip default parameters to produce even cleaner URLs
 * 
 * This is nearly the code from inc/common.php. Replaced lib/exe/fetch.php 
 * with lib/plugins/owncloud/fetch.php.
 * 
 * @author     Andreas Gohr <andi@splitbrain.org>
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
			$xlink .= 'lib/plugins/owncloud/fetch.php';
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
				$script = 'lib/plugins/owncloud/fetch.php';
			}
		} else {
			if($conf['userewrite'] == 1) {
				$script = '_detail';
			} else {
				// + changed lib/exe to lib/plugins/owncloud/
				$script = 'lib/plugins/owncloud/detail.php';
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
    
	/* Looking fpr a fileid when a wikiid is given
	 *
	 * @param String wikiid of the mediafile 
	 */
	public function fileIDForWikiID($src){
			$path = str_replace(':','/',$src);
			$path = trim($path,'/'); //Remove slashes at the beginning
			return $this->getIDForFilename($path);
	}
    
   
}
