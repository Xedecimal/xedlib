<?php

require_once(dirname(__FILE__).'/../../classes/present/form.php');
require_once(dirname(__FILE__).'/../../classes/present/form_input.php');

class FormEmail extends Module
{
	public $Name = 'email';
	public $Title = 'Contact Form';
	protected $_template;
	protected $_from = 'nobody@nowhere.com';
	protected $_to = 'nobody@nowhere.com';
	protected $_source = 'form';

	function __construct()
	{
		$this->CheckActive($this->Name);
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
			$this->_data = Server::GetVar($this->_source);
			$t = new Template();
			$t->use_getvar = true;

			$headers[] = 'From: '.$this->_from;
			$headers[] = 'Reply-To: '.$this->_from;

			$this->send = true;

			# Handle Captcha
			$c = Server::GetVar('c');
			if (!empty($c)) $this->send = false;

			if (!$this->send) return;

			$sx = simplexml_load_string(file_get_contents($this->_template));

			# Find all label text
			foreach ($sx->xpath('//label[@for]') as $id)
				$this->_labels[(string)$id['for']] = (string)$id;
			# Find all elements with an id
			foreach ($sx->xpath('//*[@id]') as $in)
				$this->_inputs[(string)$in['id']] = (string)$in['name'];

			$t->ReWrite('field', array(&$this, 'TagEmailField'));
			$body = $t->ParseFile($this->_email_template);

			if (!empty($this->debug))
			{
				var_dump("To: {$this->_to}");
				var_dump("Subject: {$this->_subject}");
				var_dump($headers);
				echo "<pre>$body</pre>";
				die();
			}

			mail($this->_to, $this->_subject, $body, implode($headers, "\r\n"));
		}
	}

	function Get()
	{
		if (!$this->Active) return;
		$t = new Template($this);
		$t->ReWrite('input', array('Form', 'TagInput'));
		$t->ReWrite('field', array(&$this, 'TagField'));
		if ($this->send) return $t->ParseFile($this->_template_send);
		return $t->ParseFile($this->_template);
	}

	function TagField($t, $g)
	{
		foreach ($this->_fields as $n => $f)
		{
			$row['name'] = $n;
			$row['field'] = $f->Get();
			$row['fid'] = @$f->atrs['ID'];
			if ($f->attr('TYPE') == 'captcha') $row['style'] = ' display: none';
			else $row['style'] = !empty($this->_styles[$n]) ? $this->_styles[$n] : '';
			$rows[$n] = $row;
		}
		return VarParser::Concat($g, $rows, false);
	}

	function TagEmailField($t, $g)
	{
		# Preg out and find all elements we're working with

		$preg = '/'.$this->_source.'\[([^\]]+)\]/';
		$rows = array();
		foreach ($this->_inputs as $i => $n)
		//foreach ($this->_fields as $n => $f)
		{
			if (!preg_match($preg, $n, $m)) continue;

			$row['name'] = $this->_labels[$i];
			$row['value'] = $this->_data[$m[1]];

			$rows[] = $row;
		}

		return VarParser::Concat($g, $rows);
	}
}

?>
