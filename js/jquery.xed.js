// For dynamically adding fields to a form.

jQuery.fn.repeat = function(target) {
	var repeat_ix = $(target).children().length
	obj = $(this).clone();
	obj.html(obj.html().replace(/:/g, repeat_ix++));
	$(target).append(obj);
	obj.show();
	obj.find('.cloneable').removeClass('cloneable');
	obj.find('.date').datepicker();
	return obj;
};

// Javascript Hinted Form Input ; Taken from: http://remysharp.com

jQuery.fn.hint = function () {
	blurClass = 'quiet';

	return this.each(function () {
		var $input = jQuery(this),
		title = $input.attr('title'),
		$form = jQuery(this.form),
		$win = jQuery(window);

		function remove() {
			if ($input.val() === title && $input.hasClass(blurClass)) {
				$input.val('').removeClass(blurClass);
			}
		}

		if (title) {
			$input.blur(function () {
				if (this.value === '') {
					$input.val(title).addClass(blurClass);
				}
			}).focus(remove).blur();

			$form.submit(remove);
			$win.unload(remove);
		}
	});
};