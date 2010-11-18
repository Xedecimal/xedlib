<?php

require_once(__DIR__.'/Server.php');

class File
{
	function PregRename($glob, $preg_src, $preg_dst)
	{
		foreach (glob($glob) as $f)
		{
			if (preg_match($preg_src, $f))
			{
				$dst = preg_replace($preg_src, $preg_dst, $f);
				rename($f, $dst);
			}
		}
	}

	/**
	 * Resizes an image bicubicly and constrains proportions.
	 *
	 * @param resource $image Result of imagecreate* functions.
	 * @param int $newWidth Horizontal pixel size you wish the result to meet.
	 * @param int $newHeight Vertical pixel size you wish the result to meet.
	 * @return resource Resized/sampled image.
	 */
	function ResizeImage($image, $newWidth, $newHeight)
	{
		$srcWidth  = imagesx($image);
		$srcHeight = imagesy($image);
		if ($srcWidth < $newWidth && $srcHeight < $newHeight) return $image;

		if ($srcWidth < $srcHeight)
		{
			$destWidth  = $newWidth * $srcWidth/$srcHeight;
			$destHeight = $newHeight;
		}
		else
		{
			$destWidth  = $newWidth;
			$destHeight = $newHeight * $srcHeight/$srcWidth;
		}
		$destImage = imagecreatetruecolor($destWidth, $destHeight);
		ImageCopyResampled($destImage, $image, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
		return $destImage;
	}

	/**
	 * Returns a filename without the extension.
	 *
	 * @param string $name Name to strip the extension off.
	 * @return string Stripped filename.
	 */
	static function GetFile($name)
	{
		if (strpos($name, '.')) return substr($name, 0, strrpos($name, '.'));
		return $name;
	}

	/**
	 * Returns the filename portion of path without the extension applied.
	 *
	 * @param string $name Name of the file to return the extension from.
	 * @return string File extension.
	 */
	static function ext($name)
	{
		return substr(strrchr($name, '.'), 1);
	}

	/**
	 * Careful with this sucker.
	 *
	 * @param string $dir Directory to obliterate.
	 */
	static function DelTree($dir)
	{
		if (!file_exists($dir)) return;
		$dh = @opendir($dir);
		if (!$dh) return;
		while (($obj = readdir($dh)))
		{
			if ($obj == '.' || $obj == '..') continue;
			$target = "{$dir}/{$obj}";
			if (is_dir($target)) DelTree($target);
			else unlink($target);
		}
		closedir($dh);
		@rmdir($dir);
	}

	/**
	 * Recursively deletes empty folders in the direction of the parent.
	 *
	 * @param string $dir Deepest level directory to empty.
	 */
	static function DelEmpty($dir)
	{
		$files = glob($dir.'/*');
		if (count($files) < 1) { @rmdir($dir); DelEmpty(dirname($dir)); }
	}

	/**
	 * Returns true if $src path is inside $dst path.
	 *
	 * @param string $src Source pathname.
	 * @param string $dst Destination pathname.
	 * @return bool True if the source exists inside the destination.
	 */
	static function IsIn($src, $dst)
	{
		$rpdst = realpath($dst);
		return substr(realpath($src), 0, strlen($rpdst)) == $rpdst;
	}

	/**
	 * Gets the webserver path for a given local filesystem directory.
	 *
	 * @param string $path
	 * @return string Translated path.
	 */
	static function GetRelativePath($path)
	{
		$dr = $_SERVER['DOCUMENT_ROOT']; //Probably Apache situated

		if (empty($dr)) //Probably IIS situated
		{
			//Get the document root from the translated path.
			$pt = str_replace('\\\\', '/', Server::GetVar('PATH_TRANSLATED',
				Server::GetVar('ORIG_PATH_TRANSLATED')));
			$dr = substr($pt, 0, -strlen(Server::GetVar('SCRIPT_NAME')));
		}

		$dr = str_replace('\\\\', '/', $dr);

		return substr(str_replace('\\', '/', str_replace('\\\\', '/', $path)), strlen($dr));
	}

	/**
	 * Attempts to disable the ability to inject different paths to gain higher
	 * level directories in urls or posts.
	 *
	 * @param string $path Path to secure from url hacks.
	 * @return string Properly secured path.
	 */
	static function SecurePath($path)
	{
		$ret = preg_replace('#^\.#', '', $path);
		$ret = preg_replace('#^/#', '', $ret);
		return preg_replace('#\.\./#', '', $ret);
	}

	/**
	 * Returns an array of all files located recursively in a given path, excluding
	 * anything matching the regular expression of $exclude.
	 *
	 * @param string $path Path to recurse.
	 * @param string $exclude Passed to preg_match to blacklist files.
	 * @return array Series of non-directories that were not excluded.
	 */
	static function Comb($path, $exclude, $flags = 3)
	{
		if ($exclude != null && preg_match($exclude, $path)) return array();
		// This is a file and unable to recurse.
		if (is_file($path))
		{
			if (OPT_FILES & $flags) return array($path);
			return array();
		}

		else if (is_dir($path))
		{
			// We will return ourselves if we're including directories.
			$ret = ($flags & OPT_DIRS) ? array($path) : array();
			$dp = opendir($path);
			while ($f = readdir($dp))
			{
				if ($f[0] == '.') continue;
				$ret = array_merge($ret, Comb($path.'/'.$f, $exclude, $flags));
			}

			return $ret;
		}

		return array();
	}
}

?>
