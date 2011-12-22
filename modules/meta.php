<?php

class Meta extends Module
{
	function Link()
	{
		global $_d;

		$_d['template.rewrites']['title'] = array(&$this, 'TagTitle');
	}

	function Get()
	{
		global $_d;

		if (!empty($_d['config']['meta'][$_d['q'][0]]))
		{
			$meta = $_d['config']['meta'][$_d['q'][0]];
			$ret['head'] = '';
			if (isset($meta['description']))
				$ret['head'] .= '<meta name="description" content="'.$meta['description'].'" />';
			if (isset($meta['keywords']))
				$ret['head'] .= '<meta name="keywords" content="'.$meta['keywords'].'" />';

			return $ret;
		}
	}

	function TagTitle($t, $g)
	{
		global $_d;

		$meta = @$_d['config']['meta'][$_d['q'][0]];
		if (!empty($meta['title'])) $t = $meta['title'];
		else $t = $g;
		return '<title>'.$t.'</title>';
	}
}

Module::Register('Meta');
