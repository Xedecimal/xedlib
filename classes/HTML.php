<?php

class HTML
{
	/**
	 * Returns a cleaned up string to work in an html id attribute without w3c
	 * errors.
	 *
	 * @param string $id
	 * @return string
	 */
	static function CleanID($id)
	{
		return str_replace('[', '_', str_replace(']', '', str_replace(' ', '_', $id)));
	}

	static function GetAttribs($attribs)
	{
		$ret = '';
		if (is_array($attribs))
		foreach ($attribs as $n => $v)
			$ret .= ' '.strtolower($n).'="'.htmlspecialchars($v).'"';
		else return ' '.$attribs;
		return $ret;
	}

	/**
	 * Converts html style tag attributes into an xml array style.
	 *
	 * @param string $atrs Attributes to process.
	 * @return array String indexed array of attributes.
	 */
	static function ParseAtrs($atrs)
	{
		if (empty($atrs)) return;
		$m = null;
		preg_match_all('/([^= ]+)="([^"]+)"/', $atrs, $m);
		for ($ix = 0; $ix < count($m[1]); $ix++) $ret[$m[1][$ix]] = $m[2][$ix];
		return $ret;
	}
}

?>
