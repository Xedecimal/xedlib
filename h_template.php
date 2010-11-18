<?php

require_once('h_utility.php');

/**
 * @package Presentation
 */

/**
 * Returns a callstack style template stack, showing the path that
 * processing has gone.
 * @param array $data Context information.
 * @return string Debug template stack.
 */
function GetTemplateStack(&$data)
{
	$ret = null;
	if (!empty($data['template.stack']))
	{
		$parsers = $data['template.parsers'];
		$stack = $data['template.stack'];
		for ($ix = count($data['template.stack'])-1; $ix >= 0; $ix--)
		{
			$ret .= "{$stack[$ix]} made it to line: ".
				xml_get_current_line_number($parsers[$ix])."<br/>\n";
		}
	}
	return $ret;
}

/**
 * Requires $file and creates a new $class of type DisplayObject to prepare and
 * present.
 *
 * @param array $data Context information.
 * @param string $file Filename to require.
 * @param string $class Class to prepare.
 * @return mixed Module
 */
function RequireModule(&$data, $file, $class)
{
	if (isset($data['includes'][$class]))
	{
		Trace("RequireModule: Returning already included module.
			({$class})<br/>\n");
		return $data['includes'][$class];
	}

	if (!file_exists($file))
	{
		Error("\n<b>What</b>: File ({$file}) does not exist.
		<b>Who</b>: RequireModule()
		<b>Where</b>: Template stack...\n".GetTemplateStack($data).
		"<b>Why</b>: You may have moved or deleted this file.");
	}

	require_once($file);

	if (!class_exists($class))
		Error("\n<b>What</b>: Class ({$class}) does not exist.
		<b>Who</b>: &lt;INCLUDE> tag
		<b>Where</b>: Template stack...\n".GetTemplateStack($data).
		"<b>Why</b>: You may have moved this class to another file.");

	$mod = new $class();
	$data['includes'][$class] = $mod;
	$mod->Prepare($data);
	return $mod;
}

require_once('h_display.php');

/**
 * A template
 */

class TemplateBehavior
{
	/**
	 * I forgot what this does.
	 * @var bool
	 */
	public $MakeDynamic = false;

	/**
	 * Whether variables in the data {{example}} will bleed through if null.
	 *
	 * @var bool
	 */
	public $Bleed = true;
}

/**
 * Enter description here...
 */
class VarParser
{
	/**
	 * Vars specified here override all else.
	 *
	 * @var array
	 */
	public $vars;

	public $Behavior;

	function __construct()
	{
		$this->Behavior = new VarParserBehavior;
	}

	/**
	 * Processes variables in the given string $data for variables named as keys
	 * in the array $vars.
	 *
	 * @param string $data Data to search for variables.
	 * @param array $vars Override existing names with these.
	 * @return mixed Reformatted text with variables replaced.
	 */
	function ParseVars($data, $vars = null)
	{
		if (empty($data)) return '';
		$this->vars = $vars;
		return preg_replace_callback('/\{\{([^\}]+)\}\}/',
			array(&$this, 'var_parser'), $data);
	}

	/**
	 * Callback for each regex match, not for external use.
	 *
	 * @param array $match Matches found by preg_replace_callback calling this.
	 * @return string
	 */
	function var_parser($match)
	{
		/*$tvar = $match[1];

		$ret = null;

		//Process an array values from $this->vars
		if (is_array($this->vars) && isset($this->vars[$tvar]))
			$ret = $this->vars[$tvar];
		//Process an object property from $this->vars
		else if (is_object($this->vars))
		{
			$ov = get_object_vars($this->vars);
			if (isset($ov[$tvar])) $ret = $ov[$tvar];
		}
		else if (isset($GLOBALS[$tvar])) $ret = $GLOBALS[$tvar];
		else if (defined($tvar)) $ret = constant($tvar);
		else $ret = $this->Bleed ? $match[0] : null;

		return $ret;*/

		$tvar = $match[1];

		// This is an advanced variable.
		if (strpos($tvar, '.') && !$this->Behavior->SimpleVars)
		{
			$indices = explode('.', $tvar);
			$var = $this->FindVar($indices[0]);

			for ($ix = 1; $ix < count($indices); $ix++)
			{
				if (is_object($var)) $var = $var->$indices[$ix];
				else if (isset($var[$indices[$ix]])) $var = $var[$indices[$ix]];
			}

			if (!is_array($var)) return $var;
		}
		$ret = $this->FindVar($tvar);
		if (isset($ret)) return $ret;
		return $this->Behavior->Bleed ? $match[0] : $ret;
	}

	function FindVar($tvar)
	{
		global $$tvar;
		if (!empty($this->vars) && key_exists($tvar, $this->vars))
			return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if (isset($this->data[$tvar])) return $this->data[$tvar];
		else if ($this->Behavior->UseGetVar) return GetVar($tvar);
		return null;
	}
}

class VarParserBehavior
{
	/**
	 * Whether variables in the template {{example}} will bleed through if not
	 * set.
	 *
	 * @var bool
	 */
	public $Bleed = true;

	public $UseGetVar = false;

	public $SimpleVars = false;
}

function TagEmpty($t, $g, $a)
{
	if (empty($GLOBALS[$a['VAR']]) && empty($t->vars[$a['VAR']])) return $g;
}

function TagNotEmpty($t, $g, $a)
{
	if (!empty($GLOBALS[$a['VAR']])) return $g;
	if (!empty($t->vars[$a['VAR']])) return $g;
}

function TagRelativeDate($t, $g)
{
	return GetDateOffset(strtotime($t->ProcessVars($g)));
}

function TagPassthrough($t, $g)
{
	return $g;
}

function TagLoop($t, $g, $a)
{
	$vp = new VarParser;
	$ret = null;
	for ($ix = $a['START']; $ix <= $a['END']; $ix++)
	{
		$ret .= $vp->ParseVars($g, array($a['VAR'] => $ix));
	}
	return $ret;
}

function TagEach($t, $g, $a)
{
	$vp = new VarParser;
	$vp->Behavior->UseGetVar = true;
	$ret = null;
	$dat = GetVar($a['VAR']);
	for ($ix = $a['START']; $ix < count($dat); $ix++)
		$ret .= $vp->ParseVars(preg_replace("/{$a['IDX']}/i", $ix, $g));
	return $ret;
}

function TagUpper($t, $g, $a) { return strtoupper($g); }

function TagExists($t, $g, $a)
{
	if (file_exists($a['FILE'])) return $g;
}

?>
