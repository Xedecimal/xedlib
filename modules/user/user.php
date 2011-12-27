<?php

require_once(dirname(__FILE__).'/../../classes/str.php');
require_once(dirname(__FILE__).'/../nav.php');

$_d['user.session.user'] = 'sess_user';
$_d['user.session.pass'] = 'sess_pass';
$_d['user.cols.access'] = 'usr_access';

class User extends Module
{
	/**
	 * @var LoginManager Associated login manager.
	 *
	 */
	public $Block = 'user';
	public $Name = 'user';

	public $fields = array(
		'user' => array(
			'column' => 'usr_name',
			'text' => 'Username',
			'type' => 'user'
		),
		'email' => array(
			'column' => 'usr_email',
			'text' => 'Email',
			'type' => 'email'
		),
		'pass' => array(
			'column' => 'usr_pass',
			'text' => 'Password',
			'type' => 'password'
		),
		'access' => array(
			'column' => 'usr_access',
			'default' => 1
		)
	);

	public $NavLogout = 'Log Out';

	function __construct()
	{
		$this->Behavior = new ModUserBehavior();
		$this->CheckActive($this->Name);

		global $_d;

		if ($this->Active) $_d['user.login'] = true;

		global $_d;
		$_d['user.session.user'] = 'user_sessuser';
		$_d['user.session.pass'] = 'user_sesspass';
		if (!isset($_d['user.cols.access']))
			$_d['user.cols.access'] = 'usr_access';
	}

	function Link()
	{
		global $_d;

		if (!empty($_d['user.user']) && empty($_d['user.hide_logout']))
		{
			global $rw;

			$url = http_build_query(array(
				'user_action' => 'logout'
			));
			$_d['nav.links'][$this->NavLogout] = "{$rw}?$url";
		}

		$_d['template.rewrites']['access'] = array('ModUser', 'TagAccess');
	}

	function Prepare()
	{
		global $_d;

		# Create Account

		if (@$_d['q'][1] == 'create' && @$_d['q'][2] == 'complete')
		{
			foreach ($this->fields as $f['name'] => $f)
			{
				$add[$f['column']] = Server::GetVar($f['name'].'_create', @$f['default']);
				if (@$f['type'] == 'password') $add[$f['column']] = md5($add[$f['column']]);
			}
			$this->dsUser->Add($add);

			$_d['user'] = $add;
		}

		# Forgot Password

		if (@$_d['q'][1] == 'forgot-password' && @$_d['q'][2] == 'complete')
		{
			$em = Server::GetVar('email');
			$q['match']['usr_email'] = $em;
			$act = $this->dsUser->Get($q);
			if (!empty($act))
			{
				$pass = Str::Random();
				$body = "Your new password is: $pass";
				$this->dsUser->Update(array('usr_email' => $em),
					array('usr_pass' => md5($pass)));
				mail($em, 'Forgotten Password', $body);
			}
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

		# Create Account

		if ($this->Active && @$_d['q'][1] == 'create')
		{
			if (!empty($_d['user.disable_signup'])) return;
			$t = new Template();

			if (@$_d['q'][2] == 'complete')
			{
				$t->Set($_d['user']);
				$ret['default'] =
					$t->ParseFile(Module::L('user/signup_complete.xml'));
			}
			else
			{
				$t->ReWrite('field', array(&$this, 'TagFieldCreate'));
				$ret['default'] = $t->ParseFile(Module::L('user/signup.xml'));
			}
		}

		# Forgot Password

		if ($this->Active && @$_d['q'][1] == 'forgot-password')
		{
			if (!empty($_d['user.disable_signup'])) return;
			$t = new Template();
			$t->ReWrite('field', array(&$this, 'TagFieldForgot'));
			$ret['default'] = $t->ParseFile(Module::L('user/forgot.xml'));
		}

		# Nobody is logged in.

		if (empty($_d['user.user']) && @$_d['user.login'])
		{
			$t = new Template();
			$t->ReWrite('links', array(&$this, 'TagLinks'));
			$t->Rewrite('field', array(&$this, 'TagFieldLogin'));
			//$t->Set('name', $this->Name);
			$ret['user'] = $t->ParseFile(Module::L('user/login.xml'));
		}

		return $ret;
	}

	function Simple($pass)
	{
		global $_d;

		unset($this->fields['user']);
		$this->Behavior->CreateAccount = false;
		$this->Behavior->ForgotPassword = false;
		$_d['user.datasets'][] = array($pass, array('usr_access' => 1));
	}

	function TagUser($t, $g)
	{
		if (!isset($this->_ds[0])) return;
		if (is_string($this->_ds[0][0])) return;
		return $g;
	}

	function TagLinks()
	{
		if ($this->Behavior->CreateAccount)
			$nav['Create an account'] = "{{app_abs}}/{$this->Name}/create";
		if ($this->Behavior->ForgotPassword)
			$nav['Forgot your password?'] = "{{app_abs}}/{$this->Name}/forgot-password";
		if (!empty($nav))
			return ModNav::GetLinks(ModNav::LinkTree($nav));
	}

	function TagFieldLogin($t, $g)
	{
		$vp = new VarParser();
		$ret = null;
		foreach ($this->fields as $v['name'] => $v)
		{
			if (@is_array($v['type'])) { $cmp = 'in_array'; $v['itype'] = 'text'; }
			else { $cmp = 'strmatch'; $v['itype'] = isset($v['type']) ? $v['type'] : 'text'; }

			if ($cmp('user', @$v['type']))
			{
				$v['itype'] = 'text';
				$v['name'] = 'user';
				$ret .= $vp->ParseVars($g, $v);
			}
			else if ($cmp('password', @$v['type'])) $ret .= $vp->ParseVars($g, $v);
		}
		return $ret;
	}

	function TagFieldCreate($t, $g)
	{
		$vp = new VarParser();

		$ret = null;
		foreach ($this->fields as $v['name'] => $v)
		{
			if (empty($v['text'])) continue;

			$v['name'] .= '_create';

			if (@is_array($v['type'])) { $cmp = 'in_array'; $v['itype'] = 'text'; }
			else { $cmp = 'strmatch'; $v['itype'] = isset($v['type']) ? $v['type'] : 'text'; }

			$ret .= $vp->ParseVars($g, $v);

			if ($cmp('password', @$v['type']))
			{
				$v['itype'] = $v['type'];
				$v['text'] = 'Repeat '.$v['text'];
				$v['name'] .= '2';
				$ret .= $vp->ParseVars($g, $v);
			}
		}
		return $ret;
	}

	function TagFieldForgot($t, $g)
	{
		$vp = new VarParser();

		foreach ($this->fields as $v['name'] => $v)
		{
			if (@is_array($v['type'])) { $cmp = 'in_array'; $v['itype'] = 'text'; }
			else { $cmp = 'strmatch'; $v['itype'] = isset($v['type']) ? $v['type'] : 'text'; }
			if ($cmp('email', @$v['type'])) $ret = $vp->ParseVars($g, $v);
		}
		return $ret;
	}

	static function RequireAccess($level)
	{
		global $_d;
		if (@$_d['user.user'][$_d['user.cols.access']] >= $level) return true;
		return false;
	}

	static function GetAccess()
	{
		return @$GLOBALS['_d']['cl']['usr_access'];
	}

	static function TagAccess($t, $g, $a)
	{
		global $_d;
		if (@$_d['user'][$_d['user.cols.access']] >= @$a['REQUIRE']) return $g;
	}

	static function AddUserDataSet($ds, $passcol, $usercol)
	{
		global $_d;
		$_d['user.datasets'][] = array($ds, $passcol, $usercol);
	}

	static function Authenticate()
	{
		global $_d;

		# Process state changes

		$check_user = Server::GetVar($_d['user.session.user']);
		$check_pass = Server::GetVar($_d['user.session.pass']);
		$act = Server::GetVar('user_action');

		if ($act == 'login')
		{
			$check_user = Server::SetVar($_d['user.session.user'],
				Server::GetVar('user'));
			$check_pass = Server::SetVar($_d['user.session.pass'],
				md5(Server::GetVar('pass')));
		}
		if ($act == 'logout')
		{
			$check_pass = null;
			Server::UnsetVar($_d['user.session.pass']);
		}

		# Check existing data sources

		$u = null;

		if (!empty($_d['user.datasets']))
		foreach ($_d['user.datasets'] as $ds)
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
					$ds[2] => $check_user
				);

				$item = $ds[0]->GetOne($query);
			}

			$u = $_d['user.user'] = $item;
		}

		return $u;
	}
}

class ModUserBehavior
{
	public $CreateAccount = true;
	public $ForgotPassword = true;
	public $ForgotUsername = true;
	public $Password = true;
}

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

		if (empty($_d['user.levels']))
			$_d['user.levels'] = array(0 => 'Guest', 1 => 'User', 2 => 'Admin');
	}

	function Auth() { return ModUser::RequireAccess(2); }

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

		$modUser->_ds[0][0]->Description = 'User';
		$modUser->_ds[0][0]->DisplayColumns = array(
			'usr_name' => new DisplayColumn('Name'),
			'usr_access' => new DisplayColumn('Access', 'socallback')
		);
		$modUser->_ds[0][0]->FieldInputs = array(
			'usr_name' => new FormInput('Name'),
			'usr_pass' => new FormInput('Password', 'password'),
			'usr_access' => new FormInput('Access', 'select', null,
				ArrayToSelOptions($_d['user.levels']))
		);

		$this->edUser = new EditorData('user', $mods['ModUser']->_ds[0][0]);
		$this->edUser->Behavior->Search = false;
		$this->edUser->Behavior->Target = $_d['app_abs'].'/user';
		$this->edUser->Prepare();
	}

	function Get()
	{
		global $_d;

		if (@$_d['q'][0] != 'user') return;
		if (ModUser::RequireAccess(2)) return $this->edUser->GetUI();
	}
}

?>
