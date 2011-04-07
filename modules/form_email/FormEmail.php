<?php

class FormEmail extends Module
{
	public $Name = 'email';
	protected $_template;

	function __construct()
	{
		$this->CheckActive($this->Name);
		$this->_template = Module::L('form_email/form.xml');
		$this->_subject = 'Web Contact Submission';
		$this->_email_template = Module::L('form_email/email.xml');
		$this->_fields = array(
			'Name' => new FormInput('Name', null, 'name'),
			'Email' => new FormInput('Email', null, 'email'),
			'Message' => new FormInput('Message', 'area', 'message')
		);
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'send')
		{
			$this->_from = Server::GetVar('from');
			$t = new Template();
			$t->use_getvar = true;

			$headers[] = 'From: '.$this->_from;
			$headers[] = 'Reply-To: '.$this->_from;

			$t->ReWrite('field', array(&$this, 'TagEmailField'));
			die($t->ParseFile($this->_email_template));
			mail($this->_to, $this->_subject,
				$t->ParseFile($this->_email_template),
				implode($headers, "\r\n"));
		}
	}

	function Get()
	{
		if (!$this->Active) return;
		$t = new Template;
		$t->ReWrite('field', array(&$this, 'TagField'));
		$t->Set($this);
		return $t->ParseFile($this->_template);
	}

	function TagField($t, $g)
	{
		foreach ($this->_fields as $n => $f)
		{
			$row['name'] = $n;
			$row['field'] = $f->Get();
			$rows[] = $row;
		}
		return VarParser::Concat($g, $rows);
	}

	function TagEmailField($t, $g)
	{
		foreach ($this->_fields as $n => $f)
		{
			$row['name'] = $n;
			$row['value'] = Server::GetVar($f->name);
			$rows[] = $row;
		}
		return VarParser::Concat($g, $rows);
	}
}

?>
