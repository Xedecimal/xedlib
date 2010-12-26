<?php

class Math
{
	/**
	 * Constrains a large series of values into the values specified.
	 * if $arr holds small values and you use large values for min and max,
	 * they will increase respectively and vice versa.
	 * @param array $arr Eg. array('title' => #, 'Title 2' => #)
	 * @param int $min_size Floor of total sizes.
	 * @param int $max_size Ceiling of total sizes.
	 * @return array Modified array of new sizes.
	 */
	function RespectiveSize($arr, $min_size = 12, $max_size = 32)
	{
		$max_qty = max(array_values($arr));
		$min_qty = min(array_values($arr));

		$spread = max(1, $max_qty - $min_qty);

		$step = ($max_size - $min_size) / ($spread);

		foreach ($arr as $key => $value)
		{
			$size = round($min_size + (($value - $min_qty) * $step));
			$ret[$key] = $size;
		}
		return $ret;
	}

	/**
	 * Return the mathematical expression of the string $val.
	 * @param string $val Expression, eg. 2+5.
	 * @param mixed $def Default value to return on failure.
	 * @return int Result of evaluation.
	 */
	function StrToVal($val, $def)
	{
		if (is_numeric($val)) $ret = (int)$val;
		else $ret = @eval('return '.$val.';');
		if (!isset($ret)) return $def;
		return $ret;
	}
}

?>
