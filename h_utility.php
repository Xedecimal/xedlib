<?php

////////////////////////////////////////////////////////////////////////////////
//Session
//

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

/////////////////
// Organize Me!
//

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
