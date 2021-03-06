<?php

/**
 * (eXtensible|Hyper Text) (Markup|Transfer) (Protocol|Language), or just
 * HyperMarkup in this case.
 */

class HM
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
		if (!isset($id)) return;
		$reps = array('[' => '_', ']' => '', '/' => '_', ' ' => '_');
		return str_replace(array_keys($reps), array_values($reps), $id);
	}

	static function GetAttribs($attribs)
	{
		$ret = '';
		if (is_array($attribs))
		foreach ($attribs as $n => $v)
		{
			if (!is_string($v) && !is_numeric($v)) continue;
			$ret .= ' '.strtolower($n).'="'.htmlspecialchars($v).'"';
		}
		else return ' '.$attribs;
		return $ret;
	}

	/**
	 * Converts html style tag attributes into an xml array style.
	 *
	 * @param string $atrs Attributes to process.
	 * @return array String indexed array of attributes.
	 */
	static function ParseAttribs($atrs)
	{
		if (empty($atrs)) return;
		$m = null;
		preg_match_all('/([^= ]+)="([^"]+)"/', $atrs, $m);
		for ($ix = 0; $ix < count($m[1]); $ix++) $ret[strtoupper($m[1][$ix])] = $m[2][$ix];
		return $ret;
	}

	static function AttribAppend(&$atrs, $name, $value)
	{
		$key = strtoupper($name);
		if (isset($atrs[$key])) $atrs[$key] .= ' '.$value;
		else $atrs[$key] = $value;
	}

	/**
	 * Returns a clean URI.
	 *
	 * @param string $url URL to clean.
	 * @param array $uri URI appended on URL and cleaned.
	 * @return string Cleaned URI+URL
	 */
	static function URL($url, $uri = null)
	{
		$ret = $url;

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
					$ret .= HM::BuildURL($key, $val, $start);
					$start = false;
				}
			}
		}
		return $ret;
	}

	static function ParseURL($url)
	{
		$up = parse_url($url);
		if (!empty($up['path'])) $ret['url'] = $up['path'];
		else $ret['url'] = '/';
		if (!empty($up['query']))
		foreach (preg_split('/&|\?/', $up['query']) as $parm)
		{
			list($var, $val) = explode('=', $parm);
			$ret['args'][$var] = $val;
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
	static function BuildURL($key, $val, $start = false)
	{
		$ret = null;
		if (is_array($val))
			foreach ($val as $akey => $aval)
			{
				$ret .= HM::BuildURL($key.'['.$akey.']', $aval, $start);
				$start = false;
			}
		else
		{
			//$nval = str_replace(' ', '%20', $val);
			$ret .= ($start ? '?' : '&amp;').$key.'='.urlencode($val);
		}
		return $ret;
	}

	static function urlencode_path($str)
	{
		return str_replace('%2F', '/', rawurlencode($str));
	}
}

?>
