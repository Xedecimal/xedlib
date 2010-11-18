<?php

function p($path)
{
	// Only translate finished paths.
	if (preg_match('/{{/', $path)) return $path;

	global $_d;
	$abs = $_d['app_abs'];
	$dir = $_d['app_dir'];
	if (substr($path, 0, strlen($abs)) == $abs) return $path;

	$tmp = @$_d['settings']['site_template'];

	// Overloaded Path
	$opath = "$tmp/$path";
	if (file_exists($opath)) return "$abs/$opath";
	// Absolute Override
	$apath = "$dir/$path";
	if (file_exists($apath)) return "$abs/$path";
	// Module Path
	$modpath = "modules/{$path}";
	if (file_exists($modpath)) return "$abs/modules/$path";
	// Xedlib Path
	$xedpath = dirname(__FILE__).'/'.$path;
	if (file_exists($xedpath))
		return GetRelativePath(dirname(__FILE__)).'/'.$path;

	return $path;
}

function l($path)
{
	global $_d;

	$ovrpath = @$_d['settings']['site_template'].'/'.$path;
	if (file_exists($ovrpath)) return "{$_d['app_dir']}/{$ovrpath}";
	$modpath = "{$_d['app_dir']}/modules/{$path}";
	if (file_exists($modpath)) return $modpath;
	$xmodpath = dirname(__FILE__)."/modules/$path";
	if (file_exists($xmodpath)) return $xmodpath;
	$xedpath = dirname(__FILE__).'/'.$path;
	if (file_exists($xedpath)) return $xedpath;
	return $path;
}

?>
