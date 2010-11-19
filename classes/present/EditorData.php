<?php

require_once(__DIR__.'/Template.php');
require_once(__DIR__.'/../Str.php');
require_once(__DIR__.'/Form.php');

/**
 * @package Editor
 */

define('ED_SORT_NONE',   0);
define('ED_SORT_MANUAL', 1);
define('ED_SORT_TABLE',  2);

define('CONTROL_SIMPLE', 0);
define('CONTROL_BOUND', 1);

define('STATE_CREATE', 0);
define('STATE_EDIT', 1);

class DisplayColumn
{
	/**
	 * Text of the column in the display table.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Callback function for evaluating this cell for each row.
	 *
	 * @var mixed
	 */
	public $callback;

	/**
	 * HTML attributes for this column.
	 *
	 * @var string
	 */
	public $attribs;

	/**
	 * Creates a new DisplayColumn.
	 *
	 * @param string $text
	 * @param mixed $callback
	 * @param string $attribs
	 */
	function DisplayColumn($text, $callback = null, $attribs = null)
	{
		$this->text = $text;
		$this->callback = $callback;
		$this->attribs = $attribs;
	}
}

class EditorHandler
{
	/**
	 * Default handler for creating an item.
	 * If you extend this object and return false, it will not add the item.
	 *
	 * @param array $data Context
	 * @return bool true by default
	 */
	function Create($s, &$data) { return true; }

	/**
	 * After an item is created, this contains the id of the new item. You
	 * cannot halt the item from being inserted at this point.
	 *
	 * @param mixed $id Unique id of this row.
	 * @param array $inserted Data that has been inserted (including the id).
	 * @return bool true by default
	 */
	function Created($s, $id, $inserted) { return true; }

	/**
	 * Before an item is updated, this function is called. If you extend this
	 * object and return false, it will not be updated.
	 *
	 * @param mixed $id Unique id of this row.
	 * @param array $original Original data before update.
	 * @param array $update Columns suggested to get updated.
	 * @return bool true by default
	 */
	function Update($s, $id, &$original, &$update) { return true; }

	/**
	 * Called before and item is deleted. If you extend this object and return
	 * false, it will not be deleted.
	 *
	 * @param int $id ID of deleted items
	 * @param array $data Context
	 * @return bool true by default (meant to be overridden)
	 */
	function Delete($s, $id, &$data) { return true; }

	/**
	 * Called to retrieve additional fields for the editor form object.
	 * @param EditorData $s Source editor.
	 * @param Form $form Contextual form suggested to add fields to.
	 * @param mixed $id Unique id of this row.
	 * @param array $data Data related to the action (update/insert).
	 */
	function GetFields($s, &$form, $id, $data) {}

	/**
	 * Returns an array of joins to be passed as an argument to DataSet->Get()
	 * @return array Join array.
	 * @see DataSet
	 */
	function GetJoins() { return array(); }
}

/**
 * Check the example...
 *
 * @example doc/examples/HandlerFile.php
 *
 */
class HandlerFile extends EditorHandler
{
	/**
	 * Root location to create folders.
	 *
	 * @var string
	 */
	public $target;

	/**
	 * Conditions that can exemplify the creation of certain items.
	 * specified as conditions['column'] = 'match';
	 *
	 * @var array
	 */
	public $conditions;

	/**
	 * Identification of the owner for the associated target.
	 * @var mixed
	 */
	private $ownership;

	/**
	 * Creates a new file handler.
	 *
	 * @param FileManager $fm Associated file manager.
	 * @param string $target VarParsed string of associated database columns.
	 * @param array $conditions Conditions to consider enabling folder
	 * management.
	 * @param mixed $ownership Identification of the owner for the associated
	 * target.
	 */
	function HandlerFile($fm, $target, $conditions = null, $ownership = null)
	{
		$this->fm = $fm;
		$this->target = $target;
		$this->conditions = $conditions;
		$this->ownership = $ownership;
	}

	function Create($s, &$data)
	{
		$vp = new VarParser();
		$dst = $vp->ParseVars($this->target, $data);
		//If all variables are not satisfied, we can end up calling a deltree
		//on a higher level folder, that could be disasterous.
		if (strpos($dst, '//') > -1) return false;
		else return true;
	}

	/**
	 * Called when an item is created.
	 * Example: array('usr_access' => array(1, 3, 5));
	 * This will manage files for the user if the column usr_access is 1, 3 or 5.
	 */
	function Created($s, $id, $inserted)
	{
		$vp = new VarParser();
		$vp->Bleed = false;
		$dst = $vp->ParseVars($this->target, $inserted);
		if (!isset($this->conditions) && !file_exists($dst))
		{
			mkrdir($dst, 0777);
		}
		else if (!empty($this->conditions))
		{
			foreach ($this->conditions as $col => $cond)
			{
				foreach ($cond as $val)
				{
					if ($inserted[$col] == $val && !file_exists($dst))
					{
						mkrdir($dst, 0777);
						if ($this->ownership)
						{
							$fi = new FileInfo($dst);
							$fi->info['owner'] = $inserted[$this->ownership];
							$fi->SaveInfo();
						}
						return true;
					}
				}
			}
		}
		return true;
	}

	function Update($s, $id, &$original, &$update)
	{
		$vp = new VarParser();
		$dst = $vp->ParseVars($this->target, $update);
		if (strpos($dst, '//') > -1) return false;
		$vp->Bleed = false;
		$src = $vp->ParseVars($this->target, $original);
		if (!isset($this->conditions) && file_exists($src))
		{
			if (!file_exists(dirname($dst))) mkrdir(dirname($dst), 0777);
			rename($src, $dst);
		}
		else if (!empty($this->conditions))
		{
			foreach ($this->conditions as $col => $cond)
			{
				foreach ($cond as $val)
				{
					if ($update[$col] == $val)
					{
						if (file_exists($src)) rename($src, $dst);
						else mkrdir($dst, 0777);

						if ($this->ownership)
						{
							$fi = new FileInfo($dst);
							$fi->info['owner'] = $update[$this->ownership];
							$fi->SaveInfo();
						}
						return true;
					}
				}

				//A condition has been met by now, cleanup time.
				if (file_exists($src) && realpath($this->fm->Root) != realpath($src))
					DelTree($src);
			}
		}
		return true;
	}

	/**
	 * Called when an item is deleted.
	 *
	 * @param mixed $id
	 * @param array $data
	 * @return bool
	 */
	function Delete($s, $id, &$data)
	{
		$vp = new VarParser();
		$dst = $vp->ParseVars($this->target, $data);
		if (!strpos($dst, '//') && file_exists($dst)) DelEmpty($dst);
		return true;
	}
}

/**
 * A complex data editor.
 */
class EditorData
{
	/**
	 * Unique name of this editor.
	 *
	 * @var string
	 */
	public $Name;

	/**
	 * Dataset to interact with.
	 *
	 * @var DataSet
	 */
	public $ds;

	/**
	 * Filter to be passed to the DataSet in it's form.
	 *
	 * @var array
	 * @see DataSet.WhereClause
	 */
	public $filter;

	/**
	 * Order to sort items, passed to the DataSet.
	 *
	 * @var array
	 * @see DataSet.OrderClause
	 */
	public $sort;

	/**
	 * State of this editor.
	 *
	 * @var int
	 * @see STATE_CREATE
	 * @see STATE_EDIT
	 */
	public $state;

	/**
	 * The method used to handle sorting of the table portion of this
	 * editor. Use either ED_SORT_NONE (0), ED_SORT_MANUAL (1) or ED_SORT_TABLE (2).
	 *
	 * @var int
	 */
	public $sorting;

	/**
	 * Callback function for when an item is created.
	 *
	 * @var mixed
	 * @deprecated  use AddHandler instead.
	 */
	public $oncreate;

	/**
	 * Calback function to be called when an item is updated.
	 *
	 * @var mixed
	 * @deprecated  use AddHandler instead.
	 */
	public $onupdate;

	/**
	 * Callback function for when an item is deleted.
	 *
	 * @var mixed
	 * @deprecated use AddHandler instead.
	 */
	public $ondelete;

	/**
	 * Callback function for when an item is swapped with another.
	 *
	 * @var mixed
	 * @deprecated use AddHandler instead.
	 */
	//var $onswap;

	/**
	 * An array of handlers used for extra functionality of create, update,
	 * delete and swap.
	 *
	 * @var array
	 */
	public $handlers;

	/**
	 * Behavior settings for this editor.
	 * @var EditorDataBehavior
	 */
	public $Behavior;

	public $Error;

	/**
	 * Default constructor.
	 *
	 * @param string $name Name of this editor
	 * @param DataSet $ds Dataset for this editor to interact with.
	 * @param array $filter Array to constrain editing to a given expression.
	 * @param array $sort Array of 'column' => 'desc/asc'.
	 */
	function EditorData($name, &$ds, $filter = null, $sort = null)
	{
		$this->Name = $name;
		$this->filter = $filter;
		$this->ds = $ds;

		$this->Behavior = new EditorDataBehavior();
		$this->View = new EditorDataView();
		$this->handlers = array();

		if (strtolower(get_class($ds)) == 'dataset')
		{
			$this->sort = $sort;
			$this->type = CONTROL_BOUND;
		}
		else $this->type = CONTROL_SIMPLE;
		$this->sorting = ED_SORT_MANUAL;
	}

	/**
	 * Adds a handler to extend the functionality of actions performed in this
	 * editor.
	 *
	 * @param callback $handler
	 * @see HandlerFile
	 */
	function AddHandler(&$handler)
	{
		$this->handlers[] = $handler;
	}

	/**
	 * To be called before presentation, will process, verify and calculate any
	 * data to be used in the Get function.
	 * @return null
	 */
	function Prepare()
	{
		$act = Server::GetState($this->Name.'_action');

		if ($this->sorting == ED_SORT_TABLE)
			$this->sort = array(Server::GetVar('sort', $this->ds->id) =>
				Server::GetVar('order', 'ASC'));

		$this->state = $act == 'edit' ? STATE_EDIT : STATE_CREATE;

		if ($act == 'Cancel') $this->Reset();

		if ($act == 'Create')
		{
			$insert = array();
			$child_id = Server::GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			$fields = $context->ds->FieldInputs;
			foreach ($fields as $col => $in)
			{
				if (is_object($in))
				{
					$value = Server::GetVar($this->Name.'_'.$col);
					if ($in->attr('TYPE') == 'date')
					{
						$insert[$col] = $value[2].'-'.$value[0].'-'.$value[1];
					}
					else if($in->attr('TYPE') == 'datetime')
					{
						if($value[5][0] == 1)
						{
							//time is in PM
							if($value[3][0] != 12) $value[3][0] += 12;
						}
						$time_portion = " {$value[3][0]}:{$value[4][0]}:00";
						$insert[$col] = $value[2].'-'.$value[0].'-'.$value[1].$time_portion;
					}
					else if ($in->attr('TYPE') == 'password' && strlen($value) > 0)
					{
						$insert[$col] = md5($value);
					}
					else if ($in->attr('TYPE') == 'file')
					{
						if (empty($value['tmp_name'])) continue;
						$ext = strrchr($value['name'], '.');

						$moves[] = array(
							'src' => $value['tmp_name'],
							'dst' => $in->attr('VALUE').$ext
						);
						//$insert[$col] = $ext;
					}
					else if ($in->attr('TYPE') == 'selects') $insert[$col] = $value;
					else $insert[$col] = $value;
				}
				else if (is_numeric($col)) continue;
				else $insert[$col] = SqlUnquote($in);
				//I just changed this to 'else' (check the history), because a
				//numeric value with a string column would not go in eg. 5
				//instead of '5', if this ends up conflicting, we'll need to
				//come up with a different solution.
			}

			foreach ($this->handlers as $handler)
			{
				if (!$handler->Create($this, $insert)) { $this->Reset(); return; }
			}

			$parent = Server::GetVar('parent');

			if (isset($parent))
			{
				$child = $this->ds->children[Server::GetVar('child')];
				$insert[$child->child_key] = $parent;
			}

			$id = $context->ds->Add($insert);
			$insert[$context->ds->id] = $id;

			if (!empty($moves))
			{
				$vp = new VarParser();
				foreach ($moves as $move)
				{
					$dst = $vp->ParseVars($move['dst'], $insert);
					move_uploaded_file($move['src'], $dst);
					chmod($dst, 0777);
				}
			}

			foreach ($this->handlers as $handler)
				$handler->Created($this, $id, $insert);

			$this->Reset();
		}

		else if ($act == 'Update')
		{
			$ci = Server::GetVar($this->Name.'_ci');

			if ($this->type == CONTROL_SIMPLE)
			{
				foreach (array_keys($this->ds->FieldInputs) as $name)
				{
					$vals[$name] = Server::GetVar($name);
				}
				$fp = fopen($ci, 'w+');
				fwrite($fp, serialize($vals));
				fclose($fp);
			}
			$child_id = Server::GetVar('child');
			$context = $child_id != null ? $this->ds->children[$child_id] : $this;
			$update = array();
			foreach ($context->ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					if (get_class($in) == 'FieldInput' && $in->type == 'label') continue;

					$value = Server::GetVar($this->Name.'_'.$col);

					//TODO: Support editing custom fields.
					#if ($in->type == 'custom')
					#	unset($update[$col]);
					if ($in->attr('TYPE') == 'date')
						$update[$col] = $value[2].'-'.$value[0].'-'.$value[1];
					else if($in->attr('TYPE') == 'datetime')
					{
						if ($value[5][0] == 1)
						{
							//time is in PM
							if ($value[3][0] != 12) $value[3][0] += 12;
						}
						$time_portion = " {$value[3][0]}:{$value[4][0]}:00";
						$update[$col] = $value[2].'-'.$value[0].'-'.$value[1].$time_portion;
					}
					else if ($in->attr('TYPE') == 'label')
						unset($update[$col]);
					else if ($in->attr('TYPE') == 'password')
					{
						if (strlen($value) > 0) $update[$col] = md5($value);
					}
					else if ($in->attr('TYPE') == 'checkbox')
						$update[$col] = ($value == 1) ? 1 : 0;
					else if ($in->attr('TYPE') == 'selects')
					{
						$update[$col] = $value;
					}
					else if ($in->attr('TYPE') == 'file')
					{
						if (strlen($value['tmp_name']) > 0)
						{
							$vp = new VarParser();
							$files = glob($vp->ParseVars($in->attr('VALUE'), $update).".*");
							foreach ($files as $file) unlink($file);
							$ext = strrchr($value['name'], '.');
							$src = $value['tmp_name'];
							$dst = $vp->ParseVars($in->attr('VALUE').$ext, $update);
							move_uploaded_file($src, $dst);
							$update[$col] = $ext;
						}
					}
					else $update[$col] = $value;
				}
			}

			if (count($this->handlers) > 0)
			{
				$data = $this->ds->GetOne(array($this->ds->id => $ci));
				$update[$this->ds->id] = $ci;
				foreach ($this->handlers as $handler)
				{
					$res = $handler->Update($this, $ci, $data, $update);
					// Returns false, simple failure.
					if (!$res) { $this->Reset(); return; }
					// Returns an array of errors.
					if (is_array($res))
					{
						$this->state = STATE_EDIT;
						$this->Errors = $res;
						return;
					}
				}
			}

			if ($this->type == CONTROL_BOUND)
				$context->ds->Update(array($context->ds->id => $ci), $update);

			$this->Reset();
		}

		else if ($act == 'delete')
		{
			$ci = Server::GetState($this->Name.'_ci');

			$child_id = Server::GetVar('child');
			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			$data = $context->ds->GetOne(array($context->ds->id => $ci));

			if (count($this->handlers) > 0)
			{
				foreach ($this->handlers as $handler)
				{
					if (!$handler->Delete($this, $ci, $data)) return;
				}
			}
			if (!empty($context->ds->FieldInputs))
			foreach ($context->ds->FieldInputs as $name => $in)
			{
				if (is_object($in) && strtolower(get_class($in)) == 'forminput')
				{
					if ($in->attr('TYPE') == 'file')
					{
						$vp = new VarParser();
						$files = glob($vp->ParseVars($in->attr('VALUE'), $data).".*");
						foreach ($files as $file) unlink($file);
					}
				}
			}
			$context->ds->Remove(array($context->ds->id => $ci));
		}

		if ($this->type == CONTROL_SIMPLE)
		{
			if (file_exists($ci))
				$this->values = unserialize(file_get_contents($ci));
			else
				$this->values = array();
		}
	}

	/**
	 * Looks like it converts database rows to an array for DataToSel or
	 * something.
	 *
	 * @param array $items
	 * @param mixed $sel
	 * @deprecated No idea where it came from.
	 * @return array
	 */
	function GetSelArray($items, $sel)
	{
		$ret = array();
		foreach ($items as $i)
		{
			$ret[$i->id] = array($i, $i->id == $sel);
		}
		return $ret;
	}

	/**
	 * Gets a selection mask, for using 'selects' types and bitmasking the
	 * results.
	 *
	 * @param array $items
	 * @param int $sel
	 * @return array
	 */
	function GetSelMask($items, $sel)
	{
		$ret = array();
		foreach ($items as $id => $i)
		{
			$i->selected = ($id & $sel) > 0;
			$ret[$id] = $i;
		}
		return $ret;
	}

	/**
	 * Gets the rendered HTML for this editor.
	 *
	 * @return string
	 */
	function Get($assoc)
	{
		$ret['name'] = $this->Name;

		$act = Server::GetVar($this->Name.'_action');
		$sq = Server::GetVar($this->Name.'_q');

		$ret['ds'] = $this->ds;
		if ($act != 'edit' && !empty($this->ds->DisplayColumns)
			&& ($this->Behavior->Search && isset($sq)))
			$ret['table'] = $this->GetTable($this->Behavior->Target, $act, $sq);
		else $ret['table'] = null;
		$ret['forms'] = $this->GetForms(Server::GetVar($assoc) == $this->Name ?
			Server::GetVar('child') : null);
		return $ret;
	}

	/**
	 * Builds a recursive tree of editable items.
	 *
	 * @param array $items Items to be inserted into the tree.
	 * @return TreeNode
	 * @see GetTable
	 */
	function BuildTree($items)
	{
		if (!empty($items))
		{
			//Columns
			//* Gather all columns required for display and relation.
			//Children
			//* Map child names to child index.
			$cols[$this->ds->table] = array($this->ds->id => 1);
			if (!empty($this->ds->DisplayColumns))
			foreach (array_keys($this->ds->DisplayColumns) as $col)
				$cols[$this->ds->table][$col] = $this->ds->id == $col;

			if (!empty($this->ds->children))
			foreach ($this->ds->children as $ix => $child)
			{
				$children[$child->ds->table] = $ix;
				$cols[$child->ds->table][$child->parent_key] = 1;
				$cols[$child->ds->table][$child->child_key] = 0;
				if (!empty($child->ds->DisplayColumns))
				foreach (array_keys($child->ds->DisplayColumns) as $col)
				{
					$cols[$child->ds->table][$col] = 0;
				}
			}

			//Flats
			// * Convert each item into separated TreeNodes
			// * Associate all indexes by table, then id

			$flats = array();

			//Iterate all the resulting database rows.
			foreach ($items as $ix => $item)
			{

				//Iterate the columns that were created in step 1.
				foreach ($cols as $table => $columns)
				{
					//This will store all the associated data in the treenode
					//for the editor to reference while processing the treee.
					$data = array();
					$skip = false;

					//Now we're iterating the display columns.
					foreach ($columns as $column => $id)
					{
						//This column is not associated with a database row.
						if (is_numeric($column)) continue;

						//Table names are included to avoid ambiguity.
						$colname = $table.'_'.$column;

						//ID would be specified if this is specified as a keyed
						//value.
						if ($id)
						{
							if (empty($item[$colname]))
							{
								$skip = true;
								break;
							}
							$idcol = $colname;
						}
						$data[$this->ds->StripTable($colname)] = $item[$this->ds->StripTable($colname)];
					}
					if (!$skip)
					{
						$tn = new TreeNode($data);
						$tn->id = $item[$idcol];
						$flats[$table][$item[$idcol]] = $tn;
					}
				}
			}

			//Tree
			// * Construct tree out of all items and children.

			$tree = new TreeNode('Root');

			foreach ($flats as $table => $items)
			{
				foreach ($items as $ix => $node)
				{
					$child_id = isset($children[$table]) ? $children[$table] : null;

					if (isset($children[$table]))
					{
						$ckeycol = $this->ds->children[$child_id]->child_key;
						$pid = $node->data["{$table}_{$ckeycol}"];
					}
					else $pid = 0;

					$node->data['_child'] = $child_id;

					if ($pid != 0)
						$flats[$this->ds->table][$pid]->children[] = $node;
					else
						$tree->children[] = $node;
				}
			}
			//Put child table children above related
			//children, helps to understand the display.
			if (count($this->ds->children) > 0) $this->FixTree($tree);
			return $tree;
		}
		return null;
	}

	/**
	 * Fixes a tree of items so that foreign children appear on the top. Makes
	 * it much more readable.
	 *
	 * @param TreeNode $tree
	 * @see BuildTree
	 */
	function FixTree(&$tree)
	{
		usort($tree->children, array($this, "SortByChild"));
		if (!empty($tree->children))
		foreach ($tree->children as $cnode) $this->FixTree($cnode);
	}

	/**
	 * Simple callback to sort items by a child, used by FixTree
	 *
	 * @access private
	 * @param TreeNode $a
	 * @param TreeNode $b
	 * @return int
	 * @see FixTree
	 * @see BuildTree
	 */
	function SortByChild($a, $b)
	{
		if (isset($a->data['_child']))
			return ($a->data['_child'] > $b->data['_child']) ? -1 : 1;
		return 0;
	}

	/**
	 * Gets the HTML rendered table portion of this editor.
	 *
	 * @param string $target Filename that is using this editor.
	 * @param mixed $ci Currently editing item (eg. GetVar('ci')).
	 * @return string
	 * @access private
	 */
	function GetTable($target)
	{
		if ($this->Behavior->Search)
		{
			$sq = Server::GetVar($this->Name.'_q');
			if (!isset($sq)) return;
		}

		$ret = null;
		if (empty($this->ds->DisplayColumns)) return;
		if ($this->type == CONTROL_BOUND)
		{
			$cols = array();

			//Build columns so nothing overlaps (eg. id of this and child table)

			$cols[$this->ds->table.'_'.$this->ds->id] =
				$this->ds->table.'.'.$this->ds->id;

			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				if (is_numeric($col)) continue;

				if (strpos($col, '.')) // Referencing a joined table.
				{
					$st = $this->ds->StripTable($col);
					$cols[$st] = $st;
				}
				else // A table from this dataset.
					$cols[$this->ds->table.'_'.$col] =
						$this->ds->table.'.'.$col;
			}

			$joins = null;
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
			{
				$joins = array();

				//Parent column of the child...
				$cols[$child->ds->table.'.'.$child->child_key] =
					$child->ds->table.'_'.$child->child_key;

				//Coming from another table, we gotta join it in.
				if ($child->ds->table != $this->ds->table)
				{
					$joins[$child->ds->table] = "{$child->ds->table}.
						{$child->child_key} = {$this->ds->table}.
						{$child->parent_key}";

					//We also need to get the column names that we'll need...
					$cols[$child->ds->table.'.'.$child->ds->id] =
						$child->ds->table.'_'.$child->ds->id;
					if (!empty($child->ds->DisplayColumns))
					foreach ($child->ds->DisplayColumns as $col => $disp)
					{
						$cols[$child->ds->table.'_'.$col] =
							$child->ds->table.'.'.$col;
					}
				}
			}

			# Search

			if (!empty($sq))
				foreach($cols as $c)
					$q['match'][$c] = SqlOr(SqlLike("%$sq%"));

			$q['columns'] = $cols;
			$q['order'] = $this->sort;
			$q['limit'] = $this->filter;
			$items = $this->ds->Get($q);

			$root = $this->BuildTree($items);
		}

		if (isset($root))
		{
			$cols = array();
			$atrs = array();

			//Columns and column attributes.
			if (!empty($this->ds->DisplayColumns))
			foreach ($this->ds->DisplayColumns as $col => $disp)
			{
				$cols[$col] = $disp->text;
				$atrs[] = $disp->attribs;
			}

			//Gather children columns.
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
			{
				if ($child->ds->table != $this->ds->table)
				if (!empty($child->ds->DisplayColumns))
				foreach ($child->ds->DisplayColumns as $col => $disp)
				{
					$cols[$col] = "<b>{$disp->text}</b>";
					$atrs[] = $disp->attribs;
				}
			}

			if ($this->sorting == ED_SORT_TABLE)
				$table = new SortTable($this->Name.'_table', $cols, $atrs);
			else
				$table = new Table($this->Name.'_table', $cols, $atrs);

			$rows = array();
			$this->AddRows($rows, $target, $root, 0);

			foreach ($rows as $ix => $row)
			{
				$class = $ix % 2 ? 'even' : 'odd';
				$table->AddRow($row, array('CLASS' => $class));
			}

			$ret .= $table->Get(array('CLASS' => 'editor'));
		}
		return $ret;
	}

	/**
	 * Recursively populates $rows with child items.
	 *
	 * @param array $rows Referenced rows that are being populated.
	 * @param string $target Filename of script using this editor.
	 * @param TreeNode $node Node of this item, for recursion.
	 * @param int $level Depth of these items.
	 * @access private
	 */
	function AddRows(&$rows, $target, $node, $level)
	{
		global $PERSISTS;

		if (!empty($node->children))
		foreach ($node->children as $cnode)
		{
			$ix = 0;
			$row = array();

			if (isset($cnode->data['_child']))
				$child_id = $cnode->data['_child'];

			$context = isset($child_id) ? $this->ds->children[$child_id] : $this;

			//Don't display children that don't have a display to show.
			if (empty($context->ds->DisplayColumns)) continue;

			//Pad all existing columns to ensure proper width.
			$total_cells = count($this->ds->DisplayColumns);
			if (!empty($this->ds->children))
			foreach ($this->ds->children as $child)
				if ($child->ds->table != $this->ds->table)
					$total_cells += count($child->ds->DisplayColumns);
			$row = array_pad($row, $total_cells, '&nbsp;');

			//Move cursor (ix) to the first column we're displaying here.
			if (isset($child_id))
			{
				if ($this->ds->children[$child_id]->ds->table != $this->ds->table)
					$ix += count($this->ds->DisplayColumns);
				$i = 0;
				while ($i++ < $child_id-1)
				{
					$ix += count($this->ds->children[$i]->ds->DisplayColumns);
				}
			}

			//Show all displays for this context.
			if (!empty($context->ds->DisplayColumns))
			foreach ($context->ds->DisplayColumns as $col => $disp)
			{
				if (strpos($col, '.'))
					$disp_index = $this->ds->StripTable($col);
				else
					$disp_index = $context->ds->table.'_'.$col;

				//Callback mapped
				if (isset($disp->callback))
				{
					$row[$ix++] = call_user_func_array($disp->callback,
						array($this->ds, $cnode->data, $disp_index, $col));
				}
				//Regular field
				else
				{
					if (array_key_exists($disp_index, $cnode->data))
					{
						$row[$ix++] = array(
							htmlspecialchars($cnode->data[$disp_index]),
							array('class' => 'editor_cell',
								'id' => "{$this->Name}:{$col}:{$cnode->id}")
						);
					}
				}
			}

			$url_defaults = array($this->assoc => $this->Name);
			if (isset($child_id)) $url_defaults['child'] = $child_id;

			if (!empty($PERSISTS)) $url_defaults = array_merge($url_defaults,
				$PERSISTS);

			$p = GetRelativePath(dirname(__FILE__));

			if ($this->Behavior->AllowEdit)
			{
				$url_edit = URL($target, array_merge(array(
					$this->Name.'_action' => 'edit',
					$this->Name.'_ci' => $cnode->id), $url_defaults));
				$url_del = URL($target, array_merge(array(
					$this->Name.'_action' => 'delete',
					$this->Name.'_ci' => $cnode->id), $url_defaults));
				$row[] = "<a href=\"$url_edit#box_{$this->Name}_forms\"><img
					src=\"{$p}/images/edit.png\" alt=\"Edit\"
					title=\"".$this->View->TextEdit."\" class=\"png\" /></a>";
				$row[] = "<a href=\"$url_del#{$this->Name}_table\"
					onclick=\"return confirm('Are you sure?')\"><img
					src=\"{$p}/images/delete.png\" alt=\"Delete\"
					title=\"".$this->View->TextDelete."\" class=\"png\" /></a>";
			}

			$rows[] = $row;

			$this->AddRows($rows, $target, $cnode, $level+1);
		}
	}

	/**
	 * Gets the form portion of this editor.
	 *
	 * Field input types...
	 * 'column' => object, //This will be processed as a FormInput
	 * # => anything, //This will be a newline.
	 * 'column' => 'string', // This will be processed destringed to mysql, eg. NOW().
	 *
	 * @param int $state Current state of the editor.
	 * @param int $curchild Current child by DataSet Relation.
	 * @return string
	 */
	function GetForm($state, $curchild = null)
	{
		if ($this->state == STATE_CREATE && !$this->Behavior->AllowCreate)
			return;

		$fullname = $this->Name;
		if ($curchild != null) $fullname .= '_'.$curchild;
		$ci = Server::GetState($this->Name.'_ci');

		if ($this->type == CONTROL_BOUND)
		{
			if ($this->state == STATE_CREATE)
			{
				if (!empty($this->ds->FieldInputs))
				foreach ($this->ds->FieldInputs as $k => $fi)
					if (is_object($fi) && $fi->attr('TYPE') == 'label')
						unset($this->ds->FieldInputs[$k]);
			}
			$context = isset($curchild) ? $this->ds->children[$curchild] :
				$this;

			if (!isset($this->ds)) Error("<br />What: Dataset is not set.
				<br />Where: EditorData({$this->Name})::GetForm.
				<br />Why: This editor was not created with a proper dataset.");

			foreach ($this->handlers as $handler)
			{
				$joins = $handler->GetJoins();
				if (!empty($joins))
				foreach ($joins as $ix => $j) $this->ds->joins[$ix] = $j;
					//$joins = array_merge($joins, $join);
			}

			$sel = $state == STATE_EDIT ? $context->ds->Get(array(
				'match' => array($context->ds->id => $ci),
					'joins' => @$joins)) : null;

			$ds = $context->ds;
		}
		else
		{
			$ds = $this->ds;
			if (!empty($ds->FieldInputs))
			foreach (array_keys($ds->FieldInputs) as $n)
			{
				if (isset($this->values[$n]))
					$ds->FieldInputs[$n]->attr('VALUE', $this->values[$n]);
			}
		}

		if (!empty($ds->FieldInputs))
		{
			$frm = new Form($fullname, null, false);

			if (isset($ds->Validation))
			{
				$frm->Validation = $ds->Validation;
				$frm->Errors = $ds->Errors;
			}

			if ($state == STATE_EDIT || $this->type != CONTROL_BOUND)
			{
				$frm->AddHidden('ci', $ci);
				if (!empty($this->assoc))
					$frm->AddHidden($this->assoc, $this->Name);
			}

			global $PERSISTS;
			if (!empty($PERSISTS))
				foreach ($PERSISTS as $key => $val)
					$frm->AddHidden($key, $val);

			if (isset($curchild))
			{
				$frm->AddHidden('parent', $ci);
				$frm->AddHidden('child', $curchild);
			}

			foreach ($ds->FieldInputs as $col => $in)
			{
				if (is_object($in))
				{
					if ($in->attr('TYPE') == 'custom') //Callback
					{
						$cb = $in->attr('VALUE');
						call_user_func($cb, isset($sel) ? $sel : null,
							$frm, $col);
						continue;
					}
					else if ($in->attr('TYPE') == 'select' || $in->attr('TYPE') == 'radios')
					{
						$val = $in->attr('VALUE');
						if (isset($sel) && isset($val[$sel[0][$col]]))
						{
							$val[$sel[0][$col]]->selected = true;
							$in->attr('VALUE', $val);
						}
					}
					else if ($in->attr('TYPE') == 'file')
					{
						if (!empty($in->atrs['EXTRA']))
						{
							$vp = new VarParser();
							$glob = $vp->ParseVars($in->attr('VALUE'), $sel[0]);
							$files = glob($glob.'.*');

							switch ($in->atrs['EXTRA'])
							{
								case 'thumb':
									$in->help = '<img src="'
									.(empty($files) ? 'xedlib/images/cross.png'
									  : $files[0]).'" />';
									break;
								case 'exists':
									$in->help = '<img src="xedlib/images/'.
									(empty($files) ? 'cross.png' : 'tick.png').
									'" />';
							}
						}
					}
					else
					{
						if ($in->attr('TYPE') == 'password') $in->attr('VALUE', '');
						else if (isset($sel[0][$col]))
						{
							if ($in->attr('TYPE') == 'date')
								$in->attr('VALUE', MyDateTimestamp($sel[0][$col]));
							else if ($in->attr('TYPE') == 'datetime')
								$in->attr('VALUE', MyDateTimestamp($sel[0][$col], true));
							else $in->attr('VALUE', $sel[0][$col]);
						}
						//If we bring this back, make sure setting explicit
						//values in DataSet::FormInputs still works.
						//else { $in->valu = null; }
					}

					$in->attr('NAME', $this->Name.'_'.$col);

					if (isset($this->Errors[$in->attr('NAME')]))
						$in->help = $this->Errors[$in->atrs['NAME']];
					$in->attr('CLASS', 'editor_input');

					$frm->AddInput($in);
				}
				else if (is_numeric($col)) $frm->AddInput('&nbsp;');
			}

			foreach ($this->handlers as $handler)
			{
				//Use plural objects to compliment the joins property.
				//For some reason I change this to a single item when
				//it can be multiple.

				$handler->GetFields($this, $frm,
				isset($sel) ? $sel[0][$this->ds->id] : null,
				isset($sel) ? $sel : null);
			}

			$frm->State = $state == STATE_EDIT || $this->type != CONTROL_BOUND
				? 'Update' : 'Create';
			$frm->Description = $ds->Description;
			$frm->AddInput(
				$frm->GetSubmitButton($this->Name.'_action', $frm->State).
				($state == STATE_EDIT && $this->type == CONTROL_BOUND ?
				 '<input type="submit" name="'.$this->Name.'_action" value="Cancel" />'
				 : null)
			);

			return $frm;
		}
	}

	/**
	 * Get update and possibly children's create forms for the lower
	 * section of this editor.
	 *
	 * @param int $curchild Current child.
	 * @return string
	 */
	function GetForms($curchild = null)
	{
		$ret = null;
		$context = $curchild != null ? $this->ds->children[$curchild] : $this;

		$ci = Server::GetState($this->Name.'_ci');
		$ca = Server::GetVar($this->Name.'_ca');

		$frm = $this->GetForm($this->state, $curchild);
		if ($frm != null) $ret[] = $frm;

		if (isset($ci) && $ca == 'edit')
		{
			if (!empty($context->ds->children))
			foreach ($context->ds->children as $ix => $child)
				if (isset($child->ds->FieldInputs))
					$ret[] = $this->GetForm(STATE_CREATE, $ix);
		}
		return $ret;
	}

	/**
	 * Prepare forms tags with their information.
	 *
	 * @param Template $t Associated template.
	 * @param string $guts Contents of the tag.
	 */
	function TagForms($t, $guts)
	{
		$out = '';
		$forms = $this->GetForms();
		$vp = new VarParser();
		if (!empty($forms))
		foreach ($forms as $frm)
		{
			$d['form_title'] = "{$frm->State} {$frm->Description}";
			$d['form_content'] = $frm->Get('method="post" action="'.
				$this->Behavior->Target.'"',
				'class="form"');
			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;
	}

	function TagSearch($t, $g, $a)
	{
		if ($this->Behavior->Search) return $g;
	}

	/**
	 * Gets a standard user interface for a single editor's Get() method.
	 *
	 * @param string Name of state variable to pass around via GPC.
	 * @return string Rendered html of associated objects.
	 */
	function GetUI($assoc = 'editor')
	{
		$this->assoc = $assoc;

		$t = new Template();
		$t->ReWrite('forms', array(&$this, 'TagForms'));
		$t->ReWrite('search', array(&$this, 'TagSearch'));
		$t->Set('target', $this->Behavior->Target);
		$t->Set('name', $this->Name);
		$t->Set('plural', Str::Plural($this->ds->Description));

		if (!empty($this->ds))
			$t->Set('table_title', Str::Plural($this->ds->Description));

		$t->Set('table', $this->GetTable($this->Behavior->Target,
			Server::GetState($this->Name.'_ci')));

		$t->Set($this->View);
		$t->Set('assoc', $assoc);

		return $t->ParseFile(__DIR__.'/../../temps/editor.xml');
	}

	function Reset()
	{
		if (isset($_SESSION[$this->Name.'_action']))
		{
			unset($_SESSION[$this->Name.'_action']);
			unset($_SESSION[$this->Name.'_ci']);
		}
	}
}

class EditorDataView
{
	public $TextHeader = '';
	public $TextSearchHeader = '';
	public $TextTableHeader = '';
	public $TextFormHeader = '';
	public $TextEdit = 'Edit Item';
	public $TextDelete = 'Delete Item';
}

class EditorDataBehavior
{
	public $AllowCreate = true;

	/**
	 * Allows users to edit items in this editor.
	 *
	 * @var bool
	 */
	public $AllowEdit = true;

	/**
	 * Whether or not to use search functions.
	 *
	 * @var bool
	 */
	public $Search = true;

	/**
	 * How to group items if they are to be grouped.
	 *
	 * @var array
	 */
	public $Group;

	public $Target;
}

?>
