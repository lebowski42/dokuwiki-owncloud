<?php
/**
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */

// Prepare
global $conf;

if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $conf and classpathes
require_once(DOKU_INC.'inc/init.php');


// db access

$helper = new helper_plugin_owncloud();
if(isset($_POST['fileid']) && $_POST['fileid'] > 0){
	$dir = $helper->getFilenameForID($_POST['fileid']);
}else{
	$dir = urldecode($_POST['dir']);
	$dir = $helper->wikiIDToPath($dir);
}
$dir = trim($dir,'/');

$fullpath = $conf['mediadir'].'/'.$dir;
$metapath = $conf['mediametadir'].'/'.$dir;

$level = 'level0';
if(isset($_POST['level'])){
	$levelInt = (intval(str_replace('level','',$_POST['level'])));
	if($levelInt != 0){
		$level = "";
		for($i = 0; $i <= $levelInt; $i++) $level .= "level$i ";
		$padding = 'style="padding-left:'.($levelInt*($helper->getConf('marginFilelist'))).'px;"';
	}
}

if(file_exists($fullpath)){
	$dircontent = scandir($fullpath);
	natcasesort($fullpath); // sort by name
	$files = array();
	$folders = array();
	foreach($dircontent as $file ){
		if($file == '.' || $file == '..') continue;
		if(is_dir($fullpath.'/'.$file)){
			array_push($folders, $file);
		}else{
			array_push($files, $file);
		}	
	}

	if(empty($folders) && empty($files)) echo '<tr class="collapsed row '.$level.'"><td  colspan="5" '.$padding.' ><div style="color:grey">'.($helper->getLang('emptyFolder')).'</div></td></tr>';
	$nr = 1;
	foreach($folders as $folder){
			$link = $helper->internalmedia(0,$helper->pathToWikiID($dir.'/'.$folder));
			$mtime = strftime($conf['dformat'],filemtime($fullpath.'/'.$folder));
			$title = $helper->_media($helper->getLastfileid(), $helper->pathToWikiID($dir.'/'.$folder), NULL, NULL, NULL, NULL, NULL, false);
			$url = $helper->ml($helper->pathToWikiID($dir.'/'.$file), array('fileid'=>($helper->getLastfileid())),false);
			$download = $helper->_formatLink(array('title'=>$title,'url'=>$url, 'class'=>"media mediafile detail"));
			echo '<tr class="collapsed row'.$nr.' '.$level.'">';
			echo '<td class="col0" '.$padding.'> '.$link.' </td><td class="col1">  </td><td class="col2 fileinfo">'.$mtime.'</td><td class="col3 fileinfo"> -- </td><td class="col4 centeralign">'.$download.'</td>';
			echo '</tr>';
			$nr++;
	}
	foreach($files as $file){
			$filesize = filesize_h(filesize($fullpath.'/'.$file));
			$mtime = strftime($conf['dformat'],filemtime($fullpath.'/'.$file));
			
			$detail = $helper->internalmedia(0,$helper->pathToWikiID($dir.'/'.$file),NULL,NULL,16,NULL,NULL,'linkonly');
			$title = $helper->_media($helper->getLastfileid(), $helper->pathToWikiID($dir.'/'.$file), NULL, NULL, NULL, NULL, NULL, false);
			$url = $helper->ml($helper->pathToWikiID($dir.'/'.$file), array('fileid'=>($helper->getLastfileid())),true);
			$download = $helper->_formatLink(array('title'=>'download: '.$title,'url'=>$url, 'class'=>"media mediafile download"));
			/*if(file_exists($metapath.'/'.$file.'.changes')){
				$meta = file($metapath.'/'.$file.'.changes');
				$authors = array();
				foreach($meta as $onemeta){
					$line = explode("\t", $onemeta);
					if($line[4] != "" && !in_array($line[4],$authors)) array_push($authors,$line[4]);
				}
				$authorsString = implode(", ", $authors);
			}*/
			list($authorsString,$desc,$count) =$helper->getAuthorsAndDescOfMediafile($dir.'/'.$file);
			
			echo '<tr title="'.$desc.'" class="row'.$nr.' '.$level.'">';
			echo '<td class="col0" '.$padding.'> '.$detail.' </td><td class="col1 fileinfo">'.$authorsString.'</td><td class="col2 fileinfo">'.$mtime.'</td><td class="col3 fileinfo">'.$filesize.'</td><td class="col4 centeralign">'.$download.'</td>';
			echo '</tr>';
			$nr++;
	}
	//echo '</table></div>';
	/*if(file_exists($conf['mediametadir']."/"."vwiki/graph.png.changes")) $a = file($conf['mediametadir']."/"."vwiki/graph.png.changes");
	foreach($a as $aa){
		$array = explode("\t", $aa); 
		
		echo $array[4]."<br>";
	}*/
}




?>




