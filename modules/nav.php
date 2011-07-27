<?php

require_once(dirname(__FILE__).'/../classes/TreeNode.php');
require_once(dirname(__FILE__).'/../classes/HM.php');

class ModNav extends Module
{
	public $Block = 'nav';

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
				if (is_string($c->data))
					$link = '<a href="'.$c->data.'">'.$c->id.'</a>';
				else if (isset($c->data['raw'])) $link = $c->data['raw'];
				else if (is_array($c->data))
					$link = '<a'.HM::GetAttribs($c->data).'>'.$c->id.'</a>';
				else if (is_string($c)) $link = $c;
				else $link = $c->id;
				$ret .= '<li>'.$link;
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

	function Get()
	{
		global $_d;

		if (isset($_d['nav.links']))
		{
			$t = new Template();
			$t->ReWrite('link', array($this, 'TagLink'));
			$t->ReWrite('head', array($this, 'TagHead'));
			$ret['nav'] = ModNav::GetLinks(ModNav::LinkTree($_d['nav.links']),
				!empty($_d['nav.class']) ? $_d['nav.class'] : 'nav');

			$ret['crumb'] = $this->GetCrumb();
		}

		return $ret;
	}

	function GetCrumb()
	{
		global $rw, $_d;
		$tree = ModNav::LinkTree($_d['nav.links']);
		$walk = $tree->UFind(array(&$this, 'cb_crumb'));
		$ret = '';
		while (!empty($walk->id))
		{
			if (!empty($ret)) $ret = ' &raquo; '.$ret;
			$ret = '<a href="'.$walk->data.'">'.$walk->id.'</a>'.$ret;
			$walk = $walk->parent;
		}

		return $ret;
	}

	function cb_crumb($item)
	{
		global $rw;
		if (substr($item->data, -strlen($rw)) == $rw) return true;
	}
}

Module::Register('ModNav');

?>
