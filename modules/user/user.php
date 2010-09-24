<?php

class ModUser extends Module
{
	/**
	 * @var LoginManager Associated login manager.
	 * 
	 */
	private $lm;

	public $Block = 'user';

	public $UserVar = 'user_sessuser';
	public $PassVar = 'user_sesspass';

	public $DataSets = null;

	/**
	 * @var DataSet User dataset
	 */
	public $ds;

	static function RequireAccess($level)
	{
		if (@$GLOBALS['mods']['ModUser']->User['usr_access'] >= $level) return true;
		return false;
	}

	static function GetAccess()
	{
		return @$GLOBALS['_d']['cl']['usr_access'];
	}

	function __construct()
	{
		global $_d;

		require_once(dirname(__FILE__).'/../../h_data.php');

		$this->CheckActive('user');

		if (empty($this->ds))
			$this->ds = new DataSet($_d['db'], 'user', 'usr_id');
		$_d['template.rewrites']['access'] = array('ModUser', 'TagAccess');
		$this->lm = new LoginManager('lmAdmin');
		if (isset($_d['user.encrypt']) && !$_d['user.encrypt'])
			$this->lm->Behavior->Encryption = false;

		$this->DataSets[] = array($this->ds, 'usr_pass', 'usr_name');
	}

	function Prepare()
	{
		global $_d;

		# Process state changes

		$check_user = GetVar($this->UserVar);
		$check_pass = GetVar($this->PassVar);

		if ($_d['q'][0] == 'user')
		{
			if ($_d['q'][1] == 'login')
			{
				$check_user = SetVar($this->UserVar, GetVar('user'));
				$check_pass = SetVar($this->PassVar, md5(GetVar('pass')));
			}
			if ($_d['q'][1] == 'logout')
			{
				$check_pass = null;
				UnsetVar($this->PassVar);
			}
			if (@$_d['q'][1] == 'signup' && @$_d['q'][2] == 'complete')
			{
				$this->ds->Add(array(
					'usr_name' => GetVar('su_user'),
					'usr_pass' => md5(GetVar('su_pass'))
				));
			}
		}

		# Check existing data

		foreach ($this->DataSets as $ds)
		{
			if (!isset($ds[0]))
				Error("<br />What: Dataset is not set.
				<br />Who: ModUser::Prepare()
				<br />Why: You may have set an incorrect dataset in the
				creation of this LoginManager.");

			$query['match'] = array(
				$ds[1] => $check_pass,
				$ds[2] => SqlAnd($check_user)
			);

			if (!empty($queryAdd))
				$query = array_merge_recursive($query, $queryAdd);

			if (!empty($conditions))
				$match = array_merge($query['match'], $conditions);

			$item = $ds[0]->GetOne($query);
			$this->User = $item;
		}

		if (!empty($this->User))
		{
			$q = GetVar('q');
			$_d['nav.links']->AddChild(new TreeNode('Log Out', '{{app_abs}}/user/logout'));
		}
	}

	function Link()
	{
		global $_d, $me;

		if (ModUser::RequireAccess(1) && empty($_d['user.hide_logout']))
		{
			varinfo($_d['user.hide_logout']);
			$q = GetVar('q');
			$_d['nav.links']->AddChild(new TreeNode('Log Out',
				"{{app_abs}}/{$this->lm->Name}/logout?{$this->lm->Name}_return=$q"));
		}
	}

	function Get()
	{
		global $_d;

		if (!empty($_d['user.pages']))
		{
			$q = $_d['q'];
			$p = array_pop($q);
			if (array_search($p, $_d['user.pages']) === false) return;
		}

		$ret = array();

		# Presentation

		if (@$_d['q'][1] == 'signup')
		{
			$t = new Template();
			$ret['default'] = $t->ParseFile(l('user/signup.xml'));
		}

		# Nobody is logged in.

		if (!empty($this->ds) && empty($this->User) && !empty($_d['user.login']))
		{
			$t = new Template();
			$ret['user'] = $t->ParseFile(l('user/login.xml'));
		}

		return $ret;
	}

	static function TagAccess($t, $g, $a)
	{
		global $_d;
		if (isset($_d['cl']) && $a['REQUIRE'] > @$_d['cl']['usr_access']) return;
		return $g;
	}
}

Module::Register('ModUser');

class ModUserAdmin extends Module
{
	/**
	* Associated data editor for the user table.
	*
	* @var EditorData
	*/
	private $edUser;

	function __construct()
	{
		global $_d, $mods;

		require_once(dirname(__FILE__).'/../../a_editor.php');
		require_once(dirname(__FILE__).'/../../h_display.php');

		if (empty($_d['user.levels']))
			$_d['user.levels'] = array(0 => 'Guest', 1 => 'User', 2 => 'Admin');

		$this->edUser = new EditorData('user', $mods['ModUser']->ds);
	}

	function Link()
	{
		global $_d;

		if (ModUser::RequireAccess(2))
			$_d['nav.links']->AddChild(new TreeNode('Users', '{{app_abs}}/user'));
	}

	function Prepare()
	{
		global $_d, $me;

		if (@$_d['q'][0] != 'user') return;

		global $mods;
		$modUser = $mods['ModUser'];

		$modUser->ds->Description = 'User';
		$modUser->ds->DisplayColumns = array(
			'usr_name' => new DisplayColumn('Name'),
			'usr_access' => new DisplayColumn('Access', 'socallback')
		);
		$modUser->ds->FieldInputs = array(
			'usr_name' => new FormInput('Name'),
			'usr_pass' => new FormInput('Password', 'password'),
			'usr_access' => new FormInput('Access', 'select', null,
				ArrayToSelOptions($_d['user.levels']))
		);
		$this->edUser->Behavior->Search = false;
		$this->edUser->Behavior->Target = $_d['app_abs'].$me.'/user';
		$this->edUser->Prepare();
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] != 'user') return;
		if (ModUser::RequireAccess(2)) return $this->edUser->GetUI();
	}
}

Module::Register('ModUserAdmin');

?>
