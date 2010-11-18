<?php

/**
 * @package Utility
 *
 */

/**
 * Handles errors and tries to open them up more to strictly find problems.
 *
 * @param string $file Filename to output to silently.
 */
function HandleErrors($file = null)
{
	if (!empty($file)) $GLOBALS['__err_file'] = $file;
	else ini_set('display_errors', 1);
	$ver = phpversion();
	//if ($ver[0] == '5') ini_set('error_reporting', E_ALL | E_STRICT);
	//else ini_set('error_reporting', E_ALL);
	ini_set('error_log', 'errors_php.txt');
	set_error_handler("ErrorHandler");
}

/**
 * Use this when you wish to output debug information only when $debug is
 * true.
 *
 * @param string $msg The message to output.
 * @version 1.0
 * @see Error, ErrorHandler, HandleErrors
 * @since 1.0
 * @todo Alternative output locations.
 * @todo Alternative verbosity levels.
 * @example test_utility.php
 */
function Trace($msg)
{
	if (!empty($GLOBALS['debug'])) varinfo($msg);
	if (!empty($GLOBALS['__debfile'])) file_put_contents('trace.txt', $msg."\r\n", FILE_APPEND);
}

/**
 * Triggers an error.
 *
 * @param string $msg Message to the user.
 * @param int $level How critical this error is.
 */
function Error($msg, $level = E_USER_ERROR) { trigger_error($msg, $level); }

/**
 * Callback for HandleErrors, used internally.
 *
 * @param int $errno Error number.
 * @param string $errmsg Error message.
 * @param string $filename Source filename of the problem.
 * @param int $linenum Source line of the problem.
 */
function ErrorHandler($errno, $errmsg, $filename, $linenum)
{
	if (error_reporting() == 0) return;
	$errortype = array (
		E_ERROR           => "Error",
		E_WARNING         => "Warning",
		E_PARSE           => "Parsing Error",
		E_NOTICE          => "Notice",
		E_CORE_ERROR      => "Core Error",
		E_CORE_WARNING    => "Core Warning",
		E_COMPILE_ERROR   => "Compile Error",
		E_COMPILE_WARNING => "Compile Warning",
		E_USER_ERROR      => "User Error",
		E_USER_WARNING    => "User Warning",
		E_USER_NOTICE     => "User Notice",
	);
	$ver = phpversion();
	if ($ver[0] >= 5) $errortype[E_STRICT] = 'Strict Error';
	if ($ver[0] >= 5 && $ver[2] >= 2)
		$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';
	if ($ver[0] >= 5 && $ver[2] >= 3)
	{
		$errortype[E_DEPRECATED] = 'Deprecated';
		$errortype[E_USER_DEPRECATED] = 'User Deprecated';
	}

	$err = "[{$errortype[$errno]}] ".nl2br($errmsg)."<br/>";
	$err .= "Error seems to be in one of these places...\n";

	if (isset($GLOBALS['_trace']))
		$err .= '<p>Template Trace</p><p>'.$GLOBALS['_trace'].'</p>';

	$err .= GetCallstack($filename, $linenum);

	if (isset($GLOBALS['__err_callback']))
		call_user_func($GLOBALS['__err_callback'], $err);

	if (!empty($GLOBALS['__err_file']))
	{
		$fp = fopen($GLOBALS['__err_file'], 'a+');
		fwrite($fp, $err);
		fclose($fp);
	}
	else echo $err;
}

/**
 * Returns a human readable callstack in html format.
 *
 * @param string $file Source of caller.
 * @param int $line Line of caller.
 * @return string Rendered callstack.
 */
function GetCallstack($file = __FILE__, $line = __LINE__)
{
	$err = "<table><tr><td>File</td><td>#</td><td>Function</td>\n";
	$err .= "<tr>\n\t<td>$file</td>\n\t<td>$line</td>\n";
	$array = debug_backtrace();
	$err .= "\t<td>{$array[1]['function']}</td>\n</tr>";
	foreach ($array as $ix => $entry)
	{
		if ($ix < 1) continue;
		$err .= "<tr>\n";
		if (isset($entry['file']))
		{ $err .= "\t<td>{$entry['file']}</td>\n"; }
		if (isset($entry['line']))
		{ $err .= "\t<td>{$entry['line']}</td>\n"; }
		if (isset($entry['class']))
		{ $err .= "\t<td>{$entry['class']}{$entry['type']}{$entry['function']}</td>\n"; }
		else if (isset($entry['function']))
		{ $err .= "\t<td>{$entry['function']}</td>\n"; }
		$err .= "</tr>";
	}
	$err .= "</table>\n<hr size=\"1\">\n";
	return $err;
}

////////////////////////////////////////////////////////////////////////////////
//Session
//

/**
 * Attempts to set a variable using sessions.
 *
 * @param string $name Name of the value to set.
 * @param string $value Value to set.
 * @return mixed Passed $value
 */
function SetVar($name, $value)
{
	global $HTTP_SESSION_VARS;
	if (is_array(@$_SESSION)) $_SESSION[$name] = $value;
	if (is_array($HTTP_SESSION_VARS)) $HTTP_SESSION_VARS[$name] = $value;
	return $value;
}

/**
 * Returns a value from files, post, get, session, cookie and finally
 * server in that order.
 *
 * @param string $name Name of the value to get.
 * @param mixed $default Default value to return if not available.
 * @return mixed
 */
function GetVar($name, $default = null)
{
	if (strlen($name) < 1) return $default;

	global $HTTP_POST_FILES, $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS,
	$HTTP_SESSION_VARS, $HTTP_COOKIE_VARS;

	if (isset($_FILES[$name])) return $_FILES[$name];
	if (isset($_POST[$name])) return $_POST[$name];
	if (isset($_GET[$name])) return $_GET[$name];
	if (isset($_SESSION[$name])) return $_SESSION[$name];
	if (isset($_COOKIE[$name])) return $_COOKIE[$name];
	if (isset($_SERVER[$name])) return $_SERVER[$name];

	if (isset($HTTP_POST_FILES[$name]) && strlen($HTTP_POST_FILES[$name]) > 0)
		return $HTTP_POST_FILES[$name];
	if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
		return $HTTP_POST_VARS[$name];
	if (isset($HTTP_GET_VARS[$name]) && strlen($HTTP_GET_VARS[$name]) > 0)
		return $HTTP_GET_VARS[$name];
	if (isset($HTTP_SESSION_VARS[$name]) && strlen($HTTP_SESSION_VARS[$name]) > 0)
		return $HTTP_SESSION_VARS[$name];
	if (isset($HTTP_COOKIE_VARS[$name]) && strlen($HTTP_COOKIE_VARS[$name]) > 0)
		return $HTTP_COOKIE_VARS[$name];
	if (isset($HTTP_SERVER_VARS[$name]) && strlen($HTTP_SERVER_VARS[$name]) > 0)
		return $HTTP_SERVER_VARS[$name];

	return $default;
}

function SanitizeEnvironment()
{
	if (ini_get('magic_quotes_gpc'))
		foreach ($_POST as $k => $v) Sanitize($_POST[$k]);
}

function Sanitize(&$v)
{
	if (is_array($v))
		foreach ($v as $i) Sanitize($i);
	else $v = stripslashes($v);
}

/**
 * Returns a series of values specified in array form, otherwise $default
 *
 * @param string $name Name example: 'posts[]'
 * @param string $default Default value alternatively.
 * @return mixed
 */
function GetVars($name, $default = null)
{
	$m = null;
	if (preg_match('#([^\[]+)\[([^\]]+)\]#', $name, $m))
	{
		$arg = GetVar($m[1]);

		$ix = 0;
		preg_match_all('/\[([^\[]*)\]/', $name, $m);
		foreach ($m[1] as $step)
		{
			if ($ix == $step) $ix++;
			$arg = @$arg[isset($step) ? $step : $ix++];
		}
		return !empty($arg)?$arg:$default;
	}
	else return GetVar($name, $default);
}

/**
 * Returns a series of posted values that match $match.
 *
 * @param string $match Regular Expression
 * @return array [name] => value
 */
function GetAssocPosts($match)
{
	$ret = array();
	foreach (array_keys($_POST) as $n)
	{
		if (preg_match("/^$match/", $n)) $ret[$n] = FormInput::GetPostValue($n);
	}
	return $ret;
}

/**
 * Just like GetVar but returns only post values.
 *
 * @param string $name Name to retrieve.
 * @param mixed $default Default value if not available.
 * @return mixed Value of $name post variable.
 */
function GetPost($name, $default = null)
{
	global $HTTP_POST_VARS;

	if (!empty($_POST[$name]))
	{
		Trace("GetVar(): $name (Post)    -> {$_POST[$name]}<br/>\n");
		return $_POST[$name];
	}

	if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
		return $HTTP_POST_VARS[$name];

	return $default;
}

/**
 * Unsets a var that was possibly set using SetVar or other methods of session
 * setting.
 *
 * @param string $name Name of variable to get rid of.
 */
function UnsetVar($name)
{
	global $HTTP_SESSION_VARS;

	if (is_array($name))
	{
		if (!empty($name))
		foreach ($name as $var) UnsetVar($var);
	}
	if (isset($_SESSION)) unset($_SESSION[$name]);
	if (isset($HTTP_SESSION_VARS)) unset($HTTP_SESSION_VARS[$name]);
}

/**
 * Returns information on a given variable in human readable form.
 *
 * @param mixed $var Variable to return information on.
 */
function VarInfo($var, $return = false)
{
	$ret = "<div class=\"debug\"><pre>\n";
	if (!isset($var)) $ret .= "[NULL VALUE]";
	else if (is_string($var) && strlen($var) < 1) $ret .= '[EMPTY STRING]';
	$ret .= str_replace("<", "&lt;", print_r($var, true));
	$ret .= "</pre></div>\n";
	if ($return) return $ret;
	echo $ret;
}

/**
 * Attempts to keep a value persistant across all xedlib editors.
 *
 * @param string $name Name of value to persist.
 * @param mixed $value Value to be persisted.
 * @return mixed The passed $value.
 * @todo This is probably not needed in place of sessions.
 */
function Persist($name, $value)
{
	global $PERSISTS;
	$PERSISTS[$name] = $value;
	return $value;
}

/**
 * Returns a clean URI.
 *
 * @param string $url URL to clean.
 * @param array $uri URI appended on URL and cleaned.
 * @return string Cleaned URI+URL
 */
function URL($url, $uri = null)
{
	$ret = $url; # This should be encoded elsewhere and not here.

	global $PERSISTS;
	$nuri = array();
	if (!empty($uri)) $nuri = $uri;
	if (!empty($PERSISTS)) $nuri = array_merge($PERSISTS, $nuri);

	if (!empty($nuri))
	{
		$start = (strpos($ret, "?") < 1);
		foreach ($nuri as $key => $val)
		{
			if (isset($val))
			{
				$ret .= URLParse($key, $val, $start);
				$start = false;
			}
		}
	}
	return $ret;
}

/**
 * Parses an object or array for serialization to a uri.
 *
 * @param string $key Parent key for the current series to iterate.
 * @param mixed $val Object or array to iterate.
 * @param bool $start Whether or not this is the first item being parsed.
 * @return string Rendered url string.
 */
function URLParse($key, $val, $start = false)
{
	$ret = null;
	if (is_array($val))
		foreach ($val as $akey => $aval)
			$ret .= URLParse($key.'['.$akey.']', $aval, $start);
	else
	{
		//$nval = str_replace(' ', '%20', $val);
		$ret .= ($start ? '?' : '&amp;').$key.'='.urlencode($val);
	}
	return $ret;
}

/**
 * Redirect the browser with a cleanly built URI.
 *
 * @param string $url Relative path to script
 * @param array $getvars Array of get variables.
 */
function Redirect($url, $getvars = NULL)
{
	session_write_close();
	$redir = GetVar("cr", $url);
	if (is_array($getvars)) $redir = URL($url, $getvars);
	header("Location: $redir");
	die();
}

////////////////////////////////////////////////////////////////////////////////
//Date
//

/**
 * Converts an integer unix epoch timestamp to a mysql equivalent.
 *
 * @param int $ts Epoch timestamp.
 * @param bool $time Whether or not to include time.
 * @return string MySql formatted date.
 * @todo Move to data.
 */
function TimestampToMySql($ts, $time = true)
{
	if (empty($ts)) return null;
	return date($time ? 'Y-m-d h:i:s' : 'Y-m-d', $ts);
}

/**
 * Converts an integer unix epoch timestamp to a mssql equivalent.
 *
 * @param string $ts MySql time stamp.
 * @return int Timestamp.
 * @todo Move to data.
 */
function TimestampToMsSql($ts)
{
	return date("m/d/y h:i:s A", $ts);
}

/**
 * Returns timestamp from a GetDateInput style GetVar value.
 *
 * @param array $value Array of 3 elements for a date returned from a GetVar.
 * @return int Timestamp result from mktime.
 */
function DateInputToTS($value)
{
	return mktime(null, null, null, $value[0], $value[1], $value[2]);
}

/**
 * Returns a string representation of time from $ts to now. Eg. '5 days'
 *
 * @param int $ts Timestamp.
 * @return string English offset.
 */
function GetDateOffset($ts)
{
	$ss = time()-$ts;
	$mm = $ss / 60;
	$hh = $mm / 60;

	$d = $hh / 24;
	$w = $d / 7;
	$m = $d / 31;
	$y = $d / 365;

	$ret = null;
	if ($y >= 1) $ret = number_format($y, 1).' year'.($y > 1 ? 's' : null);
	else if ($m >= 1) $ret = number_format($m, 1).' month'.($m > 1 ? 's' : null);
	else if ($w >= 1) $ret = number_format($w, 1).' week'.($w > 1 ? 's' : null);
	else if ($d >= 1) $ret = number_format($d, 1).' day'.($d > 1 ? 's' : null);
	else if ($hh >= 1) $ret = number_format($hh, 1).' hour'.($hh > 1 ? 's' : null);
	else if ($mm >= 1) $ret = number_format($mm, 1).' minute'.($mm > 1 ? 's' : null);
	else $ret = number_format($ss, 1).' second'.($ss > 1 ? 's' : null);
	return $ret.' ago';
}

////////////////////////////////////////////////////////////////////////////////
//File
//

/**
 * Gets the webserver path for a given local filesystem directory.
 *
 * @param string $path
 * @return string Translated path.
 */
function GetRelativePath($path)
{
	$dr = GetVar('DOCUMENT_ROOT'); //Probably Apache situated

	if (empty($dr)) //Probably IIS situated
	{
		//Get the document root from the translated path.
		$pt = str_replace('\\\\', '/', GetVar('PATH_TRANSLATED', GetVar('ORIG_PATH_TRANSLATED')));
		$dr = substr($pt, 0, -strlen(GetVar('SCRIPT_NAME')));
	}

	$dr = str_replace('\\\\', '/', $dr);

	return substr(str_replace('\\', '/', str_replace('\\\\', '/', $path)), strlen($dr));
}

////////////////////////////////////////////////////////////////////////////////
//Array
//

/**
 * Returns a new array with the idcol into the keys of each item's idcol
 * set instead of numeric offset.
 *
 * @param array $rows
 * @param string $idcol
 * @return array
 */
function DataToArray($rows, $idcol)
{
	$ret = array();
	if (!empty($rows)) foreach ($rows as $row) $ret[$row[$idcol]] = $row;
	return $ret;
}

/**
* Converts a data result into a tree of joined children.
*
* @param array $rows Result of DataSet::Get
* @param array $assocs Array of associations array('parent1' => 'child1',
* 'parent2' => 'child2')
* @param mixed $rootid Root identifier for top-most result.
* @return TreeNode
*/
function DataToTree($rows, $assocs, $rootid = null)
{
	if (empty($rows)) return;

	# Build Flats

	foreach ($assocs as $p => $c)
	foreach ($rows as $row)
	{
		$flats[$p][$row[$p]] = new TreeNode($row, $row[$p]);
		$flats[$c[0]][$row[$c[0]]] = new TreeNode($row, $row[$c[0]]);
	}

	# Build Tree

	if (!isset($rootid) || !isset($flats[$rootid])) $tnRoot = new TreeNode();
	else $tnRoot = $flats[$rootid];

	if (!empty($flats))
	foreach ($assocs as $p => $c)
	foreach ($rows as $row)
	{
		# if parent (p) id of child ($c[1]) in parent node exists

		if (isset($flats[$p][$row[$p]]) && $row[$c[1]] != $rootid)
			$flats[$p][$row[$p]]->AddChild($flats[$c[0]][$row[$c[0]]]);
		else
			$tnRoot->AddChild($flats[$p][$row[$p]]);
	}

	$pkeys = array_keys($assocs);
	foreach ($flats[$pkeys[0]] as &$rn)
		$tnRoot->AddChild($rn);

	return $tnRoot;
}

/**
 * Array Recursive Key Sort, sorting an array of any dimension by their keys.
 *
 * @param array $array Array to be sorted
 */
function arksort(&$array)
{
	ksort($array);
	foreach ($array as $k => $v)
		if (is_array($v)) arksort($array[$k]);
}

/////////////////
// Organize Me!
//

/**
 * Runs multiple callbacks with given arguments.
 *
 * @return mixed Returns whatever the callbacks do.
 */
function RunCallbacks()
{
	$args = func_get_args();
	$target = array_shift($args);
	$ret = null;
	if (!empty($target))
	foreach ($target as $cb)
	{
		$item = call_user_func_array($cb, $args);
		if (is_array($item))
		{
			if (!isset($ret)) $ret = array();
			$ret = array_merge($ret, $item);
		}
		else $ret .= $item;
	}
	return $ret;
}

/**
 * Returns a cleaned up string to work in an html id attribute without w3c
 * errors.
 *
 * @param string $id
 * @return string
 */
function CleanID($id)
{
	return str_replace('[', '_', str_replace(']', '', str_replace(' ', '_', $id)));
}

/**
 * Attempts to disable the ability to inject different paths to gain higher
 * level directories in urls or posts.
 *
 * @param string $path Path to secure from url hacks.
 * @return string Properly secured path.
 */
function SecurePath($path)
{
	$ret = preg_replace('#^\.#', '', $path);
	$ret = preg_replace('#^/#', '', $ret);
	return preg_replace('#\.\./#', '', $ret);
}

/**
 * Returns a single flat page.
 *
 * @param array $data Data to trim.
 * @param int $page Page number we are currently on.
 * @param int $count Count of items per page.
 * @return array Poperly sliced array.
 */
function GetFlatPage($data, $page, $count)
{
	return array_splice($data, $count*$page, $count);
}

/**
 * Returns a database related page filter.
 *
 * @todo Move this to Data package.
 * @param int $page Current page.
 * @param int $count Count of items per page.
 * @return array DataSet usable 'filter' argument.
 */
function GetPageFilter($page, $count)
{
	return array(($page-1)*$count, $count);
}

/**
 * Gets html rendered series of pages.
 *
 * @param array $total Total items to paginate.
 * @param int $count Number of items per page.
 * @param array $args Additional uri args.
 * @return string Rendered html page display.
 * @todo Template this.
 */
function GetPages($total, $count, $args = null)
{
	global $me;

	if (!is_numeric($total))
	{
		throw new Exception("Invalid Total Value");
		return;
	}
	if ($total <= $count) return;

	$cp = GetVar('cp');
	$ret = null;
	$page = 0;

	if ($args == null) $args = array();

	if ($cp > 1)
		$ret .= Getbutton(URL($me, array_merge($args, array('cp' => 0))), 'start.png', 'Start')
		.' &ndash; ';
	if ($cp > 0)
		$ret .= GetButton(URL($me, array_merge($args, array('cp' => $cp-1))), 'prev.png', 'Previous').
		' &ndash; ';

	for ($ix = 0; $ix < $total; $ix += $count)
	{
		if ($ix > 0) $ret .= ' &ndash; ';
		$page = $ix / $count;
		$url = URL($me, array_merge(array('cp' => $page), $args));
		if ($page == $cp) $ret .= '<b>'.($page+1).'</b>';
		else $ret .= '<b><a href="'.$url.'">'.($page+1).'</a></b>';
	}

	if ($cp < $page)
		$ret .= ' &ndash; '.
		GetButton(URL($me, array_merge(array('cp' => $cp+1), $args)), 'next.png', 'Next');
	if ($cp < max(0, $page-1))
		$ret .= ' &ndash; '.
		GetButton(URL($me, array_merge(array('cp' => $page), $args)), 'end.png', 'End');

	return $ret;
}

/**
 * PHP4 compatible array clone.
 *
 * @param mixed $arr Item to properly clone in php5 without references.
 * @return mixed Cloned copy of whatever you throw at it.
 */
function array_clone($arr)
{
	if (substr(phpversion(), 0, 1) != '5') { $copy = $arr; return $copy; }
	$ret = array();

	foreach ($arr as $id => $val)
	{
		if (is_array($val)) $ret[$id] = array_clone($val);
		else if (is_object($val)) $ret[$id] = clone($val);
		else $ret[$id] = $val;
	}

	return $ret;
}

/**
 * Create a directory recursively supporting php4.
 *
 * @param string $path Complete path to recursively create.
 * @param int $mode Initial mode for linux based filesystems.
 * @return bool Whether the directory creation was successful.
 */
function mkrdir($path, $mode = 0755)
{
	//$path = rtrim(preg_replace(array('#\\#', '#/{2,}#'), '/', $path), '/');
	$e = explode("/", ltrim($path, "/"));
	if (substr($path, 0, 1) == "/") $e[0] = "/".$e[0];
	$c = count($e);
	$cp = $e[0];
	for ($i = 1; $i < $c; $i++)
	{
		if (!is_dir($cp) && !@mkdir($cp, $mode)) return false;
		$cp .= "/".$e[$i];
	}
	return @mkdir($path, $mode);
}

/**
 * Returns var if it is set, otherwise def.
 * @param mixed $var Variable to check and return if exists.
 * @param mixed $def Default to return if $var is not set.
 * @return mixed $var if it is set, otherwise $def.
 */
function ifset($var, $def)
{
	if (isset($var)) return $var; return $def;
}

/**
 * Will possibly be depricated.
 * @param array $tree Stack of linkable items.
 * @param string $target Target script of interaction.
 * @param string $text Text to test.
 * @return string Text with words anchored in maybe html?
 */
function linkup($tree, $target, $text)
{
	require_once('h_template.php');
	$keys = array_keys($tree);
	$cur = null;
	$reps = array();

	$words = preg_split('/\s|\n/s', $text);
	$vp = new VarParser();

	foreach ($words as $word)
	{
		if (isset($cur))
		{
			if (in_array($word, array_keys($cur)))
			{
				$p = $vp->ParseVars($target,
					array('word' => $word));
				$reps[$word] = $p;
				if (isset($cur[$word]))
				{
					$cur = $cur[$word];
				}
				else $cur = null;
			}
		}
		if (in_array($word, $keys))
		{
			$p = $vp->ParseVars($target,
				array('name' => $tree[$word][0], 'word' => $word));
			$reps[$word] = $p;
			$cur = $tree[$word];
		}
	}

	$ret = $text;
	foreach ($reps as $word => $val) $ret = str_replace($word, $val, $ret);
	return $ret;
}


/**
 * Get a single result of a zip code location and information.
 *
 * @param DataSet $ds Location of zip code data.
 * @param int $zip Zip code to location information on.
 * @return array Single database result of the specified zip code.
 */
function GetZipLocation($ds, $zip)
{
	return $ds->GetOne(array('zip' => $zip));
}

/**
 * Locate the amount of miles between two latitudes and longitudes.
 *
 * @param float $lat1 Latitude Source
 * @param float $lat2 Latitude Destination
 * @param float $lon1 Longitude Source
 * @param float $lon2 Longitude Destination
 * @return float Distance.
 */
function GetMiles($lat1, $lat2, $lon1, $lon2)
{
	$lat1 = deg2rad($lat1);
	$lon1 = deg2rad($lon1);
	$lat2 = deg2rad($lat2);
	$lon2 = deg2rad($lon2);

	$delta_lat = $lat2 - $lat1;
	$delta_lon = $lon2 - $lon1;

	$temp = pow(sin($delta_lat/2.0),2) + cos($lat1) * cos($lat2) * pow(sin($delta_lon/2.0),2);
	$distance = 3956 * 2 * atan2(sqrt($temp),sqrt(1-$temp));
	return $distance;
}

/**
 * Zip code lookup to collect zip codes by mileage using the Great Circle
 * algorithm.
 *
 * @param DataSet $ds Location of zip code data.
 * @param int $zip Source zip code.
 * @param int $range Miles to search for other zip codes.
 * @return array ['dists'][zip] => distance, ['zips'] => zip
 */
function GetZips($ds, $zip, $range)
{
	$details = GetZipLocation($ds, $zip);  // base zip details
	if ($details == false) return null;

	$lat_range = $range / 69.172;
	$lon_range = abs($range / (cos($details['lng']) * 69.172));
	$min_lat = number_format($details['lat'] - $lat_range, "4", ".", "");
	$max_lat = number_format($details['lat'] + $lat_range, "4", ".", "");
	$min_lon = number_format($details['lng'] - $lon_range, "4", ".", "");
	$max_lon = number_format($details['lng'] + $lon_range, "4", ".", "");

	$query = "SELECT zip, lat, lng, name FROM zips
		WHERE lat BETWEEN '{$min_lat}' AND '{$max_lat}'
		AND lng BETWEEN '{$min_lon}' AND '{$max_lon}'";

	$items = $ds->GetCustom($query);

	$return = array();

	foreach ($items as $i)
	{
		$dist = GetMiles($details['lat'], $i['lat'], $details['lng'], $i['lng']);
		if ($dist <= $range)
		{
			$zip = str_pad($i['zip'], 5, "0", STR_PAD_LEFT);
			$return['dists'][$zip] = $dist;
			$return['zips'][] = $zip;
		}
	}

	asort($return['dists']);

	return $return;
}

define('PREG_FILES', 1);
define('PREG_DIRS', 2);

/**
 * Regular expression matches files located in $path using $pattern and $opts.
 *
 * @param string $pattern Used by preg_match.
 * @param string $path Location to search.
 * @param int $opts Bitwise combination of PREG_FILES and PREG_DIRS.
 * @return array index => filename
 */
function preg_files($pattern, $path = '.', $opts = 3, &$subs = null)
{
	$ret = array();
	$dp = opendir($path);
	while ($file = readdir($dp))
	{
		if (is_file("$path/$file") && $opts & PREG_FILES != PREG_FILES) continue;
		if (is_dir("$path/$file") && $opts & PREG_DIRS != PREG_DIRS) continue;
		$s = null;
		if (preg_match($pattern, $file, $s))
		{
			$subs[$file] = $s;
			$ret[] = $file;
		}
	}
	return $ret;
}

function preg_file($pattern, $path, $opts = 3)
{
	$ret = preg_files($pattern, $path, $opts);
	return !empty($ret) ? $ret[0] : null;
}



/**
 * Encrypts strings formatted for htaccess files for windows based Apache
 * installations as they work a bit differently than linux.
 *
 * @param string $plainpasswd Password to encrypt.
 * @return string Encrypted string that can be placed in an htaccess file.
 */
function crypt_apr1_md5($plainpasswd)
{
	$salt = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
	$len = strlen($plainpasswd);
	$text = $plainpasswd.'$apr1$'.$salt;
	$bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
	for($i = $len; $i > 0; $i -= 16) { $text .= substr($bin, 0, min(16, $i)); }
	for($i = $len; $i > 0; $i >>= 1) { $text .= ($i & 1) ? chr(0) : $plainpasswd{0}; }
	$bin = pack("H32", md5($text));
	for($i = 0; $i < 1000; $i++)
	{
		$new = ($i & 1) ? $plainpasswd : $bin;
		if ($i % 3) $new .= $salt;
		if ($i % 7) $new .= $plainpasswd;
		$new .= ($i & 1) ? $bin : $plainpasswd;
		$bin = pack("H32", md5($new));
	}
	$tmp = '';
	for ($i = 0; $i < 5; $i++)
	{
		$k = $i + 6;
		$j = $i + 12;
		if ($j == 16) $j = 5;
		$tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
	}
	$tmp = chr(0).chr(0).$bin[11].$tmp;
	$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
	"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
	"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	return "\$apr1\$".$salt."$".$tmp;
}

/**
 * Encrypts $text using $key as a passphrase using RIJNDAEL algorithm.
 *
 * @param string $key Key used to encrypt.
 * @param string $text Text to encrypt.
 * @return string Encrypted text.
 */
function RIJ_Encrypt($key, $text)
{
	srand();
	$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	$enc = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text,
		MCRYPT_MODE_CBC, $iv);
	return array($iv, $enc);
}

/**
 * Decrypts $text using $key and $iv using RIJNDAEL algorithm.
 *
 * @param string $key Used to decrypt $text.
 * @param string $text Ecrypted text.
 * @param string $iv Initialization Vector.
 * @return string Decrypted text.
 */
function RIJ_Decrypt($key, $text, $iv = null)
{
	srand();
	if ($iv == null)
	{
		$ivsize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($ivsize, MCRYPT_RAND);
	}
	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key,
		$text, MCRYPT_MODE_CBC, $iv), "\0");
}

/**
 * Returns every entry located in a given Apache style .htpasswd file.
 *
 * @param string $path Location without filename of the .htaccess file.
 * @return array username => password
 */
function get_htpasswd($path)
{
	$ret = array();
	$m = null;
	preg_match_all('/([^\n\r:]+):([^\r\n]+)/m', file_get_contents($path.'/.htpasswd'), $m);
	foreach ($m[1] as $i => $v) $ret[$v] = $m[2][$i];
	return $ret;
}

/**
 * Returns all directories for a given path but not recursively.
 *
 * @param string $path Directory to search.
 * @return array Single level array of directories located.
 */
function dir_get($path = '.')
{
	$ret = array();
	$dp = opendir($path);
	while ($f = readdir($dp))
	{
		if ($f[0] == '.') continue;
		if (is_dir($path.'/'.$f)) $ret[] = $f;
	}
	return $ret;
}

define('OPT_FILES', 1);
define('OPT_DIRS', 2);

/**
 * Returns an array of all files located recursively in a given path, excluding
 * anything matching the regular expression of $exclude.
 *
 * @param string $path Path to recurse.
 * @param string $exclude Passed to preg_match to blacklist files.
 * @return array Series of non-directories that were not excluded.
 */
function Comb($path, $exclude, $flags = 3)
{
	if ($exclude != null && preg_match($exclude, $path)) return array();
	// This is a file and unable to recurse.
	if (is_file($path))
	{
		if (OPT_FILES & $flags) return array($path);
		return array();
	}

	else if (is_dir($path))
	{
		// We will return ourselves if we're including directories.
		$ret = ($flags & OPT_DIRS) ? array($path) : array();
		$dp = opendir($path);
		while ($f = readdir($dp))
		{
			if ($f[0] == '.') continue;
			$ret = array_merge($ret, Comb($path.'/'.$f, $exclude, $flags));
		}

		return $ret;
	}

	return array();
}

/**
 * Converts html style tag attributes into an xml array style.
 *
 * @param string $atrs Attributes to process.
 * @return array String indexed array of attributes.
 */
function ParseAtrs($atrs)
{
	if (empty($atrs)) return;
	$m = null;
	preg_match_all('/([^= ]+)="([^"]+)"/', $atrs, $m);
	for ($ix = 0; $ix < count($m[1]); $ix++) $ret[$m[1][$ix]] = $m[2][$ix];
	return $ret;
}

/**
 * Will set a session variable to $name with the value of GetVar and return it.
 *
 * @param string $name Name of our state object.
 * @return mixed The GetVar value of $name.
 */
function GetState($name, $def = null)
{
	return SetVar($name, GetVar($name, $def));
}

/**
 * Converts an array to a tree using TreeNode objects.
 *
 * @param TreeNode $n Node we are working with.
 * @param array $arr Array items to add to $n.
 * @return TreeNode Root of the tree.
 */
function ArrayToTree($n, $arr)
{
	$root = new TreeNode($n);
	foreach ($arr as $k => $v)
	{
		if (is_array($v)) $n = ArrayToTree($k, $v);
		else $n = new TreeNode($v);
		$root->AddChild($n);
	}
	return $root;
}

/**
 * Will let a variable be set only if it's not already set.
 *
 * @param mixed $var Variable to Let.
 * @param mixed $val Value to set to $var.
 */
function Let(&$var, $val)
{
	if (!isset($var)) $var = $val;
}

function Ask(&$var, $default)
{
	return !empty($var) ? $var : $default;
}

function GetMonthName($month)
{
	return date('F', strtotime($month.'/1/'.date('Y')));
}

function SendDownloadStart($filename, $size = null)
{
	//Caching
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

	//Ensure we get a download dialog
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment; filename=\"$filename\";");
	header("Content-Transfer-Encoding: binary");
	if (isset($size)) header("Content-Length: {$size}");
}

function GetQ()
{
	$pi = GetVar('q', '/home');
	if (!empty($pi)) return explode('/', substr($pi, 1));
}

?>
