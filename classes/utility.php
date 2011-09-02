<?php

class U
{
	public static function StateNames()
	{
		return array('NA' => 'None', 'AL' => 'Alabama', 'AK' => 'Alaska',
			'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
			'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
			'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
			'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
			'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky',
			'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
			'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
			'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
			'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
			'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
			'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
			'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island', 'SC' => 'South Carolina',
			'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
			'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
			'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
			'WY' => 'Wyoming', 'DC' => 'District of Columbia', 'CN' => 'Canada',
			'AE' => 'Armed Forces Africa / Canada / Europe / Middle East');
	}

	static function DOut($text)
	{
		file_put_contents('debug.txt', $text, FILE_APPEND);
	}

	/**
	 * Returns information on a given variable in human readable form.
	 *
	 * @param mixed $var Variable to return information on.
	 */
	static function VarInfo($var, $return = false)
	{
		$ret = "<div class=\"debug\"><pre>\n";
		if (!isset($var)) $ret .= "[NULL VALUE]";
		else if (is_string($var) && strlen($var) < 1) $ret .= '[EMPTY STRING]';
		$ret .= str_replace("<", "&lt;", print_r($var, true));
		$ret .= "</pre></div>\n";
		if ($return) return $ret;
		echo $ret;
	}

	static function Ask(&$var, $default)
	{
		return !empty($var) ? $var : $default;
	}

	/**
	 * Simply returns yes or no depending on the positivity of the value.
	 * @param DataSet $ds Associated dataset needed for the callee.
	 * @param array $val Value array, usually a row from a dataset.
	 * @param mixed $col Index of $val to test for yes or no.
	 * @TODO Find me a better home.
	 * @return string 'Yes' or 'No'.
	 */
	static function DBoolCallback($ds, $val, $col) { return U::BoolCallback($val[$col]); }
	static function BoolCallback($val) { return $val ? 'Yes' : 'No'; }

	/**
	 * @param DataSet $ds Dataset associated with this callback.
	 * @param array $val Value array, usually a row from a dataset.
	 * @param mixed $col Index of $val to test for a unix epoch timestamp.
	 */
	static function TSCallback($ds, $val, $col) { return strftime('%x', $val[$col]); }

	/**
	 * @param array $val Value array, usually a row from a dataset.
	 * @param mixed $col Index of $val to test for a mysql formatted date.
	 */
	static function DateCallbackD($ds, $val, $col) { return DateCallback($val[$col]); }
	static function DateCallback($val) { return date('m/d/Y', Database::MyDateTimestamp($val)); }

	static function DateTimeCallbackD($ds, $val, $col) { return DateTimeCallback($val[$col]); }
	static function DateTimeCallback($val) { return date('m/d/Y h:i:s a', $val); }

	/**
	 * Runs multiple callbacks with given arguments.
	 *
	 * @return mixed Returns whatever the callbacks do.
	 */
	static function RunCallbacks()
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
				if (empty($ret)) $ret = array();
				$ret = array_merge($ret, $item);
			}
			else $ret .= $item;
		}
		else if (!empty($args)) $ret = $args[0];
		return $ret;
	}

	/**
	 * Returns var if it is set, otherwise def.
	 * @param mixed $var Variable to check and return if exists.
	 * @param mixed $def Default to return if $var is not set.
	 * @return mixed $var if it is set, otherwise $def.
	 */
	static function ifset($var, $def)
	{
		if (isset($var)) return $var; return $def;
	}

	static function Let(&$var, $val)
	{
		if (!isset($var)) $var = $val;
	}

	/**
	 * Returns a string representation of time from $ts to now. Eg. '5 days'
	 *
	 * @param int $ts Timestamp.
	 * @return string English offset.
	 */
	static function GetDateOffset($ts)
	{
		$start = time()-$ts;
		if ($start < 0) $nstart = abs($start);
		else $nstart = $start;
		$ret = U::DateRangeToString($nstart);
		if ($start < 0) return 'in '.$ret;
		else return $ret.' ago';		
	}
	
	static function DateRangeToString($secs)
	{
		$ss = $secs;
		$mm = (int)($ss / 60);
		$hh = (int)($mm / 60);

		$d = (int)($hh / 24);
		$w = (int)($d / 7);
		$m = (int)($d / 31);
		$y = (int)($d / 365);

		$ret = null;
		if ($y >= 1) $ret = $y.' year'.($y > 1 ? 's' : null);
		else if ($m >= 1) $ret = $m.' month'.($m > 1 ? 's' : null);
		else if ($w >= 1) $ret = $w.' week'.($w > 1 ? 's' : null);
		else if ($d >= 1) $ret = $d.' day'.($d > 1 ? 's' : null);
		else if ($hh >= 1) $ret = $hh.' hour'.($hh > 1 ? 's' : null);
		else if ($mm >= 1) $ret = $mm.' minute'.($mm > 1 ? 's' : null);
		else $ret = $ss.' second'.($ss > 1 ? 's' : null);
		return $ret;
	}
}

if (!function_exists('http_build_url'))
{
	define('HTTP_URL_REPLACE', 1);				// Replace every part of the first URL when there's one of the second URL
	define('HTTP_URL_JOIN_PATH', 2);			// Join relative paths
	define('HTTP_URL_JOIN_QUERY', 4);			// Join query strings
	define('HTTP_URL_STRIP_USER', 8);			// Strip any user authentication information
	define('HTTP_URL_STRIP_PASS', 16);			// Strip any password authentication information
	define('HTTP_URL_STRIP_AUTH', 32);			// Strip any authentication information
	define('HTTP_URL_STRIP_PORT', 64);			// Strip explicit port numbers
	define('HTTP_URL_STRIP_PATH', 128);			// Strip complete path
	define('HTTP_URL_STRIP_QUERY', 256);		// Strip query string
	define('HTTP_URL_STRIP_FRAGMENT', 512);		// Strip any fragments (#identifier)
	define('HTTP_URL_STRIP_ALL', 1024);			// Strip anything but scheme and host

	/**
	 * Build an URL
	 * The parts of the second URL will be merged into the first according to
	 * the flags argument.
	 * 
	 * @param mixed $url (Part(s) of) an URL in form of a string or associative
	 * array like parse_url() returns
	 * @param mixed $parts Same as the first argument
	 * @param int $flags A bitmask of binary or'ed HTTP_URL constants (Optional)
	 * HTTP_URL_REPLACE is the default
	 * @param array $new_url If set, it will be filled with the parts of the
	 * composed url like parse_url() would return.
	 */
	function http_build_url($url, $parts=array(), $flags=HTTP_URL_REPLACE, &$new_url=false)
	{
		$keys = array('user','pass','port','path','query','fragment');

		// HTTP_URL_STRIP_ALL becomes all the HTTP_URL_STRIP_Xs
		if ($flags & HTTP_URL_STRIP_ALL)
		{
			$flags |= HTTP_URL_STRIP_USER;
			$flags |= HTTP_URL_STRIP_PASS;
			$flags |= HTTP_URL_STRIP_PORT;
			$flags |= HTTP_URL_STRIP_PATH;
			$flags |= HTTP_URL_STRIP_QUERY;
			$flags |= HTTP_URL_STRIP_FRAGMENT;
		}
		// HTTP_URL_STRIP_AUTH becomes HTTP_URL_STRIP_USER and HTTP_URL_STRIP_PASS
		else if ($flags & HTTP_URL_STRIP_AUTH)
		{
			$flags |= HTTP_URL_STRIP_USER;
			$flags |= HTTP_URL_STRIP_PASS;
		}

		# Parse the original URL
		if (is_string($url))
			$parse_url = parse_url($url);
		else $parse_url = $url;

		# Scheme and Host are always replaced
		if (isset($parts['scheme']))
			$parse_url['scheme'] = $parts['scheme'];
		if (isset($parts['host']))
			$parse_url['host'] = $parts['host'];

		// (If applicable) Replace the original URL with it's new parts
		if ($flags & HTTP_URL_REPLACE)
		{
			foreach ($keys as $key)
			{
				if (isset($parts[$key]))
					$parse_url[$key] = $parts[$key];
			}
		}
		else
		{
			// Join the original URL path with the new path
			if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH))
			{
				if (isset($parse_url['path']))
					$parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
				else
					$parse_url['path'] = $parts['path'];
			}

			// Join the original query string with the new query string
			if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY))
			{
				if (isset($parse_url['query']))
					$parse_url['query'] .= '&' . $parts['query'];
				else
					$parse_url['query'] = $parts['query'];
			}
		}

		// Strips all the applicable sections of the URL
		// Note: Scheme and Host are never stripped
		foreach ($keys as $key)
		{
			if ($flags & (int)constant('HTTP_URL_STRIP_' . strtoupper($key)))
				unset($parse_url[$key]);
		}


		$new_url = $parse_url;

		return
			 ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
			.((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') .'@' : '')
			.((isset($parse_url['host'])) ? $parse_url['host'] : '')
			.((isset($parse_url['port'])) ? ':' . $parse_url['port'] : '')
			.((isset($parse_url['path'])) ? $parse_url['path'] : '')
			.((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
			.((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
		;
	}
}

?>
