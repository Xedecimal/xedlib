jQuery.fn.showHide = function(toggle) {
	if (toggle) this.show();
	else this.hide();
};

$(function () {
	$('.hider').each(function () {
		var targ = $(this).attr('id').match(/hider_(.*)/)[1];
		if (!$(this).attr('checked')) $('#hidden_'+targ).hide();
		$('#hidden_'+targ.replace('.', '\\.')).showHide($(this).attr('checked'));
		$(this).click(function () {
			$('#hidden_'+targ.replace('.', '\\.')).showHide($(this).attr('checked'));
		});
	});

	$('.delResult').click(function () {
		if (confirm('Are you sure you wish to delete this entry?'))
		{
			var id = $(this).attr('href').match(/(\d+)$/)[1];
			$.post($(this).attr('href'), null, function (data) {
				$('#result-'+id).hide();
			}, 'json');
		}
		return false;
	});

	$('.actions a').button();
});
