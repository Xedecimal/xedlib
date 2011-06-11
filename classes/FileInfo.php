<?php

require_once(dirname(__FILE__).'/File.php');

/**
 * Collects information for a SINGLE file OR folder.
 */
class FileInfo
{
	/**
	 * Path of this file, including the filename.
	 *
	 * @var string
	 */
	public $path;
	/**
	 * Directory of this file excluding filename.
	 *
	 * @var string
	 */
	public $dir;
	/**
	 * Position of the current forward slash.
	 *
	 * @var int
	 */
	public $bitpos;
	/**
	 * Name of this file, excluding path.
	 *
	 * @var string
	 */
	public $filename;

	/**
	 * Name of the associated filter, hopefully depricated.
	 *
	 * @var string
	 */
	public $filtername;
	/**
	 * Whether or not the current users owns this object.
	 *
	 * @var bool
	 */
	public $owned;
	/**
	 * Array of serializable information on this file, including but not limited
	 * to, index and title.
	 *
	 * @var array
	 */
	public $info;
	/**
	 * Extension of this filename, used for collecting icon information.
	 *
	 * @var string
	 */
	public $type;
	/**
	 * Icon of this item, this should be depricated as it only applies
	 * to FilterGallery.
	 *
	 * @var string
	 */
	public $icon;
	/**
	 * Whether or not this file should be shown.
	 *
	 * @var bool
	 */
	public $show;

	function __toString()
	{
		return $this->filename;
	}

	/**
	 * Creates a new FileInfo from an existing file. Filter manages how this
	 * information will be handled, manipulated or displayed.
	 *
	 * @param string $source Filename to gather information on.
	 * @param array $filters Array of available filters.
	 */
	function __construct($source)
	{
		global $user_root;
		if (!file_exists($source))
			Server::Error("FileInfo: File/Directory does not exist. ({$source})<br/>\n");

		if (!empty($user_root))
			$this->owned = strlen(strstr($source, $user_root)) > 0;

		$this->bitpos = 0;
		$this->path = $source;
		$this->dir = dirname(realpath($source));
		$this->filename = basename($source);
		$this->show = true;

		$finfo = $this->dir.'/.'.$this->filename;
		if (is_file($finfo) && file_exists($finfo))
		{
			$this->info = unserialize(file_get_contents($finfo));
			if (!isset($this->info))
				Error("Failed to unserialize: {$finfo}<br/>\n");
		}
		else $this->info = array();
	}

	/**
	 * Gets a bit of a path, a bit is anything between the path separators
	 * ('/').
	 *
	 * @param int $off Which bit to return
	 * @return string
	 */
	function GetBit($off)
	{
		$items = preg_split('#\/|\\\#', $this->path);
		return @$items[$off];
	}

	/**
	 * Serializes the information of this file to the filesystem for later
	 * reuse.
	 *
	 */
	function SaveInfo()
	{
		//Access is not stored in files, just directories.
		if (is_file($this->path)) unset($this->info['access']);
		$info = $this->dir.'/.'.$this->filename;
		$fp = fopen($info, 'w+');
		fwrite($fp, serialize($this->info));
		fclose($fp);
	}
}

?>
