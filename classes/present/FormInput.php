<?php

require_once(dirname(__FILE__).'/../Utility.php');
require_once(dirname(__FILE__).'/../Arr.php');
require_once(dirname(__FILE__).'/../HM.php');
require_once(dirname(__FILE__).'/FormOption.php');

class FormInput
{
	/**
	 * Text of this input object, displayed above the actual field.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Manual HTML attributes for this input object.
	 *
	 * @var string
	 */
	public $atrs;

	/**
	 * Help text is displayed below the form field. Usually in case of error or
	 * to provide better information on the input wanted.
	 *
	 * @var string
	 */
	public $help;

	/**
	 * Whether or not to attach a label to this field.
	 * @var bool
	 */
	public $labl;

	public $append;

	/**
	 * Creates a new input object with many properties pre-set.
	 *
	 * @param string $text
	 * @param string $type
	 * @param string $name
	 * @param string $valu
	 * @param string $atrs
	 * @param string $help
	 */
	function __construct($text, $type = 'text', $name = null,
		$valu = null, $atrs = null, $help = '')
	{
		$this->text = $text;
		$this->name = $name;
		$this->help = $help;

		// Consume these attributes

		if (is_array($atrs))
		{
			$this->valid = Arr::Yank($atrs, 'VALID');
			$this->invalid = Arr::Yank($atrs, 'INVALID');
		}

		// Propegate these attributes

		if (is_array($atrs))
			foreach ($atrs as $k => $v)
				$this->atrs[strtoupper($k)] = $v;
		else $this->atrs = HM::ParseAttribs($atrs);

		// Analyze these attributes

		$this->atrs['TYPE'] = $type;
		if ($name != null) $this->atrs['NAME'] = $name;
		if ($valu != null) $this->atrs['VALUE'] = $valu;

		// @TODO: I don't believe these should be in the constructor.

		switch ($type)
		{
			case 'state':
				$this->atrs['TYPE'] = 'select';
				$this->atrs['VALUE'] = FormOption::FromArray(U::StateNames(),
					$this->attr('VALUE'));
				break;
			case 'fullstate':
				return FormInput::GetState($this->atrs, @$this->valu, false);
			case 'shortstate':
				return FormInput::GetState($this->atrs, @$this->valu, true);
		}
	}

	function attr($attr = null, $val = null)
	{
		if (!isset($attr)) return $this->atrs;
		if (isset($val)) $this->atrs[$attr] = $val;
		if (isset($this->atrs[$attr])) return $this->atrs[$attr];
	}

	/**
	 * This is eventually going to need to be an array instead of a substr for
	 * masked fields that do not specify length, we wouldn't know the length
	 * for the substr. Works fine for limited lengths for now though.
	 */
	function mask_callback($m)
	{
		$ret = '<input type="text" maxlength="'.$m[1].'" size="'.$m[1].
			'" name="'.$this->name.'[]"';
		if (!empty($this->valu))
			$ret .= ' value="'.substr($this->valu, $this->mask_walk, $m[1]).'"';
		$this->mask_walk += $m[1];
		return $ret.' />';
	}

	/**
	 * Returns this input object rendered in html.
	 *
	 * @param string $parent name of the parent.
	 * @param bool $persist Whether or not to persist the value in this field.
	 * @return string
	 */
	function Get($parent = null, $persist = true)
	{
		if (!empty($this->atrs['ID']))
			$this->atrs['ID'] = $this->GetCleanID($parent);

		if ($this->atrs['TYPE'] == 'spamblock')
		{
			$this->atrs['TYPE'] = 'text';
			$this->atrs['CLASS'] = 'input_generic';
			$this->atrs['VALUE'] = $this->GetValue($persist);
			$this->atrs['ID'] = $this->GetCleanID($parent);
			$this->labl = false;
			$atrs = HM::GetAttribs($this->atrs);
			return '<label>To verify your request, please type the word <u>'.
				$this->valu.'</u>:<br/>'.
				"<input{$atrs} /></label>";
		}
		if ($this->atrs['TYPE'] == 'boolean')
		{
			return GetInputBoolean($parent, $this->atrs,
				!isset($this->valu) ? Server::GetVar(@$this->atrs['NAME'])
					: $this->valu);
		}
		if ($this->atrs['TYPE'] == 'radios')
		{
			$this->labl = false;

			$ret = null;
			if (!empty($this->valu))
			{
				$newsels = $this->GetValue($persist);
				foreach ($newsels as $id => $val)
				{
					$selected = $val->selected ? 'checked="checked"' : null;
					if ($val->group)
						$ret .= "<b><i>{$val->text}</i></b>\n";
					else
						$ret .= '<label><input
							type="radio"
							name="'.$this->atrs['NAME'].'"
							value="'.$id.'"
							id="'.CleanID($this->atrs['NAME'].'_'.$id)."\"{$selected}{$this->atrs}/>
							{$val->text}</label>";
					if (empty($this->Horizontal)) $ret .= '<br/>';
				}
			}
			return $ret;
		}
		if ($this->atrs['TYPE'] == 'area')
		{
			if (empty($this->atrs['ROWS'])) $this->atrs['ROWS'] = 10;
			if (empty($this->atrs['COLS'])) $this->atrs['COLS'] = 25;
			if (empty($this->atrs['CLASS'])) $this->atrs['CLASS'] = 'input_area';
			$natrs = $this->atrs;
			unset($natrs['TYPE']);
			$atrs = GetAttribs($natrs);
			return "<textarea$atrs>".$this->GetValue($persist).'</textarea>';
		}
		if ($this->atrs['TYPE'] == 'checkbox')
		{
			$val = $this->GetValue($persist);
			return "<input ".HM::GetAttribs($this->atrs)." />";
		}
		switch ($this->atrs['TYPE'])
		{
			case 'checks':
				$this->labl = false;

				$ret = null;
				$vp = new VarParser();
				if (!empty($this->atrs['VALUE']))
				{
					@$this->atrs['CLASS'] .= ' checks';
					$divAtrs = $this->atrs;
					unset($divAtrs['TYPE'], $divAtrs['VALUE'], $divAtrs['NAME']);
					$ret .= '<div'.GetAttribs($divAtrs).'>';
					$newsels = $this->GetValue($persist);
					foreach ($newsels as $id => $val)
						$ret .= $val->RenderCheck(array(
							'NAME' => $this->atrs['NAME'].'[]'));
					$ret .= '</div>';
				}
				return $ret;
			case 'custom':
				return call_user_func($this->atrs['VALUE'], $this);

			// Dates

			case 'date':
				$this->labl = false;
				$atrs = $this->atrs;
				$atrs['TYPE'] = 'text';
				HM::AttribAppend($atrs, 'class', 'date');
				return '<input'.HM::GetAttribs($atrs).' />';
			case 'daterange':
				$atrs = $this->atrs;
				$atrs['NAME'] .= '[]';
				$atrs['TYPE'] = 'text';
				$atrs['CLASS'] = 'date';
				$one = '<input '.HM::GetAttribs($atrs).' />';
				if (isset($atrs['ID'])) $atrs['ID'] .= '2';
				$two = '<input '.HM::GetAttribs($atrs).' />';
				return "$one to $two";
			case 'time':
				$this->labl = false;
				return FormInput::GetTime($this->atrs['NAME'], $this->valu);
			case 'datetime':
				$this->labl = false;
				return FormInput::GetDate(array(
					'name' => $this->atrs['NAME'],
					'ts' => $this->valu,
					'time' => true,
					'atrs' => $this->atrs
				));
			case 'month':
				return FormInput::GetMonthSelect($this->atrs['NAME'],
					@$this->atrs['VALUE']);

			case 'label':
				return $this->valu;
			case 'mask':
				$this->mask_walk = 0;
				return preg_replace_callback('/t([0-9]+)/',
					array($this, 'mask_callback'), @$this->mask);

			case 'select':
			case 'selects':
				if ($this->atrs['TYPE'] == 'selects')
					$this->atrs['MULTIPLE'] = 'multiple';

				$selAtrs = $this->atrs;
				unset($selAtrs['TYPE'],$selAtrs['VALUE']);
				$ret = "<select".HM::GetAttribs($selAtrs).'>';
				if (!empty($this->atrs['VALUE']))
				{
					$newsels = $this->GetValue($persist);
					$ogstarted = false;
					$useidx = empty($this->atrs['NOTYPE']);
					foreach ($newsels as $id => $opt)
					{
						if ($useidx) $opt->valu = $id;
						$ret .= $opt->Render();
					}
				}
				return $ret.'</select>';
		}

		//$val = $this->GetValue($persist && $this->atrs['TYPE'] != 'radio');
		$atrs = HM::GetAttribs($this->atrs);
		return "<input {$atrs} />";
	}

	/**
	 * @param bool $persist Whether or not to persist the data in this field.
	 * @return mixed Value of this field.
	 */
	function GetValue($persist = true)
	{
		switch ($this->atrs['TYPE'])
		{
			//Definate Failures...
			case 'password':
			case 'file':
			case 'spamblock':
				return null;
			//Single Selectables...
			case 'select':
			case 'radios':
				$newsels = Arr::Cln($this->atrs['VALUE']);
				if ($persist)
				{
					$sel = Server::GetVar($this->name);
					if ($sel && isset($newsels[$sel]))
						$newsels[$sel]->selected = true;
				}
				return $newsels;
			//Multi Selectables...
			case 'selects':
			case 'checks':
				$newsels = array_clone($this->atrs['VALUE']);
				if ($persist)
				{
					$svalus = Server::GetVar($this->name);
					if (!empty($svalus))
					foreach ($svalus as $val) $newsels[$val]->selected = true;
				}
				return $newsels;
			//Simple Checked...
			case 'checkbox':
				return @$this->atrs['VALUE'] ?
					' checked="checked"'
					: null;
			//May get a little more complicated if we don't know what it is...
			default:
				return htmlspecialchars($persist ?
					Server::GetVars($this->atrs['NAME'], @$this->atrs['VALUE']) :
					@$this->atrs['VALUE']);
		}
	}

	function GetCleanID($parent)
	{
		$id = !empty($parent) ? $parent.'_' : null;
		$id .= !empty($this->atrs['ID']) ? $this->atrs['ID'] : @$this->atrs['NAME'];
		return HM::CleanID($id);
	}

	static function GetPostValue($name)
	{
		$v = $_POST[$name];
		if (isset($_POST['type_'.$name]))
		{
			switch ($_POST['type_'.$name])
			{
				case 'date':
					return sprintf('%04d-%02d-%02d', $v[2], $v[0], $v[1]);
			}
		}

		return $v;
	}

	function GetData($val = null)
	{
		$val = Server::GetVar($this->name, $val);
		switch ($this->type)
		{
			case 'mask':
				$ret = implode(null, $val);
				return !empty($ret) ? $ret : 0;
				break;
			case 'date':
				return sprintf('%04d-%02d-%02d', $val[2], $val[0], $val[1]);
			case 'checks':
			case 'selects':
				varinfo($val);
			default:
				return $val;
			break;
		}
	}

	/**
	 * Returns a rendered <select> form input.
	 * @param array $atrs eg: 'SIZE' => '5', 'MULTIPLE' => 'multiple'
	 * @param array $value array of SelOption objects.
	 * @param mixed $selvalue default selected seloption id.
	 * @return string rendered select form input.
	 */
	static function GetSelect($atrs = null, $value = null, $selvalue = null)
	{
		if (isset($atrs['VALUE'])) $selvalue = $atrs['VALUE'];
		if (is_array($atrs)) unset($atrs['VALUE']);

		$ret = '<select'.HM::GetAttribs($atrs).">\n";
		foreach ($value as $id => $option)
			$ret .= $option->Render($id == $selvalue);
		$ret .= "</select>\n";
		return $ret;
	}

	/**
	 * Returns a rendered <select> for a series of months.
	 * @param string $name Name of the input.
	 * @param int $default Default month.
	 * @param string $attribs Additional attributes for the <select> field.
	 * @return string Rendered month selection.
	 */
	static function GetMonthSelect($name, $default, $attribs = null)
	{
		$ret = "<select name=\"$name\"";
		$ret .= HM::GetAttribs($attribs);
		$ret .= ">";
		for ($ix = 1; $ix <= 12; $ix++)
		{
			$ts = mktime(0, 0, 0, $ix, 1);
			if ($ix == $default) $sel = " selected=\"selected\"";
			else $sel = "";
			$ret .= "<option value=\"$ix\"$sel> " . date('F', $ts) . "</option>\n";
		}
		$ret .= "</select>\n";
		return $ret;
	}

	/**
	 * Returns a rendered selection for picking years.
	 * @param string $name Name of this inputs.
	 * @param int $year Default selection.
	 * @return string Rendered year selection.
	 */
	function GetYear($name, $attribs)
	{
		// Handle Attributes

		$year = strtoval(@$attribs['VALUE'], date('Y'));
		$shownav = strtoval(@$attribs['SHOWNAV'], true);
		$step = strtoval(@$attribs['STEP'], 5);
		$shownext = strtoval(@$attribs['SHOWNEXT'], true);
		$showprev = strtoval(@$attribs['SHOWPREV'], true);

		$from = $showprev ? $year-$step : $year;
		$next = $shownext ? $year+$step : $year;

		$ret = "<select name=\"$name\">";
		if ($shownav)
			$ret .= "<option value=\"".($from-1)."\"> &laquo; </option>\n";

		for ($ix = $from; $ix < $next; $ix++)
		{
			if ($ix == $year) $sel = " selected=\"selected\"";
			else $sel = "";
			$ret .= "<option value=\"$ix\"$sel>$ix</option>\n";
		}
		if ($shownav)
			$ret .= "<option value=\"".($next+1)."\"> &raquo; </option>\n";
		$ret .= "</select>\n";
		return $ret;
	}

	/**
 * Returns a DateTime picker
 * @param string $name Name of this field.
 * @param int $timestamp Date to initially display.
 * @param bool $include_time Whether or not to add time to the date.
 * @return string Rendered date input.
 */
	static function GetDate($args)
	{
		if (is_array($args['ts']))
		{
			if (isset($args['ts'][5]))
				$args['ts'] = mktime($args['ts'][3], $args['ts'][4], $args['ts'][5],
					$args['ts'][0], $args['ts'][1], $args['ts'][2]);
			else
				$args['ts'] = mktime(0, 0, 0, $args['ts'][0], $args['ts'][1],
					$args['ts'][2]);
		}
		else if (!is_numeric($args['ts']) && !empty($args['ts']))
		{
			$args['ts'] = Database::MyDateTimestamp($args['ts'], $args['time']);
		}
		if (!isset($args['ts'])) $args['ts'] = time();
		$divAtrs = $args['atrs'];
		unset($divAtrs['NAME'],$divAtrs['TYPE']);
		# $strout = '<div'.GetAttribs(@$divAtrs).'>';
		$strout = FormInput::GetMonthSelect(@$args['atrs']['NAME'].'[]',
			date('n', $args['ts']));
		$strout .= '/ <input type="text" size="2" name="'.@$args['atrs']['NAME'].'[]" value="'.
			date('d', $args['ts']).'" alt="Day" />'."\n";
		$strout .= '/ <input type="text" size="4" name="'.@$args['atrs']['NAME'].'[]" value="'.
			date('Y', $args['ts']).'" alt="Year" />'."\n";
		$strout .= @$args['time'] ? GetInputTime($args['atrs']['NAME'].'[]', $args['ts']) : null;
		return $strout /*.'</div>'*/;
	}

	/**
	 * Returns a series of 3 text boxes for a given timestamp.
	 * @param string $name Name of these inputs are converted into name[] array.
	 * @param int $timestamp Epoch timestamp for default value.
	 * @return string Rendered form inputs.
	 */
	static function GetTime($name, $timestamp)
	{
		$strout = "<input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"".
			date('g', $timestamp)."\" alt=\"Hour\" />\n";
		$strout .= ": <input type=\"text\" size=\"2\" name=\"{$name}[]\" value=\"".
			date('i', $timestamp)."\" alt=\"Minute\" />\n";
		$strout .= "<select name=\"{$name}[]\">
			<option value=\"0\">AM</option>
			<option value=\"1\">PM</option>
			</select>";
		return $strout;
	}

	/**
	 * Returns two radio buttons for selecting yes or no (1 or 0).
	 * @param string $parent Name of the parent form if one is available.
	 * @param array $atrs Array of HTML attributes.
	 * @return string Rendered time input.
	 */
	static function GetBoolean($parent, $attribs)
	{
		$id = CleanID((isset($parent) ? $parent.'_' : null).@$attribs['NAME']);
		if (!isset($attribs['ID'])) $attribs['ID'] = $id;
		if (!isset($attribs['VALUE'])) $attribs['VALUE'] = 0;
		if (!isset($attribs['TEXTNO'])) $attribs['TEXTNO'] = 'No';
		if (!isset($attribs['TEXTYES'])) $attribs['TEXTYES'] = 'Yes';
		return '<label><input type="radio" id="'.$attribs['ID'].'"
		name="'.@$attribs['NAME'].'" value="0"'.
		($attribs['VALUE'] ? null : ' checked="checked"').' /> '.$attribs['TEXTNO'].'</label> ' .
		'<label><input type="radio" name="'.@$attribs['NAME'].'" value="1"'.
		($attribs['VALUE'] ? ' checked="checked"' : null).' /> '.$attribs['TEXTYES'].'</label>';
	}

	/**
	 * @param array $atrs Default state number.
	 * @return string Rendered <select> box.
	 */
	static function GetState($atrs = array(), $keys = true, $short = false)
	{
		$vals = $short ? array_keys(U::StateNames()) : U::StateNames();
		return FormInput::GetSelect($atrs,
			FormOption::FromArray($vals, null, $keys));
	}

	/**
	 * Converts any type of FormInput into a usable string, for example in text
	 * only emails and suchs.
	 *
	 * @param FormInput $field
	 * @return string Converted field.
	 */
	function InputToString($field)
	{
		$val = Server::GetVar($field->name);

		if ($field->type == 'time')
			return "{$val[0]}:{$val[1]}".($val[2] == 0 ? ' AM' : ' PM');
		else if ($field->type == 'checks')
		{
			$out = null;
			if (!empty($val))
			foreach (array_keys($val) as $ix)
				$out .= ($ix > 0?', ':'').$field->valu[$ix]->text;
			return $out;
		}
		else if ($field->type == 'radios') return $field->valu[$val]->text;
		else if ($field->type == 'boolean') return $val == 1 ? 'yes' : 'no';
		else if ($field->type == 'select') return $field->valu[$val]->text;
		else Error("Unknown field type.");
	}
}

?>
