<?php

class EditorSearch extends Module
{
	/** @var string */
	public $Name;
	/** @var EditorSearchBehavior */
	public $Behavior;
	/** @var array */
	public $SearchFields;
	/**
	 * @var string Filename of associated form.
	 */
	public $Form;
	/** @var DataSet */
	protected $_ds;
	
	public $FieldName = 'name';

	function __construct()
	{
		$this->Behavior = new EditorSearchBehavior();
		$this->Behavior->Buttons['View Details'] = array(
			'href' => "{{app_abs}}/{$this->Name}/view/{{id}}",
			'target' => '_blank'
		);
		$this->Behavior->Buttons['Edit'] = array(
			'href' => "{{app_abs}}/{$this->Name}/edit/{{id}}",
			'target' => '_blank'
		);
		$this->Behavior->Buttons['Delete'] = array(
			'class' => 'delResult',
			'href' => "{{app_abs}}/{$this->Name}/delete/{{id}}"
		);

		$this->CheckActive($this->Name);
		$this->_template = Module::L('editor_search/t.xml');
		$this->_template_results = Module::L('editor_search/results.xml');
	}

	function Prepare()
	{
		global $_d;

		if (!$this->Active) return;

		$this->_q = array_reverse($_d['q']);

		if (@$this->_q[3] == $this->Name)
		{
			if (@$this->_q[2] == 'js')
			{
				header('Content-Type: text/javascript');
				$t = new Template();
				if (@$this->_q[1] == 'fill')
				{
					$ci = $this->_q[0];
					$q['match'][$this->_ds->id] = $ci;
					$q['args'] = GET_ASSOC;
					foreach ($this->_ds->joins as $j)
						$q['orders'][$j->DataSet->id] = 'ASC';

					$data = $this->_ds->Get($q);

					$t->Set('json', json_encode($data));
				}
				$t->Set('name', $this->Name);
				die($t->ParseFile(Module::L('editor_search/'.@$this->_q[1].'.js')));
			}
		}

		# Collect search data

		if (@$this->_q[1] == $this->Name && @$this->_q[0] == 'search')
		{
			$this->ss = Server::GetVar($this->Name.'_search');
			$this->ipp = Server::GetVar($this->Name.'_ipp', 10);

			$query['group'] = $this->_ds->id;

			$query['columns'] = array(Database::SqlUnquote('SQL_CALC_FOUND_ROWS *'));

			foreach (array_keys($this->_ds->DisplayColumns) as $col)
			{
				if (!isset($this->_ds->FieldInputs[$col])) continue;

				$fi = $this->_ds->FieldInputs[$col];
				if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
					$query['columns'][$ms[2]] =
						Database::SqlUnquote("GROUP_CONCAT(DISTINCT {$col})");
			}

			# Collect the data.

			if (!empty($this->ss))
			{
				foreach (array_keys($this->ss) as $col)
				{
					$val = Server::GetVar($col);
					if (!isset($val)) return;
					$this->AddToQuery($query, $col, $val);
				}

				$this->items = $this->_ds->Get($query);

				if (Server::GetVar('butSubmit') == 'Save Results as CSV')
				{
					Server::SendDownloadStart('results.csv');
					echo '"'.implode('","', array_keys($this->items[0]))."\"\r\n";
					foreach ($this->items as $item)
					{
						echo '"'.implode('","', $item)."\"\r\n";
					}
					die();
				}

				$count = $this->_ds->GetCustom('SELECT FOUND_ROWS()');
				$this->count = $count[0][0];
			}
			else $this->items = array();
		}

		if (@$this->_q[2] == $this->Name && @$this->_q[1] == 'delete')
		{
			$id = $this->_q[0];
			$this->_ds->Remove(array($this->_ds->id => $id));
			die(json_encode(array('result' => 1)));
		}
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		$qc = $_d['q'];
		$ci = array_pop($qc);
		$act = array_pop($qc);
		$target = array_pop($qc);

		$t = new Template();

		$t->Set($_d);

		if ($target == $this->Name && $act == 'view')
		{
			$t->ReWrite('loop', 'TagLoop');
			$t->ReWrite('input', array('Form', 'TagInput'));
			$ret =<<<EOF
<script type="text/javascript" src="../../xedlib/js/jquery.js"></script>
<script type="text/javascript" src="../js/fill/{$ci}"></script>
<script type="text/javascript" src="../js/print/{$ci}"></script>
EOF;
			return $ret.$t->ParseFile($this->Form);
		}
		if ($target == $this->Name && $act == 'edit'
			&& $this->Behavior->AllowEdit)
		{
			$t->ReWrite('loop', 'TagLoop');
			$t->ReWrite('input', array('Form', 'TagInput'));
			$t->ReWrite('form', array($this, 'TagEditForm'));
			$this->ci = $ci;
			$ret = '<script type="text/javascript" src="../js/fill/'
				.$ci.'"></script>';
			return $ret.$t->ParseFile($this->Form);
		}
		else if ($ci == 'search')
		{
			$t->ReWrite('result', array($this, 'TagResult'));
			$t->ReWrite('page', array($this, 'TagPage'));
			$t->Set('count', $this->count);
			die($t->Parsefile($this->_template_results));
		}
		else
		{
			$t->ReWrite('search', array($this, 'TagSearch'));
			$t->ReWrite('results', array($this, 'TagResults'));
			return $t->Parsefile($this->_template);
		}
	}

	# Search Related

	function TagSearch($t, $g, $a)
	{
		$tt = new Template();
		$tt->Set('name', HM::CleanID($this->GetName()));
		$tt->Set('tempurl', Module::L('temps'));

		$tt->ReWrite('searchfield', array(&$this, 'TagSearchField'));
		return $tt->GetString($g);
		return $g;
	}

	function TagSearchField($t, $g)
	{
		$ret = null;
		if (empty($this->SearchFields)) Server::Error('Please specify SearchFields');
		foreach ($this->SearchFields as $ix => $sf)
			$ret .= $this->AddSearchField($ix, $sf, $g);
		return $ret;
	}

	function AddSearchField($ix, $sf, $g)
	{
		$vp = new VarParser();
		$ret = null;

		$checks = Server::GetVar($this->Name.'_search');

		if (is_array($sf)) $sf = $ix;
		if (array_key_exists($sf, $this->_ds->FieldInputs))
		{
			# Configure the search field.

			$fi = $this->_ds->FieldInputs[$sf];
			$fi->attr('NAME', $sf);

			if ($fi->attr('TYPE') == 'date') $fi->attr('TYPE', 'daterange');
			if ($fi->attr('TYPE') == 'select') $fi->attr('TYPE', 'checks');
			$fname = $fi->attr('NAME');
			$field = $fi->Get($this->Name);
			$fi->Value(Server::GetVar($fname));

			# Configure the checkbox.

			$cb = new FormInput($fi->text, 'checkbox', "{$this->Name}_search[{$fname}]");
			$cb->attr('id', 'hider_'.$fname);
			$cb->attr('class', 'hider');
			if (isset($checks[$fname])) $cb->attr('checked', 'checked');

			# Output the value.

			$ret .= $vp->ParseVars($g, array(
				'id' => $fname,
				'text' => $fi->text,
				'checkbox' => $cb->Get(),
				'field' => $fi->Get()
			));
		}
		return $ret;
	}

	function AddToQuery(&$query, $col, $val)
	{
		if (is_array(@$this->SearchFields[$col]))
		{
			foreach ($this->SearchFields[$col] as $icol)
				$this->AddToQuery($query, $icol, $val);
		}

		$fi = $this->_ds->FieldInputs[$col];
		$fi->attr('NAME', $col);

		if ($fi->attr('TYPE') == 'checks' || $fi->attr('TYPE') == 'select')
			$query['match'][$col] = Database::SqlAnd(Database::SqlIn($val));
		else if (preg_match('/([^.]+)\.(.*)/', $col, $ms))
			foreach ($this->fs[$col] as $ix => $v)
				$query['having'][] = " FIND_IN_SET($v, $ms[2]) > 0";
		else if ($fi->attr('TYPE') == 'date')
		{
			$vals = Server::GetVar($col);
			$query['match'][$col] = Database::SqlBetween(
				Database::TimestampToMySql(strtotime($vals[0])),
				Database::TimestampToMySql(strtotime($vals[1]))
			);
		}
		else $query['match'][$col] = Database::SqlLike('%'.$val.'%');
	}

	# Result Related

	/** @param Template $t Associated template */
	function TagResults($t, $g)
	{
		if (isset($this->count))
		{
			$t->ReWrite('result', array($this, 'TagResult'));
			$t->ReWrite('page', array($this, 'TagPage'));
			return $t->GetString($g);
		}
	}

	function TagResult($t, $g)
	{
		if (!empty($this->items) && !empty($this->_ds->DisplayColumns))
		{
			global $_d;
			$t->ReWrite('result_field', array($this, 'TagResultField'));
			$t->ReWrite('result_button', array($this, 'TagResultButton'));

			$ret = '';
			$start = $this->Behavior->ItemsPerPage *
				(Server::GetVar($this->Name.'_page', 1) - 1);
			for ($ix = 0; $ix < $this->Behavior->ItemsPerPage; $ix++)
			#foreach ($this->items as $ix => $i)
			{
				if ($start+$ix >= count($this->items)) break;
				$i = $this->items[$start+$ix];
				if (!empty($this->Callbacks->Result))
					U::RunCallbacks($this->Callbacks->Result, $t, $i);
				$this->item = $i;

				$t->Set('res_links', U::RunCallbacks(@$_d['datasearch.cb.head_res'], $this, $i));
				$t->Set('name', $i[$this->FieldName]);
				$t->Set('id', $i[$this->_ds->id]);
				$t->Set($i);
				$ret .= $t->GetString($g);
			}
			return $ret;
		}
		else if (isset($this->count)) return '<p>No results found!</p>';
	}

	function TagResultButton($t, $g)
	{
		$ret = null;
		$tButton = new Template();
		$tButton->ReWrite('a', array($this, 'TagButtonA'));
		foreach ($this->Behavior->Buttons as $text => $b)
		{
			$tButton->Set('text', $text);
			$this->but = $b;
			$ret .= $tButton->GetString($g);
		}
		return $ret;
	}

	function TagButtonA($t, $g, $a)
	{
		return '<a'.HM::GetAttribs(array_merge($this->but, $a)).'>'.$g.'</a>';
	}

	function TagResultField($t, $g)
	{
		$ret = '';
		$vp = new VarParser();
		foreach ($this->_ds->DisplayColumns as $f => $dc)
		{
			$vars['text'] = $dc->text;
			$vars['val'] = '';

			// Sub Table
			if (strpos($f, '.'))
			{
				$vs = explode(',', $this->item[$this->_ds->StripTable($f)]);

				foreach ($vs as $ix => $val)
				{
					if ($ix > 0) $vars['val'] .= ', ';

					if (!empty($this->fs[$f]))
					{
						$bold = array_search($val, $this->fs[$f]) ? true : false;
						if ($bold) $vars['val'] .= '<span class="result">';
					}
					if (!empty($val))
					{
						$vars['val'] .= $this->_ds->FieldInputs[$f]->atrs['VALUE'][$val]->text;
					}
					if (!empty($this->fs[$f]) && $bold) $vars['val'] .= '</span>';
				}
			}
			else // Standard column
			{
				$vars['val'] = !empty($dc->callback)
					? call_user_func($dc->callback, $this->_ds, $this->item, $f, $f)
					: $this->item[$this->_ds->StripTable($f)];
			}
			$ret .= $vp->ParseVars($g, $vars);
		}
		return $ret;
	}

	function TagPage($t, $g)
	{
		global $_d;

		$pages = $this->count / $this->Behavior->ItemsPerPage;
		$ret = null;
		$vp = new VarParser();
		for ($ix = 0; $ix < $pages; $ix++)
		{
			$vars['name'] = $this->Name;
			$vars['url'] = HM::URL($_d['app_abs'].'/'.$this->Name
				.'/search', array_merge($_GET, $_POST));
			$vars['num'] = $ix+1;
			$ret .= $vp->ParseVars($g, $vars);
		}
		return $ret;
	}

	# Edit Related

	function TagEditForm($t, $g, $a)
	{
		return '<form'.HM::GetAttribs($a).'>'.
		'<input type="hidden" name="form['.$this->_ds->id.']" value="'
			.$this->ci.'" />'.$g.'</form>';
	}
}

class EditorSearchBehavior
{
	/** @var Boolean */
	public $AllowEdit;

	public $ItemsPerPage = 5;

	function AllowAll()
	{
		$this->AllowEdit = true;
	}
}

?>
