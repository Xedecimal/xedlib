<?php

class Sitemap extends Module
{
	public $Name = 'sitemap';

	function Get()
	{
		if (!$this->CheckActive($this->Name)) return;

		global $_d;

		$tree = ModNav::LinkTree($_d['nav.links']);
		return ModNav::GetLinks($tree, array('CLASS' => 'sitemap'));
	}
}

Module::Register('Sitemap');