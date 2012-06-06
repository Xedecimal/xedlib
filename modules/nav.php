<?php

require_once(dirname(__FILE__).'/../classes/tree_node.php');
require_once(dirname(__FILE__).'/../classes/hm.php');

class ModNav extends Module
{
	public $Block = 'nav';

	function Link()
	{
		global $_d;

		$_d['template.rewrites']['crumb'] = array(&$this, 'TagCrumb');
	}

	function Get()
	{
		global $_d;

		$ret = '';

		if (isset($_d['nav.links']))
		{
			$this->MarkCurrent();

			$t = new Template();
			$t->ReWrite('link', array($this, 'TagLink'));
			$t->ReWrite('head', array($this, 'TagHead'));
			$tree = ModNav::LinkTree($_d['nav.links']);
			if (empty($_d['nav.class'])) $_d['nav.class'] = 'nav';
			$ret['nav'] = ModNav::GetLinks($tree, array('CLASS' => @$_d['nav.class']));
		}

		return $ret;
	}

	/**
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($link, $atrs = null, $depth = -1)
	{
		global $_d;

		# Iterate Children, skip root node as it's just a container.

		if ($depth < 0 && $atrs == null) $ratrs['CLASS'] = 'nav';
		else $ratrs = $atrs;

		$ret = null;
		if (!empty($link->children))
		{
			$ret .= '<ul'.HM::GetAttribs($ratrs).'>';
			$ix = 0;
			foreach ($link->children as $c)
			{
				if ($ix++ > 0 && !empty($_d['nav.sep']) && $depth < 0)
					$ret .= $_d['nav.sep'];

				$liratrs = array();
				if ($ix == count($link->children))
					@$liratrs['CLASS'] .= ' last';

				if (is_string($c->data))
					$slink = '<a href="'.$c->data.'">'.str_replace('\\', '/', $c->id).'</a>';
				else if (isset($c->data['raw'])) $slink = $c->data['raw'];
				else if (is_array($c->data))
					$slink = '<a'.HM::GetAttribs($c->data).'>'.$c->id.'</a>';
				else if (is_string($c)) $slink = $c;
				else $slink = $c->id;

				if (is_array($c->data))
					$liratrs = @$c->data['liatrs'];

				$liatrs = HM::GetAttribs($liratrs);

				$ret .= "<li$liatrs>$slink";
				$ret .= ModNav::GetLinks($c, null, $depth+1);
				$ret .= '</li>';
			}
			$ret .= '</ul>';
		}

		return $ret;
	}

	function MarkCurrent()
	{
		global $_d, $rw;

		foreach ($_d['nav.links'] as $t => $u)
		{
			if (is_string($u)) $url = $u;
			else continue;

			$end = substr(strstr($url, '/'), 1);

			if (strcmp($end, $rw) == 0)
			{
				$els = explode('/', $t);
				foreach ($els as $e)
				{
					$l = $_d['nav.links'][$t];
					if (!is_array($l)) $l = array('HREF' => $l);
					$l['liatrs']['CLASS'] = 'current';
					$_d['nav.links'][$t] = $l;
				}
			}
		}
	}

	/**
	 *
	 * @param type $nav
	 * @return TreeNode
	 */
	static function LinkTree($nav)
	{
		$r = new TreeNode();
		foreach ($nav as $path => $t)
		{
			$ep = explode('/', $path);
			foreach ($ep as $ix => $d)
			{
				# Has Parent
				if ($ix > 0)
				{
					# Find Parent
					$tnp = $r->Find($ep[$ix - 1]);

					# Find this item
					$tn = $tnp->Find($d);

					# This item does not exist.
					if (empty($tn))
					{
						# Add Child
						$tn = new TreeNode($t, $d);
						$tnp->AddChild($tn);
					}
				}

				# Is Parent
				else
				{
					$tnp = $r->Find($d);

					if ($ix == (count($ep)-1)) $nd = $t;
					else $nd = null;

					# There is no parent for this item.
					if (empty($tnp))
					{
						# This is the actual definition of this root item.
						$tn = new TreeNode($nd, $d);

						# Add this to root.
						$r->AddChild($tn);
					}
					else if (empty($tnp->data)) $tnp->data = $nd;
				}
			}
		}

		return $r;
	}

	# Breadcrumb Related

	function TagCrumb($t, $g)
	{
		return $this->GetCrumb();
	}

	function GetCrumb()
	{
		global $rw, $_d;
		$tree = ModNav::LinkTree($_d['nav.links']);
		$walk = $tree->UFind(array(&$this, 'cb_crumb'));
		$ret = '';
		while (!empty($walk->id))
		{
			if (!empty($ret)) $ret = ' » '.$ret;
			$url = is_array($walk->data) ? $walk->data['HREF'] : $walk->data;
			$ret = '<a href="'.$url.'">'.$walk->id.'</a>'.$ret;
			$walk = $walk->parent;
		}

		return $ret;
	}

	function cb_crumb($item)
	{
		global $rw;
		if (is_array($item->data) && !empty($item->data['HREF']))
			$url = $item->data['HREF'];
		else if (is_string($item->data)) $url = $item->data;
		else return;

		$end = substr(strstr($url, '/'), 1);
		if (strcmp($end, $rw) == 0) return true;
	}
}

Module::Register('ModNav');

?>
