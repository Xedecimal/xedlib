<?php

class ModUser extends Module
{
	/**
	 * @var LoginManager Associated login manager.
	 * 
	 */
	private $lm;

	private $_ds = array();

	public $Block = 'user';

	public $UserVar = 'user_sessuser';
	public $PassVar = 'user_sesspass';

	public $Name = 'user';
	public $User;

	static function RequireAccess($level)
	{
		if (empty($GLOBALS['mods']['ModUser']->User)) return false;
		if (@$GLOBALS['mods']['ModUser']->User['usr_access'] >= $level) return true;
		return false;
	}

	static function GetAccess()
	{
		return @$GLOBALS['_d']['cl']['usr_access'];
	}

	function __construct()
	{
		$this->Behavior = new ModUserBehavior();

		global $_d;

		require_once(dirname(__FILE__).'/../../h_data.php');

		$this->CheckActive('user');

		$_d['template.rewrites']['access'] = array('ModUser', 'TagAccess');
		$this->lm = new LoginManager('lmAdmin');
		if (isset($_d['user.encrypt']) && !$_d['user.encrypt'])
			$this->lm->Behavior->Encryption = false;
	}

	function Link()
	{
		global $_d;

		if (!empty($this->User))
		{
			global $rw;

			$url = http_build_query(array(
				$this->Name.'_action' => 'logout'
			));
			$_d['nav.links']['Log Out'] = "{$rw}?$url";
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

		if (@$_d['q'][1] == 'create')
		{
			if (!empty($_d['user.disable_signup'])) return;
			$t = new Template();
			$ret['default'] = $t->ParseFile(l('user/signup.xml'));
		}

		# Nobody is logged in.

		else if (empty($this->User) && !empty($_d['user.login']))
		{
			$t = new Template();
			$t->ReWrite('links', array(&$this, 'TagLinks'));
			$t->Rewrite('user', array(&$this, 'TagUser'));
			$t->Set('name', $this->Name);
			$ret['user'] = $t->ParseFile(l('user/login.xml'));
		}

		return $ret;
	}

	function TagUser($t, $g)
	{
		if (!isset($this->_ds[0])) return;
		if (is_string($this->_ds[0][0])) return;
		return $g;
	}

	function TagLinks()
	{
		if (!isset($this->_ds[0])) return;
		if (is_string($this->_ds[0][0])) return;

		$nav = new TreeNode();
		if ($this->Behavior->CreateAccount) $nav->AddChild(new TreeNode(
			'Create an account', '{{app_abs}}/user/create'));
		if ($this->Behavior->ForgotPassword) $nav->AddChild(new TreeNode(
			'Forgot your password?', '{{app_abs}}/user/forgot-password'));
		if ($this->Behavior->ForgotUsername) $nav->AddChild(new TreeNode(
			'Forgot your username?', '{{app_abs}}/user/forgot-username'));
		return ModNav::GetLinks($nav);
	}

	function AddDataset($ds, $passcol, $usercol)
	{
		$this->_ds[] = array($ds, $passcol, $usercol);
	}

	function Authenticate()
	{
		global $_d;

		# Process state changes

		$check_user = GetVar($this->UserVar);
		$check_pass = GetVar($this->PassVar);
		$act = GetVar($this->Name.'_action');

		if ($act == 'login')
		{
			$check_user = SetVar($this->UserVar, GetVar('user'));
			$check_pass = SetVar($this->PassVar, md5(GetVar('pass')));
		}
		if ($act == 'logout')
		{
			$check_pass = null;
			UnsetVar($this->PassVar);
		}
		if (@$_d['q'][1] == 'create' && @$_d['q'][2] == 'complete')
		{
			$this->ds->Add(array(
				'usr_name' => GetVar('su_user'),
				'usr_pass' => md5(GetVar('su_pass'))
			));
		}

		# Check existing data sources

		foreach ($this->_ds as $ds)
		{
			if (!isset($ds[0]))
				Error("<br />What: Dataset is not set.
				<br />Who: ModUser::Prepare()
				<br />Why: You may have set an incorrect dataset in the
				creation of this LoginManager.");

			# Simple login.

			$item = null;

			if (is_string($ds[0]))
			{
				if (strlen($ds[0]) != 32)
					die('Plaintext pass, use: '.md5($ds[0]));
				if ($ds[0] === $check_pass) $item = $ds[1];
			}

			# Database login

			else
			{
				$query['match'] = array(
					$ds[1] => $check_pass,
					$ds[2] => SqlAnd($check_user)
				);

				$item = $ds[0]->GetOne($query);
			}

			$this->User = $item;
		}

		return $this->User;
	}

	static function TagAccess($t, $g, $a)
	{
		global $_d;
		if (isset($_d['cl']) && $a['REQUIRE'] > @$_d['cl']['usr_access']) return;
		return $g;
	}
}

class ModUserBehavior
{
	public $CreateAccount = true;
	public $ForgotPassword = true;
	public $ForgotUsername = true;
	public $Password = true;
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

	function Auth() { return false; }

	function Link()
	{
		global $_d;

		if (ModUser::RequireAccess(2))
			$_d['nav.links']['Users'] = '{{app_abs}}/user';
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
