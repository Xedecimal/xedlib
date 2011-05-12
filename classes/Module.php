<?php

require_once(dirname(__FILE__).'/present/Template.php');

class Module
{
	static function Initialize($root_path, $repath = false)
	{
		global $_d;

		if ($repath)
		{
			$_d['template.transforms']['link'] = array('Module', 'TransPath', 'HREF');
			$_d['template.transforms']['a'] = array('Module', 'TransPath', 'HREF');
			$_d['template.transforms']['img'] = array('Module', 'TransPath', 'SRC');
			$_d['template.transforms']['script'] = array('Module', 'TransPath', 'SRC');
			$_d['template.transforms']['form'] = array('Module', 'TransPath', 'ACTION');
		}

		$_d['xl_dir'] = realpath(dirname(__FILE__).'/../');
		$_d['xl_abs'] = Server::GetRelativePath($_d['xl_dir']);
		$_d['app_dir'] = $root_path;
		if (!isset($_d['app_abs']))
			$_d['app_abs'] = Server::GetRelativePath($root_path);
		$_d['q'] = explode('/', $GLOBALS['rw'] =
			Server::GetVar('rw', isset($_d['default']) ? $_d['default'] : ''));

		if (!file_exists('modules')) return;
		$dp = opendir('modules');
		while ($f = readdir($dp))
		{
			$p = 'modules/'.$f;
			if ($f[0] == '.') continue;
			if (is_dir($p) && file_exists($p.'/'.$f.'.php'))
				require_once($p.'/'.$f.'.php');
			else if (File::ext($p) == 'php') require_once($p);
		}
		closedir($dp);
	}

	/**
	* 
	*
	* @param string $name Class name of defined module class.
	* @param array $deps Depended modules eg. array('ModName', 'ModName2')
	*/
	static function Register($name, $deps = null)
	{
		global $_d;
		if (!empty($_d['module.disable'][$name])) return;

		if (!empty($deps))
			foreach ($deps as $n => $dep)
			{
				if (!empty($_d['module.disable'][$n])) continue;
				require_once(Module::L($dep));
			}

		if (!empty($_d['module.enable']) && empty($_d['module.enable'][$name]))
			return;

		$mod = new $name(file_exists('settings.ini'));
		$GLOBALS['mods'][$name] = $mod;
	}

	static function TransPath($a, $t)
	{
		if (isset($a[$t[0]])) $a[$t[0]] = Module::P($a[$t[0]]);
		return $a;
	}

	static function Run($template)
	{
		return Module::RunString(file_get_contents($template));
	}

	static function RunString($string)
	{
		global $_d;

		$tprep = new Template($_d);
		$tprep->ReWrite('block', array('Module', 'TagPrepBlock'));
		$tprep->GetString($string);
		$_d['blocks']['hidden'] = null;

		$t = new Template($_d);
		$t->ReWrite('head', array($t, 'TagAddHead'));
		$t->ReWrite('block', array('Module', 'TagBlock'));

		global $mods;

		if (!empty($mods))
		{
			if (!empty($_d['module.disable']))
				foreach (array_keys($_d['module.disable']) as $m)
					unset($mods[$m]);

			uksort($mods, array('Module', 'cmp_mod'));
			U::RunCallbacks(@$_d['index.cb.prelink']);

			foreach ($mods as $n => $mod)
				if (!$mod->Auth()) unset($mods[$n]);
			foreach ($mods as $n => $mod) $mod->Link();
			foreach ($mods as $n => $mod) $mod->Prepare();
			foreach ($mods as $n => $mod)
			{
				$ret = $mod->Get();
				if (is_array($ret))
					foreach ($ret as $b => $d)
						Module::AddToBlock($b, $d);
				else
					Module::AddToBlock($mod->Block, $ret);
			}
		}

		$t = new Template($_d);
		$t->ReWrite('block', array('Module', 'TagBlock'));
		return $t->GetString($string);
	}

	static function AddToBlock($block, $data)
	{
		global $_d;

		if (array_key_exists($block, $_d['blocks']))
			$_d['blocks'][$block] .= $data;
		else
			$_d['blocks']['default'] .= $data;
	}

	static function cmp_mod($x, $y)
	{
		global $_d;

		if (isset($_d['module.order'][$x], $_d['module.order'][$y]))
			return @$_d['module.order'][$x] < @$_d['module.order'][$y];
		return 0;
	}

	static function TagPrepBlock($t, $g, $a)
	{
		global $_d;
		if (!isset($_d['blocks'][$a['NAME']])) $_d['blocks'][$a['NAME']] = null;
	}

	static function TagBlock($t, $g, $a)
	{
		global $_d;
		return $_d['blocks'][$a['NAME']];
	}

	public $Block = 'default';
	/** @var boolean */
	public $Active;

	protected static $Name = 'module';

	function DataError($errno)
	{
		global $_d;

		//No such table - Infest this database.
		if ($errno == ER_NO_SUCH_TABLE)
		{
			global $mods;

			echo mysql_error();
			echo '<p style="font-weight: bold">Got no such table.
				Verifying database integrity.
				Expect many errors during this process.</p>';

			foreach ($mods as $name => $mod)
			{
				$mod->Install();
				#preg_match('/^mod(.*)/', strtolower($name), $m);
				#if (!file_exists('modules/'.$m[1].'.sql')) continue;
				#$queries = explode(';', file_get_contents('modules/'.$m[1].'.sql'));

				#foreach ($queries as $q)
				#{
				#	$q = trim($q);
				#	if (!empty($q)) $_d['db']->Query($q);
				#}
			}
			return true;
		}
	}

	function CheckActive($name)
	{
		$items = explode('/', $name);
		$this->Active = true;
		foreach ($items as $ix => $i)
			if (@$GLOBALS['_d']['q'][$ix] != $i)
				$this->Active = false;
	}

	function Auth() { return true; }

	/**
	* New Availability: DataSet
	* Overload Responsibility: Link your datasets with any other datasets.
	*/
	function Link() { }

	/**
	* New Availability: Links
	* Overload Responsibility: Base functionality, data manipulation,
	* callbacks, etc.
	*/
	function Prepare() { }

	/**
	 * Overload Responsibility: Return the display of your module.
	 */
	function Get() { }

	/**
	 * Overload Responsibility: Create your data set and initial data.
	 */
	function Install() { }

	/**
	 * Overload Responsibility: Return fields for the installer to gather from
	 * the user.
	 */
	function InstallFields(&$frm) { }

	function AddDataset($name, $ds) { $GLOBALS['_d']['datasets'][$name] = $ds; }
	
	/**
	 * Request a previously stored dataset.
	 *
	 * @param string $name Name of dataset to request.
	 * @return DataSet Named dataset requested.
	 */
	function GetDataSet($name) { return $GLOBALS['_d']['datasets'][$name]; }
	
	function GetDatabase() { return $GLOBALS['_d']['db']; }
	
	static function P($path)
	{
		// Only translate finished paths.
		if (preg_match('/{{/', $path)) return $path;

		global $_d;
		$abs = $_d['app_abs'];
		$dir = $_d['app_dir'];

		// Overloaded Path
		$tmp = @$_d['settings']['site_template'];
		if (!empty($tmp))
		{
			$opath = "$tmp/$path";
			if (file_exists($opath)) return "$abs/$opath";
		}

		// Absolute Override
		$apath = "$dir/$path";
		if (file_exists($apath)) return "$abs/$path";

		// Module Path
		$modpath = "$dir/modules/$path";
		if (file_exists($modpath)) return "$abs/modules/$path";

		// Xedlib Path
		$xedpath = dirname(__FILE__).'/'.$path;
		if (file_exists($xedpath))
			return Server::GetRelativePath(dirname(__FILE__)).'/'.$path;

		// Xedlib Module
		$xedmpath = realpath(dirname(__FILE__)."/../modules/$path");
		if (file_exists($xedmpath))
			return Server::GetRelativePath(realpath(dirname(__FILE__)
				.'/../modules/')).'/'.$path;

		return $path;
	}

	static function L($path)
	{
		global $_d;

		$ovrpath = @$_d['settings']['site_template'].'/'.$path;
		if (file_exists($ovrpath)) return "{$_d['app_dir']}/{$ovrpath}";
		$modpath = "{$_d['app_dir']}/modules/{$path}";
		if (file_exists($modpath)) return $modpath;
		$xmodpath = dirname(__FILE__)."/../modules/$path";
		if (file_exists($xmodpath)) return $xmodpath;
		$xedpath = dirname(__FILE__).'/../'.$path;
		if (file_exists($xedpath)) return $xedpath;
		return $path;
	}
}

?>
