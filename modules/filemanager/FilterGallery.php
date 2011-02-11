<?php

require_once(dirname(__FILE__).'/FilterDefault.php');

class FilterGallery extends FilterDefault
{
	/**
	 * Name of this object for identification purposes.
	 *
	 * @var string
	 */
	public $Name = 'Gallery';

	/**
	 * Appends the width, height, thumbnail and any other image related
	 * information on this file.
	 *
	 * @param FileInfo $fi
	 * @return FileInfo
	 */
	function GetInfo(&$fi)
	{
		parent::GetInfo($fi);
		if (substr($fi->filename, 0, 2) == 't_') $fi->show = false;
		if (empty($fi->info['thumb_width'])) $fi->info['thumb_width'] = 200;
		if (empty($fi->info['thumb_height'])) $fi->info['thumb_height'] = 200;

		global $_d;

		$dir = $fi->dir;
		$abs = "{$dir}/t_{$fi->filename}";
		$rel = dirname($fi->path).'/t_'.$fi->filename;
		if (file_exists($rel)) $fi->icon = '<img src="'.htmlspecialchars($rel).'" />';

		if (is_dir($fi->path))
		{
			$fs = glob($fi->path.'/.t_image.*');
			if (!empty($fs)) $fi->vars['icon'] = $fs[0];
			else $fi->vars['icon'] = FileManager::GetIcon($fi);
		}
		return $fi;
	}

	/**
	 * @param FileInfo $fi Associated file information.
	 */
	function Updated(&$fm, &$fi, &$newinfo)
	{
		$img = Server::GetVar('image');
		$upimg = Server::GetVar('upimage');

		// Uploaded folder image
		if (!empty($upimg['name']))
		{
			mkdir("timg");
			move_uploaded_file($upimg['tmp_name'], 'timg/'.$upimg['name']);
			$newimg = 'timg/'.$upimg['name'];
		}
		// No folder image.
		else if ($img == 1)
		{
			$files = glob("{$fi->path}.t_image.*");
			foreach ($files as $f) unlink($f);
		}
		// Selected folder image.
		else if (!empty($img)) $newimg = $fi->path.$img;

		// Uploaded folder image.
		if (!empty($newimg))
		{
			$this->ResizeFile($newimg,
				"{$fi->path}/.t_image",
				$newinfo['thumb_width'], $newinfo['thumb_height']);
		}
		if (!empty($upimg['name']))
		{
			unlink("timg/{$upimg['name']}");
			rmdir("timg");
		}

		if (is_dir($fi->path) && (
			$fi->info['thumb_width'] != $newinfo['thumb_width'] ||
			$fi->info['thumb_height'] != $newinfo['thumb_height']))
		{
			$this->UpdateThumbs($fi, $newinfo);
		}

		if (is_file($fi->path)) unset(
			$newinfo['thumb_width'],
			$newinfo['thumb_height'],
			$newinfo['thumb']
		);
	}

	function UpdateThumbs($fi, $info)
	{
		$dp = opendir($fi->path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (substr($file, 0, 2) == 't_') continue;

			$fir = new FileInfo($fi->path.'/'.$file);

			if (is_dir($fi->path.'/'.$file))
			{
				$g = glob("{$fir->path}/._image.*");
				if (!empty($g))
					$this->ResizeFile($g[0], $fir->path.'/.'.
						File::GetFile('t'.substr(basename($g[0]), 1)),
						$info['thumb_width'], $info['thumb_height']);
				$this->UpdateThumbs($fir, $info);
			}
			else
			{
				$w = $info['thumb_width'];
				$h = $info['thumb_height'];
				$src = $fir->path;
				$dst = $fir->dir.'/t_'.File::GetFile($fir->filename);
				$this->ResizeFile($src, $dst, $w, $h);
			}
		}
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 * @param FileInfo $fi Associated file information.
	 * @param array $default Default values.
	 * @return array
	 */
	function GetOptions(&$fm, &$fi, $default)
	{
		$new = array();
		if (is_dir($fi->path))
		{
			$selImages[0] = new FormOption('No Change');
			$selImages[1] = new FormOption('Remove');

			if (!empty($fm->files['files']))
			foreach ($fm->files['files'] as $fiImg)
			{
				if (substr($fiImg->filename, 0, 2) == 't_') continue;
				$selImages[htmlspecialchars($fiImg->filename)] = new FormOption($fiImg->filename);
			}

			$new[] = new FormInput('Thumbnail Width', 'text',
				'info[thumb_width]', $fi->info['thumb_width']);
			$new[] = new FormInput('Thumbnail Height', 'text',
				'info[thumb_height]', $fi->info['thumb_height']);
			$new[] = new FormInput('Gallery Image', 'select',
				'image', $selImages);
			$new[] = new FormInput('or Upload', 'file',
				'upimage');
		}
		return array_merge(parent::GetOptions($fm, $fi, $default), $new);
	}

	/**
	 * This will update the thumbnail properly, after the parent filter
	 * handles the move.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param string $newname Destination filename.
	 */
	function Rename(&$fi, $newname)
	{
		parent::Rename($fi, $newname);
		$thumb = $fi->dir.'/t_'.basename($fi->filename);
		$ttarget = dirname($newname).'/t_'.basename($newname);
		if (file_exists($thumb)) rename($thumb, $ttarget);
	}

	/**
	 * Called when an item is to be deleted.
	 *
	 * @param FileInfo $fi Target to be deleted.
	 * @param bool $save Whether or not to back up the item to be deleted.
	 */
	function Delete($fi, $save)
	{
		parent::Delete($fi, $save);
		$thumb = $fi->dir.'/t_'.$fi->filename;
		if (file_exists($thumb)) unlink($thumb);
	}

	/**
	 * Called when a file is requested to upload.
	 *
	 * @param array $file Upload form's file field.
	 * @param FileInfo $target Destination folder.
	 */
	function Upload($file, $target)
	{
		parent::Upload($file, $target);
		$tdest = 't_'.substr($file, 0, strrpos($file, '.'));
		$this->ResizeFile($target->path.$file, $target->path.$tdest,
			$target->info['thumb_width'], $target->info['thumb_height']);
	}

	/**
	 * Regenerates the associated thumbnails for a given folder.
	 * @param string $path Destination path.
	 */
	function Install($path)
	{
		$files = glob($path."*.*");
		$fi = new FileInfo($path);
		$fi->info['thumb_width'] = $fi->info['thumb_height'] = 200;
		foreach ($files as $file)
		{
			if (substr($file, 0, 2) == 't_') continue;
			$pinfo = pathinfo($file);
			$this->ResizeFile($file, $path.'t_'.File::GetFile($pinfo['basename']),
				$fi->info['thumb_width'], $fi->info['thumb_height']);
		}
	}

	/**
	 * Cleans up all the generated thumbnail files for the given path.
	 * @param string $path Target path.
	 */
	function Cleanup($path)
	{
		$files = glob($path."t_*.*");
		foreach ($files as $file) unlink($file);
	}

	/**
	 * Extension will be automatically appended to $dest filename.
	 */
	function ResizeFile($file, $dest, $nx, $ny)
	{
		$pinfo = pathinfo($file);
		$dt = $dest.'.'.$pinfo['extension'];

		switch (strtolower($pinfo['extension']))
		{
			case "jpg":
			case "jpeg":
				$img = imagecreatefromjpeg($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagejpeg($img, $dt);
			break;
			case "png":
				$img = imagecreatefrompng($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagepng($img, $dt);
			break;
			case "gif":
				$img = imagecreatefromgif($file);
				$img = $this->ResizeImg($img, $nx, $ny);
				imagegif($img, $dt);
			break;
		}
	}

	/**
	 * Resizes an image bicubicly with GD keeping aspect ratio.
	 *
	 * @param resource $img
	 * @param int $nx
	 * @param int $ny
	 * @return resource
	 */
	function ResizeImg($img, $nx, $ny)
	{
		$sx  = ImageSX($img);
		$sy = ImageSY($img);
		if ($sx < $nx && $sy < $ny) return $img;

		if ($sx < $sy)
		{
			$dx = $nx * $sx / $sy;
			$dy = $ny;
		}
		else
		{
			$dx = $nx;
			$dy = $ny * $sy / $sx;
		}
		$dimg = imagecreatetruecolor($dx, $dy);
		ImageCopyResampled($dimg, $img, 0, 0, 0, 0, $dx, $dy, $sx, $sy);
		return $dimg;
	}
}

?>
