<null>
var json = {{json}}

record = 0

function fill(ix, element) {
	input = $(this);

	if ((m = $(this).prop('name').match(/form\[([^\]]+)\]/)))
	{
		// Checkbox from sub-table
		if (input.attr('type') == 'checkbox' &&
			(msub = input.attr('name').match(/form\[([^\]]+)\]\[([^\]]+)\]/))) {
			col = msub[1].match(/[^.]+\.([^\]]+)/)[1];
			val = msub[2];
			$(json).each(function (ix, row) {
				if (row[col] == val) input.click();
			});
		}
		else if (input.attr('type') == 'radio') {
			if (json[record][m[1]] == input.val()) input.replaceWith('(*)');
			else input.replaceWith('(&nbsp;&nbsp;)');
		}
		else if (input.attr('type') == 'checkbox') {
			if (json[record][m[1]] == input.val()) input.replaceWith('[X]');
			else input.replaceWith('[&nbsp;&nbsp;]');
		}
		else {
			// Contains '.'
			if (m[1].match(/\.([^.]+)/))
				// Everything after '.'
				m[1] = m[1].match(/\.([^.]+)/)[1];
			input.val(json[record][m[1]]);
		}
	}

	$('.date').each(function () {
		if ((m = $(this).val().match(/(\d+)-(\d+)-(\d+)/)))
			$(this).val(m[2]+'/'+m[3]+'/'+m[1]);
	});
}

$(function () {
	$('.noview').hide();

	// Populate the remains.
	$('input,textarea,select').each(fill);

	//Populate the repeatable entries.
	$('.repeater').each(function () {
		rep = $(this);
		$(json).each(function (ix, row) {
			ent = rep.find('.entry:last');
			ent.after(ent.clone());
			ent.find('input,select').each(function () {
				idx = $(this).attr('name').match(/[^[]+\[([^[]+)\]/)[1];
				$(this).val(row[idx]);
			});
		});
	});
});
</null>