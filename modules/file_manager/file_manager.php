<?php

require_once(dirname(__FILE__).'/../../classes/file.php');
require_once(dirname(__FILE__).'/../../classes/file_info.php');
require_once(dirname(__FILE__).'/../../classes/hm.php');
require_once(dirname(__FILE__).'/../../classes/module.php');
require_once(dirname(__FILE__).'/../../classes/utility.php');
require_once(dirname(__FILE__).'/../../classes/present/form.php');
require_once(dirname(__FILE__).'/../../classes/present/template.php');

require_once(dirname(__FILE__).'/filter_default.php');
require_once(dirname(__FILE__).'/filter_gallery.php');

/**
 * @package File Management
 */

define('FM_SORT_MANUAL', -1);
define('FM_SORT_TABLE', -2);

define('FM_ACTION_UNKNOWN', 0);
define('FM_ACTION_CREATE', 5);
define('FM_ACTION_DELETE', 2);
define('FM_ACTION_DOWNLOAD', 8);
define('FM_ACTION_MOVE', 3);
define('FM_ACTION_REORDER', 7);
define('FM_ACTION_RENAME', 6);
define('FM_ACTION_UPDATE', 4);
define('FM_ACTION_UPLOAD', 1);

/**
 * Allows a user to administrate files from a web browser.
 */
class FileManager extends Module
{
	/**
	 * Name of this file manager.
	 *
	 * @var string
	 */
	public $Name = 'files';

	/**
	 * Behavior of this filemanager.
	 *
	 * @var FileManagerBehavior
	 */
	public $Behavior;

	/**
	 * Visibility properties.
	 *
	 * @var FileManagerView
	 */
	public $View;

	/**
	 * People are not allowed above this folder.
	 *
	 * @var string
	 */
	public $Root;

	/**
	 * Current File
	 *
	 * @var string
	 */
	private $cf;

	/**
	 * Default options for any file.
	 *
	 * @var array
	 */
	public $DefaultOptionHandler;

	/**
	 * An array of files and folders.
	 *
	 * @var array
	 */
	public $files;

	/**
	 * Whether or not mass options are available and should be output.
	 *
	 * @var bool
	 */
	private $mass_avail;

	/**
	* User id that can designate what files are available to the
	* current user.
	* @var int
	*/
	public $uid;

	/**
	 * Array of filters that are available for this object.
	 *
	 * @var array
	 */
	public $Filters = array('FilterDefault');

	public $FilterConfig = array();

	function __construct()
	{
		$this->Behavior = new FileManagerBehavior();
		$this->Behavior->Target = $this->Name;
		$this->View = new FileManagerView();
		$this->Template = dirname(__FILE__).'/file_manager.xml';
		$this->CheckActive($this->Name);
	}

	/**
	 * This must be called before Get. This will prepare for presentation.
	 *
	 * @param string $action Use Server::GetVar('ca') usually.
	 */
	function Prepare()
	{
		if (!$this->Active) return;

		$act = Server::GetVar($this->Name.'_action');
		$this->cf = Server::GetVar($this->Name.'_cf');

		# TODO: Only declare $fi once!

		if (!file_exists($this->Root.'/'.$this->cf)) $fi = new FileInfo($this->Root);
		else $fi = new FileInfo($this->Root.'/'.$this->cf);

		$f = FileManager::GetFilter($fi, $this->Root, $this->Filters);
		$f->FFPrepare($fi);

		//Don't allow renaming the root or the file manager will throw errors
		//ever after.
		if (empty($this->cf)) $this->Behavior->AllowRename = false;

		//Actions

		if ($act == 'Upload' && $this->Behavior->AllowUpload)
		{
			$fi = new FileInfo($this->Root.'/'.$this->cf);
			$filter = FileManager::GetFilter($fi, $this->Root, $this->Filters);

			// Completed chunked upload.
			if (Server::GetVar('cm') == 'done')
			{
				$target = Server::GetVar('cu');
				$ftarget = $this->Root.$this->cf.$target;
				$count = Server::GetVar('count'); // Amount of peices

				if (file_exists($ftarget)) unlink($ftarget);
				$fpt = fopen($ftarget, 'ab');
				for ($ix = 0; $ix < $count+1; $ix++)
				{
					$src = $this->Root.$this->cf.".[$ix]_".$target;
					fwrite($fpt, file_get_contents($src));
					unlink($src);
				}
				fclose($fpt);

				$filter->Upload($target, $fi);
				if (!empty($this->Behavior->Watchers))
					U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPLOAD,
						$fi->path.$target);
			}

			// Actual upload, full or partial.
			if (!empty($_FILES['cu']))
			foreach ($_FILES['cu']['name'] as $ix => $name)
			{
				$tname = $_FILES['cu']['tmp_name'][$ix];
				$target = $this->Root.'/'.$this->cf.'/'.$name;
				move_uploaded_file($tname, $target);

				if (!preg_match('#^\.\[[0-9]+\]_.*#', $name))
				{
					$filter->FFUpload($name, $fi);
					if (!empty($this->Behavior->Watchers))
						U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPLOAD,
							$target);
				}
			}
		}
		else if ($act == 'Save')
		{
			if (!$this->Behavior->AllowEdit) return;
			$info = new FileInfo($this->Root.'/'.$this->cf, $this->Filters);
			$newinfo = Server::GetVar('info');
			$f = FileManager::GetFilter($info, $this->Root, $this->Filters);
			$f->FFUpdated($this, $info, $newinfo);
			$this->Behavior->Update($newinfo);

			if (!empty($newinfo))
			{
				//Filter has been changed, we need to notify them.
				if (isset($newinfo['type']) && $f->Name != $newinfo['type'])
				{
					$f->Cleanup($info->path);
					$type = "Filter".$newinfo['type'];
					$newfilter = new $type($this);
					$newfilter->Install($info->path);
				}

				$info->info = array_merge($info->info, $newinfo);
				$info->SaveInfo();

				if (!empty($this->Behavior->Watchers))
					U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_UPDATE,
						$info->path);
			}
		}
		else if ($act == 'Update Captions') //Mass Captions
		{
			if (!$this->Behavior->AllowEdit) return;
			$caps = Server::GetVar($this->Name.'_titles');

			if (!empty($caps))
			foreach ($caps as $file => $cap)
			{
				$fi = new FileInfo($this->Root.'/'.$this->cf.'/'.$file, $this->Filters);
				$fi->info['title'] = $cap;
				$f = FileManager::GetFilter($fi, $this->Root, $this->Filters);
				$f->FFUpdated($this, $fi, $fi->info);
				$fi->SaveInfo();
			}
		}
		else if ($act == 'Rename')
		{
			if (!$this->Behavior->AllowRename) return;
			$fi = new FileInfo($this->Root.'/'.$this->cf, $this->Filters);
			$name = Server::GetVar($this->Name.'_rname');
			$f = FileManager::GetFilter($fi, $this->Root, $this->Filters);
			$f->Rename($fi, $name);
			$this->cf = substr($fi->path, strlen($this->Root)).'/';
			if (!empty($this->Behavior->Watchers))
				U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_RENAME,
					$fi->path.' to '.$name);
		}
		else if ($act == 'Delete')
		{
			if (!$this->Behavior->AllowDelete) return;
			$sels = Server::GetVar($this->Name.'_sels');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo(stripslashes($file), $this->Filters);
				$f = Filemanager::GetFilter($fi, $this->Root, $this->Filters);
				$break = false;
				if (!empty($this->Behavior->Watchers))
				{
					if (!U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_DELETE,
						$fi->path)) $break = true;
				}
				if (!$break)
				{
					$f->FFDelete($fi, $this->Behavior->Recycle);
					$types = Server::GetVar($this->Name.'_type');
					$this->files = $this->GetDirectory();
					$ix = 0;
				}
			}
		}
		else if ($act == 'Create')
		{
			if (!$this->Behavior->AllowCreateDir) return;
			$p = $this->Root.'/'.$this->cf.'/'.Server::GetVar($this->Name.'_cname');
			mkdir($p);
			chmod($p, 0755);
			FilterDefault::UpdateMTime($p);

			if (!empty($this->Behavior->Watchers))
				U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_CREATE, $p);
		}
		else if ($act == 'sort')
		{
			foreach ($_GET['indices'] as $ix => $path)
			{
				$fi = new FileInfo($path);
				$fi->info['index'] = $ix;
				$fi->SaveInfo();
			}
			die();
		}
		else if ($act == 'Move To')
		{
			$sels = Server::GetVar($this->Name.'_sels');
			$ct = Server::GetVar($this->Name.'_ct');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->Filters);
				$f = FileInfo::GetFilter($fi, $this->Root, $this->Filters);
				$f->Rename($fi, "$ct/{$fi->filename}");

				if (!empty($this->Behavior->Watchers))
					U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_MOVE,
						$fi->path . ' to ' . $ct);
			}
		}
		else if ($act == 'Copy To')
		{
			$sels = Server::GetVar($this->Name.'_sels');
			$ct = Server::GetVar($this->Name.'_ct');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->Filters);
				$f = FileInfo::GetFilter($fi, $this->Root, $this->Filters);
				$f->Copy($fi, $ct.$fi->filename);

				if (!empty($this->Behavior->Watchers))
					U::RunCallbacks($this->Behavior->Watchers, FM_ACTION_COPY,
						$fi->path . ' to ' . $ct);
			}
		}
		else if ($act == 'Link In')
		{
			$sels = Server::GetVar($this->Name.'_sels');
			$ct = Server::GetVar($this->Name.'_ct');
			if (!empty($sels))
			foreach ($sels as $file)
			{
				$fi = new FileInfo($file, $this->Filters);
				`ln -s "{$fi->path}" "{$ct}"`;
			}
		}
		else if ($act == 'Download Selected')
		{
			require_once('3rd/zipfile.php');
			$zip = new zipfile();
			$sels = Server::GetVar($this->Name.'_sels');
			$total = array();
			foreach ($sels as $s) $total = array_merge($total, Comb($s, '#^t_.*#'));

			$zip->AddFiles($total);

			$fname = pathinfo($this->Root.$this->cf);
			$fname = $fname['basename'].'.zip';

			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$fname.'"');
			header('Content-Transfer-Encoding: binary');
			echo $zip->file();
			die();
		}
		else if ($act == 'getfile')
		{
			$finfo = new FileInfo($this->Root.$this->cf);
			$size = filesize($finfo->path);

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Transfer-Encoding: binary");
			header("Content-Type: application/octet-stream");
			header("Content-Length: {$size}");
			header("Content-Disposition: attachment; filename=\"{$finfo->filename}\";" );
			set_time_limit(0);
			$fp = fopen($finfo->path, 'r');
			while ($out = fread($fp, 4096))	echo $out;
			die();
		}

		$this->cf = File::SecurePath(Server::GetState($this->Name.'_cf'));

		if (is_dir($this->Root.$this->cf)
			&& strlen($this->cf) > 0
			&& substr($this->cf, -1) != '/')
			$this->cf .= '/';

		# Append trailing slash.
		if (substr($this->Root, -1) != '/') $this->Root .= '/';

		# Verify that this root exists.
		/*if (!file_exists($this->Root.$this->cf))
		{
			Server::Error('Directory does not exist:'.$this->Root.$this->cf);
			$this->cf = '';
		}*/

		if (is_dir($this->Root.$this->cf)) $this->files = $this->GetDirectory();
	}

	static function GetIcon($f)
	{
		$icons = array(
			'folder' => Module::P('file_manager/icons/folder.png'),
			'png' => Module::P('file_manager/icons/image.png'),
			'jpg' => Module::P('file_manager/icons/image.png'),
			'jpeg' => Module::P('file_manager/icons/image.png'),
			'gif' => Module::P('file_manager/icons/image.png'),
			'pdf' => Module::P('file_manager/icons/acrobat.png'),
			'sql' => Module::P('file_manager/icons/db.png'),
			'xls' => Module::P('file_manager/icons/excel.png'),
			'doc' => Module::P('file_manager/icons/word.png'),
			'docx' => Module::P('file_manager/icons/word.png')
		);

		if (!empty($f->vars['icon'])) return $f->vars['icon'];
		else if (isset($icons[$f->type])) $ret = $icons[$f->type];
		else return null;
		return '<img src="'.$ret.'" alt="icon" style="vertical-align: middle" />';
	}

	function TagPart($t, $guts, $attribs)
	{
		$this->vars[$attribs['TYPE']] = $guts;
	}

	/**
	 * Returns the top portion of the file manager.
	 * * Path
	 * * Search
	 *
	 * @return string
	 */
	function TagHeader($t, $g, $a)
	{
		return $g;
	}

	function TagPath($t, $guts, $attribs)
	{
		$fi = new FileInfo($this->Root.$this->cf);
		$vp = new VarParser();
		$ret = null;
		$cpath = '';

		global $me;

		// Home Link

		if (isset($this->cf))
		{
			$d['uri'] = HM::URL($this->Behavior->Target,
				array($this->Name.'_cf' => ''));
			$d['name'] = $attribs['ROOT'];
			$ret .= $vp->ParseVars($guts, $d);
		}

		// Each path link

		$items = explode('/', substr($fi->path, strlen($this->Root)));

		global $me;
		for ($ix = 0; $ix < count($items); $ix++)
		{
			if (strlen($items[$ix]) < 1) continue;
			$cpath = (strlen($cpath) > 0 ? $cpath.'/' : null).$items[$ix];
			$uri = HM::URL($this->Behavior->Target,
				array($this->Name.'_cf' => $cpath));
			$ret .= ' '.$attribs['SEP'];
			$d['name'] = $items[$ix];
			$d['uri'] = $uri;
			$ret .= $vp->ParseVars($guts, $d);
		}
		return $ret;
	}

	function TagSearch($t, $g, $a)
	{
		if (!$this->Behavior->AllowSearch) return null;
		return $g;
	}

	function TagBehavior($t, $g, $a)
	{
		$names = explode(',', $a['TYPE']);
		foreach ($names as $name)
			if ($this->Behavior->$name) return $g;
	}

	function TagDownload($t, $g, $a)
	{
		if (!is_file($this->Root.$this->cf)) return;
		return $g;
	}

	function TagFolders($t, $g, $a)
	{
		if (!empty($this->files['folders'])) return $g;
	}

	function TagFolder($t, $g, $a)
	{
		if (is_file($this->Root.$this->cf)) return;

		$ret = '';
		$ix = 0;

		#@TODO Move this to a higher level so both TagFolder and TagFile can benfit from it.
		$fi = new FileInfo($this->Root.$this->cf);
		$this->curfilter = $filter = Filemanager::GetFilter($fi, $this->Root,
			$this->Filters);

		if (!empty($this->files['folders']))
		foreach ($this->files['folders'] as $f)
		{
			FileManager::GetFilter($f, $this->Root, $this->Filters, $f->dir);
			if (!$f->show) continue;
			if (!$this->GetVisible($f)) continue;

			$this->curfile = $f;

			global $me;
			if (isset($this->Behavior->FolderCallback))
			{
				$cb = $this->Behavior->FolderCallback;
				$vars = $cb($f);
				foreach ($vars as $k => $v)
				{
					$vars["{$this->Name}_$k"] = $v;
					unset($vars[$k]);
				}
				global $me;
				$this->vars['url'] = HM::URL($me, $vars);
			}
			else
				$this->vars['url'] = HM::URL($this->Behavior->Target,
					array($this->Name.'_cf' =>
					"{$this->cf}/{$f->filename}"));

			$this->vars['name'] = $f;
			$this->vars['caption'] = $this->View->GetCaption($f);
			$this->vars['filename'] = $f->filename;
			$this->vars['fipath'] = $f->path;
			$this->vars['type'] = 'folders';
			$this->vars['index'] = $ix;
			$this->vars['icon'] = $this->GetIcon($f);

			$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type=folders";

			$tfile = new Template($this->vars);
			$tfile->ReWrite('quickopt', array(&$this, 'TagQuickOpt'));
			$ret .= $tfile->GetString($g);

			$ix++;
		}
		return $ret;
	}

	function TagFiles($t, $g, $a)
	{
		if (!empty($this->files['files'])) return $g;
	}

	function TagFile($t, $g, $a)
	{
		global $me;

		if (is_file($this->Root.$this->cf)) return;
		$ret = '';
		$ix = 0;

		$fi = new FileInfo($this->Root.$this->cf);
		$this->curfilter = $filter = Filemanager::GetFilter($fi, $this->Root,
			$this->Filters);

		if (!empty($this->files['files']))
		foreach ($this->files['files'] as $f)
		{
			$filter->FFGetInfo($f);
			if (!$f->show) continue;

			$this->curfile = $f;

			if (isset($this->Behavior->FileCallback))
			{
				$cb = $this->Behavior->FileCallback;
				$vars = $cb($f, $this->cf.$f->filename);
				if (!empty($vars))
				foreach ($vars as $k => $v)
				{
					$vars["{$this->Name}_$k"] = $v;
					unset($vars[$k]);
				}
				global $me;
				$this->vars['url'] = HM::URL($me, $vars);
			}
			else if ($this->Behavior->UseInfo)
				$this->vars['url'] = HM::URL($this->Behavior->Target,
					array($this->Name.'_cf' => $this->cf.$f->filename));
			else
				$this->vars['url'] = htmlspecialchars($this->Root.$this->cf.'/'.$f->filename);
			$this->vars['filename'] = htmlspecialchars($f->filename);
			$this->vars['caption'] = $this->View->GetCaption($f);
			$this->vars['fipath'] = htmlspecialchars($f->path);
			$this->vars['type'] = 'files';
			$this->vars['index'] = $ix;
			if (!empty($f->icon)) $this->vars['icon'] = $f->icon;
			else $this->vars['icon'] = '';
			$this->vars['ftitle'] = isset($f->info['title']) ?
				@stripslashes($f->info['title']) : '';

			$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type=files";

			//Move Up

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL && $ix > 0)
			{
				$uriUp = $common."&amp;{$this->Name}_action=swap&amp;cd=up&amp;index={$ix}";
				$img = Server::GetRelativePath(dirname(__FILE__)).'/images/up.png';
				$this->vars['butup'] = "<a href=\"$uriUp\"><img src=\"{$img}\" ".
				"alt=\"Move Up\" title=\"Move Up\" /></a>";
			}
			else $this->vars['butup'] = '';

			//Move Down

			if ($this->Behavior->AllowSort && $this->Behavior->Sort == FM_SORT_MANUAL
				&& $ix < count($this->files['files'])-1)
			{
				$uriDown = $common."&amp;{$this->Name}_action=swap&amp;cd=down&amp;index={$ix}";
				$img = Server::GetRelativePath(dirname(__FILE__)).'/images/down.png';
				$this->vars['butdown'] = "<a href=\"$uriDown\"><img src=\"{$img}\" ".
				"alt=\"Move Down\" title=\"Move Down\" /></a>";
			}
			else $this->vars['butdown'] = '';

			$tfile = new Template($this->vars);
			$tfile->ReWrite('quickopt', array(&$this, 'TagQuickOpt'));
			$ret .= $tfile->GetString($g);

			$ix++;
		}
		return $ret;
	}

	function TagQuickOpt($t, $guts)
	{
		$file = $this->curfile;

		$d['opt'] = '';

		if ($this->Behavior->QuickCaptions)
		{
			$d['opt'] .= '<textarea name="'.$this->Name.'_titles['.$file->filename.
				']" rows="2" cols="30">'.
				@htmlspecialchars(stripslashes($file->info['title'])).
				'</textarea>';
		}

		$d['opt'] .= $this->curfilter->FFGetQuickOpts($file, $guts);

		return VarParser::Parse($guts, $d);
	}

	function TagDetails($t, $g, $a)
	{
		if (is_dir($this->Root.$this->cf)) return;
		$vp = new VarParser();
		$this->vars['date'] = gmdate("M j Y H:i:s ", filectime($this->Root.$this->cf));
		$this->vars['size'] = File::SizeToString(filesize($this->Root.$this->cf));
		return $vp->ParseVars($g, $this->vars);
	}

	function TagDirectory($t, $g, $a)
	{
		if (is_file($this->Root.$this->cf)) return;
		$vp = new VarParser();
		return $vp->ParseVars($g, $this->vars);
	}

	function TagCheck($t, $guts)
	{
		if ($this->mass_avail) return $guts;
	}

	function TagQuickOptFinal($t, $guts)
	{
		$ret = '';

		if ($this->Behavior->AllowEdit && $this->Behavior->QuickCaptions)
		{
			$ret .= '<input type="submit" name="'.$this->Name.'_action" value="Update Captions" />';
		}

		if (isset($this->curfilter))
			$ret .= $this->curfilter->FFGetQuickOptFinal(@$this->curfile);

		return $ret;
	}

	function TagOptions($t, $guts)
	{
		if ($this->Behavior->AllowMove ||
			$this->Behavior->AllowCreateDir ||
			$this->Behavior->AllowEdit ||
			$this->Behavior->AllowRename) return $guts;
	}

	function TagAddOpts($t, $guts)
	{
		$ret = '<table>';
		$vp = new VarParser();

		$fi = new FileInfo($this->Root.$this->cf);

		if (isset($this->DefaultOptionHandler))
		{
			$handler = $this->DefaultOptionHandler;
			$def = $handler($fi);
		}
		else $def = null;

		$f = FileManager::GetFilter($fi, $this->Root, $this->Filters, $fi->info);

		if ($this->Behavior->AllowSetType && count($this->Filters) > 1 && is_dir($fi->path))
		{
			$in = new FormInput('Change Type', 'select',
				'info[type]', FormOption::FromArray($this->Filters, $f->Name,
				false));
			$this->vars['text'] = $in->text;
			$this->vars['field'] = $in->Get($this->Name);
			$ret .= $vp->ParseVars($guts, $this->vars);
		}

		$options = $f->FFGetOptions($this, $fi, $def);
		$options = array_merge($options, $this->Behavior->GetOptions($fi));

		if (!empty($options))
		{
			foreach ($options as $field)
			{
				if (is_string($field))
				{
					$this->vars['text'] = '';
					$this->vars['field'] = $field;
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
				else if (is_array($field))
				{
					//This is a series of fields, only the first text matters
					//the rest can just be appended.
					$this->vars['text'] = $field[0]->text;
					$this->vars['field'] = '';
					foreach ($field as $f)
						$this->vars['field'] .= $f->Get($this->Name);
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
				else
				{
					$this->vars['text'] = $field->text;
					$this->vars['field'] = $field->Get($this->Name);
					$ret .= $vp->ParseVars($guts, $this->vars);
				}
			}
			if ($this->Behavior->UpdateButton)
			{
				$sub = new FormInput(null, 'submit', $this->Name.'_action', 'Save');
				$this->vars['text'] = '';
				$this->vars['field'] = $sub->Get($this->Name, false);
				$ret .= $vp->ParseVars($guts, $this->vars);
			}
		}
		return $ret.'</table>';
	}

	/**
	* Return the display.
	*
	* @param string $target Target script.
	* @param string $action Current action, usually stored in Server::GetVar('ca').
	* @return string Output.
	*/
	function Get()
	{
		if (!$this->Active) return;

		if (!file_exists($this->Root.$this->cf))
			$this->cf = '';

		$relpath = Server::GetRelativePath(dirname(__FILE__));

		$this->mass_avail = $this->Behavior->MassAvailable();

		//TODO: Get rid of this.
		$fi = new FileInfo($this->Root.$this->cf);

		global $me;
		$this->vars['target'] = $this->Behavior->Target;

		$ex = HM::ParseURL($this->Behavior->Target);
		$ex['args'][$this->Name.'_action'] = 'upload';
		$ex['args']['PHPSESSID'] = Server::GetVar('PHPSESSID');
		$this->vars['java_target'] = HM::URL($ex['url'], $ex['args']);

		$this->vars['root'] = $this->Root;
		$this->vars['cf'] = $this->cf;

		$this->vars['filename'] = $fi->filename;
		$this->vars['path'] = $this->Root.$this->cf;
		$this->vars['dirsel'] = $this->GetDirectorySelect($this->Name.'_ct');
		$this->vars['relpath'] = $relpath;
		$this->vars['host'] = Server::GetVar('HTTP_HOST');
		$this->vars['sid'] = Server::GetVar('PHPSESSID');
		$this->vars['behavior'] = $this->Behavior;

		$this->vars['folders'] = count($this->files['folders']);
		$this->vars['files'] = count($this->files['files']);

		$t = new Template();
		$t->Set($this->vars);

		#$t->ReWrite('form', array('Form', 'TagForm'));
		$t->ReWrite('header', array(&$this, 'TagHeader'));
		$t->ReWrite('path', array(&$this, 'TagPath'));
		$t->ReWrite('download', array(&$this, 'TagDownload'));
		$t->ReWrite('search', array(&$this, 'TagSearch'));

		$t->ReWrite('behavior', array(&$this, 'TagBehavior'));

		$t->ReWrite('details', array(&$this, 'TagDetails'));
		$t->ReWrite('directory', array(&$this, 'TagDirectory'));
		$t->ReWrite('folders', array(&$this, 'TagFolders'));
		$t->ReWrite('folder', array(&$this, 'TagFolder'));
		$t->ReWrite('files', array(&$this, 'TagFiles'));
		$t->ReWrite('file', array(&$this, 'TagFile'));
		$t->ReWrite('check', array(&$this, 'TagCheck'));
		$t->ReWrite('quickoptfinal', array(&$this, 'TagQuickOptFinal'));

		$t->ReWrite('options', array(&$this, 'TagOptions'));
		$t->ReWrite('addopts', array(&$this, 'TagAddOpts'));

		$fi = new FileInfo($this->Root.$this->cf);

		$t->Set('fn_name', $this->Name);
		$t->Set($this->View);

		$ret['head'] = '<script type="text/javascript"
				src="{{xl_abs}}/modules/file_manager/file_manager.js"></script>';
		$ret['default'] = $t->ParseFile($this->Template);

		return $ret;
	}

	/**
	 * Returns a tree selection of a directory mostly used for moving files.
	 *
	 * @param string $name Name of form item.
	 * @return string
	 */
	function GetDirectorySelect($name)
	{
		$ret = "<select name=\"{$name}\">";
		$dirs = File::Comb($this->Root, null, SCAN_DIRS);
		sort($dirs);
		foreach ($dirs as $d) $ret .= "<option value=\"{$d}\">{$d}</option>";
		$ret .= '</select>';
		return $ret;
	}

	/**
	 * Recurses a single item in a directory.
	 *
	 * @access private
	 * @param string $path Root path to recurse into.
	 * @param bool $ignore Don't include this path.
	 * @return string
	 */
	function GetDirectorySelectRecurse($path, $ignore)
	{
		if (!$ignore) $ret = "<option value=\"{$path}\">{$path}</option>";
		else $ret = '';
		$dp = opendir($path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (!is_dir($path.$file)) continue;
			$ret .= $this->GetDirectorySelectRecurse($path.$file.'/', false);
		}
		closedir($dp);
		return $ret;
	}

	/**
	 * Returns a series of files or folders.
	 *
	 * @param string $target Target filename of script using this.
	 * @param string $type files or dirs
	 * @param string $title Header
	 * @return string
	 * @deprecated This is no longer used.
	 */
	function GetFiles($target, $type)
	{
		$ret = '';
		if (!empty($this->files[$type]))
		{
			$cfi = new FileInfo($this->Root.$this->cf);
			$f = FileInfo::GetFilter($cfi, $this->Root, $this->Filters);
			$ret .= '<table class="tableFiles">';
			$end = false;
			if (count($this->files[$type]) > 1
				&& $this->View->Sort == FM_SORT_MANUAL
				&& $this->Behavior->AllowSort)
			{
				$ret .= '<tr><th>File</th>';
				$ret .= '<th colspan="2">Action</th>';
				$end = true;
			}
			if ($end) $ret .= '</tr>';
			$ix = 0;
			foreach($this->files[$type] as $file)
			{
				$f->FFGetInfo($file);
				if (!$file->show) continue;
				if (!$this->Behavior->ShowAllFiles)
					if (!$this->GetVisible($file)) continue;
				$ret .= $this->GetFile($target, $file, $type, $ix++);
			}
			$ret .= '</table>';
			if ($this->Behavior->MassAvailable())
				$ret .= "<input id=\"butSelAll{$type}\" type=\"button\"
					onclick=\"docmanSelAll('{$type}');\"
					value=\"Select all {$type}\" />";
		}
		return $ret;
	}

	/**
	 * Get a single file.
	 *
	 * @param string $target Target script to anchor to.
	 * @param FileInfo $file File information on this object.
	 * @param string $type files or dirs.
	 * @param int $index Index of this item in the parent.
	 * @return string Single row for the files table.
	 */
	function GetFile($file, $type, $index)
	{
		$d['class'] = $index % 2 ? 'even' : 'odd';

		$types = $file->type ? 'folders' : 'files';
		if (isset($file->icon))
			$d['icon'] = "<img src=\"".HM::URL($file->icon)."\" alt=\"Icon\" />";

		else
			$d['icon'] = '';

		$name = ($this->View->ShowTitle && isset($file->info['title'])) ?
			$file->info['title'] : $file->filename;

		$uri = "?editor={$this->Name}&amp;cf=".urlencode($this->cf.$file->filename);

		if ($this->mass_avail)
			$d['check'] = "\t\t<input type=\"checkbox\"
			id=\"sel_{$type}_{$index}\" name=\"sels[]\" value=\"{$file->path}\"
			onclick=\"toggleAny(['sel_files_', 'sel_dirs_'],
			'{$this->Name}_mass_options');\" />\n";
		else $d['check'] = '';
		$d['uri'] = $uri;
		$d['file'] = $name;
		$time = isset($file->info['mtime']) ? $file->info['mtime'] : filemtime($file->path);
		if ($this->View->ShowDate) $d['date'] = gmdate("m/d/y h:i", $time);

		$common = "?cf={$this->cf}&amp;editor={$this->Name}&amp;type={$types}";
		$uriUp = $common."&amp;ca=swap&amp;cd=up&amp;index={$index}";
		$uriDown = $common."&amp;ca=swap&amp;cd=down&amp;index={$index}";

		//Move Up

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index > 0)
		{
			$img = Server::GetRelativePath(dirname(__FILE__)).'/images/up.png';
			$d['butup'] = "<a href=\"$uriUp\"><img src=\"{$img}\" ".
			"alt=\"Move Up\" title=\"Move Up\" /></a>";
		}
		else $d['butup'] = '';

		//Move Down

		if ($this->Behavior->AllowSort
			&& $this->View->Sort == FM_SORT_MANUAL
			&& $index < count($this->files[$type])-1)
		{
			$img = Server::GetRelativePath(dirname(__FILE__)).'/images/down.png';
			$d['butdown'] = "<a href=\"$uriDown\"><img src=\"{$img}\" ".
			"alt=\"Move Down\" title=\"Move Down\" /></a>";
		}
		else $d['butdown'] = '';

		return $d;
	}

	/**
	 * Gets an array of files and directories in a directory.
	 *
	 * @return array
	 */
	function GetDirectory()
	{
		$dp = opendir($this->Root.'/'.$this->cf);
		$ret['files'] = array();
		$ret['folders'] = array();

		$foidx = $fiidx = 0;

		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;

			$newfi = new FileInfo($this->Root.$this->cf.'/'.$file, $this->Filters);
			if (!$newfi->show) continue;
			if (is_dir($this->Root.$this->cf.'/'.$file))
			{
				if ($this->Behavior->ShowFolders) $ret['folders'][] = $newfi;
				U::Let($newfi->info['index'], $foidx++);
				$newfi->SaveInfo();
			}
			else
			{
				$ret['files'][] = $newfi;
				U::Let($newfi->info['index'], $fiidx++);
			}
		}

		usort($ret['folders'], array($this, 'cmp_file'));
		usort($ret['files'], array($this, 'cmp_file'));

		return $ret;
	}

	/**
	 * Compare two files.
	 *
	 * @param FileInfo $f1
	 * @param FileInfo $f2
	 * @return int Higher or lower in comparison.
	 */
	function cmp_file($f1, $f2)
	{
		if ($this->Behavior->Sort == FM_SORT_MANUAL)
			return $f1->info['index'] < $f2->info['index'] ? -1 : 1;
		else return strnatcasecmp($f1->filename, $f2->filename);
	}

	/**
	 * Whether or not $file is visible to the current user or not.
	 * @param FileInfo $file FileInfo object to get access information out of.
	 * @return bool Whether or not this object is visible.
	 */
	function GetVisible($file)
	{
		if (!isset($this->uid) || is_file($file->path)
			|| $this->Behavior->ShowAllFiles)
			return true;

		// If there is no ACL assigned, check the parent folder for it.

		if (!isset($file->info['access']) &&
			dirname($file->path) != dirname($this->Root))
			return $this->GetVisible(new FileInfo($file->dir));

		//Altering this again, user ids are stored as keys, not values!
		//if there is a specific reason for them to be stored as values then
		//we'll need another solution. -- Xed

		if (isset($file->info['access']))
			if (!empty($file->info['access'][$this->uid]))
				return true;

		return false;
	}

	/**
	 * Gets a breadcrumb like path.
	 * @param string $root Root location of this path.
	 * @param string $path Current path we are recursing.
	 * @param string $arg Name of url argument to attach path to.
	 * @param string $sep Separation of folders use this character.
	 * @param string $rootname Name of the top level folder.
	 * @return string Rendered breadcrumb trail.
	 */
	static function TagBreadcrumb($t, $g, $a)
	{
		$path = Server::GetVar($a['SOURCE']);
		if (empty($path)) return null;

		$items = explode('/', $path);

		$ret = null;
		$cpath = '';

		foreach ($items as $ix => $i)
		{
			if ($ix > 0) { $ret .= $a['SEP']; $text = $i; }
			else $text = $a['ROOT'];
			$cpath .= ($ix > 0 ? '/' : null).$i;
			$uri = HM::URL('', array($a['SOURCE'] => $cpath));
			$ret .= "<a href=\"{$uri}\">{$text}</a>";
		}
		return $ret;
	}

	/**
	 * Returns the filter that was explicitely set on this object, object's
	 * directory, or fall back on the default filter.
	 *
	 * @param string $path Path to file to get filter of.
	 * @param string $default Default filter to fall back on.
	 * @return FilterDefault Or a derivitive.
	 */
	function GetFilter(&$fi, $root, $defaults)
	{
		$ft = $fi;

		# Either file or no filter here.
		while (is_file($fi->path) || empty($fi->info['type']))
		{
			if (File::IsIn($ft->dir, $root))
				$ft = new FileInfo(realpath($ft->dir));
			else
			{
				if (isset($defaults[0]))
					$fname = $defaults[0];
				else
					$fname = 'FilterDefault';
				$f = new $fname($this);
				$f->FFGetInfo($fi);
				return $f;
			}
		}

		if (in_array($ft->info['type'], $defaults))
			$fname = 'Filter'.$ft->info['type'];
		else $fname = 'Filter'.$defaults[0];

		$f = new $fname();
		$f->FFGetInfo($fi);
		return $f;
	}
}

class FileManagerView
{
	//Display
	/**
	 * Whether titles of files or filenames are shown.
	 *
	 * @var bool
	 */
	public $ShowTitle;

	/**
	 * Text of the header that comes before files.
	 *
	 * @var string
	 */
	public $FilesHeader = 'Files';

	/**
	 * Text of the header that comes before folders.
	 *
	 * @var string
	 */
	public $FoldersHeader = 'Folders';

	/**
	 * Whether files or folders come first.
	 *
	 * @var bool
	 */
	public $ShowFilesFirst = false;

	/**
	 * Whether or not to show the date next to files.
	 *
	 * @var bool
	 */
	public $ShowDate = true;

	/**
	 * Whether to float items instead of displaying them in a table.
	 *
	 * @var bool
	 */
	public $FloatItems = false;

	/**
	 * Create folder text to be displayed.
	 * @var string
	 */
	public $TextCreateFolder = 'Create New Folder';

	/**
	 * Title message for the upload box.
	 * @var string
	 */
	public $TitleUpload = '<b>Upload Files to Current Folder</b> -
		<i>Browse hard drive then click "upload"</i>';

	/**
	 * Test displayed as collapsable link from the main view.
	 * @var string
	 */
	public $TextAdditional = '<b>Additional Settings</b>';

	public $RenameTitle = 'Rename File / Folder';

	public $Captions = false;

	/**
	 * Returns the caption of a given thumbnail depending on caption display
	 * configuration.
	 * @param FileInfo $file File to gather information from.
	 * @return string Actual caption.
	 */
	function GetCaption($file)
	{
		if (!empty($file->info['title']))
			return stripslashes($file->info['title']);
		else return '';
	}
}

class FileManagerBehavior
{
	//Access Restriction

	/**
	* Target url of this script, to keep everything related.
	*
	* @var string
	*/
	public $Target = '';

	/**
	 * Whether or not file uploads are allowed.
	 *
	 * @var bool
	 */
	public $AllowUpload = false;

	/**
	 * Whether or not users are allowed to create directories.
	 *
	 * @var bool
	 */
	public $AllowCreateDir = false;

	/**
	 * Whether users are allowed to delete files.
	 * @see AllowAll
	 *
	 * @var bool
	 */
	public $AllowDelete = false;

	/**
	 * Whether users are allowed to manually sort files.
	 *
	 * @var bool
	 */
	public $Sort = FM_SORT_TABLE;

	public $AllowSort = false;

	/**
	 * Whether users are allowed to set filter types on folders.
	 *
	 * @var bool
	 */
	public $AllowRename = false;

	/**
	 * Whether users are allowed to rename or update file information.
	 *
	 * @var bool
	 */
	public $AllowEdit = false;

	/**
	 * Allow moving files to another location.
	 *
	 * @var bool
	 */
	public $AllowMove = false;

	public $AllowCopy = false;

	public $AllowLink = false;

	/**
	 * Allow downloading all packaged files as a zip file.
	 *
	 * @var bool
	 */
	public $AllowDownloadZip = false;

	/**
	 * Whether users are allowed to change directory filters.
	 *
	 * @var bool
	 */
	public $AllowSetType = false;

	/**
	 * Whether file information is shown, or file is simply downloaded on click.
	 *
	 * @var bool
	 */
	public $UseInfo = false;

	/**
	 * If true, do not delete files, they are renamed to
	 * .delete_filename
	 *
	 * @var bool
	 */
	public $Recycle = false;

	/**
	 * Override file hiding.
	 *
	 * @var bool
	 */
	public $ShowAllFiles = false;

	public $ShowFolders = true;

	/**
	 * Allow searching files.
	 *
	 * @var bool
	 */
	public $AllowSearch = false;

	/**
	 * Location of where to store logs.
	 *
	 * @var mixed
	 */
	public $Watchers = null;

	/**
	 * Whether or not to ignore the root folder when doing file operations.
	 * @var bool
	 */
	public $IgnoreRoot = false;

	/**
	 * A callback to modify the output of each file link.
	 * @var string
	 */
	public $FileCallback = null;

	/**
	 * Array of possible accessors.
	 * @var array
	 */
	public $Access;

	/**
	 * Whether or not quick captions are available.
	 * @var bool
	 */
	public $QuickCaptions = false;

	/**
	 * @var bool
	 */
	public $UpdateButton = true;

	public $HideOptions = true;

	public $UploadJava = false;
	public $UploadNormal = true;

	/**
	 * Return true if options are available.
	 * @return bool
	 */
	function Available()
	{
		return $this->AllowCreateDir ||
			$this->AllowUpload ||
			$this->AllowEdit;
	}

	/**
	 * Return true if mass options are available.
	 * @return bool
	 */
	function MassAvailable()
	{
		return $this->AllowMove || $this->AllowDelete || $this->AllowDownloadZip;
	}

	/**
	 * Turns on all allowances for administration usage.
	 */
	function AllowAll()
	{
		$this->AllowCreateDir =
		$this->AllowDelete =
		$this->AllowMove =
		$this->AllowDownloadZip =
		$this->AllowEdit =
		$this->AllowRename =
		$this->AllowSort =
		$this->AllowUpload =
		$this->AllowSetType =
		true;
	}

	/**
	 * Get behavior / security related options.
	 * @param FileInfo $fi Associated file information.
	 * @return array Array of FormInput objects to append to the parent form.
	 */
	function GetOptions($fi)
	{
		if (!empty($fi->info['access']))
		foreach (array_keys($fi->info['access']) as $id)
		{
			if (isset($this->Access[$id]))
				$this->Access[$id]->selected = true;
		}
		$ret = array();
		if (isset($this->Access))
			$ret[] = new FormInput('<b>File / Folder Access</b> - <i>Ctrl+ select the users who can access this file/folder.</i><br/>', 'selects', 'info[access]',
				$this->Access);
		return $ret;
	}

	/**
	 * Called when an item gets updated as a handler.
	 * @param array $info Related file information.
	 */
	function Update(&$info)
	{
		if (!empty($info['access']))
		{
			$na = array();
			foreach ($info['access'] as $id) $na[$id] = 1;
			$info['access'] = $na;
		}
	}
}

interface FileFilter
{
	function FFPrepare(&$fi);
	function FFGetInfo(&$fi);
	/**
	 * @returns array
	 */
	function FFGetOptions(&$fm, &$fi, $default);
	function FFGetQuickOpts(&$fi, $g);
	function FFGetQuickOptFinal($g);
	function FFUpload($file, $target);
	function FFRename(&$fi, $newname);
	function FFUpdated(&$fm, &$fi, &$newinfo);
	function FFDelete($fi, $save);
	function FFInstall($path);
	function FFCleanup($path);
}

?>
