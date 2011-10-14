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

	/**
	 * Array Recursive Key Sort, sorting an array of any dimension by their keys.
	 *
	 * @param array $array Array to be sorted
	 */
	static function ARKSort(&$array)
	{
		ksort($array);
		foreach ($array as $k => $v)
			if (is_array($v)) Arr::ARKSort($array[$k]);
	}

	/**
	 * PHP4 compatible array clone.
	 *
	 * @param mixed $arr Item to properly clone in php5 without references.
	 * @return mixed Cloned copy of whatever you throw at it.
	 */
	static function Cln($arr)
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

	static function MergeRecursive($arr1, $arr2)
	{
		foreach ($arr2 as $k => $v)
		{
			if(array_key_exists($k, $arr1) && is_array($v)) $arr1[$k] =
				$arr1[$k] = Arr::MergeRecursive($arr1[$k], $arr2[$k]);
			else $arr1[$k] = $arr2[$k];
		}

		return $arr1;
	}

	static function FromXML($xml)
	{
		return json_decode(json_encode((array)simplexml_load_string($xml)), 1);
	}
}

?>
