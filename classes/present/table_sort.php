<?php

/**
 * A table with columns that can sort, a bit
 * more processing with a better user experience.
 */
class TableSort extends Table
{
	/**
	* Instantiate this table with columns that can use
	* sort values.
	* @param string $name Unique name only used in Html comments for identification.
	* @param array $cols Default columns headers to display ( eg. array("Column1", "Column2") ).
	* @param array $attributes An array of attributes for each column ( eg. array('width="100%"', 'valign="top"') ).
	*/
	function SortTable($name, $cols, $attributes = NULL)
	{
		if (!is_array($cols)) Error("If you are not going to specify any
			columns, you might as well just use Table.");

		$this->name = $name;

		$sort = Server::GetVar("sort");
		$order = Server::GetVar("order", "ASC");

		global $me, $PERSISTS;
		$this->cols = array();

		$imgUp = '<img src="'.Server::GetRelativePath(dirname(__FILE__)).
		'/images/up.png" style="vertical-align: text-bottom;"
		alt="Ascending" title="Ascending" />';

		$imgDown = '<img src="'.Server::GetRelativePath(dirname(__FILE__)).
		'/images/down.png" style="vertical-align: text-bottom;"
		alt="Descending" title="Descending" align="middle"/>';

		foreach ($cols as $id => $disp)
		{
			$append = "";
			if ($sort == $id)
			{
				$append = $order == 'ASC' ? $imgUp : $imgDown;
				($order == "ASC") ? $order = "DESC" : $order = "ASC";
			}

			$uri_defaults = !empty($PERSISTS) ? $PERSISTS : array();
			$uri_defaults = array_merge($uri_defaults, array(
				'sort' => $id,
				'order' => $order
			));

			$this->cols[] = "<a href=\"".
				HM::URL($me, $uri_defaults).
				"\">$disp</a>$append";
		}

		$this->atrs = $attributes;
	}
}

?>
