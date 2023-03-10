<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright (C) 2010 Alcatel-Lucent
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012-2017,2021, Franck Villaume - TrivialDev
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
global $group_id; // id of the group
global $dirid; // id of doc_group
global $HTML; // Layout object
global $LUSER; // User object
global $g; // the Group object
global $dm; // the docman manager
global $warning_msg;
global $start; // use to set the offset
global $childgroup_id;

$linkmenu = 'listfile';
$baseredirecturl = DOCMAN_BASEURL.$group_id;
$redirecturl = $baseredirecturl.'&view='.$linkmenu.'&dirid='.$dirid;

echo html_ao('div', array('id' => 'leftdiv'));
include ($gfcommon.'docman/views/tree.php');
echo html_ac(html_ap() - 1);

// plugin projects-hierarchy support
if ($childgroup_id) {
	$redirecturl .= '&childgroup_id='.$childgroup_id;
	$g = group_get_object($childgroup_id);
}

if (!forge_check_perm('docman', $g->getID(), 'read')) {
	$warning_msg = _('Document Manager Access Denied');
	session_redirect($baseredirecturl);
}

if (session_loggedin()) {
	if (getStringFromRequest('setpaging')) {
		/* store paging preferences */
		$paging = getIntFromRequest('nres');
		if (!$paging) {
			$paging = 25;
		}
		$LUSER->setPreference('paging', $paging);
	}
	/* logged in users get configurable paging */
	$paging = $LUSER->getPreference('paging');
}

if(!isset($paging) || !$paging) {
	$paging = 25;
}
$df = new DocumentFactory($g);
if ($df->isError()) {
	exit_error($df->getErrorMessage(), 'docman');
}
$dgf = new DocumentGroupFactory($g);
if ($dgf->isError()) {
	exit_error($dgf->getErrorMessage(), 'docman');
}
$stateidArr = array(1);
$stateIdDg = 1;
if (forge_check_perm('docman', $g->getID(), 'approve')) {
	$stateidArr[] = 5;
	$stateIdDg = 5;
}

$df->setLimit($paging);
$df->setOffset($start);
$df->setDocGroupID($dirid);
//active, hidden & private state ids
$df->setStateID(array(1, 4, 5));
$df->setDocGroupState($stateIdDg);
$d_arr = $df->getDocuments();
$nested_groups = $dgf->getNested($stateidArr);

$nested_docs = array();
$DocGroupName = 0;
$dgpath = '';
if ($dirid) {
	$ndg = documentgroup_get_object($dirid, $g->getID());
	if ($ndg->isError()) {
		$error_msg = $ndg->getErrorMessage();
		session_redirect($baseredirecturl, false);
	}
	$DocGroupName = $ndg->getName();
	$dgpath = $ndg->getPath(true, false);
	if (!$DocGroupName) {
		$error_msg = $g->getErrorMessage();
		session_redirect($baseredirecturl, false);
	}
	if (($ndg->getState() != 1 && $ndg->getState() != 5) || !$dgpath) {
		$error_msg = _('Invalid folder');
		session_redirect($baseredirecturl.'&view=listfile', false);
	}
	$nbDocs = $ndg->getNumberOfDocuments(1);
	if (forge_check_perm('docman', $g->getID(), 'approve')) {
		$nbDocs += $ndg->getNumberOfDocuments(3);
		$nbDocs += $ndg->getNumberOfDocuments(4);
		$nbDocs += $ndg->getNumberOfDocuments(5);
	}
}

if (is_array($d_arr) && count($d_arr) > 0) {
	// Get the document groups info
	//put the doc objects into an array keyed off the docgroup
	foreach ($d_arr as $doc) {
		$nested_docs[$doc->getDocGroupID()][] = $doc;
	}
}

$df->setStateID(array(3));
$d_pending_arr = $df->getDocuments(1);

if (is_array($d_pending_arr) && count($d_pending_arr) > 0) {
	// Get the document groups info
	//put the doc objects into an array keyed off the docgroup
	foreach ($d_pending_arr as $doc) {
		$nested_pending_docs[$doc->getDocGroupID()][] = $doc;
	}
}

echo html_ao('div', array('id' => 'rightdiv'));
echo html_ao('script', array('type' => 'text/javascript'));
?>
//<![CDATA[
var controllerListFile;

jQuery(document).ready(function() {
	controllerListFile = new DocManListFileController({
		groupId:		<?php echo $group_id ?>,
		divAddItem:		jQuery('#additem'),
		divEditDirectory:	jQuery('#editdocgroup'),
		divMoveFile:		jQuery('#movefile'),
		buttonAddItem:		jQuery('#docman-additem'),
		buttonTrashDirectory:	jQuery('#docman-trashdirectory'),
		buttonEditDirectory:	jQuery('#docman-editdirectory'),
		docManURL:		'<?php echo util_make_uri('/docman') ?>',
		divLeft:		jQuery('#leftdiv'),
		divRight:		jQuery('#rightdiv'),
		childGroupId:		<?php echo util_ifsetor($childgroup_id, 0) ?>,
		divEditFile:		jQuery('#editFile'),
		divEditTitle:		'<?php echo _('Edit document dialog box') ?>',
		divNotifyUsers:		jQuery('#notifyUsers'),
		divNotifyTitle:		'<?php echo _('Notify selected users dialog box') ?>',
		divNotifySaveButtonTxt:	'<?php echo _('Send') ?>',
		enableResize:		true,
		page:			'listfile',
		docgroupId:		<?php echo $dirid ?>,
		lockIntervalDelay:	60000,
		tableAddVersion:	jQuery('#doc_version_edit'),
		useCreateOnline:	<?php echo $g->useCreateOnline() ?>
	});
});

//]]>
<?php
echo html_ac(html_ap() - 1);
if ($DocGroupName) {
	$headerPath = '';
	if ($childgroup_id) {
		$headerPath .= _('Subproject')._(': ').util_make_link(DOCMAN_BASEURL.$g->getID(), $g->getPublicName()).'::';
	}
	$headerPath .= html_e('em', array(), preg_replace('/\/\//','/', $dgpath.'/'.$DocGroupName), false);
	echo html_e('h2', array('class' => 'docman_h2'), $headerPath, false);
	$max = ($nbDocs > ($start + $paging)) ? ($start + $paging) : $nbDocs;
	echo $HTML->paging_top($start, $paging, $nbDocs, $max, $redirecturl, array('style' => 'display:inline-block'));
	/* should we steal the lock on folder ? */
	if ($ndg->getLocked() && ((session_loggedin() && ($ndg->getLockedBy() == $LUSER->getID())) || ((time() - $ndg->getLockdate()) > 600))) {
		/* if you change the 60000 lockIntervalDelay value, please update here too */
		$ndg->setLock(0);
	}
	if (!$ndg->getLocked()) {
		if (forge_check_perm('docman', $ndg->Group->getID(), 'approve')) {
			echo html_e('input', array('type' => 'hidden', 'id' => 'doc_group_id', 'value' => $ndg->getID()));
			echo util_make_link('#', $HTML->getConfigurePic(_('Edit this folder'), 'edit'), array('id' => 'docman-editdirectory', 'onclick' => 'javascript:controllerListFile.toggleEditDirectoryView()'), true);
			echo util_make_link($redirecturl.'&action=trashdir', $HTML->getDeletePic(_('Move this folder and his content to trash'), 'trashdir'), array('id' => 'docman-trashdirectory'));
			if (!isset($nested_docs[$dirid]) && !isset($nested_groups[$dirid]) && !isset($nested_pending_docs[$dirid])) {
				echo util_make_link($redirecturl.'&action=deldir', $HTML->getRemovePic(_('Permanently delete this folder'), 'deldir'), array('id' => 'docman-deletedirectory'));
			}
		}

		if (forge_check_perm('docman', $group_id, 'submit')) {
			echo util_make_link('#', $HTML->getAddDirPic(_('Add a new item in this folder'), 'additem'), array('id' => 'docman-additem'));
		}
	}

	if ($ndg->hasDocuments($nested_groups, $df)) {
		echo util_make_link('/docman/view.php/'.$ndg->Group->getID().'/zip/full/'.$dirid, html_image('docman/download-directory-zip.png', 22, 22, array('alt' => 'downloadaszip')), array('title' => _('Download this folder as a ZIP')));
	}
	if (session_loggedin()) {
		if ($ndg->isMonitoredBy($LUSER->getID())) {
			$option = 'stop';
			$titleMonitor = _('Stop monitoring this folder');
			$image = $HTML->getStopMonitoringPic($titleMonitor, '');
		} else {
			$option = 'start';
			$titleMonitor = _('Start monitoring this folder');
			$image = $HTML->getStartMonitoringPic($titleMonitor, '');
		}
		echo util_make_link($redirecturl.'&action=monitordirectory&option='.$option.'&directoryid='.$ndg->getID(), $image, array('title' => $titleMonitor));
	}

	if (forge_check_perm('docman', $ndg->Group->getID(), 'approve')) {
		echo html_ao('div', array('class' => 'docman_div_include hide', 'id' => 'editdocgroup'));
		echo html_e('h3', array('class' => 'docman_h3'), _('Edit this folder'), false);
		include ($gfcommon.'docman/views/editdocgroup.php');
		echo html_ac(html_ap() - 1);
	}
	if (forge_check_perm('docman', $ndg->Group->getID(), 'submit')) {
		echo html_ao('div', array('class' => 'docman_div_include hide', 'id' => 'additem'));
		include ($gfcommon.'docman/views/additem.php');
		echo html_ac(html_ap() - 1);
	}
}

// to be used by pendingfiles.php
$edittitle = _('Edit this document, add version');
if (forge_get_config('use_object_associations')) {
	$edittitle .= ', '._('associate to other objects');
}
if (forge_get_config('use_docman_review')) {
	$edittitle .= ', '._('create or comment a review');
}

if (isset($nested_docs[$dirid]) && is_array($nested_docs[$dirid])) {
	$tabletop = array(html_e('input', array('id' => 'checkallactive', 'type' => 'checkbox', 'title' => _('Select / Deselect all documents for massaction'), 'onClick' => 'controllerListFile.checkAll("checkeddocidactive", "active")')), '', 'ID', _('File Name'), _('Title'), _('Description'), _('Author'), _('Last time'), _('Status'), _('Size'), _('View'));
	$classth = array('unsortable', 'unsortable', '', '', '', '', '', '', '', '', '');
	if (forge_check_perm('docman', $ndg->Group->getID(), 'approve')) {
		$tabletop[] = _('Actions');
		$classth[] = 'unsortable';
	}
	echo html_ao('div', array('class' => 'docmanDiv'));
	echo $HTML->listTableTop($tabletop, array(), 'sortable', 'sortable_docman_listfile', $classth);
	$time_new = 604800;
	foreach ($nested_docs[$dirid] as $d) {
		$cells = array();
		/* should we steal the lock on file ? */
		if ($d->getLocked() && (($d->getLockedBy() == $LUSER->getID()) || ((time() - $d->getLockdate()) > 600))) {
			/* if you change the 60000 value below, please update here too */
			$d->setLock(0);
		}
		if (!$d->getLocked() && !$d->getReserved()) {
			$cells[][] = html_e('input', array('type' => 'checkbox', 'value' => $d->getID(), 'class' => 'checkeddocidactive', 'title' => _('Select / Deselect this document for massaction'), 'onClick' => 'controllerListFile.checkgeneral("active")'));
		} else {
			if (session_loggedin() && ($d->getReservedBy() != $LUSER->getID())) {
				$cells[][] = html_e('input', array('type' => 'checkbox', 'name' => 'disabled', 'disabled' => 'disabled'));
			} else {
				$cells[][] = html_e('input', array('type' => 'checkbox', 'value' => $d->getID(), 'class' => 'checkeddocidactive', 'title' => _('Select / Deselect this document for massaction'), 'onClick' => 'controllerListFile.checkgeneral("active")'));
			}
		}
		if ($d->getFileType() == 'URL') {
			$cells[][] =  util_make_link($d->getFileName(), html_image($d->getFileTypeImage(), 22, 22, array('alt' => $d->getFileType())), array('title' => _('Visit this link')), true);
		} else {
			$cells[][] =  util_make_link('/docman/view.php/'.$d->Group->getID().'/'.$d->getID(), html_image($d->getFileTypeImage(), 22, 22, array('alt' => $d->getFileType())), array('title' => _('View this document')));
		}
		$cells[][] = 'D'.$d->getID();
		$nextcell = '';
		if (($d->getUpdated() && $time_new > (time() - $d->getUpdated())) || $time_new > (time() - $d->getCreated())) {
			$nextcell .= $HTML->getNewPic(_('Created or updated since less than 7 days'), 'new').'&nbsp;';
		}
		if ($d->hasValidatedReview()) {
			$nextcell .= $HTML->getTagPic(_('Document reviewed and validated'), 'reviewed').'&nbsp;';
		}
		$cells[] = array($nextcell.$d->getFileName(), 'style' => 'word-wrap: break-word; max-width: 250px;');
		$cells[] = array($d->getName(), 'style' => 'word-wrap: break-word; max-width: 250px;');
		$cells[] = array($d->getDescription(), 'style' => 'word-wrap: break-word; max-width: 250px;');
		$cells[][] =  util_display_user($d->getCreatorUserName(), $d->getCreatorID(), $d->getCreatorRealName());
		if ($d->getUpdated()) {
			$cells[] = array(date(_('Y-m-d H:i'), $d->getUpdated()), 'content' => $d->getUpdated());
		} else {
			$cells[] = array(date(_('Y-m-d H:i'), $d->getCreated()), 'content' => $d->getCreated());
		}
		$nextcell = '';
		if ($d->getReserved()) {
			$nextcell = html_image('docman/document-reserved.png', 22, 22, array('alt' => _('Reserved Document'), 'title' => _('Reserved Document')));
			$reserved_by = $d->getReservedBy();
			if ($reserved_by) {
				$user = user_get_object($reserved_by);
				if (is_object($user)) {
					$cells[][] = $nextcell.' '._('by').' '.util_display_user($user->getUnixName(), $user->getID(), $user->getRealName());
				}
			}
		} else {
			$cells[][] = $d->getStateName();
		}
		if ($d->getFileType() == 'URL') {
			$cells[] = array('--', 'content' => 0);
		} else {
			$cells[] = array(human_readable_bytes($d->getFileSize()), 'content' => $d->getFileSize());
		}
		$cells[][] = $d->getDownload();

		if (forge_check_perm('docman', $g->getID(), 'approve')) {
			$nextcell = '';
			$editfileaction = DOCMAN_BASEURL.$group_id.'&action=editfile&fromview=listfile&dirid='.$d->getDocGroupID();
			$notifyaction = DOCMAN_BASEURL.$group_id.'&action=notifyusers&fromview=listfile&dirid='.$d->getDocGroupID();
			if ($childgroup_id) {
				$editfileaction .= '&childgroup_id='.$childgroup_id;
				$notifyaction .= '&childgroup_id='.$childgroup_id;
			}
			if (!$d->getLocked() && !$d->getReserved()) {
				$nextcell .= util_make_link($redirecturl.'&action=trashfile&fileid='.$d->getID(), $HTML->getDeletePic(_('Move this document to trash'), 'delfile'));

				$nextcell .= util_make_link('#', $HTML->getEditFilePic($edittitle, 'editdocument'), array('onclick' => 'javascript:controllerListFile.toggleEditFileView({action:\''.util_make_uri($editfileaction).'\', lockIntervalDelay: 60000, childGroupId: '.util_ifsetor($childgroup_id, 0).' , id:'.$d->getID().', groupId:'.$d->Group->getID().', docgroupId:'.$d->getDocGroupID().', statusId:'.$d->getStateID().', statusDict:'.$dm->getStatusNameList('json').', docgroupDict:'.$dm->getDocGroupList($nested_groups, 'json').', isText:\''.$d->isText().'\', isHtml:\''.$d->isHtml().'\', docManURL:\''.util_make_uri('/docman').'\'})'), true);
				if (session_loggedin()) {
					$nextcell .= util_make_link($redirecturl.'&action=reservefile&fileid='.$d->getID(), html_image('docman/reserve-document.png', 22, 22, array('alt' => _('Reserve this document'))), array('title' => _('Reserve this document for later edition')));
				}
			} else {
				if (session_loggedin() && $d->getReservedBy() != $LUSER->getID()) {
					if (forge_check_perm('docman', $ndg->Group->getID(), 'admin')) {
						$nextcell .= util_make_link($redirecturl.'&action=enforcereserve&fileid='.$d->getID(), html_image('docman/enforce-document.png',22,22,array('alt'=>_('Enforce reservation'))), array('title' => _('Enforce reservation')));
					}
				} else {
					$nextcell .= util_make_link($redirecturl.'&action=trashfile&fileid='.$d->getID(), $HTML->getDeletePic(_('Move this document to trash'), 'delfile'));
					$nextcell .= util_make_link('#', $HTML->getEditFilePic($edittitle, 'editdocument'), array('onclick' => 'javascript:controllerListFile.toggleEditFileView({action:\''.util_make_uri($editfileaction).'\', lockIntervalDelay: 60000, childGroupId: '.util_ifsetor($childgroup_id, 0).' , id:'.$d->getID().', groupId:'.$d->Group->getID().', docgroupId:'.$d->getDocGroupID().', statusId:'.$d->getStateID().', statusDict:'.$dm->getStatusNameList('json').', docgroupDict:'.$dm->getDocGroupList($nested_groups, 'json').', isText:\''.$d->isText().'\', isHtml:\''.$d->isHtml().'\', docManURL:\''.util_make_uri('/docman').'\'})'), true);
					$nextcell .= util_make_link($redirecturl.'&action=releasefile&fileid='.$d->getID(), html_image('docman/release-document.png', 22, 22, array('alt' => _('Release reservation'))), array('title' => _('Release reservation')));
				}
			}
			if (session_loggedin()) {
				if ($d->isMonitoredBy($LUSER->getID())) {
					$option = 'stop';
					$titleMonitor = _('Stop monitoring this document');
					$image = $HTML->getStopMonitoringPic($titleMonitor, '');
				} else {
					$option = 'start';
					$titleMonitor = _('Start monitoring this document');
					$image = $HTML->getStartMonitoringPic($titleMonitor, '');
				}
				$nextcell .= util_make_link($redirecturl.'&action=monitorfile&option='.$option.'&fileid='.$d->getID(), $image, array('title' => $titleMonitor));
			}
			$nextcell .= util_make_link('#', $HTML->getMailNotifyPic(_('Notify users'), 'notifyusers'), array('onclick' => 'javascript:controllerListFile.toggleNotifyUserView({action:\''.util_make_uri($notifyaction).'\', lockIntervalDelay: 60000, childGroupId: '.util_ifsetor($childgroup_id, 0).' , id:'.$d->getID().', groupId:'.$d->Group->getID().', docgroupId:'.$d->getDocGroupID().', title:\''.json_encode($d->getName(), JSON_HEX_APOS).'\', filename:\''.addslashes($d->getFileName()).'\', description:\''.json_encode($d->getDescription(), JSON_HEX_APOS).'\', isURL:\''.$d->isURL().'\', isText:\''.$d->isText().'\', isHtml:\''.$d->isHtml().'\', docManURL:\''.util_make_uri('/docman').'\'})', 'title' => _('Notify users')), true);
			$cells[][] = $nextcell;
		}
		echo $HTML->multiTableRow(array(), $cells);
	}
	echo $HTML->listTableBottom();
	echo html_ao('span', array('id' => 'massactionactive', 'class' => 'hide'));
	echo html_e('span', array('id' => 'docman-massactionmessage', 'title' => _('Actions availables for selected documents, you need to check at least one document to get actions')), _('Mass actions for selected documents')._(':'), false);
	if (forge_check_perm('docman', $ndg->Group->getID(), 'approve')) {
		echo util_make_link('#', $HTML->getDeletePic(_('Move to trash'), ''), array('onclick' => 'window.location.href=\''.util_make_uri($redirecturl.'&action=trashfile&fileid=\'+controllerListFile.buildUrlByCheckbox("active")')), true);
		if (session_loggedin()) {
			echo util_make_link('#', html_image('docman/reserve-document.png', 22, 22, array('alt' => _('Reserve'))), array('onclick' => 'window.location.href=\''.util_make_uri($redirecturl.'&action=reservefile&fileid=\'+controllerListFile.buildUrlByCheckbox("active")'), 'title' => _('Reserve for later edition')), true);
			echo util_make_link('#', html_image('docman/release-document.png', 22, 22, array('alt' => _('Release reservation'))) , array('onclick' => 'window.location.href=\''.util_make_uri($redirecturl.'&action=releasefile&fileid=\'+controllerListFile.buildUrlByCheckbox("active")'), 'title' => _('Release reservation')), true);
			echo util_make_link('#', $HTML->getStartMonitoringPic(_('Start monitoring'), ''), array('onclick' => 'window.location.href=\''.util_make_uri($redirecturl.'&action=monitorfile&option=start&fileid=\'+controllerListFile.buildUrlByCheckbox("active")')), true);
			echo util_make_link('#', $HTML->getStopMonitoringPic(_('Stop monitoring'), ''), array('onclick' => 'window.location.href=\''.util_make_uri($redirecturl.'&action=monitorfile&option=stop&fileid=\'+controllerListFile.buildUrlByCheckbox("active")')), true);
			echo util_make_link('#', html_image('docman/move-document.png', 22, 22, array('alt' => _('Move files to another folder'))), array('onclick' => 'javascript:controllerListFile.toggleMoveFileView({})', 'title' => _('Move files to another folder')), true);
		}
	}
	echo util_make_link('#', html_image('docman/download-directory-zip.png', 22, 22, array('alt' => _('Download as a ZIP'))) , array('onclick' => 'window.location.href=\''.util_make_uri('/docman/view.php/'.$g->getID().'/zip/selected/'.$dirid.'/\'+controllerListFile.buildUrlByCheckbox("active")'), 'title' => _('Download as a ZIP')), true);
	echo html_ac(html_ap() - 2);
	if (forge_check_perm('docman', $ndg->Group->getID(), 'approve') && session_loggedin()) {
		echo html_ao('div', array('class' => 'docman_div_include hide', 'id' => 'movefile'));
		include ($gfcommon.'docman/views/movefile.php');
		echo html_ac(html_ap() - 1);
		}
} else {
	if ($dirid) {
		echo $HTML->information(_('No documents.'));
	}
}

if ($DocGroupName) {
	if (forge_check_perm('docman', $g->getID(), 'approve') && $DocGroupName) {
		include ($gfcommon.'docman/views/pendingfiles.php');
	}
	$foundFiles = 0;
	if (isset($nested_docs[$dirid]) && is_array($nested_docs[$dirid])) {
		$foundFiles = count($nested_docs[$dirid]);
	} elseif (isset($nested_pending_docs)) {
		$foundFiles .= count($nested_pending_docs);
	}
	if (forge_check_perm('docman', $g->getID(), 'approve') && $foundFiles) {
		include ($gfcommon.'docman/views/editfile.php');
		include ($gfcommon.'docman/views/notifyusers.php');
		$directViewFileRequestedID = getIntFromRequest('filedetailid', null);
		if ($directViewFileRequestedID) {
			$localDocumentObject = document_get_object($directViewFileRequestedID, $g->getID());
			echo html_ao('script', array('type' => 'text/javascript'));
			echo '//<![CDATA['."\n";
			echo 'jQuery(document).ready(function() {javascript:controllerListFile.toggleEditFileView({action:\''.util_make_uri($editfileaction).'\',
														lockIntervalDelay: 60000,
														childGroupId: '.util_ifsetor($childgroup_id, 0).',
														id:'.$localDocumentObject->getID().',
														groupId:'.$localDocumentObject->Group->getID().',
														docgroupId:'.$localDocumentObject->getDocGroupID().',
														statusId:'.$localDocumentObject->getStateID().',
														statusDict:'.$dm->getStatusNameList('json').',
														docgroupDict:'.$dm->getDocGroupList($nested_groups, 'json').',
														title:\''.addslashes($localDocumentObject->getName()).'\',
														filename:\''.addslashes($localDocumentObject->getFileName()).'\',
														description:'.json_encode($localDocumentObject->getDescription()).',
														isURL:\''.$localDocumentObject->isURL().'\',
														isText:\''.$localDocumentObject->isText().'\',
														isHtml:\''.$d->isHtml().'\',
														useCreateOnline:'.$localDocumentObject->Group->useCreateOnline().',
														docManURL:\''.util_make_uri('/docman').'\'})';
			echo '})';
			echo '//]]>';
			echo html_ac(html_ap() - 1);
		}
	}

	echo $HTML->paging_bottom($start, $paging, $nbDocs, $redirecturl);
}

include ($gfcommon.'docman/views/help.php');
echo html_ac(html_ap() - 1);
