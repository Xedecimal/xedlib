<?php

require_once(__DIR__.'/../classes/TreeNode.php');

class ModNav extends Module
{
	public $Block = 'nav';

	/**
	*
	*
	* @param TreeNode $link
	* @param int $depth
	*/
	static function GetLinks($link, $depth = -1)
	{
		# Iterate Children, skip root node as it's just a container.

		$ret = null;
		if (!empty($link->children))
		{
			$ret .= '<ul class="nav">';
			foreach ($link->children as $c)
			{
				$ret .= '<li>';
				if (!empty($c->data)) $ret .= '<a href="'.$c->data.'">';
				$ret .= $c->id;
				if (!empty($c->data)) $ret .= '</a>';
				$ret .= ModNav::GetLinks($c, $depth+1);
				$ret .= '</li>';
			}
			$ret .= '</ul>';
		}

		return $ret;
	}

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

		$out = null;
		if (isset($_d['nav.links']))
		{
			$t = new Template();
			$t->ReWrite('link', array($this, 'TagLink'));
			$t->ReWrite('head', array($this, 'TagHead'));
			return ModNav::GetLinks(ModNav::LinkTree($_d['nav.links']));
		}
	}
}

Module::Register('ModNav');

?>
