function inline_mce_save(ed) {
	var el = $(ed.getElement());
	var t = el.data('target');
	var handler = el.data('handler');
	var post = { type: handler, target: t, content: ed.getContent() }
	$.post('editor_inline/save', post, function () { alert('Saved.'); });
}

$(function () {
	$('.editor-content').each(function () {
		if (!$(this).data('noreset'))
			$(this).before('<a href="'+$(this).data('target')+'" class="inline-reset">Reset</a>');
	});

	$('.editor-content[data-target]').tinymce({
		// Location of TinyMCE script
		script_url : 'js/tiny_mce/tiny_mce.js',

		// General options
		width: '100%',
		theme : "advanced",
		plugins : "autolink,lists,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,advlist",

		// Theme options
		theme_advanced_buttons1 : "save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
		theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
		theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
		theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak",
		theme_advanced_toolbar_location : "external",
		theme_advanced_toolbar_align : "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resizing : true,
		content_css: "css.css",
		save_onsavecallback : "inline_mce_save",

		// Replace P with BR.
		force_br_newlines: true,
		force_p_newlines: false,
		forced_root_block: false
	});

	$('.inline-reset').live('click', function () {
		if (!confirm('Are you sure you wish to reset your changes to this section to it\'s original content?')) return false;

		var target = $(this).attr('href');
		$.post('editor_inline/reset', {'target': target}, function (data) {
			window.location.reload();
		}, 'json');

		return false;
	});
});
