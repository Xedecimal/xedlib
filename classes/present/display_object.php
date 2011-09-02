<?php

/**
 * A generic page, associated with h_main.php and passed on to index.php .
 */
class DisplayObject
{
	/**
	 * Creates a new display object.
	 */
	function __construct() { }

	/**
	 * Gets the content of this object.
	 * @param array $data Context data.
	 * @return string The name of this page for the browser's titlebar.
	 */
	function Get()
	{
		return "Class " . get_class($this) . " does not overload Get().";
	}

	/**
	 * Prepare this object for output.
	 * @param array $data Context data.
	 */
	function Prepare() { }
}

?>
