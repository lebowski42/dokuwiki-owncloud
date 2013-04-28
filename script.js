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
					success: function(data) {filehistory.putContent(data, $row);},
					async:false
				});
			});
		},
		
		putContent: function(data, $place){
				
				//var $response=jQuery(data);
				$place.replaceWith(data);
		},
		
	
};







jQuery(document).ready(function() {
	if(window.filelistOnThisSide){
		filelist.start();
	}
});
