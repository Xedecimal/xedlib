<?php

/**
 * A simple themed box.
 */
class Box
{
	/**
	 * For unique identifier.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Title to be displayed in this box, placement depends on the theme.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Standard text to be output inside this box.
	 *
	 * @var string
	 */
	public $out;

	/**
	 * Template filename to use with this box.
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Constructs a new box object with empty title and body.
	 */
	function Box()
	{
		$this->title = "";
		$this->out = "";
		$this->name = "";
		$this->template = ifset(@$GLOBALS['__xedlib_box_template'],
			'template_box.html');
	}

	function Out($t) { $this->out .= $t; }

	/**
	* Returns the rendered html output of this Box.
	* @param string $template Filename of template to use for display.
	* @return string Rendered box
	*/
	function Get($template = null)
	{
		$temp = isset($template) ? $template : $this->template;
		if (file_exists($temp))
		{
			$d = null;
			$t = new Template($d);
			$t->set("box_name", $this->name);
			$t->set("box_title", $this->title);
			$t->set("box_body", $this->out);
			return $t->ParseFile($temp);
		}
		$ret  = '<!-- Start Box: '.$this->name.' -->';
		$ret .= '<div ';
		if (!empty($this->name)) $ret .= " id=\"{$this->name}\"";
		$class = (!empty($this->prefix)?$this->prefix.'_box':'box');
		$ret .= " class=\"{$class}\">";
		$ret .= '<div class="box_title">'.$this->title.'</div>';
		$ret .= '<div class="box_body">'.$this->out.'</div>';
		$ret .= '</div>';
		$ret .= "<!-- End Box {$this->name} -->\n";
		return $ret;
	}
	
	/**
	 * Quick macro to retreive a generated box.
	 * @param string $name Name of the box (good for javascript calls to getElementById()).
	 * @param string $title Title of the returned box.
	 * @param string $body Raw text contents of the returned box.
	 * @param string $template Template file to use for the returned box.
	 * @example test_display.php
	 * @return string Rendered box.
	 */
	static function GetBox($name, $title, $body, $template = null)
	{
		$box = new Box();
		$box->name = $name;
		$box->title = $title;
		$box->Out($body);
		return $box->Get($template);
	}
}

?>
