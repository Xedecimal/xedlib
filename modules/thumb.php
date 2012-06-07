<?php

require_once(dirname(__FILE__).'/file_manager/filter_gallery.php');

class Thumb extends Module
{
	function Link()
	{
		global $_d;

		$_d['template.rewrites']['thumb'] = array(&$this, 'TagThumb');
	}

	function Prepare()
	{
		global $_d;

		if (@$_GET['act'] == 'upthumb' && User::RequireAccess(1))
		{
			$src = $_FILES['image']['tmp_name'];
			$dst = $_POST['img'];
			$thm = $_POST['thm'];
			$x = $_POST['x'];
			$y = $_POST['y'];

			if (!empty($dst))
				file_put_contents($dst, file_get_contents($src));

			FilterGallery::ResizeFile($src, $thm, $x, $y);
		}
	}

	function TagThumb($t, $g, $a)
	{
		global $_d;

		$ret = '<div'.HM::GetAttribs($a).'>';

		if (!empty($a['HREF'])) $ret .= '<a class="gal" href="'.$a['HREF'].'">';
		if (file_exists($a['SRC'])) $ret .= '<img src="'.$a['SRC'].'" alt="'.$a['ALT'].'" />';
		if (!empty($a['HREF'])) $ret .= '</a>';

		if (User::RequireAccess(1))
		{
			$rw = @$_GET['rw'];
			$href = @$a['HREF'];

			$ret .= <<<EOF
<p><form action="{$rw}?act=upthumb" method="post"
	enctype="multipart/form-data">
<input type="hidden" name="img" value="{$href}" />
<input type="hidden" name="thm" value="{$a['SRC']}" />
<input type="hidden" name="x" value="{$a['WIDTH']}" />
<input type="hidden" name="y" value="{$a['HEIGHT']}" />
<input type="file" name="image" />
<input type="submit" value="Replace" /></form></p>
EOF;
		}

		return $ret.'</div>';
	}
}

Module::Register('Thumb');
