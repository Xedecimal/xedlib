<?php

class ModPage extends Module
{
	function Get()
	{
		global $_d;

		$name = @$_d['q'][0];

		if ($name == 'part') $name = $_d['q'][1];
		$file = "content/{$name}.xml";
		if (!file_exists($file)) $file = "content/{$name}/{$name}.xml";

		if (@$_d['page.opts'][$name] == 'xml')
		{
			$t = new Template($_d);
			$content = $t->ParseFile($file);
		}
		else $content = @file_get_contents($file);
		if ($_d['q'][0] == 'part') die(VarParser::Parse($content, $_d));
		return '<div class="page_content">'.stripslashes($content).'</div>';
	}
}

Module::Register('ModPage');

?>
