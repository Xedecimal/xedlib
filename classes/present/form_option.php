<?php

require_once(dirname(__FILE__).'/../tree_node.php');

/**
 * A select box, multiple select boxes, checkboxes, etc.
 */
class FormOption extends TreeNode
{
	/**
	 * The text of this option.
	 *
	 * @var string
	 */
	public $text;
	public $valu;
	/**
	 * Whether this is a group header.
	 *
	 * @var bool
	 */
	public $group;
	/**
	 * Whether this option is selected by default.
	 *
	 * @var bool
	 */
	public $selected;

	public $disabled;

	/**
	 * Create a new select option.
	 *
	 * @param string $text The text of this option.
	 * @param bool $group
	 * @param bool $selected
	 */
	function __construct($text, $selected = false)
	{
		$this->text = $text;
		$this->selected = $selected;
		$this->disabled = false;
	}

	function __tostring() { return $this->text; }

	function RenderCheck($atrs)
	{
		if ($this->selected) $atrs['CHECKED'] = 'checked';
		if (!empty($this->children))
		{
			$ret = '<p><b><i>'.$this->text.'</i></b><br />';
			foreach ($this->children as $c) $ret .= $c->RenderCheck($atrs);
			$ret .= '</p>';
			return $ret;
		}
		else
		{
			$valu = isset($this->valu) ? ' value="'.$this->valu.'"' : null;
			return '<label><input type="checkbox" value="'.$this->valu.'"'
				.HM::GetAttribs($atrs).' />'.htmlspecialchars($this->text)
					.'</label>';
		}
	}

	function Render($selected = false)
	{
		if ($this->selected || $selected)
			$selected = ' selected="selected"';
		else $selected = '';
		if (!empty($this->children))
		{
			$ret = '<optgroup label="'.$this->text.'">';
			foreach ($this->children as $c) $ret .= $c->Render();
			$ret .= '</optgroup>';
			return $ret;
		}
		else
		{
			$valu = isset($this->valu) ? ' value="'.$this->valu.'"' : null;
			return "<option{$valu}{$selected}>".htmlspecialchars($this->text).'</option>';
		}
	}

	/**
	 * Converts array('id' => 'text') items into SelOption objects.
	 * @param array $array Array of items to convert.
	 * @param mixed $default Default selected item id.
	 * @param bool $use_keys Whether to use array keys or indices.
	 * @return array Array of SelOption objects.
	 */
	static function FromArray($array, $default = null, $use_keys = true)
	{
		if (!is_array($array)) Server::Error("Attempted to create FormOptions
			from invalid array: {$array}");
		$opts = array();
		foreach ($array as $ix => $item)
		{
			if (is_array($item))
			{
				$o = new FormOption($ix, $default == $item);
				$o->children = FormOption::FromArray($item, $default, $use_keys);
				$o->group = true;
			}
			else $o = new FormOption($item, $default == $item);

			if ($use_keys) $o->valu = $o->id = $ix;
			$opts[$use_keys ? $ix : $item] = $o;
		}
		return $opts;
	}

	/**
	 * Converts data retrieved from a DataSet into manageable SelOption objects.
	 * @param array $result Rows retrieved from Get()
	 * @param string $col_disp Column used for display.
	 * @param string $col_id Column used for identification.
	 * @param mixed $default Default selection.
	 * @param string $none Text for unselected item (id of 0)
	 * @return array SelOption array.
	 */
	static function FromData($result, $col_disp, $col_id, $default = 0, $none = null)
	{
		$ret = null;
		if (isset($none))
		{
			$sel = new FormOption($none, false, $default == 0);
			$sel->valu = 0;
			$ret[0] = $sel;
		}
		foreach ($result as $res)
		{
			$sel = new FormOption($res[$col_disp],
				strcmp($default, $res[$col_id]) == 0);
			$sel->valu = $res[$col_id];
			$ret[$res[$col_id]] = $sel;
		}
		return $ret;
	}

	static function GetChecks($atrs = null, $value = null, $selvalue = null)
	{
		if (isset($atrs['VALUE'])) $selvalue = $atrs['VALUE'];
		if (is_array($atrs)) unset($atrs['VALUE']);

		$strout = null;
		if (!empty($value))
		foreach ($value as $id => $option)
		{
			$selected = $disabled = null;
			if ($id == $selvalue) $selected = ' selected="selected"';
			if ($option->selected) $selected = ' selected="selected"';
			if ($option->disabled) $disabled = ' disabled="disabled"';
			if ($option->group)
				$strout .= "<strong><em>{$option->text}</em></strong><br />\n";
			else
				$strout .= '<label><input type="checkbox" value="'
					.$id.'"'.HM::GetAttribs($atrs).$selected.$disabled.' />'.$option->text
					.'</label>'."<br/>\n";
			$selected = null;
		}

		return $strout;
	}

	function ArrayToSelText($array, $sel)
	{
		$ret = array();
		foreach ($array as $ix => $v)
			$ret[] = $sel[$v]->text;
		return implode(', ', $ret);
	}
}

?>
