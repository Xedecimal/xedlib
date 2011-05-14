<?php

class FormEmail extends Module
{
	public static $Name = 'email';
	protected $_template;

	function __construct()
	{
		$this->CheckActive(self::$Name);
		$this->title = 'Contact Form';
		$this->_template = Module::L('form_email/form.xml');
		$this->_subject = 'Web Contact Submission';
		$this->_template_send = Module::L('form_email/send.xml');
		$this->_email_template = Module::L('form_email/email.xml');
		$this->_fields = array(
			'Name' => new FormInput('Name', null, 'name'),
			'Email' => new FormInput('Email', null, 'email'),
			'Message' => new FormInput('Message', 'area', 'message')
		);
		$this->send = false;
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

			$this->send = true;
			foreach ($this->_fields as $f) if (!$f->Validate()) $this->send = false;

			if (!$this->send) return;

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
		if ($this->send) return $t->ParseFile($this->_template_send);
		return $t->ParseFile($this->_template);
	}

	function TagField($t, $g)
	{
		foreach ($this->_fields as $n => $f)
		{
			$row['name'] = $n;
			$row['field'] = $f->Get();
			$row['fid'] = $f->atrs['ID'];
			if ($f->attr('TYPE') == 'captcha') $row['style'] = ' display: none';
			else $row['style'] = '';
			$rows[$n] = $row;
		}
		return VarParser::Concat($g, $rows, false);
	}

	function TagEmailField($t, $g)
	{
		foreach ($this->_fields as $n => $f)
		{
			if (!$f->IsSignificant()) continue;

			$row['name'] = $n;
			$row['value'] = Server::GetVar($f->name);
			$rows[] = $row;
		}
		return VarParser::Concat($g, $rows);
	}
}

?>
