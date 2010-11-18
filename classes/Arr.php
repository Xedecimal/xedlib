<?php

class Arr
{
	static function LowerKeys($array)
	{
		if (!is_array($array)) return null;
		$ret = array();
		foreach ($array as $k => $v) $ret[strtolower($k)] = $v;
		return $ret;
	}

	static function Yank(&$arr, $key)
	{
		$ret = @$arr[$key];
		unset($arr[$key]);
		return $ret;
	}

	/**
	 * Converts an array to a BitMask.
	 *
	 * @param array $array Array to bitmask.
	 * @return int Bitwise combined values.
	 */
	static function GetMask($array)
	{
		$ret = 0;
		foreach ($array as $ix) $ret |= $ix;
		return $ret;
	}
}

?>
