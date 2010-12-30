<?php

class EditorUpload
{
	public $Name;
	private $item;

	function EditorUpload($name, $item)
	{
		$this->Name = $name;
		$this->item = $item;
	}

	function Prepare()
	{
		$action = Server::GetVar($this->Name.'_action');
		if ($action == 'update')
		{
			move_uploaded_file($_FILES[$this->Name.'file']['tmp_name'], $this->item);
		}
	}

	function Get($target)
	{
		$frmRet = new Form($this->Name);
		$frmRet->AddHidden('action', 'update');

		$frmRet->AddInput(new FormInput(null, 'file', 'file'));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		return $frmRet->Get('enctype="multipart/form-data" method="post" action="'.$target.'"');
	}
}

?>
