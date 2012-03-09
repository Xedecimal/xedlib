<?php

require_once(dirname(__FILE__).'/utility.php');

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
		else return "{$str}s";
	}

	/**
	 * Returns the start of a larger string trimmed down to the length you specify
	 * without chomping words.
	 * @param string $text Text to chomp.
	 * @param int $length Maximum length you're going for.
	 * @return string Chomped text.
	 */
	static function Chomp($text, $length = 255)
	{
		if (strlen($text) > $length)
		{
			$ret = substr($text, 0, $length);
			while ($ret[strlen($ret)-1] != ' ' && strlen($ret) > 1)
				$ret = substr($ret, 0, count($ret)-2);
			return $ret.'...';
		}
		return $text;
	}

	static function Random($chars = 15)
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

	static function MakeUTF8($string)
	{
		$encodes = array('CP850', 'CP1252', 'ASCII', 'UTF-8', 'ISO-8859-1');
		$enc = mb_detect_encoding($string, $encodes);
		if ($enc == 'UTF-8') return $string;
		if ($enc == 'ISO-8859-1') $enc = 'CP1252';
		return mb_convert_encoding($string, 'UTF-8', $enc);
	}
}

function strmatch($str1, $str2)
{
	return $str1 === $str2;
}

?>
