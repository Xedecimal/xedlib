<?php

require_once('h_utility.php');

/**
 * @package Presentation
 */



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
		Server::Trace("RequireModule: Returning already included module.
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
	$dat = Server::GetVar($a['VAR']);
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
