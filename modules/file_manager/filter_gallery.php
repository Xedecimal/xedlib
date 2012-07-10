<?php

require_once(dirname(__FILE__).'/filter_default.php');

class FilterGallery extends FilterDefault
{
	/**
	 * Name of this object for identification purposes.
	 *
	 * @var string
	 */
	public $Name = 'Gallery';

	function __construct(&$fm)
	{
		$this->Behavior = new FilterGalleryBehavior($fm->FilterConfig);
	}

	# FileFilter implementation

	/**
	 * Appends the width, height, thumbnail and any other image related
	 * information on this file.
	 *
	 * @param FileInfo $fi
	 * @return FileInfo
	 */
	function FFGetInfo(&$fi)
	{
		parent::FFGetInfo($fi);

		if (is_file($fi->path))
			$dinfo = $this->FFGetInfo(new FileInfo($fi->dir))->info;
		else $dinfo = $fi->info;

		if (substr($fi->filename, 0, 2) == 't_') $fi->show = false;
		if (substr($fi->filename, 0, 2) == 'f_') $fi->show = false;

		if (!isset($dinfo['full_width'])) $dinfo['full_width'] = 1024;
		if (!isset($dinfo['full_height'])) $dinfo['full_height'] = 1024;
		$fi->info['full_width'] = $dinfo['full_width'];
		$fi->info['full_height'] = $dinfo['full_height'];

		if (!isset($dinfo['thumb_width'])) $dinfo['thumb_width'] = 200;
		if (!isset($dinfo['thumb_height'])) $dinfo['thumb_height'] = 200;
		$fi->info['thumb_width'] = $dinfo['thumb_width'];
		$fi->info['thumb_height'] = $dinfo['thumb_height'];

		global $_d;

		$dir = $fi->dir;
		if ($this->Behavior->UseThumbs) $abs = "{$dir}/t_{$fi->filename}";
		else $abs = "$dir/{$fi->filename}";

		$relpath = Module::P($abs);
		$path = HM::urlencode_path(dirname($relpath).'/'.basename($relpath));

		if (file_exists($abs)) $fi->icon = $path;

		# Prepare custom folder icon

		if (is_dir($fi->path))
		{
			$fs = glob($fi->path.'/.t_image.*');
			if (!empty($fs)) $fi->vars['icon'] = $fs[0];
			else $fi->vars['icon'] = FileManager::GetIcon($fi);
		}
		return $fi;
	}

	/**
	 * Returns an array of options that allow configuring this filter.
	 * @param FileInfo $fi Associated file information.
	 * @param array $default Default values.
	 * @return array
	 */
	function FFGetOptions(&$fm, &$fi, $default)
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
				if (substr($fiImg->filename, 0, 2) == 'f_') continue;
				$selImages[htmlspecialchars($fiImg->filename)] = new FormOption($fiImg->filename);
			}

			if ($this->Behavior->ResizeFull)
			{
				$new[] = new FormInput('Full Width', 'text',
					'info[full_width]', $fi->info['full_width']);
				$new[] = new FormInput('Full Height', 'text',
					'info[full_height]', $fi->info['full_height']);
			}

			if ($this->Behavior->UseThumbs)
			{
				$new[] = new FormInput('Thumbnail Width', 'text',
					'info[thumb_width]', $fi->info['thumb_width']);
				$new[] = new FormInput('Thumbnail Height', 'text',
					'info[thumb_height]', $fi->info['thumb_height']);
			}
			$new[] = new FormInput('Gallery Image', 'select',
				'image', $selImages);
			$new[] = new FormInput('or Upload', 'file',
				'upimage');
		}
		return array_merge(parent::FFGetOptions($fm, $fi, $default), $new);
	}

	/**
	 * Called when a file is requested to upload.
	 *
	 * @param array $file Upload form's file field.
	 * @param FileInfo $target Destination folder.
	 */
	function FFUpload($file, $target)
	{
		parent::FFUpload($file, $target);

		if ($this->Behavior->ResizeFull)
		{
			$this->ResizeFile($target->path.'/'.$file, $target->path.'/f_'.$file,
				$target->info['full_width'], $target->info['full_height']);
		}
		if ($this->Behavior->UseThumbs)
		{
			$this->ResizeFile($target->path.'/'.$file, $target->path.'/t_'.$file,
				$target->info['thumb_width'], $target->info['thumb_height']);
		}
	}

	/**
	 * This will update the thumbnail properly, after the parent filter
	 * handles the move.
	 *
	 * @param FileInfo $fi Source file information.
	 * @param string $newname Destination filename.
	 */
	function FFRename(&$fi, $newname)
	{
		parent::FFRename($fi, $newname);
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
	function FFDelete($fi, $save)
	{
		parent::FFDelete($fi, $save);
		$thumb = $fi->dir.'/t_'.$fi->filename;
		if (file_exists($thumb)) unlink($thumb);
		$full = $fi->dir.'/f_'.$fi->filename;
		if (file_exists($full)) unlink($full);
	}

	/**
	 * Regenerates the associated thumbnails for a given folder.
	 * @param string $path Destination path.
	 */
	function FFInstall($path)
	{
		$files = glob($path."*.*");
		$fi = new FileInfo($path);

		if ($this->Behavior->ResizeFull)
		{
			if (empty($fi->info['full_width'])) $fi->info['full_width'] = 1024;
			if (empty($fi->info['full_height'])) $fi->info['full_height'] = 1024;
		}

		if ($this->Behavior->UseThumbs)
		{
			if (empty($fi->info['thumb_width'])) $fi->info['thumb_width'] = 200;
			if (empty($fi->info['thumb_height'])) $fi->info['thumb_height'] = 200;
		}

		# Regenerate thumbnails.
		foreach ($files as $file)
		{
			if (substr($file, 0, 2) == 't_') continue;
			if (substr($file, 0, 2) == 'f_') continue;

			$pinfo = pathinfo($file);
			$this->ResizeFile($file, $path.'t_'.$pinfo['basename'],
				$fi->info['thumb_width'], $fi->info['thumb_height']);
			$this->ResizeFile($file, $path.'f_'.$pinfo['basename'],
				$fi->info['full_width'], $fi->info['full_height']);
		}
	}

	/**
	 * Cleans up all the generated thumbnail files for the given path.
	 * @param string $path Target path.
	 */
	function FFCleanup($path)
	{
		$files = glob($path."t_*.*");
		foreach ($files as $file) unlink($file);
		$files = glob($path."f_*.*");
		foreach ($files as $file) unlink($file);
	}

	/**
	 * @param FileInfo $fi Associated file information.
	 */
	function FFUpdated(&$fm, &$fi, &$newinfo)
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
		else if ($img == 'No Change')
		{
			$files = glob("{$fi->path}.t_image.*");
			foreach ($files as $f) unlink($f);
		}
		// Selected folder image.
		else if (!empty($img)) $newimg = $fi->path.'/'.$img;

		// Uploaded folder image.
		if (!empty($newimg))
		{
			$this->ResizeFile($newimg, "{$fi->path}/.t_image.".File::ext($newimg),
				$newinfo['thumb_width'], $newinfo['thumb_height']);
		}
		if (!empty($upimg['name']))
		{
			unlink("timg/{$upimg['name']}");
			rmdir("timg");
		}

		if (is_dir($fi->path) && (
			$fi->info['full_width'] != $newinfo['full_width'] ||
			$fi->info['full_height'] != $newinfo['full_height'] ||
			$fi->info['thumb_width'] != $newinfo['thumb_width'] ||
			$fi->info['thumb_height'] != $newinfo['thumb_height']))
		{
			$this->UpdateThumbs($fi, $newinfo);
		}

		if (is_file($fi->path)) unset(
			$newinfo['full_width'],
			$newinfo['full_height'],
			$newinfo['thumb_width'],
			$newinfo['thumb_height'],
			$newinfo['thumb']
		);
	}

	function UpdateThumbs($fi, $info)
	{
		set_time_limit(60*5);
		$dp = opendir($fi->path);
		while ($file = readdir($dp))
		{
			if ($file[0] == '.') continue;
			if (substr($file, 0, 2) == 't_') continue;
			if (substr($file, 0, 2) == 'f_') continue;

			$fir = new FileInfo($fi->path.'/'.$file);

			# Resize folder thumbnail
			if (is_dir($fi->path.'/'.$file))
			{
				$g = glob("{$fir->path}/._image.*");
				if (!empty($g))
					$this->ResizeFile($g[0], $fir->path.'/.'.
						File::GetFile('t'.substr(basename($g[0]), 1)),
						$info['thumb_width'], $info['thumb_height']);
			}
			else
			{
				$src = $fir->path;

				# Resize thumbnail.
				$w = $info['thumb_width'];
				$h = $info['thumb_height'];
				$dst = $fir->dir.'/t_'.$fir->filename;
				$this->ResizeFile($src, $dst, $w, $h);

				$fw = $info['full_width'];
				$fh = $info['full_height'];
				$fdst = $fir->dir.'/f_'.$fir->filename;
				$this->ResizeFile($src, $fdst, $fw, $fh);
			}
		}
	}

	/**
	 * Extension will be automatically appended to $dest filename.
	 */
	static function ResizeFile($file, $dest, $nx, $ny, $literal = false)
	{
		$img = imagecreatefromstring(file_get_contents($file));
		$img = FilterGallery::ResizeImg($img, $nx, $ny, $literal);
		imagejpeg($img, $dest);
	}

	/**
	 * Resizes an image bicubicly with GD keeping aspect ratio.
	 *
	 * @param resource $img
	 * @param int $nx
	 * @param int $ny
	 * @return resource
	 */
	static function ResizeImg($img, $nx, $ny, $literal = false)
	{
		$sx  = ImageSX($img);
		$sy = ImageSY($img);
		if ($sx < $nx && $sy < $ny) return $img;

		if ($literal)
		{
			$dx = $nx;
			$dy = $ny;
		}
		else # Not literal, maintain aspect ratio
		{
			# Get a scale factor
			if ($sx > $sy) $sf = ($nx / $sx);
			else $sf = ($ny / $sy);

			$dx = $sx * $sf;
			$dy = $sy * $sf;
		}
		$dimg = imagecreatetruecolor((int)$dx, (int)$dy);
		ImageCopyResampled($dimg, $img, 0, 0, 0, 0, $dx, $dy, $sx, $sy);
		return $dimg;
	}
}

class FilterGalleryBehavior
{
	public $UseThumbs = true;
	public $ResizeFull = true;

	function __construct($config)
	{
		foreach ($config as $k => $v)
			$this->$k = $v;
	}
}

?>
