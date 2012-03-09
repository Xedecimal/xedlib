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
			$type = Server::GetVar('type');
			$target = Server::GetVar('target');
			$content = Server::GetVar('content');

			if (empty($type) || $type == 'file')
			{
				file_put_contents($target, $content);
			}
			else if (!empty($type))
			{
				$h = new $type;
				var_dump($h);
				$h->Save($target, $content);
			}

			die(json_encode(array('msg' => 'Success', 'target' => $target)));
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
<script type="text/javascript" src="{{app_abs}}/js/tiny_mce/jquery.tinymce.js"></script>
<script type="text/javascript" src="{$p_js}"></script>
EOF;

		return $ret;
	}

	function TagInlineEditor($t, $g, $a)
	{
		$data = $g;

		$a['ID'] = @$a['HANDLER'].'-'.$a['TARGET'];
		if (isset($a['TARGET']))
		{
			if (file_exists($a['TARGET'])) $data = file_get_contents($a['TARGET']);
		}
		if (isset($a['HANDLER']))
		{
			$a['DATA-HANDLER'] = $a['HANDLER'];
			unset($a['HANDLER']);
		}

		$a['CLASS'] = 'editor-content';
		$a['DATA-TARGET'] = $a['TARGET'];
		unset($a['TARGET']);

		if (User::RequireAccess(1))
		{
			return '<div'.HM::GetAttribs($a).'>'.$data.'</div>';
		}
		return $data;
	}
}

Module::Register('EditorInline');

?>
