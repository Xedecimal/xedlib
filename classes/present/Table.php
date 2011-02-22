<?php

require_once(dirname(__FILE__).'/../HM.php');

/**
 * A generic table class to manage a top level table, with children rows and cells.
 */
class Table
{
	/**
	 * Name of this table (only used as identifer in html comments).
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Column headers for this table (displayed at the top of the rows).
	 *
	 * @var array
	 */
	public $cols;

	/**
	 * Each row array that makes up the bulk of this table.
	 *
	 * @var array
	 */
	public $rows;

	/**
	 * Array of attributes on a per-column basis.
	 *
	 * @var array
	 */
	public $atrs;

	/**
	 * Array of attributes on a per-row basis.
	 *
	 * @var array
	 */
	public $rowattribs;

	/**
	 * Instantiates this table with the specified attributes.
	 * @param string $name Unique name only used in Html comments for identification.
	 * @param array $cols Default columns headers to display ( eg.
	 * array("Column1", "Column2") ).
	 * @param array $col_attribs An array of attributes for each column ( eg.
	 * array('width="100%"', 'valign="top"') ).
	 */
	function __construct($name, $cols, $col_attribs = NULL)
	{
		$this->name = $name;
		$this->cols = $cols;
		$this->atrs = $col_attribs;
	}

	/**
	 * Adds a single row to this table, the widest row is how spanned
	 * out the complete table will be when calling GetTable().
	 * @param array $row A string array of columns.
	 * @param array $attribs Attributes to be applied to each column.
	 */
	function AddRow($row, $attribs = null)
	{
		$this->rows[] = $row;
		$this->rowattribs[] = $attribs;
	}

	/**
	 * Returns the complete html rendered table for output purposes.
	 * @param string $attributes A set of html attributes to apply to the entire table. (eg. 'class="mytableclass"')
	 * @return string The complete html rendered table.
	 */
	function Get($attributes = null)
	{
		$ret = "<!-- Start Table: {$this->name} -->\n";
		$ret .= '<table'.HM::GetAttribs($attributes).">\n";

		$atrs = null;

		if (!empty($this->cols))
		{
			$ret .= "<thead><tr>\n";
			$ix = 0;
			var_dump($this->atrs);
			foreach ($this->cols as $col)
			{
				if (isset($this->atrs)) $atrs = " ".
					$this->atrs[$ix++ % count($this->atrs)];
				else $atrs = "";
				$ret .= "<th $atrs>{$col}</th>\n";
			}
			$ret .= "</tr></thead>\n";
		}

		if (!empty($this->rows))
		{
			$ret .= "<tbody>\n";
			if (!isset($this->cols))
			{
				$span = 0;
				foreach ($this->rows as $row) if (count($row) > $span) $span = count($row);
				for ($ix = 0; $ix < $span; $ix++) $this->cols[] = null;
			}
			foreach ($this->rows as $ix => $row)
			{
				$ret .= '<tr';
				if (!empty($this->rowattribs))
					$ret .= HM::GetAttribs($this->rowattribs[$ix]);
				$ret .= ">\n";
				if (count($row) < count($this->cols))
					$span = " colspan=\"".
						(count($this->cols) - count($row) + 1);
				else $span = '';
				$x = 0;
				$atrs = null;

				if (is_array($row))
				{
					foreach ($row as $val)
					{
						if (is_array($val))
						{
							$atrs = HM::GetAttribs($val[1]);
							$val = $val[0];
						}
						else if (isset($this->atrs))
							$atrs = ' '.$this->atrs[$x % count($this->atrs)];
						else $atrs = null;
						$ret .= "<td$span$atrs>{$val}</td>\n";
						$x++;
					}
				}
				else $ret .= "<td{$span}{$atrs}>{$row}</td>\n";
				$ret .= "</tr>\n";
			}
			$ret .= "</tbody>\n";
		}
		$ret .= "</table>\n";
		$ret .= "<!-- End Table: {$this->name} -->\n";
		return $ret;
	}
}

?>
