<?php

define('ACCESS_GUEST', 0);
define('ACCESS_ADMIN', 1);

//Form Functions

function StateCallback($ds, $data, $col)
{
	global $__states;
	return $__states[$data[$col]]->text;
}

function TagIToState($t, $g, $a)
{
	global $StateNames; return @$StateNames[$a['STATE']];
}

function TagIToSState($t, $g, $a)
{
	global $StateSNames; return @$StateSNames[$a['STATE']];
}

/**
 * A SelOption callback, returns the value by the integer.
 */
function SOCallback($ds, $item, $icol, $col = null)
{
	if (is_array($ds->FieldInputs[$col]->attr('VALUE')))
	foreach ($ds->FieldInputs[$col]->attr('VALUE') as $v)
	{
		$res = $v->Find($item[$icol]);
		if (isset($res)) return $res->text;
	}

	return $item[$icol];
}

/**
* Rewriting form tag to add additional functionality.
*
* @param Template $t
* @param string $g
* @param array $a
*/
function TagForm($t, $g, $a)
{
	global $PERSISTS;
	$frm = new Form(@$a['ID']);
	$t->Push($frm);
	$ret = '<form'.GetAttribs($a).'>';
	if (is_array($PERSISTS))
	foreach ($PERSISTS as $n => $v)
		$ret .= '<input type="hidden" name="'.$n.'" value="'.$v.'" />';
	$t->ReWrite('input', 'TagInput');
	$ret .= $t->GetString('<null>'.$g.'</null>');
	$obj = $t->Pop();
	$ret .= $obj->outs[0];
	$ret .= '</form>';

	if (!empty($frm->inputs))
	foreach ($frm->inputs as $in)
	{
		if (!empty($in->valid))
		{
			require_once('a_validation.php');
			$ret .= Validation::GetJS($frm);
			break;
		}
	}

	return $ret;
}

function TagInputData($atrs)
{
	global $binds;

	if (!empty($atrs['NAME']))
	{
		if (!empty($binds[0][$atrs['NAME']]))
		{
			switch (strtolower($atrs['TYPE']))
			{
				case 'password':
					$atrs['VALUE'] = null; break;
				case 'date':
					$atrs['VALUE'] = MyDateTimestamp($binds[0][$atrs['NAME']]);
					break;
				case 'radio':
				case 'checkbox':
					if (!isset($atrs['VALUE'])) $atrs['VALUE'] = 1;
					if ($atrs['VALUE'] == $binds[0][$atrs['NAME']])
						$atrs['CHECKED'] = 'checked';
					break;
				default:
					$atrs['VALUE'] = $binds[0][$atrs['NAME']];
			}
		}
	}
	return $atrs;
}

function TagInputDisplay($t, $guts, $tag)
{
	switch (strtolower($tag['TYPE']))
	{
		case 'hidden':
		case 'submit':
		case 'button':
			break;
		case 'password':
			return '********';
		case 'text':
			return @$tag['VALUE'];
		case 'date':
			return strftime('%x', $tag['VALUE']);
		case 'checkbox':
			return @$tag['CHECKED'] == 'checked' ? 'Yes' : 'No';
		default:
			echo "Unknown type: {$tag}<br/>\n";
	}
}

//TODO: Replace Nav with Tree

/**
 * @param string $t Target page that this should link to.
 */
function GetNav($t, $links, $attribs = null, $curpage = null, &$pinfo = null, &$stack = null)
{
	$ret = "\n<ul".GetAttribs($attribs).">\n";
	foreach ($links->children as $ix => $link)
	{
		$stack[] = $ix;
		if (isset($link->data['page']))
			if (substr($_SERVER['REQUEST_URI'], -strlen($link->data['page']))
			== $link->data['page'])
				$pinfo = $stack;
		$ret .= '<li>';

		if (isset($link->data['page'])) $ret .= '<a href="'.$link->data['page'].'">';
		$ret .= $link->data['text'];
		if (isset($link->data['page'])) $ret .= '</a>';
		if (!empty($link->children))
			$ret .= GetNav($t, $link, null, $curpage, $pinfo, $stack);
		$ret .= "</li>\n";
		array_pop($stack);
	}
	return $ret."</ul>\n";
}

function GetNavPath($t, $tree, $pinfo)
{
	$ret = '';
	$tn = $tree;
	if (!empty($pinfo))
	foreach ($pinfo as $level => $idx)
	{
		$tn = $tn->children[$idx];
		$ret .= ($level ? ' &raquo; ' : null);
		if (!empty($tn->data['page'])) $ret .= '<a href="'.$tn->data['page'].'">';
		$ret .= $tn->data['text'];
		if (!empty($tn->data['page'])) $ret .= '</a>';
	}
	return $ret;
}

function TagSum(&$t, $guts, $attribs)
{
	//Concatination with string based names.
	if (!empty($attribs['NAMES']))
	{
		$names = $GLOBALS[$attribs['NAMES']];
		$ret = '';
		$ix = 0;
		$m = null;
		foreach ($t->vars as $n => $v)
			if (!empty($v) && preg_match($attribs['VALUE'], $n, $m))
			{
				if ($ix++ > 0) $ret .= ', ';
				$ret .= (count($m) > 1 ? $names[$m[1]]->text : $names[$v]->text);
			}
		return $ret;
	}

	//Collect total numeric sum.
	else
	{
		$sum = null;
		foreach ($t->vars as $n => $v)
			if (preg_match($attribs['VALUE'], $n))
				$sum += $v;
		return $sum;
	}
}

?>
