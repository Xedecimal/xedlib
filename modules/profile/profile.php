<?php

class ModProfile extends Module
{
	function __construct()
	{
		$this->CheckActive('profile');
	}

	function Auth()
	{
		return ModUser::RequireAccess(0);
	}

	function Link()
	{
		global $_d;
		
		$_d['nav.links']['Profile'] = '{{app_abs}}/profile';
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d, $mods, $dsUser;

		if (@$_d['q'][1] == 'update')
		{
			$fields = $mods['ModUser']->fields;
			$p = GetVar('profile');

			foreach ($p as $n => $v)
			{
				if (!isset($fields[$n])) continue;

				$c = $fields[$n]['column'];
				$t = isset($fields[$n]['type']) ? $fields[$n]['type'] : 'text';
				if ($t == 'password') $up[$c] = MD5($v);
				else $up[$c] = $v;
			}


			$dsUser->Update(array('usr_id' => $mods['ModUser']->User['usr_id']),
				$up);
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$t = new Template();
		$t->ReWrite('field', array(&$this, 'TagField'));
		return $t->ParseFile(l('profile/profile.xml'));
	}

	function TagField($t, $g)
	{
		global $mods;

		$vp = new VarParser();
		$ret = '';
		$user = $mods['ModUser']->User;

		foreach ($mods['ModUser']->fields as $f['name'] => $f)
		{
			$f['value'] = $user[$f['column']];

			if (@is_array($f['type']))
				{ $cmp = 'in_array'; $f['itype'] = 'text'; }
			else
				{ $cmp = 'strmatch'; $f['itype'] = isset($f['type'])
				? $f['type'] : 'text'; }

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
