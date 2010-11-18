<?php

/**
 * A node holds children.
 */
class TreeNode
{
	/**
	 * ID of this node (usually for database association)
	 *
	 * @var mixed
	 */
	public $id;
	/**
	 * Data associated with this node.
	 *
	 * @var mixed
	 */
	public $data;
	/**
	 * Child nodes of this node.
	 *
	 * @var array
	 */
	public $children;

	public $parent;

	/**
	 * Create a new TreeNode object.
	 *
	 * @param mixed $data Data to associate with this node.
	 */
	function TreeNode($data = null, $id = null)
	{
		$this->data = $data;
		$this->id = $id;
		$this->_index[$id] = &$this;
		$this->children = array();
	}

	function AddChild(&$tn)
	{
		$this->children[$tn->id] = $tn;
		$tn->parent = $this;
		# Indexes can get gigantic, find another method.
		// $this->Index();
	}

	function Index()
	{
		$this->GetIndex();
		if (isset($this->parent) && $this->id != $this->parent->id)
			$this->parent->Index();
	}

	function GetIndex()
	{
		foreach ($this->children as &$c)
			foreach ($c->_index as $id => &$tn)
				$this->_index[$id] = $tn;
	}

	function Find($id)
	{
		if ($this->id == $id) return $this;

		if (is_array($this->children))
		foreach ($this->children as $c)
		{
			if ($c->id == $id) return $c;
			else
			{
				$ret = $c->Find($id);
				if (isset($ret)) return $ret;
			}
		}
	}

	function Dump($in = 0)
	{
		foreach ($this->children as $c)
		{
			echo str_repeat(' ', $in);
			echo $c->id."\n";
			$c->Dump($in+1);
		}
	}

	static function AddNodes(&$tv, $nodes)
	{
		foreach ($nodes as $t => $v) $tv->AddChild(new TreeNode($t, $v));
	}

	function Collapse()
	{
		if (!empty($this->id)) $ret = array($this->id => $this->data);
		else $ret = array();
		foreach ($this->children as $c)
			$ret = array_merge($ret, $c->Collapse());
		return $ret;
	}

	function GetUL($tree, $display = 'name')
	{
		$ret = '<ul><li>'.$tree->data[$display];
		if (!empty($tree->children))
		foreach ($tree->children as $child)
		{
			$ret .= TreeToUL($child, $display);
		}
		$ret .= "</li></ul>\n";
		return $ret;
	}
}

?>
