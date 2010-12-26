<?php

require_once(dirname(__FILE__).'/present/FormInput.php');

/**
 * The generic file handler.
 */
class FilterDefault
{
	/**
	 * Name of this filter for identification purposes.
	 * @var string
	 */
	public $Name = "Default";

	/**
	 * Places information into $fi for later use.
	 *
	 * @param FileInfo $fi
	 * @return FileInfo
	 * @todo Replace this with ApplyInfo as reference with no return.
	 */
	function GetInfo(&$fi)
	{
		if (is_dir($fi->path)) $fi->type = 'folder';
		else $fi->type = File::ext($fi->filename);
		return $fi;
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 * @param FileManager $fm Calling filemanager.
	 * @param FileInfo $fi Object to get information out of.
	 * @param string $default Default option set.
	 * @return array
	 */
	function GetOptions(&$fm, &$fi, $default)
	{
		$more = array(
			new FormInput('Description', 'text', 'info[title]',
			stripslashes(@$fi->info['title']), null)
		);

		if (!empty($default)) return array_merge($default, $more);
		else return $more;
	}

	/**
	 * Called when a file is requested to upload.
	 *
	 * @param array $file Upload form's file field.
	 * @param string $target Destination folder.
	 */
	function Upload($file, $target)
	{
		$this->UpdateMTime($target->path.$file);
	}

	/**
	 * This will rename the info file and update the virtual modified time
	 * accordingly, as well as handle moving files.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param FileInfo $newname Destination file information.
	 */
	function Rename(&$fi, $newname)
	{
		$pinfo = pathinfo($newname);
		$finfo = "{$fi->dir}/.{$fi->filename}";
		$ddir = $pinfo['dirname'] == '.' ? $fi->dir : $pinfo['dirname'];
		if (file_exists($finfo))
			rename($finfo, $ddir.'/.'.$pinfo['basename']);
		rename($fi->path, $ddir.'/'.$pinfo['basename']);
		$fi->path = $ddir.'/'.$newname;
		$fi->filename = $newname;
		$this->UpdateMTime($ddir.'/'.$pinfo['basename']);
	}

	/**
	 * When options are updated, this will be fired.
	 * @param FileInfo $fi Associated file information.
	 */
	function Updated(&$fm, &$fi, &$newinfo)
	{
	}

	/**
	* Delete a file or folder.
	*
	* @param FileInfo $fi Associated file information.
	* @param bool $save Whether or not to back up the file getting deleted.
	*/
	function Delete($fi, $save)
	{
		$finfo = "{$fi->dir}/.{$fi->filename}";
		if (file_exists($finfo)) unlink($finfo);
		if ($save)
		{
			$r_target = $fi->dir.'/.deleted_'.$fi->filename;
			if (file_exists($r_target)) unlink($r_target);
			rename($fi->path, $fi->dir.'/.deleted_'.$fi->filename);
		}
		else if (is_dir($fi->path)) DelTree($fi->path);
		else unlink($fi->path);
	}

	/**
	 * Called when a filter is set to this one.
	 * @param string $path Source path.
	 */
	function Install($path) {}

	/**
	 * Called when a filter is no longer set to this one.
	 * @param string $path Source path.
	 */
	function Cleanup($path) {}

	static function UpdateMTime($filename)
	{
		$finfo = new FileInfo($filename);
		$finfo->info['mtime'] = time();
		$finfo->SaveInfo();
	}
}

?>
