<?php

class EditorDisplay
{
	/**
	 * @var string
	 */
	public $Name;

	/**
	 * @var DataSet
	 */
	public $ds;

	/**
	 * Array of Join objects associated with this data display.
	 *
	 * @var array
	 */
	public $joins;

	/**
	 * Behavior that will affect this data display.
	 *
	 * @var DisplayDataBehavior
	 */
	public $Behavior;

	private $count;

	/**
	 * @param string $name Name of this display for state management.
	 * @param DataSet $ds Associated dataset to collect information from.
	 */
	function DisplayData($name, $ds)
	{
		$this->Name = $name;
		$this->ds = $ds;
		$this->Behavior = new DisplayDataBehavior();
	}

	/**
	 * Available to calling script to prepare any actions that may be ready to
	 * be performed.
	 *
	 * @access public
	 */
	function Prepare()
	{
		$act = Server::GetVar($this->Name.'_action');

		if ($act == 'update')
		{
			$ci = GetVar('ci');
			$up = array();
			foreach ($this->ds->FieldInputs as $col => $fi)
			{
				$fi->atrs['NAME'] = $col;
				// Sub table, we're going to need to clear and re-create the
				// associated table rows.
				$ms = null;
				if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
				{
					$join = $this->joins[$ms[1]];
					$cond = $join->Condition;
					$join->DataSet->Remove(array($cond[0] => $ci));
					$vals = GetVar($ms[2]);
					if (!empty($vals))
					foreach ($vals as $val)
					{
						$add = array($cond[0] => $ci);
						$add[$ms[2]] = $val;
						$join->DataSet->Add($add);
					}
				}
				else $up[$col] = $fi->GetData();
			}
			$this->ds->Update(array($this->ds->id => $ci), $up);
		}

		//Collect search data

		if ($act == 'search')
		{
			$this->fs = GetVar('field');
			$this->ss = GetVar($this->Name.'_search');
			$this->ipp = GetVar($this->Name.'_ipp', 10);

			$query = array();

			foreach (array_keys($this->ds->DisplayColumns) as $col)
			{
				$fi = $this->ds->FieldInputs[$col];
				if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
				{
					$query['columns'][0] = '*';
					$query['columns'][$ms[2]] = SqlUnquote("GROUP_CONCAT(DISTINCT {$col})");
					$query['group'] =  $this->ds->id;
				}
			}

			// Collect the data.

			if (!empty($this->ss))
			{
				foreach (array_keys($this->ss) as $col)
				{
					if (!isset($this->fs[$col])) return;
					$this->AddToQuery($query, $col, $this->fs[$col]);
				}

				$this->result = $this->ds->Get($query);
				$this->count = count($this->result);
			}
			else $this->result = array();

			if (!empty($this->result))
				$this->items = GetFlatPage($this->result, GetVar('cp', 0),
					$this->ipp);
		}
	}

	function AddToQuery(&$query, $col, $val)
	{
		if (is_array(@$this->SearchFields[$col]))
		{
			foreach ($this->SearchFields[$col] as $icol)
				$this->AddToQuery($query, $icol, $val);
		}

		$fi = $this->ds->FieldInputs[$col];
		$fi->atrs['NAME'] = $col;

		// This may not work.
		if ($fi->atrs['TYPE'] == 'select')
			$query['match'][$col] = SqlIn($val);
		else if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
			foreach ($this->fs[$col] as $ix => $v)
				$query['having'][] = " FIND_IN_SET($v, $ms[2]) > 0";
		else if ($fi->atrs['TYPE'] == 'date')
			$query['match'][$col] = SqlBetween(TimestampToMySql(DateInputToTS(
				$this->fs[$col][0]), false), TimestampToMySql(DateInputToTS(
				$this->fs[$col][1]), false));
		else $query['match'][$col] = SqlLike('%'.$val.'%');
	}

	/**
	 * @param string $temp Template to use for rendering me.
	 */
	function Get($temp = null)
	{
		$t = new Template();
		$t->Set('name', $this->Name);

		$t->ReWrite('results', array(&$this, 'TagResults'));
		$t->ReWrite('result', array(&$this, 'TagResult'));
		$t->ReWrite('search', array(&$this, 'TagSearch'));
		$t->ReWrite('pages', array(&$this, 'TagPages'));

		return $t->ParseFile(!isset($temp) ? dirname(__FILE__).
			'/temps/displaydata.xml' : $temp);

		/*$q = GetVar('q');

		if ($ca == 'edit' && $this->Behavior->AllowEdit)
		{
			$ci = GetVar('ci');

			if (!empty($this->ds->FieldInputs))
			{
				foreach (array_keys($this->ds->FieldInputs) as $col)
				{
					// This is a sub table, we GROUP_CONCAT these for
					// finding later if need be.
					$ms = null;
					if (preg_match('/([^.]+)\.(.+)/', $col, $ms))
						$cols[$ms[2]] =
							SqlUnquote("GROUP_CONCAT(DISTINCT {$ms[2]})");
					else $cols[$col] = $col;
				}

				$item = $this->ds->GetOne(array($this->ds->id => $ci),
					$this->joins, $cols, $this->ds->id);

				$frm = new Form('frmEdit');
				$frm->AddHidden($this->assoc, $this->Name);
				$frm->AddHidden('ca', 'update');
				$frm->AddHidden('ci', $ci);

				foreach ($this->ds->FieldInputs as $col => $fi)
				{
					if (preg_match('/([^.]+)\.(.+)/', $col, $ms))
						$col = $ms[2];
					$fi->atrs['NAME'] = $col;
					if ($fi->type == 'select' || $fi->type == 'selects'
						|| $fi->type == 'radios' || $fi->type == 'checks')
					{
						$sels = explode(',', $item[$col]);
						if (!empty($sels))
						foreach($sels as $sel)
							if (isset($fi->valu[$sel]))
								$fi->valu[$sel]->selected = true;
						if (isset($fi->valu[$item[$col]]))
							$fi->valu[$item[$col]]->selected = true;
					}
					else $fi->valu = $item[$col];

					$frm->AddInput($fi);
				}
				$frm->AddInput(new FormInput(null, 'submit', null, 'Update'));
				$ret .= $frm->Get('action="'.$target.'" method="post"');
			}

			if (!empty($this->Editors))
			foreach ($this->Editors as $join => $editor)
			{
				if (preg_match('/([^.]+)\.(.*)/', $join, $ms))
					$editor->filter = "{$ms[2]} = $ci";
				$ret .= $editor->GetUI($target, $ci);
			}
		}

		//else $ret = $this->GetSearch($target);

		return $ret;*/
	}

	function TagSearch($t, $g, $a)
	{
		global $me;

		$act = Server::GetVar($this->Name.'_action');

		if ($act == 'search') return;

		if (empty($this->SearchFields))
		{
			Error("You should specify a few SearchField items");
			return;
		}

		require_once('h_display.php');
		$frm = new Form($this->Name);
		$frm->Template = $g;
		$frm->AddHidden($this->Name.'_action', 'search');
		if (isset($GLOBALS['editor'])) $frm->AddHidden('editor', $GLOBALS['editor']);
		$frm->AddInput(new FormInput('Search', 'custom', null, array(&$this, 'callback_fields')));
		$frm->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Search'));
		return $frm->Get('action="'.Server::GetVar('q').'" method="post"');
	}

	function TagResults($t, $g, $a)
	{
		if (isset($this->count)) return $g;
		else return 'No results';
	}

	/**
	 * @param Template $t Associated template.
	 */
	function TagResult($t, $g, $a)
	{
		if (!empty($this->items) && !empty($this->ds->DisplayColumns))
		{
			$tField = new Template();
			$tField->ReWrite('field', array(&$this, 'TagField'));

			$ret = '';
			foreach ($this->items as $ix => $i)
			{
				if (!empty($this->Callbacks->Result))
					RunCallbacks($this->Callbacks->Result, $tField, $i);
				$this->item = $i;
				$tField->Set($i);
				$ret .= $tField->GetString($g);

				if ($ix > $this->ipp) break;
			}
			return $ret;
		}
		else if (isset($this->count)) return '<p>No results found!</p>';
	}

	function TagField($t, $g, $a)
	{
		$ret = '';
		$vp = new VarParser();
		foreach ($this->ds->DisplayColumns as $f => $dc)
		{
			$vars['text'] = $dc->text;
			$vars['val'] = '';

			if (strpos($f, '.')) // Sub Table
			{
				$vs = explode(',', $this->item[$this->ds->StripTable($f)]);

				foreach ($vs as $ix => $val)
				{
					if ($ix > 0) $vars['val'] .= ', ';

					if (!empty($this->fs[$f]))
					{
						$bold = array_search($val, $this->fs[$f]) ? true : false;
						if ($bold) $vars['val'] .= '<span class="result">';
					}
					if (!empty($val))
						$vars['val'] .= $this->ds->FieldInputs[$f]->atrs['VALUE'][$val]->text;
					if (!empty($this->fs[$f]) && $bold) $vars['val'] .= '</span>';
				}
			}
			else // Standard column
			{
				$vars['val'] = !empty($dc->callback)
					? call_user_func($dc->callback, $this->ds, $this->item, $f)
					: $this->item[$this->ds->StripTable($f)];
			}
			$ret .= $vp->ParseVars($g, $vars);
		}
		return $ret;
	}

	function TagPages($t, $g, $a)
	{
		if ($this->count > 10)
		{
			$vars = array_merge($_GET, $_POST);
			unset($vars['cp']);
			return GetPages(count($this->result), $this->ipp, $vars);
		}
	}

	function callback_fields()
	{
		$ret = '<table>';
		foreach ($this->SearchFields as $idx => $col)
			$ret .= $this->add_field($idx, $col);
		return $ret.'</table>';
	}

	function add_field($idx, $col)
	{
		$ret = null;

		// Tied to multiple fields
		if (is_array($col)) $col = $idx;
		if (!isset($this->ds->FieldInputs[$col])) return;
		$fi = $this->ds->FieldInputs[$col];
		$fi->atrs['NAME'] = "field[{$col}]";
		$target = '#'.str_replace('.','\\\\.',$col);
		$ret .= '<tr><td valign="top"><label><input type="checkbox"
			value="1" id="'.$this->Name.'_search_'.$col.'" name="'.$this->Name.'_search['.$col.']"
			onclick="$(\''.$target.'\').showHide($(this).attr(\'checked\'))" />
			'.$fi->text.'</label></td>';
		if ($fi->atrs['TYPE'] == 'date')
		{
			$fi->atrs['NAME'] = 'field['.$col.'][0]';
			$ret .= ' <td valign="top"><div id="'.$col.'" class="hidden">
				from '.$fi->Get($this->Name).' to ';
			$fi->atrs['NAME'] = 'field['.$col.'][1]';
			$ret .= $fi->Get($this->Name)."</div></td>\n";
		}
		else if ($fi->atrs['TYPE'] == 'select')
			$fi->type = 'checks';
		else $ret .= '<td><div id="'.$col.'" class="hidden">'.
			$fi->Get($this->Name).'</div></td>';
		$ret .= '</tr>';
		return $ret;
	}
}

class EditorDisplayBehavior
{
	public $AllowEdit;

	function AllowAll()
	{
		$this->AllowEdit = true;
	}
}

?>
