$(function () {
	$('.a-file').click(function () {
		var uri = URLToArray(window.location.href);
		window.opener.CKEDITOR.tools.callFunction(uri['CKEditorFuncNum'],
			$(this).data('icon'));
		window.close();
		return false;
	});
});

function URLToArray(url) {
  var request = {};
  var pairs = url.substring(url.indexOf('?') + 1).split('&');
  for (var i = 0; i < pairs.length; i++) {
    var pair = pairs[i].split('=');
    request[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
  }
  return request;
}