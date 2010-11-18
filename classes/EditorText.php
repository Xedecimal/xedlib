<?php

class EditorText
{
	public $Name;
	private $item;

	function EditorText($name, $item)
	{
		$this->Name = $name;
		$this->item = str_replace('\\', '', $item);
	}

	function Prepare()
	{
		$q = $GLOBALS['_d']['q'];
		$action = array_pop($q);
		$target = array_pop($q);

		if ($target != $this->Name) return;

		if ($action == 'update')
		{
			$this->item = SecurePath(GetVar($this->Name.'_ci'));
			file_put_contents($this->item, GetVar($this->Name.'_body'));
		}
	}

	function Get($target)
	{
		$frmRet = new Form($this->Name);
		$frmRet->AddHidden($this->Name.'_action', 'update');
		$frmRet->AddHidden($this->Name.'_ci', $this->item);

		$frmRet->AddInput(new FormInput(null, 'area', $this->Name.'_body',
			@file_get_contents($this->item),
				array('ROWS' => 30, 'COLS' => 30, 'style' => 'width: 100%')));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		return $frmRet->Get('method="post" action="'.$target.'/'.$this->Name.'/update"');
	}
}

?>
