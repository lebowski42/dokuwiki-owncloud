<?php
/**
 * This syntax plugin overwrites the defaultdokuwiki image rendering. It uses the fileid
 * ftom the owncloud-database to link the image, and not the imagepath.
 * The Image also provides the the imagebox from the imagebox-plugin written by 
 * FFTiger <fftiger@wikisquare.com>, myst6re <myst6re@wikisquare.com>. see 
 * https://www.dokuwiki.org/plugin:imagebox for the original code. The style.css and
 * most code for the thumbnails from the imagebox-plugin is used here.

 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */
 
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_owncloud extends DokuWiki_Syntax_Plugin {

	function getInfo(){
		return array(
			'author' => 'Martin Schulte',
			'email'  => 'lebowski[at]corvus[dot]uberspace[dot]de',
			'date'   => '2013-04-14',
			'name'   => 'ownCloud Plugin',
			'desc'   => 'Uses ownCloud fileID instead filepathes'
		);
	}

	function getType(){
		return 'substition';
	}
	
	function getAllowedTypes() {
		return array('substition','disabled','formatting');
	}
	
	function getSort(){
		return 319;  // before Dokuwiki-media-parser (320)
	}
	function getPType(){
		return 'normal';
	}
	
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern("\{\{[^\}]+\}\}",$mode,'plugin_owncloud');
		$this->Lexer->addSpecialPattern("\[\{\{[^\}]+\}\}\]",$mode,'plugin_owncloud');
		
	}

	function handle($match, $state, $pos, &$handler){
		$imagebox =false;
		if(preg_match('#\[(.*)\]#',$match,$itis)){
			$match = $itis[1];
			$imagebox = true;
		}//file_put_contents("LinkTesten1.txt","\n",FILE_APPEND);
		$rawdata = $match;
		$match= Doku_Handler_Parse_Media($match);
		$match['imagebox'] = $imagebox;
		$match['pos'] = $pos;
		return array($match, $state, $pos);
	}

	function render($mode, &$renderer, $data){
		//$renderer->doc .= "<div class=\"wrapper_filelist\"><div class=\"das\">Hallo</div>\n<a href=\"#\" onclick=\"javaScript:filelist.start(this)\">This is me</a></div>";
		list($match, $state, $pos) = $data;
		$helper = $this->loadHelper('owncloud',false);
		if(!$helper) return false;
		if($match['type']=='internalmedia'){
			$match['fileid']=0;
			if(preg_match('#fileid=(\d+)?#i',$rawdata,$fileid)){
				($fileid[1]) ? $match['fileid'] = $fileid[1]:"";
			}else{
				$match['fileid'] = $helper->fileIDForWikiID($match['src']);
			}
		}
		
		//var_dump($match);
		//exit(0);
		$opener = '';
		$closer = '';
		if($match['type']!='internalmedia'){
			$match['title'] .= ' ('.$this->getLang('source').': '.$match['src'].')';
		}
		if($match['imagebox']){
			$this->handleImageBox(&$match);
			$match['linking'] = 'details'; // Detail when click on image, enlarge if click on magnify
			list($opener,$closer) = $this->buildImagebox($match);
			$match['align'] = 'box2'; // overwrite class to mediabox2, alignment from thumb.
		}
		$renderer->doc.=$opener;
		if($match['type']!='internalmedia'){
			if($helper->isAllowedExternalImage($match['src'])) $renderer->doc.= $helper->externalmedia($match['src'], $match['title'], $match['align'], $match['width'],$match['height'], $match['cache'], $match['linking']);
		}else{
			$renderer->doc.=  $helper->internalmedia($match['fileid'],$match['src'], $match['title'], $match['align'], $match['width'],$match['height'], $match['cache'], $match['linking']);
		}
		$renderer->doc.=$closer;
		//$b = $helper->getFolderContent(233);
		//$renderer->doc .= var_export($b);
		return true;
	}
	
	
	/**
    * Expands the return from Doku_Handler_Parse_Media with settings for an imagebox.
    * This code based on the code from the imagebox-plugin (https://www.dokuwiki.org/plugin:imagebox)
    * written by FFTiger <fftiger@wikisquare.com> and myst6re <myst6re@wikisquare.com>
    * licensed under the GPL 2 (http://www.gnu.org/licenses/gpl.html) 
    *
    * @param $match return from Doku_Handler_Parse_Media()
    *
    */
	function handleImageBox($match){// Detail immer, M
		$match['w'] = $match['width'];
		$dispMagnify = ($match['w'] || $match['height']);
		$gimgs = false;
		list($src,$hash) = explode('#',$match['src'],2);
		if($match['type']=='internalmedia') {
			$exists = false;
			resolve_mediaid(getNS($ID), $src, $exists);
			$match['magnifyLink'] = ml($src,array('cache'=>$match['cache'],'fileid'=>$match['fileid']),true);
			if($hash) $match['magnifyLink'] .= '#'.$hash;
			if($exists)	$gimgs = @getImageSize(mediaFN($src));
		}else{
			$match['magnifyLink'] = ml($src,array('cache'=>'cache'),true);
			if($hash) $match['detail'] .= '#'.$hash;
			$gimgs = @getImageSize($src);
		}
		$match['exist'] = $gimgs!==false;
		if(!$match['w'] && $match['exist']){
				if($match['height']){
					$match['w'] = $match['height']*$gimgs[0]/$gimgs[1];
				}else{
					$match['w'] = $gimgs[0];
				}
		}
		if(!$match['align']) $match['align'] = 'rien';	
	}
	
	
	/**
    * Builds the div's arround an image to get an imagebox. This code based 
    * on the code from the imagebox-plugin (https://www.dokuwiki.org/plugin:imagebox)
    * written by FFTiger <fftiger@wikisquare.com> and myst6re <myst6re@wikisquare.com>
    * licensed under the GPL 2 (http://www.gnu.org/licenses/gpl.html) 
    *
    * @param $url match (return from Doku_Handler_Parse_Media() + handleImageBox())
    * @return @openAndClose Array with two elements: the opening div's and 
    *                       the closing div's
    */
	public function buildImagebox($match){
		$opener  = '<div class="thumb2 t'.$match['align'].'" style="width:'.($match['w']?($match['w']+10).'px':'auto').'"><div class="thumbinner">';
		$closer  = '<div class="thumbcaption">';
		$closer .= '<div class="magnify">';
		$closer .= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$match['magnifyLink'].'">';
		$closer .= '<img width="15" height="11" alt="" src="'.DOKU_BASE.'lib/plugins/owncloud/images/magnify-clip.png"/>';
		$closer .= '</a></div>';
		
		$style=$this->getConf('default_caption_style');
		if($style=='Italic')	$closer .= '<em>'.htmlspecialchars($match['title']).'</em>';
		elseif($style=='Bold')	$closer .= '<strong>'.htmlspecialchars($match['title']).'</strong>';
		else 					$closer .= htmlspecialchars($match['title']);
		
		$closer .= '</div></div></div>';
		return array($opener,$closer);
	}
}
