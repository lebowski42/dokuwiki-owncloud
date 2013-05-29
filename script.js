/**
 * jQuery-stuff to handle filelist and versionlist on detailpage
 * 
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Martin Schulte <lebowski[at]corvus[dot]uberspace[dot]de>, 2013
 */
var filelist = {
		start: function(){
			var fileid;
			var $container =  jQuery('.filelistOC');
			$container.each(function(){
				$current = jQuery(this);
				fileid = parseInt($current.attr("class").replace('filelistOC fileid',''));
				$row = $current.find('.load').parent();
				filelist.subfolder('',$row,'level0',fileid);
			});
		},
		
		folderContent: function(data, $place, level){
				var $response=jQuery(data);
				$folder = $response.find('.mf_folder');
				$folder.removeAttr('href');
				$folder.css('cursor','pointer');
				$place.replaceWith($response);
				$folder.each(function(){filelist.folderEvent(jQuery(this),level);});
				
		},
		
		subfolder: function(dir, $row, level,fileid){			
			jQuery.ajax({
					type: 'POST',
					url: DOKU_BASE + '/lib/plugins/owncloud/ajaxFilelist.php',
					data: {dir: dir, fileid: fileid, level: level},
					success: function(data) {filelist.folderContent(data, $row, level);},
					async:false
				});
		},
		
		folderEvent: function(folder,level){
			folder.bind('click', function() {
					var $row = folder.parent().parent();
					nextLevel = 'level'+(parseInt(level.replace('level',''))+1);
					if($row.hasClass('expanded')){
						$row.nextAll('.'+nextLevel).remove();
						$row.removeClass('expanded').addClass('collapsed');
					}else{
						var dir = folder.attr('title');
						
						$row.after('<tr><td colspan="5" class="load"></td></tr>');
						filelist.subfolder(dir,$row.next(),nextLevel);
						$row.removeClass('collapsed').addClass('expanded');
					}
				});
		}
	
};

var filehistory = {
		start: function(file){
			var $container =  jQuery('.historyOC');
			$container.each(function(){
				$current = jQuery(this);
				$row = $current.find('.load').parent();
				jQuery.ajax({
					type: 'POST',
					url: DOKU_BASE + '/lib/plugins/owncloud/ajaxHistory.php',
					data: {file: file},
					success: function(ret) {if(ret != file){filehistory.putContent(ret, $row);}else{}},
					async:false
				});
			});
		},
		
		putContent: function(data, $place){
				//var $response=jQuery(data);
				$place.replaceWith(data);
		},
		
	
};

var Usedmedia = {
		start: function(){
			var $ol = jQuery('#usedmedia');
			var $items =  $ol.children();
			jQuery.each($items,function(){
										$li = jQuery(this);
										//$li.append('<div class="load3"></div>');
										Usedmedia.addInfo($li);
								}
			);
			var $link = jQuery('#usemediadetail');
			$link.attr('onclick', 'Usedmedia.collapse();');
		},
		addInfo: function(li){
				//li.append("<ul><li>"+li.attr('fileid')+"</li><li>Mein Text</li></ul>");
				if(!li.hasClass('expand')){
					jQuery.ajax({
						type: 'POST',
						url: DOKU_BASE + '/lib/plugins/owncloud/ajaxUsedMedia.php',
						data: {fileid: li.attr('fileid')},
						success: function(data) {
										//li.find('.load3').remove();
										li.append(data);
										li.addClass('expand')
									},
						async:true
					});
				}
		},
		collapse: function(){
			var $ol = jQuery('#usedmedia');
			var $items =  $ol.children();
			jQuery.each($items,function(){
						jQuery(this).find('.filedesc').remove();
						jQuery(this).removeClass('expand');
			});
			var $link = jQuery('#usemediadetail');
			$link.attr('onclick', 'Usedmedia.start();');
		}
};







jQuery(document).ready(function() {
	if(window.filelistOnThisSide){
		filelist.start();
	}
});
