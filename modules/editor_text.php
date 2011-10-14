<?php

require_once(dirname(__FILE__).'/../classes/present/form.php');

class EditorText extends Module
{
	public $Name = 'text';
	protected $item;

	function Prepare()
	{
		$this->CheckActive($this->Name);
		if (!$this->Active) return;

		global $_d;

		if (@$_d['q'][1] == 'update')
		{
			$this->item = File::SecurePath(Server::GetVar($this->Name.'_ci'));
			file_put_contents($this->item, Server::GetVar($this->Name.'_body'));
		}
	}

	function Get()
	{
		if (!$this->Active) return;

		$frmRet = new Form($this->Name);
		$frmRet->AddHidden($this->Name.'_action', 'update');
		$frmRet->AddHidden($this->Name.'_ci', $this->item);

		$frmRet->AddInput(new FormInput(null, 'area', $this->Name.'_body',
			@file_get_contents($this->item),
				array('ROWS' => 30, 'COLS' => 30, 'style' => 'width: 100%')));
		$frmRet->AddInput(new FormInput(null, 'submit', 'butSubmit', 'Update'));

		$ret['head'] = <<< EOD
<script language="javascript" type="text/javascript" src="{{app_abs}}/js/tiny_mce/tiny_mce.js"></script>
<script language="javascript" type="text/javascript" src="{{app_abs}}/js/tiny_mce/jquery.tinymce.js"></script>
<script type="text/javascript">
$(function () {
	$('textarea').tinymce({
		// Location of TinyMCE script
		script_url : 'js/tiny_mce/tiny_mce.js',

		// General options
		theme : "advanced",
		plugins : "pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

		// Theme options
		theme_advanced_buttons1 : "save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
		theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
		theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
		theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak",
		theme_advanced_toolbar_location : "top",
		theme_advanced_toolbar_align : "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resizing : true,

		// Drop lists for link/image/media/template dialogs
		template_external_list_url : "lists/template_list.js",
		external_link_list_url : "lists/link_list.js",
		external_image_list_url : "lists/image_list.js",
		media_external_list_url : "lists/media_list.js",

		save_onsavecallback : "inline_mce_save"
	});
});
</script>
EOD;

		$ret['default'] = $frmRet->Get('method="post" action="{{app_abs}}/'.$this->Name.'/update"');
		return $ret;
	}
}

?>
