<?php

/**
 * A join specified singly or as an array to objects or methods like
 * DataSet.Get.
 *
 * @see DataSet.Get
 */
class Join
{
	/**
	 * The associated dataset in this join.
	 *
	 * @see DataSet.Get
	 * @var DataSet
	 */
	public $DataSet;

	/**
	 * The condition of this join, For example: 'child.parent = parent.id'.
	 *
	 * @see DataSet.Get
	 * @var string
	 */
	public $Condition;

	/**
	 * The type of this join, off hand I can think of three, 'LEFT JOIN', 'INNER JOIN'
	 * and 'JOIN'.
	 *
	 * @see DataSet.Get
	 * @var string
	 */
	public $Type;

	/**
	 * Unique identifier for this join to associate all the columns.
	 * @var string
	 */
	public $Shortcut;

	/**
	 * Creates a new Join object that will allow DataSet to identify the type
	 * and context of where, when and how to use a join when it is needed. This
	 * is used when you call DataSet.Get().
	 *
	 * @param DataSet $dataset Target DataSet to join.
	 * @param string $condition Context of the join.
	 * @param string $type Type of join, 'JOIN', 'LEFT JOIN' or 'INNER JOIN'.
	 * @param string $shortcut Specify an easier name for this joining table.
	 * @see DataSet.Get
	 */
	function __construct($dataset, $condition, $type = 'JOIN', $shortcut = null)
	{
		$this->DataSet = $dataset;
		$this->Condition = $condition;
		$this->Type = $type;
		$this->Shortcut = $shortcut;
	}
}

?>
