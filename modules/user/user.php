<?php

class ModUser extends Module
{
	/**
	 * @var LoginManager Associated login manager.
	 * 
	 */
	private $lm;

	public $_ds = array();

	public $Block = 'user';

	public $UserVar = 'user_sessuser';
	public $PassVar = 'user_sesspass';

	public $Name = 'user';
	public $User;

	public $fields = array(
		'user' => array(
			'column' => 'usr_user',
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
		)
	);

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

		#$this->lm = new LoginManager('lmAdmin');
		#if (isset($_d['user.encrypt']) && !$_d['user.encrypt'])
		#	$this->lm->Behavior->Encryption = false;
	}

	function Link()
	{
		global $_d;

		if (!empty($this->User) && empty($_d['user.hide_logout']))
		{
			global $rw;

			$url = http_build_query(array(
				$this->Name.'_action' => 'logout'
			));
			$_d['nav.links']['Log Out'] = "{$rw}?$url";
		}
	}

	function Prepare()
	{
		global $_d;

		# Create Account

		if (@$_d['q'][1] == 'create' && @$_d['q'][2] == 'complete')
		{
			foreach ($this->fields as $f['name'] => $f)
			{
				$add[$f['column']] = GetVar($f['name'].'_create');
				if (@$f['type'] == 'password') $add[$f['column']] = md5($add[$f['column']]);
			}
			foreach ($this->_ds as $ds)
			{
				$ds[0]->Add($add);
			}
		}

		# Forgot Password

		if (@$_d['q'][1] == 'forgot-password' && @$_d['q'][2] == 'complete')
		{
			$em = GetVar('email');
			$q['match']['usr_email'] = $em;
			$act = $this->ds->Get($q);
			if (!empty($act))
			{
				$pass = random_string();
				$body = "Your new password is: $pass";
				$this->ds->update(array('usr_email' => $em),
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

		if (@$_d['q'][1] == 'create')
		{
			if (!empty($_d['user.disable_signup'])) return;
			$t = new Template();
			$t->ReWrite('field', array(&$this, 'TagFieldCreate'));
			$ret['default'] = $t->ParseFile(l('user/signup.xml'));
		}

		# Forgot Password

		if (@$_d['q'][1] == 'forgot-password')
		{
			$t = new Template();
			$t->ReWrite('field', array(&$this, 'TagFieldForgot'));
			$ret['default'] = $t->ParseFile(l('user/forgot.xml'));
		}

		# Nobody is logged in.

		else if (empty($this->User) && !empty($_d['user.login']))
		{
			$t = new Template();
			$t->ReWrite('links', array(&$this, 'TagLinks'));
			$t->Rewrite('field', array(&$this, 'TagFieldLogin'));
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

		if ($this->Behavior->CreateAccount)
			$nav['Create an account'] = '{{app_abs}}/user/create';
		if ($this->Behavior->ForgotPassword)
			$nav['Forgot your password?'] = '{{app_abs}}/user/forgot-password';
		return ModNav::GetLinks(ModNav::LinkTree($nav));
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
			'usr_email' => new DisplayColumn('Email'),
			'usr_access' => new DisplayColumn('Access', 'socallback')
		);
		$modUser->_ds[0][0]->FieldInputs = array(
			'usr_email' => new FormInput('Email'),
			'usr_pass' => new FormInput('Password', 'password'),
			'usr_access' => new FormInput('Access', 'select', null,
				ArrayToSelOptions($_d['user.levels']))
		);

		$this->edUser = new EditorData('user', $mods['ModUser']->_ds[0][0]);
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
