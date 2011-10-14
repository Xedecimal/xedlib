<?php

class Meta extends Module
{
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
}

Module::Register('Meta');

?>
