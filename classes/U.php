<?php

class U
{
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
	static function DBoolCallback($ds, $val, $col) { return BoolCallback($val[$col]); }
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
	static function DateCallback($val) { return date('m/d/Y', MyDateTimestamp($val)); }

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
				if (!isset($ret)) $ret = array();
				$ret = array_merge($ret, $item);
			}
			else $ret .= $item;
		}
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
}

?>
