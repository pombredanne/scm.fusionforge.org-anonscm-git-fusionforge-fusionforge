<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2011, Roland Mas
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012-2014, Franck Villaume - TrivialDev
 * http://fusionforge.org
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/* please do not add require here : use www/docman/index.php to add require */
/* global variables used */
global $g; // group object
global $group_id; // id of the group
global $dirid; //id of the doc_group
global $dm; // the Document Manager object

// plugin projects-hierarchy
$actionurl = '?group_id='.$group_id.'&action=addfile&dirid='.$dirid;
$redirecturl = '/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid;
if (isset($childgroup_id) && $childgroup_id) {
	$g = group_get_object($childgroup_id);
	$actionurl .= '&childgroup_id='.$childgroup_id;
	$redirecturl .= '&childgroup_id='.$childgroup_id;
}

if (!$dm)
	$dm = new DocumentManager($g);

$dgf = new DocumentGroupFactory($g);
if ($dgf->isError())
	exit_error($dgf->getErrorMessage(), 'docman');

if (!forge_check_perm('docman', $group_id, 'submit')) {
	$return_msg = _('Document Manager Action Denied.');
	session_redirect($redirecturl.'&warning_msg='.urlencode($return_msg));
}
?>

<script type="text/javascript">//<![CDATA[
var controllerAddFile;

jQuery(document).ready(function() {
	controllerAddFile = new DocManAddFileController({
		fileRow:		jQuery('#filerow'),
		urlRow:			jQuery('#urlrow'),
		pathRow:		jQuery('#pathrow'),
		editRow:		jQuery('#editrow'),
		editNameRow:		jQuery('#editnamerow'),
		buttonFile:		jQuery('#buttonFile'),
		buttonUrl:		jQuery('#buttonUrl'),
		buttonManualUpload:	jQuery('#buttonManualUpload'),
		buttonEditor:		jQuery('#buttonEditor')
	});
});

//]]></script>
<?php
echo html_ao('div', array('class' => 'docmanDivIncluded'));
if ($dgf->getNested() == NULL) {
	$dg = new DocumentGroup($g);

	if ($dg->isError())
		session_redirect('/docman/?group_id='.$group_id.'&error_msg='.urlencode($dg->getErrorMessage()));

	if ($dg->create('Uncategorized Submissions')) {
		session_redirect('/docman/?group_id='.$group_id.'&view=additem');
	}

	echo html_e('div', array('class' => 'warning'), _('You MUST first create at least one folder to store your document.'), false);
} else {
	/* display the add new documentation form */
	echo html_ao('p');
	echo html_e('strong', array(), _('Document Title')._(': ')._('Refers to the relatively brief title of the document (e.g. How to use the download server).'), false);
	echo html_ac(html_ap() - 1);
	echo html_ao('p');
	echo html_e('strong', array(), _('Description')._(': ')._('A brief description to be placed just under the title.'), false);
	echo html_ac(html_ap() - 1);
	if ($g->useDocmanSearch())
		echo html_e('p', array(), _('Both fields are used by the document search engine.'), false);

	echo html_ao('form', array('name' => 'adddata', 'action' => $actionurl, 'method' => 'post', 'enctype' => 'multipart/form-data'));
	echo html_ao('table', array('class' => 'infotable'));
	echo html_ao('tr');
	echo html_e('td', array(), _('Document Title').utils_requiredField(), false);
	echo html_ao('td');
	echo html_e('input', array('pattern' => '.{5,}', 'placeholder' => _('Document Title'), 'title' => sprintf(_('(at least %s characters)'), 5), 'type' => 'text', 'name' => 'title', 'size' => '40', 'maxlength' => '255', 'required' => 'required'));
	echo html_e('span', array(), sprintf(_('(at least %s characters)'), 5), false);
	echo html_ac(html_ap() - 2);
	echo html_ao('tr');
	echo html_e('td', array(), _('Description') .utils_requiredField(), false);
	echo html_ao('td');
	echo html_e('input', array('pattern' => '.{10,}', 'placeholder' => _('Description'), 'title' => sprintf(_('(at least %s characters)'), 10), 'type' => 'text', 'name' => 'description', 'size' => '50', 'maxlength' => '255', 'required' => 'required'));
	echo html_e('span', array(), sprintf(_('(at least %s characters)'), 10), false);
	echo html_ac(html_ap() - 2);
	echo html_ao('tr');
	echo html_e('td', array(), _('Type of Document') .utils_requiredField());
	echo html_ao('td');
	echo html_e('input', array('type' => 'radio', 'id' => 'buttonFile', 'name' => 'type', 'value' => 'httpupload', 'checked' => 'checked', 'required' => 'required')).html_e('span', array(), _('File'), false);
	echo html_e('input', array('type' => 'radio', 'id' => 'buttonUrl', 'name' => 'type', 'value' => 'pasteurl', 'required' => 'required')).html_e('span', array(), _('URL'), false);
	if (forge_get_config('use_manual_uploads')) {
		echo html_e('input', array('type' => 'radio', 'id' => 'buttonManualUpload', 'name' => 'type', 'value' => 'manualupload', 'required' => 'required')).html_e('span', array(), _('Already-uploaded file'), false);
	}
	if ($g->useCreateOnline()) {
		echo html_e('input', array('type' => 'radio', 'id' => 'buttonEditor', 'name' => 'type', 'value' => 'editor', 'required' => 'required')).html_e('span', array(), _('Create online'), false);
	}
	echo html_ac(html_ap() - 2);
	echo html_ao('tr', array('id' => 'filerow'));
	echo html_e('td', array(), _('Upload File').utils_requiredField(), false);
	echo html_ao('td');
	echo html_e('input', array('type' => 'file', 'required' => 'required', 'name' => 'uploaded_data'));
	echo html_e('span', array(), sprintf(_('(max upload size: %s)'), human_readable_bytes(util_get_maxuploadfilesize())), false);
	echo html_ac(html_ap() - 2);
	echo html_ao('tr', array('id' => 'urlrow', 'style' => 'display:none'));
	echo html_e('td', array(), _('URL').utils_requiredField());
	echo html_ao('td');
	echo html_e('input', array('type' => 'url', 'name' => 'file_url', 'size' => '30', 'placeholder' => _('Enter a valid URL'), 'pattern' => 'ftp://.+|https?://.+'));
	echo html_ac(html_ap() - 2);
	if (forge_get_config('use_manual_uploads')) {
		echo html_ao('tr', array('id' => 'pathrow', 'style' => 'display:none'));
		echo html_e('td', array(), _('File') . utils_requiredField(), false);
		echo html_ao('td');
		$incoming = forge_get_config('groupdir_prefix')."/".$g->getUnixName()."/incoming";
		$manual_files_arr = ls($incoming, true);
		if (count($manual_files_arr)) {
			echo html_build_select_box_from_arrays($manual_files_arr, $manual_files_arr, 'manual_path', '');
			echo html_e('br');
			echo html_e('span', array(), sprintf(_('Pick a file already uploaded (by SFTP or SCP) to the <a href="%1$s">project\'s incoming directory</a> (%2$s).'),
								'sftp://'.forge_get_config('web_host').$incoming.'/', $incoming), false);
		} else {
			echo html_e('p', array('class' => 'warning'), printf(_('You need first to upload file in %s'),$incoming), false);
		}
		echo html_ac(html_ap() - 2);
	}
	echo html_ao('tr', array('id' => 'editnamerow', 'style' => 'display:none'));
	echo html_e('td', array(), _('File Name').utils_requiredField(), false);
	echo html_ao('td');
	echo html_e('input', array('type' => 'text', 'name' => 'name', 'size' => '30'));
	echo html_ac(html_ap() - 2);
	echo html_ao('tr', array('id' => 'editrow', 'style' => 'display:none'));
	echo html_ao('td', array('colspan' => '2'));
	$GLOBALS['editor_was_set_up'] = false;
	$params = array() ;
	/* name must be details !!! if name = data then nothing is displayed */
	$params['name'] = 'details';
	$params['height'] = "300";
	$params['body'] = "";
	$params['group'] = $group_id;
	plugin_hook("text_editor", $params);
	if (!$GLOBALS['editor_was_set_up']) {
		echo '<textarea name="details" rows="5" cols="80"></textarea>';
	}
	unset($GLOBALS['editor_was_set_up']);
	echo html_ac(html_ap() - 2);
	if ($dirid) {
		echo html_ao('tr');
		echo html_ao('td', array('colspan' => 2));
		echo html_e('input', array('type' => 'hidden', 'name' => 'doc_group', 'value' => $dirid));
		echo html_ac(html_ap() - 2);
	} else {
		echo html_ao('tr');
		echo html_e('td', array(), _('Documents folder that document belongs in'), false);
		echo html_ao('td');
		$dm->showSelectNestedGroups($dgf->getNested(), 'doc_group', false, $dirid);
		echo html_ac(html_ap() - 2);
	}
	if (forge_check_perm('docman', $group_id, 'approve')) {
		echo html_ao('tr');
		echo html_e('td', array(), _('Status of that document'), false);
		echo html_ao('td');
		doc_get_state_box('xzxz', 2); /**no direct deleted status */
		echo html_ac(html_ap() - 2);
	}
	echo html_ac(html_ap() - 1);
	echo html_eo('p', array(), printf(_('Fields marked with %s are mandatory.'), utils_requiredField()));
	echo html_ao('div', array('class' => 'docmanSubmitDiv'));
	echo html_e('input', array('type' => 'submit', 'name' => 'submit', 'value' => _('Submit Information')));
	echo html_ac(html_ap() - 2);
}
echo html_ac(html_ap() - 1);
