<?php

require_once(dirname(__FILE__).'/../hm.php');
require_once(dirname(__FILE__).'/../layered_output.php');
require_once(dirname(__FILE__).'/form_input.php');
require_once(dirname(__FILE__).'/template.php');

/**
 * A web page form, with functions for easy field creation and layout.
 * @todo Create sub classes for each input type.
 */
class Form extends LayeredOutput
{
	/**
	 * Unique name of this form (used in html / js / identifying).
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Hidden fields stored from AddHidden()
	 *
	 * @var array
	 */
	private $hiddens;

	/**
	 * Form tag attributes, "name" => "value" pairs.
	 *
	 * @var array
	 */
	public $attribs;

	/**
	 * Actual output.
	 *
	 * @var string
	 */
	public $out;

	/**
	 * Whether to use persistant vars or not.
	 *
	 * @var bool
	 */
	public $Persist;

	/**
	 * Associated validator. Make sure you set this BEFORE you use AddInput or
	 * they will not be prepared for validation. You can also specify an array
	 * as $form->Validation = array($val1, $val2, $val3);
	 * @var Validation
	 */
	public $Validation;

	/**
	 * Associated errors that are previously gotten with FormValidate().
	 *
	 * @var array
	 */
	public $Errors;

	/**
	 * @var string
	 */
	public $FormStart = '<table>';

	/**
	 * @var string
	 */
	public $RowStart = array('<tr class="even">', '<tr class="odd">');

	public $FirstStart = '<td align="right">';

	public $FirstEnd = '</td>';

	public $StringStart = '<td colspan="2">';

	/**
	 * @var string
	 */
	public $CellStart = '<td>';

	/**
	 * @var string
	 */
	public $CellEnd = '</td>';

	/**
	 * @var string
	 */
	public $RowEnd = '</tr>';

	/**
	 * @var string
	 */
	public $FormEnd = '</table>';

	/**
	 * @var array
	 */
	public $words = array(
		'complete', 'finish', 'end', 'information'
	);

	public $rowx = 0;

	private $multipart = false;

	/**
	* Instantiates this form with a unique name.
	* @param string $name Unique name only used in Html comments for identification.
	* @param array $colAttribs Array of table's column attributes.
	* @param bool $persist Whether or not to persist the values in this form.
	*/
	function __construct($name, $persist = true)
	{
		parent::__construct();
		$this->name = $name;
		$this->attribs = array();
		$this->Persist = $persist;
		$this->Template = file_get_contents(dirname(__FILE__).'/../../temps/form.xml');
	}

	/**
	* Adds a hidden field to this form.
	* @param string $name The name attribute of the html field.
	* @param mixed $value The value attribute of the html field.
	* @param string $attribs Attributes to append on this field.
	* @param bool $general Whether this is a general name. It will not
	* have the form name prefixed on it.
	*/
	function AddHidden($name, $value, $attribs = null, $general = false)
	{
		$this->hiddens[] = array($name, $value, $attribs, $general);
	}

	/**
	 * Adds an input item to this form. You can use a single FormInput object,
	 * a string, an array or a series of arguments of strings and FormInputs and
	 * this will try to sort it all out vertically or horizontally.
	 */
	function AddInput()
	{
		if (func_num_args() < 1) Error("Not enough arguments.");
		$args = func_get_args();

		if (!empty($args))
		{
			$this->out .= $this->RowStart[$this->rowx++%2];
			foreach ($args as $ix => $item)
				$this->out .= $this->IterateInput($ix == 0, $item);
			$this->out .= $this->RowEnd;
		}
	}

	/**
	 * @param mixed $input FormInput, multiple FormInputs, arrays, whatever.
	 * @return string Rendered input field.
	 */
	function IterateInput($start, $input)
	{
		if (is_array($input))
		{
			if (empty($input)) return;
			$out = null;
			foreach ($input as $item)
			{
				$out .= $this->IterateInput($start, $item);
				$start = false;
			}
			return $out;
		}

		$helptext = null;

		if (is_string($input))
		{
			$this->inputs[] = $input;
			return $this->StringStart.$input.
				($start ? $this->FirstEnd : $this->CellEnd);
		}

		if (!is_object($input)) Error("Form input is not an object.");

		$this->inputs[] = $input;

		if ($input->attr('TYPE') == 'submit' && isset($this->Validation))
			$input->atrs['ONCLICK'] = "return {$this->name}_check(1);";
		if ($input->attr('TYPE') == 'file') $this->multipart = true;

		$right = false;
		if ($input->attr('TYPE') == 'checkbox') $right = true;
		if ($input->attr('TYPE') == 'spamblock')
		{
			//This form has been submitted.
			$b = Server::GetVar('block_'.$input->name);
			if (isset($b) && Server::GetVar($input->name) != $this->words[$b])
				$this->Errors[$input->name] = ' '.GetImg('error.png', 'Error',
					'style="vertical-align: text-bottom"').
					"<span class=\"error\"> Invalid phrase.</span>";
			$rand = rand(0, count($this->words)-1);
			$input->valu = $this->words[$rand];
			$this->AddHidden('block_'.$input->name, $rand);
		}

		$out = !empty($input->text)?$input->text:null;

		$helptext = $input->help;
		if (isset($this->Errors[$input->name]))
			$helptext .= $this->Errors[$input->name];

		return ($start ? $this->FirstStart : $this->CellStart).
			($input->labl ? '<label for="'.CleanID($this->name, $input)
			.'">' : null).
			($right ? null : $out).
			($input->labl ? '</label>' : null).$this->CellEnd.
			$this->CellStart.$input->Get($this->name, $this->Persist).
			($right ? $out : null).$helptext.
			($start ? $this->FirstEnd : $this->CellEnd);
	}

	function TagField($t, $g)
	{
		$ret = '';
		$tt = new Template();
		$tt->ReWrite('error', array(&$this, 'TagError'));

		$ix = 0;
		if (!empty($this->inputs))
		foreach ($this->inputs as $in)
		{
			$d['even_odd'] = ($ix++ % 2) ? 'even' : 'odd';
			$d['text'] = !empty($in->text) ? $in->text : '';
			$d['id'] = '';
			if (is_object($in) && strtolower(get_class($in)) == 'forminput')
			{
				$d['id'] = HM::CleanID($in->atrs['ID']);
				$d['field'] = $in->Get($this->name);
				$d['help'] = $in->help;
			}
			else
			{
				$d['field'] = $in;
				$d['help'] = '';
			}

			$this->d = $d;
			$tt->Set($d);
			$ret .= $tt->GetString($g);
		}
		return $ret;
	}

	function TagError($t, $g)
	{
		if (!empty($this->d['help']))
		{
			$vp = new VarParser();
			return $vp->ParseVars($g, $this->d);
		}
	}

	/**
	* Returns the complete html rendered form for output purposes.
	* @param string $formAttribs Additional form attributes (method, class, action, etc)
	* @param string $tblAttribs To be passed to Table::GetTable()
	* @return string The complete html rendered form.
	*/
	function Get($formAttribs = null)
	{
		global $_d;

		$this->formAttribs = $formAttribs;
		$t = new Template($_d);
		$t->Set('form_name', $this->name);
		$t->ReWrite('form', array(&$this, 'TagForm'));
		$t->ReWrite('field', array(&$this, 'TagField'));
		return $t->GetString($this->Template);
	}

	/**
	 * Returns the properly scripted up submit button, should be used in place
	 * of AddInput(null, 'submit').
	 *
	 * @param string $name Name of this button.
	 * @param string $text Text displayed on this button.
	 * @return string
	 */
	function GetSubmitButton($name, $text)
	{
		$ret = '<input type="submit" name="'.$name.'" class="btn btn-primary" value="'.$text.'"';
		if (isset($this->Validation))
			$ret .= " onclick=\"return {$this->name}_check(1);\"";
		return $ret.' /> ';
	}

	/**
	 * Rewrites inputs into FormInputs for further processing.
	 *
	 * @param Template $t
	 * @param string $guts
	 * @param array $attribs
	 * @param string $tag
	 * @param mixed $args
	 * @return string
	 */
	static function TagInput($t, $guts, $attribs, $tag, $args)
	{
		// Handle Persistent Values

		if ($args['persist'])
		{
			switch (strtolower($attribs['TYPE']))
			{
				case 'radio':
					if (Server::GetVar($attribs['NAME']) == $attribs['VALUE'])
						$attribs['CHECKED'] = 'checked';
					break;
				default:
					if (!isset($attribs['VALUE']))
					$attribs['VALUE'] = Server::GetVar($attribs['NAME']);
					break;
			}
		}

		$searchable = false;

		if (!empty($attribs['TYPE']))
		{
			$searchable =
				$attribs['TYPE'] != 'hidden' &&
				$attribs['TYPE'] != 'radio' &&
				$attribs['TYPE'] != 'checkbox' &&
				$attribs['TYPE'] != 'submit';

			$fi = new FormInput(null, @$attribs['TYPE'], @$attribs['NAME'],
				@$attribs['VALUE'], $attribs);
			if (get_class($t->GetCurrentObject()) == 'Form')
				$t->GetCurrentObject()->AddInput($fi);
			return $fi->Get(null, false);
		}

		$ret = '';

		if ($args == 'search' && $searchable)
		{
			if (!isset($attribs['ID'])) $attribs['ID'] = 'in'.$attribs['NAME'];

			$ret .= "<input name=\"search[{$attribs['NAME']}]\" type=\"checkbox\"
				onclick=\"$('#div{$attribs['ID']}').toggle('slow');\" />";
			$ret .= '<div id="div'.$attribs['ID'].'" style="display: none">';
		}

		$ret .= $field;

		if ($args == 'search' && $searchable) $ret .= '</div>';
		return $ret;
	}

	/**
	* Rewriting form tag to add additional functionality.
	*
	* @param Template $t
	* @param string $g
	* @param array $a
	*/
	function TagForm($t, $g, $a)
	{
		global $PERSISTS;
		$frm = new Form(@$a['ID']);
		$t->Push($frm);
		$ret = '<form'.HM::GetAttribs($a).HM::GetAttribs($this->formAttribs).'>';
		if (is_array($PERSISTS))
		foreach ($PERSISTS as $n => $v)
			$ret .= '<input type="hidden" name="'.$n.'" value="'.$v.'" />';
		if (is_array($this->hiddens))
		foreach ($this->hiddens as $h)
			$ret .= '<input type="hidden" name="'.$h[0].'" value="'.$h[1].'" />';
		$t->ReWrite('input', array('Form', 'TagInput'));
		$ret .= $t->GetString('<null>'.$g.'</null>');
		$obj = $t->Pop();
		$ret .= $obj->outs[0];
		$ret .= '</form>';

		if (!empty($frm->inputs))
		foreach ($frm->inputs as $in)
		{
			if (!empty($in->valid))
			{
				require_once('a_validation.php');
				$ret .= Validation::GetJS($frm);
				break;
			}
		}

		return $ret;
	}
}

?>
