<?php

require_once(dirname(__FILE__).'/VarParser.php');

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

	/** @var TreeNode TreeNode that this was passed to AddChild on. */
	public $parent;

	/**
	 * Create a new TreeNode object.
	 *
	 * @param mixed $data Data to associate with this node.
	 */
		function __construct($data = null, $id = null)
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

	static function GetUL($tree, $display = 'name')
	{
		$ret = '<ul><li>'.$tree->data[$display];
		if (!empty($tree->children))
		foreach ($tree->children as $child)
			$ret .= TreeNode::GetUL($child, $display);
		$ret .= "</li></ul>\n";
		return $ret;
	}

	/**
	 * put your comment there...
	 *
	 * @param TreeNode $root Root treenode item.
	 * @param string $text VarParser capable text linked to treenode data items.
	 */
	static function GetTree($root, $text)
	{
		$vp = new VarParser();

		$ret = null;
		if (!empty($root->children))
		{
			$ret .= '<ul>';
			foreach ($root->children as $c)
			{
				$ret .= '<li>'.$vp->ParseVars($text, $c->data);
				$ret .= TreeNode::GetTree($c, $text);
				$ret .= "</li>";
			}
			$ret .= '</ul>';
		}
		return $ret;
	}

	/**
	 * Converts an array to a tree using TreeNode objects.
	 *
	 * @param TreeNode $n Node we are working with.
	 * @param array $arr Array items to add to $n.
	 * @return TreeNode Root of the tree.
	 */
	static function FromArray($n, $arr = null, $use_keys = true)
	{
		$root = new TreeNode($n);
		foreach ($arr as $k => $v)
		{
			if (is_array($v)) $n = TreeNode::FromArray($k, $v);
			else $n = new TreeNode($v);
			$root->AddChild($n);
		}
		return $root;
	}
}

?>
