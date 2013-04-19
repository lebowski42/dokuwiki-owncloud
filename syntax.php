<?php
/**
 * This syntax plugin overwrites the defaultdokuwiki image rendering. It uses the fileid
 * ftom the owncloud-database to link the image, and not the imagepath.
 * The Image also provides the the imagebox from the imagebox-plugin written by 
 * FFTiger <fftiger@wikisquare.com>, myst6re <myst6re@wikisquare.com>. see 
 * https://www.dokuwiki.org/plugin:imagebox for the original code. The style.css and
 * most code for the thumbnails from the imagebox-plugin is used here.

 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>
 */
 
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');

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
		return 'block';
	}
	
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern("\{\{[^\}]+\}\}",$mode,'plugin_owncloud');
		//$this->Lexer->addSpecialPattern("\[\{\{[^\}]+\}\}\]",$mode,'owncloud');
		
	}

	function handle($match, $state, $pos, &$handler){
		$rawdata = $match;
		$match= Doku_Handler_Parse_Media($match);
		$match['fileid']=0;
		if(preg_match('#fileid=(\d+)?#i',$rawdata,$fileid)){
			($fileid[1]) ? $match['fileid'] = $fileid[1]:"";
		}
		$match['pos'] = $pos;
		return array($match, $state, $pos);
	}

	function render($mode, &$renderer, $data){
		$renderer->doc .= "<div class=\"wrapper_filelist\"><div class=\"das\">Hallo</div>\n<a href=\"#\" onclick=\"javaScript:filelist.start(this)\">This is me</a></div>";
		return true;
		
		list($match, $state, $pos) = $data;
		$helper = $this->loadHelper('owncloud',false);
		if(!$helper) return false;
		
		if($helper->isExternal($match['src'])){
			$renderer->doc.= $helper->externalmedia($match['src'], $match['title'], $match['align'], $match['width'],$match['height'], $match['cache'], $match['linking']);
		}else{
			$renderer->doc.=  $helper->internalmedia($match['fileid'],$match['src'], $match['title'], $match['align'], $match['width'],$match['height'], $match['cache'], $match['linking']);
		}
		//$b = $helper->getFolderContent(233);
		//$renderer->doc .= var_export($b);
		return true;
	}
}
