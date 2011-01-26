<?php

/**
 * Enter description here...
 */
class VarParser
{
	/**
	 * Vars specified here override all else.
	 *
	 * @var array
	 */
	public $vars;

	public $Behavior;

	function __construct()
	{
		$this->Behavior = new VarParserBehavior;
	}

	/**
	 * Processes variables in the given string $data for variables named as keys
	 * in the array $vars.
	 *
	 * @param string $data Data to search for variables.
	 * @param array $vars Override existing names with these.
	 * @return mixed Reformatted text with variables replaced.
	 */
	function ParseVars($data, $vars = null)
	{
		if (empty($data)) return '';
		$this->vars = $vars;
		return preg_replace_callback('/\{\{([^\}]+)\}\}/',
			array(&$this, 'var_parser'), $data);
	}

	/**
	 * Callback for each regex match, not for external use.
	 *
	 * @param array $match Matches found by preg_replace_callback calling this.
	 * @return string
	 */
	function var_parser($match)
	{
		$tvar = $match[1];

		// This is an advanced variable.
		if (strpos($tvar, '.') && !$this->Behavior->SimpleVars)
		{
			$indices = explode('.', $tvar);
			$var = $this->FindVar($indices[0]);

			for ($ix = 1; $ix < count($indices); $ix++)
			{
				if (is_object($var)) $var = $var->$indices[$ix];
				else if (isset($var[$indices[$ix]])) $var = $var[$indices[$ix]];
			}

			if (!is_array($var)) return $var;
		}
		$ret = $this->FindVar($tvar);
		if (isset($ret)) return $ret;
		return $this->Behavior->Bleed ? $match[0] : $ret;
	}

	function FindVar($tvar)
	{
		global $$tvar;
		if (!empty($this->vars) && key_exists($tvar, $this->vars))
			return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if (isset($this->data[$tvar])) return $this->data[$tvar];
		else if ($this->Behavior->UseGetVar) return GetVar($tvar);
		return null;
	}

	static function Concat($t, $items)
	{
		$vp = new VarParser();
		$ret = '';
		foreach ($items as $i) $ret .= $vp->ParseVars($t, $i);
		return $ret;
	}
}

class VarParserBehavior
{
	/**
	 * Whether variables in the template {{example}} will bleed through if not
	 * set.
	 *
	 * @var bool
	 */
	public $Bleed = true;

	public $UseGetVar = false;

	public $SimpleVars = false;
}

?>
