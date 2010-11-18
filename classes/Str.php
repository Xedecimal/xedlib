<?php

/**
 * String manipulation.
 */
class Str
{
	/**
	 * Pluralizes a string, eg User -> Users
	 *
	 * @param string $str String to pluralize.
	 * @return string Properly pluralized string.
	 */
	static function Plural($str)
	{
		if (strlen($str) < 1) return null;
		if (substr($str, -1) == 'y') return substr($str, 0, -1).'ies';
		if (substr($str, -1) != 's') return "{$str}s";
		return $str;
	}

	/**
	 * Converts a string from digits to proper data size string.
	 *
	 * @param int $size Size to convert into proper string.
	 * @return string Size converted to a string.
	 */
	function SizeString($size)
	{
		$units = explode(' ','B KB MB GB TB');
		for ($i = 0; $size > 1024; $i++) { $size /= 1024; }
		return round($size, 2).' '.$units[$i];
	}

	/**
	 * Converts a data size string to proper digits.
	 *
	 * @param string $str String to convert into proper size.
	 * @return int String converted to proper size.
	 */
	function GetStringSize($str)
	{
		$num = (int)substr($str, 0, -1);
		switch (strtoupper(substr($str, -1)))
		{
			case 'Y': $num *= 1024;
			case 'Z': $num *= 1024;
			case 'E': $num *= 1024;
			case 'P': $num *= 1024;
			case 'T': $num *= 1024;
			case 'G': $num *= 1024;
			case 'M': $num *= 1024;
			case 'K': $num *= 1024;
		}
		return $num;
	}

	/**
	 * Returns the start of a larger string trimmed down to the length you specify
	 * without chomping words.
	 * @param string $text Text to chomp.
	 * @param int $length Maximum length you're going for.
	 * @return string Chomped text.
	 */
	function Chomp($text, $length)
	{
		if (strlen($text) > $length)
		{
			$ret = substr($text, 0, $length);
			while ($ret[strlen($ret)-1] != ' ' && strlen($ret) > 1)
				$ret = substr($ret, 0, count($ret)-2);
			return $ret . "...";
		}
		return $text;
	}

	function random_string($chars = 15)
	{
		$ret = null;
		for ($ix = 0; $ix < $chars; $ix++)
			$ret .= sprintf('%c',
				rand(0, 1)
				# 0 - 9
				? rand(48, 57)
				: rand(0, 1)
				# a - z
				? rand(97, 122)
				# A - Z
				: rand(65, 90));
		return $ret;
	}
}

?>
