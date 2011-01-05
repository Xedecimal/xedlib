<?php

/**
 * Enter description here...
 *
 */
class Relation
{
	/**
	 * Associated dataset.
	 * @var DataSet
	 */
	public $ds;

	/**
	 * Name of the column that is the primary key for the parent of this
	 * relation.
	 * @var string
	 */
	public $parent_key;

	/**
	 * Name of the column that is the primary key for the child of this
	 * relation.
	 * @var string
	 */
	public $child_key;

	/**
	 * Prepares a relation for database association.
	 *
	 * @param DataSet $ds DataSet for this child.
	 * @param string $parent_key Column name of the parent key of $ds.
	 * @param string $child_key Column that references the parent.
	 * @example doc\examples\dataset.php
	 */
	function __construct($ds, $parent_key, $child_key)
	{
		$this->ds = $ds;
		$this->parent_key = $parent_key;
		$this->child_key = $child_key;
	}
}

?>
