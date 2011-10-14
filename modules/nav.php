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

		if (isset($_d['nav.links']))
		{
			$t = new Template();
			$t->ReWrite('link', array($this, 'TagLink'));
			$t->ReWrite('head', array($this, 'TagHead'));
			$tree = ModNav::LinkTree($_d['nav.links']);
			$ret['nav'] = ModNav::GetLinks($tree, U::ifset(@$_d['nav.class'], 'nav'));
		}

		return $ret;
	}

	/**
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($link, $class = 'nav', $depth = -1)
	{
		global $_d;

		# Iterate Children, skip root node as it's just a container.

		$ret = null;
		if (!empty($link->children))
		{
			$ret .= '<ul class="'.$class.'">';
			$ix = 0;
			foreach ($link->children as $c)
			{
				if ($ix++ > 0 && !empty($_d['nav.sep']) && $depth < 0)
					$ret .= $_d['nav.sep'];

				$liatrs = '';

				if (is_string($c->data))
					$link = '<a href="'.$c->data.'">'.str_replace('\\', '/', $c->id).'</a>';
				else if (isset($c->data['raw'])) $link = $c->data['raw'];
				else if (is_array($c->data))
				{
					$liatrs = HM::GetAttribs(@$c->data['liatrs']);
					if (!empty($c->data['liatrs'])) unset($c->data['liatrs']);
					$link = '<a'.HM::GetAttribs($c->data).'>'.$c->id.'</a>';
				}
				else if (is_string($c)) $link = $c;
				else $link = $c->id;

				$ret .= "<li$liatrs>$link";
				$ret .= ModNav::GetLinks($c, $class, $depth+1);
				$ret .= '</li>';
			}
			$ret .= '</ul>';
		}

		return $ret;
	}

	/**
	 *
	 * @param type $nav
	 * @return TreeNode
	 */
	static function LinkTree($nav)
	{
		$r = new TreeNode();
		foreach ($nav as $p => $t)
		{
			$ep = explode('/', $p);
			foreach ($ep as $ix => $d)
			{
				# Has Parent
				if ($ix > 0)
				{
					# Find Parent
					$tnp = $r->Find($ep[$ix - 1]);

					# Find Child
					$tn = $tnp->Find($d);
					if (empty($tn))
					{
						# Add Child
						$tn = new TreeNode(null, $d);
						$tnp->AddChild($tn);
					}
				}
				# Is Parent
				else
				{
					$tnp = $r->Find($d);
					if (empty($tnp))
					{
						# Add Child
						$tn = new TreeNode(null, $d);
						$r->AddChild($tn);
					}
				}
			}

			$tn->data = $t;
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
			$ret = '<a href="'.$walk->data.'">'.$walk->id.'</a>'.$ret;
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
		if (substr($url, -strlen($rw)) == $rw) return true;
	}
}

Module::Register('ModNav');

?>
