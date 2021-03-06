<?php

require_once(dirname(__FILE__).'/../file_manager/file_manager.php');
require_once(dirname(__FILE__).'/../file_manager/filter_gallery.php');

/**
 * @package Gallery
 */

define('CAPTION_NONE',  0);
define('CAPTION_TITLE', 1);
define('CAPTION_FILE',  2);
define('CAPTION_TITLE_FILE', 3);

class Gallery extends Module
{
	/**
	 * Whether or not to display the caption specified in the file manager.
	 * @var bool
	 */
	public $InfoCaption = true;

	/**
	 * Behavioral properties.
	 * @var GalleryBehavior
	 */
	public $Behavior;

	/**
	 * Display properties.
	 * @var GalleryDisplay
	 */
	public $Display;

	public $Filter = 'Gallery';

	/**
	 * Constructor, sets default properties, behavior and display.
	 * @param string $root Root location of images for this gallery.
	 */
	function __construct()
	{
		$this->Behavior = new GalleryBehavior();
		$this->Display = new GalleryDisplay();
		$this->Template = Module::L('gallery/gallery.xml');
		$this->FileManager = new FileManager();
		$this->CheckActive($this->Name);
	}

	function TagHeader($t, $guts)
	{
		global $page_head;
		$page_head .= $guts;
	}

	function TagFolder($t, $guts)
	{
		$me = Server::GetVar('q');

		$out = '';
		$vp = new VarParser();

		foreach ($this->files['folders'] as $fi)
		{
			$this->f->FFGetInfo($fi);

			$du['editor'] = Server::GetVar('editor');
			$du['galcf'] = Server::GetVar('galcf', '').$fi->filename.'/';
			$d['url'] = HM::URL($me, $du);

			$d['name'] = $fi->filename;
			$d['icon'] = $fi->icon;
			$d['editor'] = Server::GetVar('editor');

			$size = getimagesize($fi->licon);
			$d['width'] = $size[0];
			$d['height'] = $size[1];

			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;
	}

	function TagFile($t, $guts)
	{
		global $_d;

		$out = '';
		$vp = new VarParser();
		$vp->Behavior->Bleed = false;

		foreach ($this->files['files'] as $ix => $fi)
		{
			$this->f->FFGetInfo($fi);
			if (!$fi->show || empty($fi->icon)) continue;

			if ($ix >= count($this->files['files'])-1) $d['class'] = ' last';
			else $d['class'] = '';

			$d['fullname'] = '{{app_abs}}/'.HM::urlencode_path($fi->path);
			$d['idx'] = $ix;
			$d['name'] = $this->GetCaption($fi);
			$d['path'] = Server::GetVar('galcf', '');
			$d['icon'] = $fi->icon;

			$size = getimagesize($fi->licon);
			$d['width'] = $size[0];
			$d['height'] = $size[1];
			$d['desc'] = $this->GetCaption($fi);

			$out .= $vp->ParseVars($guts, $d);
		}
		return $out;
	}

	function TagImage($t, $guts)
	{
		$out = '';
		$view = Server::GetVar('view');
		if (!isset($view)) return null;

		$out .= '  ';

		$out .= '</p>';
		$out .= '';
		return $guts;
	}

	function TagPage($t, $guts)
	{
		if ($this->Behavior->PageCount != null)
		{
			$args = array('galcf' => $this->path);
			return GetPages($this->files['files'], $this->Behavior->PageCount, $args);
		}
	}

	function TagPart($t, $guts, $attribs)
	{
		$this->$attribs['TYPE'] = $guts;
	}

	/**
	 * Returns the rendered gallery.
	 * @param string $path Current location, usually Server::GetVar('galcf')
	 * @return string Rendered gallery.
	 */
	function Get()
	{
		global $me;

		if (!$this->Active) return;
		if (substr($this->Root, -1, 1) != '/') $this->Root .= '/';
		$this->f = new FilterGallery($this->FileManager);

		require_once(dirname(__FILE__).'/../file_manager/file_manager.php');
		require_once(dirname(__FILE__).'/../../classes/present/template.php');

		$path = Server::GetVar('galcf');

		$this->FileManager->Name = $this->Name;
		$this->FileManager->Filters = array($this->Filter);
		$this->FileManager->Root = $this->Root.$path;
		$this->FileManager->Behavior->ShowAllFiles = true;
		$this->FileManager->Behavior->Sort = $this->Display->Sort;
		$this->files = $this->FileManager->GetDirectory();

		$t = new Template();
		$this->path = $path;
		$t->ReWrite('breadcrumb', array('FileManager', 'TagBreadcrumb'));
		$t->ReWrite('header', array(&$this, 'TagHeader'));
		$t->ReWrite('folder', array(&$this, 'TagFolder'));
		$t->ReWrite('file', array(&$this, 'TagFile'));
		$t->ReWrite('image', array(&$this, 'TagImage'));
		$t->ReWrite('page', array(&$this, 'TagPage'));
		$t->ReWrite('part', array(&$this, 'TagPart'));

		$t->Set('disable_save', $this->Behavior->DisableSave);
		$t->Set('current', Server::GetVar('view'));
		$t->Set('galcf', Server::GetVar('galcf'));
		$t->Set('galme', HM::URL($me));

		$tot = 0;
		foreach ($this->files['files'] as $f)
		{
			if (substr($f->filename, 0, 2) == 't_') continue;
			$tot++;
		}
		$t->Set('total', count($this->files['files']));

		//Page related
		$view = Server::GetVar('view');

		if (isset($view))
		{
			$fi = $this->files['files'][$view];
			$t->Set('url', $fi->path);
			$t->Set('caption', $this->GetCaption($fi));
		}

		//Back Button
		if ($view > 0)
		{
			$args = array(
				'view' => $view-1,
				'galcf' => Server::GetVar('galcf'),
			);
			if ($this->Behavior->PageCount > 0)
				$args['cp'] = floor(($view-1)/$this->Behavior->PageCount);

			$t->Set('butBack', GetButton(HM::URL($me, $args).'#fullview',
				'back.png', 'Back', 'class="png"'));
		}
		else $t->Set('butBack', '');

		//Forward Button
		if ($view < count($this->files['files'])-1)
		{
			$args = array(
				'view' => $view+1,
				'galcf' => Server::GetVar('galcf'),

			);
			if ($this->Behavior->PageCount > 0)
				$args['cp'] = floor(($view+1)/$this->Behavior->PageCount);

			$url = HM::URL($me, $args);
			$img = '<img src="images/forward.png" alt="Forward" /></a>';
			$t->Set('butForward', "<a href=\"$url#fullview\">$img</a>");
		}
		else $t->Set('butForward', '');

		//Gallery settings
		$fig = new FileInfo($this->Root);
		$this->FileManager->GetFilter($fig, $this->Root, array('Gallery'));
		$t->Set('file_thumb_width', $fig->info['thumb_width']);
		$t->Set('file_thumb_height', $fig->info['thumb_height']+60);

		$fi = new FileInfo($this->Root.$path);
		if ($path != $this->Root) $t->Set('name', $this->GetCaption($fi));
		else $t->Set('name', '');

		$ret['head'] = <<<EOF
<link type="text/css" rel="stylesheet" href="{{app_abs}}/xedlib/modules/gallery/gallery.css" />
<script type="text/javascript" src="{{app_abs}}/xedlib/js/jquery.flyout.js"></script>
<script type="text/javascript" src="{{app_abs}}/xedlib/modules/gallery/gallery.js"></script>
EOF;
		$ret['gallery'] = $t->ParseFile($this->Template);
		return $ret;
	}

	/**
	 * Returns the caption of a given thumbnail depending on caption display
	 * configuration.
	 * @param FileInfo $file File to gather information from.
	 * @return string Actual caption.
	 */
	function GetCaption($file)
	{
		if ($this->Display->Captions == CAPTION_NONE) return '';
		if ($this->InfoCaption
			&& !empty($file->info['title'])
			&& $this->Display->Captions == CAPTION_TITLE)
			return stripslashes($file->info['title']);
		else if ($this->Display->Captions == CAPTION_FILE)
			return str_replace('_', ' ', substr($file->filename, 0, strrpos($file->filename, '.')));
		else if ($this->Display->Captions == CAPTION_TITLE_FILE)
			return str_replace('_', ' ', $file->filename);
	}
}

class GalleryDisplay
{
	/**
	 * How to display captions, can be CAPTION_NONE, CAPTION_TITLE and
	 * CAPTION_FILE.
	 * @var int
	 */
	public $Captions = CAPTION_TITLE_FILE;

	/**
	 * String to append on the left side of the caption, this also handles
	 * variables like templates: {{variable}}.
	 * @var string
	 */
	public $CaptionLeft = '';

	/**
	 * String to append on the right side of the caption. This also handles
	 * variables like templates: {{variable}}.
	 * @var string
	 */
	public $CaptionRight = '';

	/**
	 * Method of sorting this gallery, can be FM_SORT_MANUAL or FM_SORT_NONE.
	 * @var int
	 */
	public $Sort;
}

class GalleryBehavior
{
	/**
	 * When true, generates a bit of javascript to block right clicking
	 * on images. Easily bypassable but easily implemented as well.
	 * @var bool
	 */
	public $DisableSave = false;

	/**
	 * The amount of images to display per-page, everything else will be
	 * calculated.
	 * @var int Numeric amount of images per page.
	 */
	public $PageCount = null;
}

class GalleryAdmin extends FileManager
{
	public $Name = 'admin/gallery';
	public $Root = 'galimg';
	public $Title = 'Gallery';

	function Auth() { return User::RequireAccess(1); }

	function __construct()
	{
		parent::__construct();

		global $me, $_d;
		$this->CheckActive($this->Name);

		$this->Behavior->Target = '{{app_abs}}/'.$this->Name;
		$this->Filters = array('Gallery');
	}

	function Link()
	{
		global $_d, $me;

		$_d['nav.links']['Admin/'.$this->Title] = '{{app_abs}}/'.$this->Name;
	}

	function Prepare()
	{
		$this->Behavior->AllowAll();
		parent::Prepare();
	}
}

?>
