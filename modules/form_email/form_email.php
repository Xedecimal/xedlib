<?php

require_once(dirname(__FILE__).'/../../classes/present/form.php');
require_once(dirname(__FILE__).'/../../classes/present/form_input.php');

class FormEmail extends Module
{
	public $Name = 'email';
	public $Title = 'Contact Form';
	protected $_template;
	protected $_from = 'email';
	protected $_from_name = 'name';
	protected $_to = 'nobody@nowhere.com';
	protected $_source = 'form';

	function __construct()
	{
		$this->CheckActive($this->Name);
		$this->_template = Module::L('form_email/form.xml');
		$this->_subject = 'Web Contact Submission';
		$this->_template_send = Module::L('form_email/send.xml');
		$this->_email_template = Module::L('form_email/email.xml');

		$this->_data = $_POST[$this->_source];

		$this->_fields = array(
			'Name' => new FormInput('Name', null, 'form[name]', $this->_data['name'], array('class' => 'required')),
			'Email' => new FormInput('Email', null, 'form[email]', $this->_data['email']),
			'City' => new FormInput('City', null, 'form[city]', $this->_data['city']),
			'State' => new FormInput('State', null, 'form[state]', $this->_data['state']),
			'Zip' => new FormInput('Zip', null, 'form[zip]', $this->_data['zip']),
			'Phone' => new FormInput('Phone', null, 'form[phone]', $this->_data['phone']),
			'Message' => new FormInput('Message', 'area', 'form[message]', $this->_data['message'], array('rows' => 10, 'style' => 'width: 100%')),
			'' => new FormInput(null, 'captcha', 'c')
		);
		$this->send = false;
	}

	function Prepare()
	{
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'send')
		{
			$this->GenerateMail(true);
		}
	}

	function Get()
	{
		global $_d;

		if (!$this->Active) return;

		$t = new Template($_d);
		$t->Behavior->Bleed = false;
		$t->ReWrite('input', array('Form', 'TagInput'));
		$t->ReWrite('field', array(&$this, 'TagField'));
		$t->Set('Name', $this->GetName());
		if ($this->send)
			return array($this->GetName() => $t->ParseFile($this->_template_send));
		return array($this->GetName() => $t->ParseFile($this->_template));
	}

	function GenerateMail($send = false)
	{
		$this->_data = Server::GetVar($this->_source);
		$t = new Template();
		$t->use_getvar = true;

		if (strpos($this->_from, '@') === false)
		{
			$this->_from = $this->_data[$this->_from];
			$this->_from_name = $this->_data[$this->_from_name];
		}

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

		$preg = '/'.$this->_source.'\[([^\]]+)\]/';

		# Process code fields.
		foreach ($this->_fields as $text => $f)
		{
			preg_match($preg, $f->atrs['NAME'], $m);

			if (!empty($f->atrs['CLASS']))
			{
				if (array_search('required', explode(' ', $f->atrs['CLASS'])) !== false)
				{
					if (empty($this->_data[$m[1]]))
					{
						$this->send = false;
						$this->error = 'You are missing fields.';
					}
				}
			}

			$this->_labels[$f->atrs['NAME']] = $text;
			$this->_inputs[$f->atrs['NAME']] = $f->atrs['NAME'];
			if ($f->atrs['TYPE'] == 'file')
			{
				$this->_attach = true;
				$file = $_FILES[$f->atrs['NAME']];
				$this->_files[] = $file;
			}
		}

		$t->ReWrite('field', array(&$this, 'TagEmailField'));
		$this->body = $t->ParseFile($this->_email_template);

		if ($send)
		{
			if (@$this->_attach)
			{
				$args['to'] = $this->_to;
				$args['subject'] = $this->_subject;
				$args['body'] = $this->body;
				$args['files'] = $this->_files;
				$args['from-name'] = $this->_from_name;
				$args['from-email'] = $this->_from;
				U::MailWithAttachment($args);
			}
			else mail($this->_to, $this->_subject, $this->body, implode($headers, "\r\n"));
		}

		if (!empty($this->debug))
		{
			var_dump($headers);
			var_dump("Subject: {$this->_subject}");
			echo "Body: <pre>$this->body</pre>";
			die();
		}
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
		{
			if (!preg_match($preg, $n, $m)) continue;
			if (!isset($this->_labels[$i])) continue;

			$row = array();

			# Repeating Value
			if (is_array(@$this->_data[$m[1]]))
			{
				$l = $this->_labels[$i];

				$row['name'] = $l;
				$row['value'] = '';

				foreach ($this->_data[$m[1]] as $ix => $val) $row['value'] .= ' '.$val;

				$rows[] = $row;
			}
			else # Non-repeating value
			{
				$row['name'] = $this->_labels[$i];
				$row['value'] = @$this->_data[$m[1]];
				$rows[] = $row;
			}
		}

		return VarParser::Concat($g, $rows);
	}
}

?>
