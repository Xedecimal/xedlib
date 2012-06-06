$(function () {
	CKEDITOR.plugins.registered['save']=
	{
		init : function( editor )
		{
			editor.addCommand('save', {
				modes : { wysiwyg:1, source:1 },
				exec : function(editor) {
					var fo = editor.element.$.form;
					editor.updateElement();
					var post = {
						h: $(editor.element.$).data('handler'),
						t: $(editor.element.$).data('target'),
						data: $(editor.element.$).html()
					};
					$.post('inline/save', post, function () {
						editor.destroy();
					});
				}}
			);
			editor.ui.addButton('Save', { label: 'Save', command: 'save' });
		}
	}

	CKEDITOR.config.toolbar_Custom = [
		{ name: 'document', items : [ 'Save','DocProps' ] },
		{ name: 'clipboard', items : [ 'Cut','Copy','Paste','PasteText','PasteFromWord','-','Undo','Redo' ] },
		{ name: 'editing', items : [ 'Find','Replace' ] },
		{ name: 'basicstyles', items : [ 'Bold','Italic','Underline','Strike','Subscript','Superscript','-','RemoveFormat' ] },
		{ name: 'paragraph', items : [ 'NumberedList','BulletedList','-','Outdent','Indent','-','Blockquote','CreateDiv',
		'-','JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock' ] },
		{ name: 'insert', items : [ 'Link','Image' ] },
		{ name: 'styles', items : [ 'Format' ] },
		{ name: 'colors', items : [ 'TextColor','BGColor' ] }
	];

	$('.editor-content[data-target]').live('click', function () {
		$(this).ckeditor({
			filebrowserBrowseUrl: 'inline/images',
			toolbar: 'Custom'
		});
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
