<?php

require_once(dirname(__FILE__).'/../user/user.php');

class ModProfile extends Module
{
	function Auth()
	{
		return ModUser::RequireAccess(0);
	}

	function Link()
	{
		global $_d;

		$_d['nav.links']['Profile'] = '{{app_abs}}/profile';
		$this->CheckActive('profile');
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d, $mods, $dsUser;

		if (@$_d['q'][1] == 'update')
		{
			$fields = $_d['profile.source']->fields;
			$p = Server::GetVar('profile');

			foreach ($p as $n => $v)
			{
				if (!isset($fields[$n])) continue;

				$c = $fields[$n]['column'];
				$t = isset($fields[$n]['type']) ? $fields[$n]['type'] : 'text';
				if ($t == 'password')
				{
					if (empty($v)) continue;
					$up[$c] = MD5($v);
				}
				else $up[$c] = $v;
			}

			$_d['profile.source']->dsUser->Update(array(
				'usr_id' => $_d['user']['usr_id']), $up);
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		$t->ReWrite('field', array(&$this, 'TagField'));
		return $t->ParseFile(Module::L('profile/profile.xml'));
	}

	function TagField($t, $g)
	{
		global $mods, $_d;

		$vp = new VarParser();
		$ret = '';
		$user = $_d['user'];
		if (empty($user)) return;

		foreach ($_d['profile.source']->fields as $f['name'] => $f)
		{
			$f['value'] = $user[$f['column']];

			if (@is_array($f['type']))
			{
				$cmp = 'in_array';
				$f['itype'] = 'text';
			}
			else
			{
				$cmp = 'strmatch';
				$f['itype'] = isset($f['type']) ? $f['type'] : 'text';
			}

			if ($f['itype'] == 'password') $f['value'] = '';

			$ret .= $vp->ParseVars($g, $f);

			if ($cmp('password', @$f['type']))
			{
				$f['itype'] = $f['type'];
				$f['text'] = 'Repeat '.$f['text'];
				$f['name'] .= '2';
				$ret .= $vp->ParseVars($g, $f);
			}
		}
		return $ret;
	}
}

Module::Register('ModProfile');

?>
