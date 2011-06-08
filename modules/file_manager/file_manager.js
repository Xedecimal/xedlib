$(function () {
	$('.div-mass-options').hide();
	$('.in-sel-folders').click(function () {
		$('.check_folder').attr('checked', $(this).attr('checked'));
		$('.check_folder').change();
	});

	$('.in-sel-files').click(function () {
		$('.check_file').attr('checked', $(this).attr('checked'));
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
});

function checkChanged()
{
	if ($('.check_folder:checked,.check_file:checked').length > 0)
		$('.div-mass-options').show(500);
	else
		$('.div-mass-options').hide(500);
}