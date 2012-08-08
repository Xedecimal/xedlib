jQuery.fn.showHide = function(toggle) {
	if (toggle) this.show();
	else this.hide();
};

$(function () {
	$('.hider').each(function () {
		var targ = $(this).attr('id').match(/hider_(.*)/)[1];
		if (!$(this).attr('checked')) $('#hidden_'+targ).hide();
		$('#hidden_'+targ.replace('.', '\\.')).showHide($(this).attr('checked'));
		$(this).on('click', function (e) {
			var obj = $('#hidden_'+targ.replace('.', '\\.'));
			$('#hidden_'+targ.replace('.', '\\.')).showHide($(this).attr('checked'));
		});
	});

	$(document).on('click', '.delResult', function (e) {
		e.preventDefault();
		if (confirm('Are you sure you wish to delete this entry?'))
		{
			var id = $(this).attr('href').match(/(\d+)$/)[1];
			$.post($(this).attr('href'), null, function (data) {
				$('#result-'+id).hide();
				alert('Done.');
			}, 'json');
			e.preventDefault();
		}
	});

	$('.actions a,.action').button();

	$(document).on('click', '#but-search', function (e) {
		e.preventDefault();
		$form = $('#data_search_'+$(this).data('name'));
		$.post($form.attr('action'), $form.serialize(), function (data) {
			$('#div-results').html(data);
			$('.actions a,.action').button();
		});
	});
	
	$(document).on('click', 'a.a-page', function (e) {
		e.preventDefault();
		$('#div-results').load($(this).attr('href'));
	});
});
