<?php

require_once('xedlib/classes/data/data_set.php');

class Articles extends Module
{
	public $Block = 'articles';
	public $Name = 'articles';
	public $Table = 'news';
	public $ID = 'nws_id';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = Module::L('article/articles.xml');
		$this->_map = array();

		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Table, $this->ID);
	}

	function Get()
	{
		$t = new Template($GLOBALS['_d']);
		$t->ReWrite('articles', array($this, 'TagArticles'));
		$t->Set('foot', @$this->_foot);
		$t->Behavior->Bleed = false;
		return array($this->Table => $t->ParseFile($this->_template));
	}

	function TagArticle($t, $g)
	{
		$vp = new VarParser();

		// $this->_map = array('column' => val/callback);

		foreach ($this->_map as $k => $v)
		{
			if (is_array($v))
				$this->_article = call_user_func($v, $this->_article);
			else $this->_article[$k] = $this->_article[$v];
		}
		$this->_article['target'] = $this->Table;
		return $vp->ParseVars($g, $this->_article);
	}

	function TagArticles($t, $g)
	{
		$t->ReWrite('article', array($this, 'TagArticle'));
		if (isset($this->_source))
			$this->_articles = $this->_source->Get();
		if (!empty($this->_articles))
		{
			$ret = '';
			foreach ($this->_articles as $a)
			{
				$this->_article = $a;
				$this->_article['date'] = Date('M d, Y', Database::MyDateTimestamp($a['nws_date']));
				$ret .= $t->GetString($g);
			}
			return $ret;
		}
	}
}

class Article extends Module
{
	public $Block = 'article';
	public $Name = 'news';
	protected $ID = 'nws_id';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = Module::L('article/article.xml');
		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);

		$this->CheckActive($this->Name);
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		$t->ReWrite('newsdetail', array(&$this, 'TagNewsDetail'));
		return $t->ParseFile($this->_template);
	}

	function TagNews($t, $g)
	{
		global $_d;

		if (empty($_d['q'][1]))
		{
			$items = $this->_source->Get();
			$vp = new VarParser();
			$ret = null;
			foreach ($items as $i) $ret .= $vp->ParseVars($g, $i);
			return $ret;
		}
	}

	function TagNewsDetail($t, $g)
	{
		global $_d;

		$ci = @$_d['q'][1];

		if (!empty($ci))
		{
			$query = array('match' => array($this->ID => $ci));
			$item = $this->_source->GetOne($query);
			$vp = new VarParser();
			return $vp->ParseVars($g, $item);
		}
	}
}

class ArticleAdmin extends Module
{
	/**
	* Associated news editor.
	*
	* @var EditorData
	*/
	private $edNews;

	public $Name = 'news';
	protected $ID = 'nws_id';
	protected $NavAdmin = 'Admin/News';

	function Auth() { return User::RequireAccess(1); }

	function __construct()
	{
		require_once('xedlib/modules/editor_data/editor_data.php');
		global $_d;

		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);

		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d, $me;

		if (!User::RequireAccess(1)) return;

		$_d['nav.links'][$this->NavAdmin] = '{{app_abs}}/'.$this->Name;
	}

	function Prepare()
	{
		global $_d;
		if (!User::RequireAccess(1)) return;

		if (empty($this->_source->Description))
			$this->_source->Description = 'Article';
		if (empty($this->_source->DisplayColumns))
			$this->_source->DisplayColumns = array(
				'nws_title' => new DisplayColumn('Title')
			);
		if (empty($this->_source->FieldInputs))
			$this->_source->FieldInputs = array(
				'nws_date' => new FormInput('Date', 'date'),
				'nws_title' => new FormInput('Title'),
				'nws_body' => new FormInput('Body', 'area', null, null,
					array('rows' => 10, 'width' => "100%"))
			);

		global $me;
		$this->edNews = new EditorData($this->Name, $this->_source);
		$this->edNews->Behavior->Search = false;
		$this->edNews->Behavior->Target = Module::P($this->Name);
		$this->edNews->Prepare();
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;
		if (!User::RequireAccess(1)) return;

		return $this->edNews->Get();
	}
}

?>
