<?php

require_once(dirname(__FILE__).'/../classes/present/Form.php');

class EditorText extends Module
{
	public $Name = 'text';
	protected $item;

	function Prepare()
	{
		$q = $GLOBALS['_d']['q'];
		$action = array_pop($q);
		$target = array_pop($q);

		if ($target != $this->Name) return;

		if ($action == 'update')
		{
			$this->item = File::SecurePath(Server::GetVar($this->Name.'_ci'));
			file_put_contents($this->item, Server::GetVar($this->Name.'_body'));
		}
	}

	function Get()
	{
		$frmRet = new Form($this->Name);
		$frmRet->AddHidden($this->Name.'_action', 'update');
		$frmRet->AddHidden($this->Name.'_ci', $this->item);

		$frmRet->AddInput(new FormInput(null, 'area', $this->Name.'_body',
			@file_get_contents($this->item),
				array('ROWS' => 30, 'COLS' => 30, 'style' => 'width: 100%')));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		$ret['default'] = $frmRet->Get('method="post" action="{{app_abs}}/'.$this->Name.'/update"');
		return $ret;
	}
}

?>
