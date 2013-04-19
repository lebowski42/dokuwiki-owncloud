<?php
error_reporting (E_ALL | E_STRICT);  
ini_set ('display_errors', 'On');

// Prepare
global $conf;
if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
// $conf and classpathes
require_once(DOKU_INC.'inc/init.php');


// db access

$helper = new helper_plugin_owncloud();
if(isset($_POST['fileid']) && $_POST['fileid'] > 0){
	$dir = $helper->getFilenameForID($_POST['fileid']);
}else{
	$dir = urldecode($_POST['dir']); 
}
$dir = trim($dir,'/');

$fullpath = $conf['mediadir'].'/'.$dir;
$metapath = $conf['mediametadir'].'/'.$dir;

if(file_exists($fullpath)){
	$dircontent = scandir($fullpath);
	natcasesort($fullpath); // sort by name
	$files = array();
	$folders = array();
	foreach( $dircontent as $file ){
		if($file == '.' || $file == '..') continue;
		if(is_dir($fullpath.'/'.$file)){
			array_push($folders, $file);
		}else{
			array_push($files, $file);
		}	
	}
	 // folders at the beginning, each part sort by name
	
	echo '<div class="table"><table class="inline">';
	echo '<tr class="row0">';
	echo '<th class="col0">Name1 </th><th class="col1">Autoren </th><th class="col2">Datum </th><th class="col3">Größe </th><th class="col4"></th>';
	echo '</tr>';
	$nr = 1;
	foreach( $folders as $folder ){
			$link = $helper->internalmedia(0,$helper->pathToWikiID($dir.'/'.$folder));
			$mtime = strftime($conf['dformat'],filemtime($fullpath.'/'.$folder));
			$detail = $helper->internalmedia(0,$helper->pathToWikiID($dir.'/'.$folder),NULL,NULL,16,NULL,NULL,'linkonly');
			$title = $helper->_media($helper->getLastfileid(), $helper->pathToWikiID($dir.'/'.$folder), NULL, NULL, NULL, NULL, NULL, false);
			$url = $helper->ml($helper->pathToWikiID($dir.'/'.$file), array('fileid'=>($helper->getLastfileid())),true);
			$download = $helper->_formatLink(array('title'=>$title,'url'=>$url, 'class'=>"media mediafile detail"));
			echo '<tr class="row'.$nr.'">';
			echo '<td class="col0"> '.$link.' </td><td class="col1">  </td><td class="col2 fileinfo">'.$mtime.'</td><td class="col3 fileinfo"> -- </td><td class="col4 centeralign">'.$download.'</td>';
			echo '</tr>';
			$nr++;
	}
	foreach( $files as $file ){
			$filesize = filesize_h(filesize($fullpath.'/'.$file));
			$mtime = strftime($conf['dformat'],filemtime($fullpath.'/'.$file));
			$detail = $helper->internalmedia(0,$helper->pathToWikiID($dir.'/'.$file),NULL,NULL,16,NULL,NULL,'linkonly');
			$title = $helper->_media($helper->getLastfileid(), $helper->pathToWikiID($dir.'/'.$file), NULL, NULL, NULL, NULL, NULL, false);
			$url = $helper->ml($helper->pathToWikiID($dir.'/'.$file), array('fileid'=>($helper->getLastfileid())),true);
			$download = $helper->_formatLink(array('title'=>'download: '.$title,'url'=>$url, 'class'=>"media mediafile download"));
			if(file_exists($metapath.'/'.$file.'.changes')){
				$meta = file($metapath.'/'.$file.'.changes');
				$authors = array();
				foreach($meta as $onemeta){
					$line = explode("\t", $onemeta);
					if($line[4] != "" && !in_array($line[4],$authors)) array_push($authors,$line[4]);
				}
				$authorsString = implode(", ", $authors);
			}
			
			echo '<tr class="row'.$nr.'">';
			echo '<td class="col0"> '.$detail.' </td><td class="col1 fileinfo">'.$authorsString.'</td><td class="col2 fileinfo">'.$mtime.'</td><td class="col3 fileinfo">'.$filesize.'</td><td class="col4 centeralign">'.$download.'</td>';
			echo '</tr>';
			$nr++;
	}
	echo '</table></div>';
	if(file_exists($conf['mediametadir']."/"."vwiki/graph.png.changes")) $a = file($conf['mediametadir']."/"."vwiki/graph.png.changes");
	foreach($a as $aa){
		$array = explode("\t", $aa); 
		
		echo $array[4]."<br>";
	}
}
 echo "" ;



?>




