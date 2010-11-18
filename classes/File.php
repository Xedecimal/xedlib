<?php

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
	static function file($name)
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
}

?>
