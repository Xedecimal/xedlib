<?php

class SimpleDOM
{
	/** @var DOMDocument */
	public $Doc;
	/** @var DOMXPath */
	public $XPath;

	function __construct($data)
	{
		$this->Doc = new DOMDocument();
		$this->Doc->loadXML($data);
		$this->XPath = new DOMXPath($this->Doc);
	}

	// XPath Related

	function Query($xpath)
	{
		$items = $this->XPath->query($xpath);
		$ret = array();
		for ($ix = 0; ($item = $items->item($ix)); $ix++)
			$ret[] = $item;
		return $ret;
	}

	function RegisterNamespace($prefix, $url)
	{
		$this->XPath->registerNamespace($prefix, $url);
	}

	// Manipulation

	function RemoveElements($xpath)
	{
		$els = $this->Query($xpath);
		foreach ($els as $e)
			$e->parentNode->removeChild($e);
	}

	function Append($xpath, $element, $attributes)
	{
		$elAdd = $this->Doc->createElement($element);
		foreach ($attributes as $n => $v)
			$elAdd->setAttribute($n, $v);
		$elsTarget = $this->Query($xpath);
		foreach ($elsTarget as $elTarget)
			$elTarget->appendChild($elAdd);
	}
}

?>
