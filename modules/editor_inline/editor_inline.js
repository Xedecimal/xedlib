$(function () {
	$('.editor-content').each(function () {
		$(this).before('<a href="'+$(this).data('file')+'" class="inline-edit"">Edit</a>');
		if (!$(this).data('noreset'))
			$(this).before(' <a href="'+$(this).data('file')+'" class="inline-reset">Reset</a>');
	});

	$('.inline-edit').live('click', function () {
		var target = $('.editor-content[data-file="'+$(this).attr('href')+'"]');
		//target.replaceWith('<textarea>'+target.html()+'</textarea>');
		target.tinymce({
			// Location of TinyMCE script
			script_url : 'js/tiny_mce/tiny_mce.js',

			// General options
			theme : "advanced",
			plugins : "pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

			// Theme options
			theme_advanced_buttons1 : "save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
			theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
			theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
			theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_statusbar_location : "bottom",
			theme_advanced_resizing : true,

			// Drop lists for link/image/media/template dialogs
			template_external_list_url : "lists/template_list.js",
			external_link_list_url : "lists/link_list.js",
			external_image_list_url : "lists/image_list.js",
			media_external_list_url : "lists/media_list.js",

			save_onsavecallback : "inline_mce_save"
		});
		return false;
	});

	$('.inline-reset').live('click', function () {
		var target = $(this).attr('href');
		$.post('editor_inline/reset', {file: target}, function (data) {
			window.location.reload();
		}, 'json');

		return false;
	});
});

function inline_mce_save(ed)
{
	var target = $(ed.getElement()).data('file');
	$.post('editor_inline/save', {file: target,
		content: ed.getContent()}, function (data) {
			if (data.msg == 'Success')
			{
				$('.editor-content[data-file="'+data.file+'"]').tinymce().hide();
			}
		}, 'json');
}