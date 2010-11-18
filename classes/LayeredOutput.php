<?php

class LayeredOutput
{
	public $layer = -1;
	public $outs;

	function __construct() { $this->CreateBuffer(); }
	function CreateBuffer() { $this->outs[++$this->layer] = ''; }
	function Out($data) { @$this->outs[$this->layer] .= $data; }
	function Get() { return $this->outs[$this->layer]; }
	function FlushBuffer() { return $this->outs[$this->layer--]; }
}

?>
