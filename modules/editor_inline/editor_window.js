var t = null;

$(function () {
	$('textarea').val(window.opener.$(".editor-content[data-target="+target+"]").html())
	var ed = new nicEditor({
		iconsPath: app_abs+'/js/nicedit/nicEditorIcons.gif',
		fullPanel: true,
		onSave : function(content, id, instance) {
			el = $(instance.e);

			post_data = {
				content: content,
				target: el.data('target')
			}

			if (el.data('handler'))
			{
				post_data['type'] = 'handler';
				post_data['handler'] = el.data('handler');
			}
			else post_data['type'] = 'file';

			$.post('save', post_data, function (data) {
				if (data.msg == 'Success')
				{
					clearTimeout(t);
					window.close();
				}
			}, 'json');
		}
	}).panelInstance('editor');
	setTimeout(function () { updatePage(); }, 1000);
});

function updatePage()
{
	window.opener.$(".editor-content[data-target="+target+"]").html($('.nicEdit-main').html())
	setTimeout(function () { updatePage(); }, 1000);
}
