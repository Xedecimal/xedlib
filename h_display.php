<?php

define('STATE_CREATE', 0);
define('STATE_EDIT', 1);

define('CONTROL_SIMPLE', 0);
define('CONTROL_BOUND', 1);

/**
 * Simply returns yes or no depending on the positivity of the value.
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for yes or no.
 * @return string 'Yes' or 'No'.
 */
function DBoolCallback($ds, $val, $col) { return BoolCallback($val[$col]); }

function BoolCallback($val) { return $val ? 'Yes' : 'No'; }

/**
 * @param DataSet $ds Dataset associated with this callback.
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for a unix epoch timestamp.
 */
function TSCallback($ds, $val, $col) { return date('m/d/Y', $val[$col]); }

/**
 * @param array $val Value array, usually a row from a dataset.
 * @param mixed $col Index of $val to test for a mysql formatted date.
 */
function DateCallbackD($ds, $val, $col) { return DateCallback($val[$col]); }
function DateCallback($val) { return date('m/d/Y', MyDateTimestamp($val)); }

function DateTimeCallbackD($ds, $val, $col) { return DateTimeCallback($val[$col]); }
function DateTimeCallback($val) { return date('m/d/Y h:i:s a', $val); }

define('ACCESS_GUEST', 0);
define('ACCESS_ADMIN', 1);

/**
 * A generic page, associated with h_main.php and passed on to index.php .
 */
class DisplayObject
{
	/**
	 * Creates a new display object.
	 */
	function __construct() { }

	/**
	 * Gets the content of this object.
	 * @param array $data Context data.
	 * @return string The name of this page for the browser's titlebar.
	 */
	function Get()
	{
		return "Class " . get_class($this) . " does not overload Get().";
	}

	/**
	 * Prepare this object for output.
	 * @param array $data Context data.
	 */
	function Prepare() { }
}

//Form Functions

$StateNames = array(
	'NA' => 'None', 'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona',
	'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
	'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida',
	'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
	'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky',
	'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
	'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
	'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
	'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
	'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
	'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
	'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania',
	'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
	'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
	'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
	'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
	'CN' => 'Canada',
	'AE' => 'Armed Forces Africa / Canada / Europe / Middle East'
);

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
 * Converts any type of FormInput into a usable string, for example in text
 * only emails and suchs.
 *
 * @param FormInput $field
 * @return string Converted field.
 */
function InputToString($field)
{
	$val = GetVar($field->name);

	if ($field->type == 'time')
		return "{$val[0]}:{$val[1]}".($val[2] == 0 ? ' AM' : ' PM');
	else if ($field->type == 'checks')
	{
		$out = null;
		if (!empty($val))
		foreach (array_keys($val) as $ix)
			$out .= ($ix > 0?', ':'').$field->valu[$ix]->text;
		return $out;
	}
	else if ($field->type == 'radios') return $field->valu[$val]->text;
	else if ($field->type == 'boolean') return $val == 1 ? 'yes' : 'no';
	else if ($field->type == 'select') return $field->valu[$val]->text;
	else Error("Unknown field type.");
}

function ArrayToSelText($array, $sel)
{
	$ret = null;
	foreach ($array as $ix => $v)
		$ret .= ($ix > 0?', ':null).$sel[$v]->text;
	return $ret;
}

function GetHiddenPost($name, $val)
{
	$ret = '';
	if (is_array($val))
		foreach ($val as $n => $v)
			$ret .= GetHiddenPost($name.'['.$n.']', $v);
	else if (!empty($val)) $ret .= '<input type="hidden" name="'.$name.'" value="'.$val."\" />\n";
	return $ret;
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

/**
* Rewrites inputs into FormInputs for further processing.
*
* @param Template $t
* @param string $guts
* @param array $attribs
* @param string $tag
* @param mixed $args
* @return string
*/
function TagInput($t, $guts, $attribs, $tag, $args)
{
	// Handle Persistent Values

	if ($args['persist'])
	{
		switch (strtolower($attribs['TYPE']))
		{
			case 'radio':
				if (GetVar($attribs['NAME']) == $attribs['VALUE'])
					$attribs['CHECKED'] = 'checked';
				break;
			default:
				if (!isset($attribs['VALUE']))
				$attribs['VALUE'] = GetVar($attribs['NAME']);
				break;
		}
	}

	$searchable =
		$attribs['TYPE'] != 'hidden' &&
		$attribs['TYPE'] != 'radio' &&
		$attribs['TYPE'] != 'checkbox' &&
		$attribs['TYPE'] != 'submit';

	if (!empty($attribs['TYPE']))
	{
		$fi = new FormInput(null, @$attribs['TYPE'], @$attribs['NAME'],
			@$attribs['VALUE'], $attribs);
		if (get_class($t->GetCurrentObject()) == 'Form')
			$t->GetCurrentObject()->AddInput($fi);
		return $fi->Get(null, false);
	}

	$ret = '';

	if ($args == 'search' && $searchable)
	{
		if (!isset($attribs['ID'])) $attribs['ID'] = 'in'.$attribs['NAME'];

		$ret .= "<input name=\"search[{$attribs['NAME']}]\" type=\"checkbox\"
			onclick=\"$('#div{$attribs['ID']}').toggle('slow');\" />";
		$ret .= '<div id="div'.$attribs['ID'].'" style="display: none">';
	}

	$ret .= $field;

	if ($args == 'search' && $searchable) $ret .= '</div>';
	return $ret;
}

function GetAttribs($attribs)
{
	$ret = '';
	if (is_array($attribs))
	foreach ($attribs as $n => $v)
		$ret .= ' '.strtolower($n).'="'.htmlspecialchars($v).'"';
	else return ' '.$attribs;
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
			return date('m/d/Y', $tag['VALUE']);
		case 'checkbox':
			return @$tag['CHECKED'] == 'checked' ? 'Yes' : 'No';
		default:
			echo "Unknown type: {$tag}<br/>\n";
	}
}

//TODO: Replace Nav with Tree

/**
* put your comment there...
*
* @param TreeNode $root Root treenode item.
* @param string $text VarParser capable text linked to treenode data items.
*/
function GetTree($root, $text)
{
	$vp = new VarParser();

	$ret = null;
	if (!empty($root->children))
	{
		$ret .= '<ul>';
		foreach ($root->children as $c)
		{
			$ret .= '<li>'.$vp->ParseVars($text, $c->data);
			$ret .= GetTree($c, $text);
			$ret .= "</li>";
		}
		$ret .= '</ul>';
	}
	return $ret;
}

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
