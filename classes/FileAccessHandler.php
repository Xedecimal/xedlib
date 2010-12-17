<?php

class FileAccessHandler extends EditorHandler
{
	/**
	 * Top level directory to allow access.
	 * @var string
	 */
	private $root;

	private $ed;

	/**
	 * Constructor for this object, sets required properties.
	 * @param string $root Top level directory to allow access.
	 */
	function FileAccessHandler($ed, $root, $depth = 0)
	{
		require_once('a_file.php');
		$this->ed = $ed;
		$this->depth = $depth;
		$this->root = $root;
	}

	/**
	 * Recurses a single folder to collect access information out of it.
	 * @param string $root Source folder to recurse into.
	 * @param int $level Amount of levels deep for tree construction.
	 * @param int $id Identifier of the object we are looking for access to.
	 * @return array Array of SelOption objects.
	 */
	static function PathToSelOption($root, $id, $level, $depth = 0)
	{
		$ret = array();
		if (!empty($depth) && $level > $depth) return $ret;

		//Get information on this item.
		$so = new SelOption($root);
		$fi = new FileInfo($root);

		if (!empty($id) && !empty($fi->info['access'][$id]))
			$so->selected = true;
		$ret[$root] = $so;

		//Recurse children.
		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) $ret = array_merge($ret,
				FileAccessHandler::PathToSelOption($fp, $id, $level+1, $depth));
		}

		natcasesort($ret);

		return $ret;
	}

	/**
	 * Recurses a single folder to set access information in it.
	 * @param string $root Source folder to recurse into.
	 * @param int $id Identifier of the object we are looking for access to.
	 * @param array $accesses Series of access items that will eventually get set.
	 */
	static function RecurseSetPerm($root, $id, $accesses)
	{
		//Set information on this item.
		$fi = new FileInfo($root);

		if (!empty($accesses) && in_array($root, $accesses))
			$fi->info['access'][$id] = 1;
		else if (isset($fi->info['access'][$id]))
			unset($fi->info['access'][$id]);
		$fi->SaveInfo();

		//Recurse children.
		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) FileAccessHandler::RecurseSetPerm($fp, $id, $accesses);
		}
	}

	static function RecurseGetPerm($root, $id)
	{
		$ret = array();
		$fi = new FileInfo($root);
		if (isset($fi->info['access'][$id])) $ret[] = $root;

		$dp = opendir($root);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			$fp = $root.'/'.$file;
			if (is_dir($fp)) $ret = array_merge($ret, FileAccessHandler::RecurseGetPerm($fp, $id));
		}

		return $ret;
	}

	static function Copy($root, $src, $dst)
	{
		FileAccessHandler::RecurseSetPerm($root, $dst, FileAccessHandler::RecurseGetPerm($root, $src));
	}

	/**
	 * Called when a file or folder gets updated.
	 */
	function Update($s, $id, &$original, &$update)
	{
		$accesses = GetVar($this->ed->Name.'_accesses');
		$this->RecurseSetPerm($this->root, $id, $accesses);
		return true;
	}

	function Created($s, $id, $inserted)
	{
		$accesses = GetVar($this->ed->Name.'_accesses');
		$this->RecurseSetPerm($this->root, $id, $accesses);
	}

	/**
	 * Adds a series of options to the form associated with the given file.
	 * @todo Rename to AddFields
	 */
	function GetFields($s, &$form, $id, $data)
	{
		$form->AddInput(new FormInput('Accessable Folders', 'selects',
			'accesses', $this->PathToSelOption($this->root, $id, 0, 2), array('SIZE' => 8)));
	}
}

?>
