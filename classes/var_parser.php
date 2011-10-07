<?php

/**
 * Variable Parser, this will locate variables and include them in strings using
 * the format {{name}} or {{name.index.etc}} to traverse arrays. They are
 * usually supplied using $_d, the varparser constructor, or the Template
 * constructor.
 */
class VarParser
{
	/**
	 * Vars specified here override all else.
	 *
	 * @var array
	 */
	public $vars;

	/**
	 * Control the way this VarParser behaves.
	 *
	 *  @var VarParserBehavior
	 */
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

	static function Parse($string, $vars)
	{
		$vp = new VarParser();
		return $vp->ParseVars($string, $vars);
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
		# Periods break into recursion.
		if (preg_match('/\./', $tvar))
		{
			$tree = explode('.', $tvar);
			$cv = $this->FindVar($tree[0]);
			for ($ix = 1; $ix < count($tree); $ix++) $cv = $cv[$tree[$ix]];
			return $cv;
		}
		global $$tvar;
		if (is_object($this->vars)) return @$this->vars->$tvar;
		if (!empty($this->vars) && key_exists($tvar, $this->vars))
			return $this->vars[$tvar];
		else if (isset($$tvar)) return $$tvar;
		else if (defined($tvar)) return constant($tvar);
		else if (isset($this->data[$tvar])) return $this->data[$tvar];
		else if ($this->Behavior->UseGetVar) return Server::GetVar($tvar);
		return null;
	}

	static function Concat($t, $items, $bleed = true, $ext_data = array())
	{
		$vp = new VarParser();
		$vp->Behavior->Bleed = $bleed;
		$ret = '';
		foreach ($items as $i['vp-id'] => $i) $ret .= $vp->ParseVars($t, array_merge($i, $ext_data));
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
