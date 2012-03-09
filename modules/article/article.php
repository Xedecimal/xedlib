<?php

class ModArticles extends Module
{
	public $Block = 'articles';
	public $Name = 'articles';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = Module::L('article/articles.xml');
		$this->_map = array();
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
				$ret .= $t->GetString($g);
			}
			return $ret;
		}
	}

	function Get()
	{
		$t = new Template($GLOBALS['_d']);
		$t->ReWrite('articles', array($this, 'TagArticles'));
		$t->Set('foot', @$this->_foot);
		$t->Behavior->Bleed = false;
		return $t->ParseFile($this->_template);
	}
}

class ModArticle extends Module
{
	public $Block = 'article';
	public $Name = 'article';
	protected $ID = 'art_id';

	protected $_template;

	function __construct()
	{
		global $_d;
		$this->_template = Module::L('article/article.xml');
		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);
	}

	function Get()
	{
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

class ModArticleAdmin extends Module
{
	/**
	* Associated news editor.
	*
	* @var EditorData
	*/
	private $edNews;

	public $Name = 'news';
	protected $ID = 'id';

	function Auth() { return ModUser::RequireAccess(1); }

	function __construct()
	{
		require_once('xedlib/a_editor.php');
		global $_d;

		if (empty($this->_source))
			$this->_source = new DataSet($_d['db'], $this->Name, $this->ID);

		$this->CheckActive($this->Name);
	}

	function Link()
	{
		global $_d, $me;

		if (!ModUser::RequireAccess(2)) return;
		$_d['nav.links']['News'] = '{{app_abs}}/'.$this->Name;
	}

	function Prepare()
	{
		global $_d;
		if (!ModUser::RequireAccess(1)) return;

		if (empty($this->_source->Description))
			$this->_source->Description = 'Articles';
		if (empty($this->_source->DisplayColumns))
			$this->_source->DisplayColumns = array(
				'title' => new DisplayColumn('Title')
			);
		if (empty($this->_source->FieldInputs))
			$this->_source->FieldInputs = array(
				'date' => new FormInput('Date', 'date'),
				'title' => new FormInput('Title'),
				'body' => new FormInput('Body', 'area', null, null,
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
		if (!ModUser::RequireAccess(1)) return;

		return $this->edNews->Get();
	}
}

?>
