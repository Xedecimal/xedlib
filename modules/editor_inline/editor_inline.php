<?php

class EditorInline extends Module
{
	public $Name = 'inline';

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
			$type = Server::GetVar('h');
			$target = Server::GetVar('t');
			$content = Server::GetVar('data');

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

		global $_d;

		if (@$_d['q'][1] == 'images')
		{
			require_once('xedlib/modules/file_manager/file_manager.php');
			$fm = new FileManager;
			$fm->Active = true;
			$fm->Behavior->Target = 'images';
			$fm->Filters = array('FilterGallery');
			$fm->Root = 'img/upload';
			$fm->Behavior->AllowAll();
			$fm->Prepare();
			$ret = $fm->Get();
			$p_js = Module::P('editor_inline/inline_images.js');
			$ret['head'] .= '<script type="text/javascript" src="'.$p_js.'"></script>';
			return $ret;
		}

		$p_css = Module::P('editor_inline/editor_inline.css');
		$p_js = Module::P('editor_inline/editor_inline.js');
		$ret['head'] = '<link rel="stylesheet" type="text/css" href="'.$p_css.'" />';
		$ret['js'] = <<<EOF
<script type="text/javascript" src="{{app_abs}}/js/ckeditor/ckeditor.js"></script>
<script type="text/javascript" src="{{app_abs}}/js/ckeditor/adapters/jquery.js"></script>
<script type="text/javascript" src="{$p_js}"></script>
EOF;

		return $ret;
	}

	function TagInlineEditor($t, $g, $a)
	{
		$data = $g;

		$a['ID'] = '';
		if (isset($a['HANDLER'])) $a['ID'] .= $a['HANDLER'].'_';
			$a['ID'] .= $a['TARGET'];
		if (isset($a['TARGET']))
		{
			if (file_exists($a['TARGET'])) $data = file_get_contents($a['TARGET']);
		}
		if (isset($a['HANDLER']))
		{
			$a['DATA-HANDLER'] = $a['HANDLER'];
			unset($a['HANDLER']);
		}

		if (empty($a['CLASS'])) $a['CLASS'] = '';
		$a['CLASS'] .= ' editor-content';
		$a['DATA-TARGET'] = $a['TARGET'];
		if (!empty($a['TB'])) $a['DATA-TB'] = $a['TB'];
		unset($a['TARGET']);

		if (User::RequireAccess(1))
		{
			$atrs = HM::GetAttribs($a);
			return '<div'.$atrs.'>'.$data.'</div>';
		}
		return $data;
	}
}

Module::Register('EditorInline');

?>
