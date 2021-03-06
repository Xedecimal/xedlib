<?php

class ModPage extends Module
{
	function __construct()
	{
		global $_d;

		if (!isset($_d['default'])) $_d['default'] = 'index';
	}

	function Get()
	{
		global $_d;

		$name = @$_d['q'][0];

		if ($name == 'part') $name = $_d['q'][1];
		# $_d['page.strict'] will not present if url contains pagename/extra
		# data.
		if (!empty($_d['page.strict']) && !empty($_d['q'][1])) return;
		$file = "content/{$name}.xml";
		if (!file_exists($file)) $file = "content/{$name}/{$name}.xml";

		if (@$_d['page.opts'][$name] == 'xml')
		{
			$t = new Template($_d);
			$content = $t->ParseFile($file);
		}
		else $content = @file_get_contents($file);
		if ($_d['q'][0] == 'part') die(VarParser::Parse($content, $_d));

		if (!empty($content))
			return stripslashes($content);
	}
}

Module::Register('ModPage');

?>
