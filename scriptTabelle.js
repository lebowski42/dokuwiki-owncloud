var filelist = {
		start: function(id){
			 var $element = jQuery(id);
			 $element = $element.parent().find('.das');
			 //$element.removeClass('das');
			 //$element.addClass('load');
			 //filelist.folder($element);
			 $element.empty();
			 jQuery.post(DOKU_BASE + 'lib/plugins/owncloud/sajax.php', {dir: 'vwiki'}, function(data) {
				$element.append(data);
				$row = $element.find('.mf_folder');
				$row.removeAttr('href');
				$row.css('cursor','pointer');
				filelist.folder($row);
			 });
			 //alert(DOKU_BASE + 'lib/plugins/owncloud/sajax.php');
		},
		
		folder: function(e){
			alert(e.attr('class'));
			e.bind('click', function() {
					alert("Yeah");
					
				});
		}
	
};

/*showTree function(c, t) {
	$(c).addClass('wait');
	$(".jqueryFileTree.start").remove();
	$.post(o.script, { dir: t }, function(data) {
		$(c).find('.start').html('');
		$(c).removeClass('wait').append(data);
		if( o.root == t ) $(c).find('UL:hidden').show(); else $(c).find('UL:hidden').slideDown({ duration: o.expandSpeed, easing: o.expandEasing });
		bindTree(c);
	});
				}

bindTree function(t) {
	$(t).find('LI A').bind(o.folderEvent, function() {
		if( $(this).parent().hasClass('directory') ) {
			if( $(this).parent().hasClass('collapsed') ) {
				// Expand
				if( !o.multiFolder ) {
					$(this).parent().parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
					$(this).parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
				}
				$(this).parent().find('UL').remove(); // cleanup
				showTree( $(this).parent(), escape($(this).attr('rel').match( /.*\// )) );
				$(this).parent().removeClass('collapsed').addClass('expanded');
			} else {
				// Collapse
				$(this).parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
				$(this).parent().removeClass('expanded').addClass('collapsed');
			}
		} else {
			h($(this).attr('rel'));
		}
		return false;
	});

if( o.folderEvent.toLowerCase != 'click' ) $(t).find('LI A').bind('click', function() { return false; });
				}
				// Loading message
				$(this).html('<ul class="jqueryFileTree start"><li class="wait">' + o.loadMessage + '<li></ul>');
				// Get the initial file list
				showTree( $(this), escape(o.root) );
*/
