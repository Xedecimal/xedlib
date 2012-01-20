<?php

class EditorInline extends Module
{
	public $Name = 'editor_inline';

	function Link()
	{
		global $_d;
		$_d['template.rewrites']['inline'] = array(&$this, 'TagInlineEditor');
	}

	function Prepare()
	{
		$this->CheckActive($this->Name);
		if (!$this->Active) return;
		if (!User::RequireAccess(1)) return;

		global $_d;

		if ($_d['q'][1] == 'save')
		{
			$file = Server::GetVar('file');
			file_put_contents($file, Server::GetVar('content'));
			die(json_encode(array('msg' => 'Success', 'file' => $file)));
		}
		if (@$_d['q'][1] == 'reset')
		{
			$file = Server::GetVar('file');
			if (file_exists($file)) unlink($file);
			die(json_encode(array('file' => $file)));
		}
	}

	function Get()
	{
		if (!User::RequireAccess(1)) return;

		$p_css = Module::P('editor_inline/editor_inline.css');
		$p_js = Module::P('editor_inline/editor_inline.js');
		$ret['head'] = <<<EOF
<link rel="stylesheet" type="text/css" href="{$p_css}" />
<script type="text/javascript" src="{{app_abs}}/js/tiny_mce/tiny_mce.js"></script>
<script type="text/javascript" src="{{app_abs}}/js/tiny_mce/jquery.tinymce.js"></script>
<script type="text/javascript" src="{$p_js}"></script>
EOF;

		return $ret;
	}

	function TagInlineEditor($t, $g, $a)
	{
		if (file_exists($a['FILE'])) $data = file_get_contents($a['FILE']);
		else $data = $g;
		if (User::RequireAccess(1))
		{
			return '<div data-file="'.$a['FILE'].'" class="editor-content"'.HM::GetAttribs($a).'>'.$data.'</div>';
		}
		return $data;
	}
}

Module::Register('EditorInline');

?>
