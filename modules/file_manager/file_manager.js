$(function () {
	$('.in-sel-folders').click(function () {
		$('.check_folder').attr('checked',
			$(this).attr('checked') ? 'checked' : false);
		$('.check_folder').change();
	});

	$('.in-sel-files').click(function () {
		$('.check_file').attr('checked',
			$(this).attr('checked') ? 'checked' : false);
		$('.check_folder').change();
	});

	$('.check_folder,.check_file').click(checkChanged);
	$('.check_folder,.check_file').change(checkChanged);

	$('.a-toggle').click(function () {
		$('#'+$(this).attr('href')).slideToggle(100);
		return false;
	});

	$('.delete').click(function () {
		return confirm('Are you sure you wish to delete selected items?');
	});

	$('.table-listing tbody').sortable({
		handle: '.icon',
		stop: function (event, ui) {
			var indices = [];
			ui.item.parent().find('.tr-entry').each(function (ix) {
				indices.push($(this).data('path'));
			});
			var target = ui.item.closest('div.file-manager').attr('id');
			var args = {}
			args[target+'_action'] = 'sort';
			args['indices'] = indices;
			$.get(target, args);
		}
	});
});

function checkChanged()
{
	if ($('.check_folder:checked,.check_file:checked').length > 0)
		$('.div-mass-options').show(500);
	else
		$('.div-mass-options').hide(500);
}