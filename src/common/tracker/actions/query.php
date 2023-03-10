<?php
/**
 * Copyright 2005 (c) GForge Group, LLC; Anthony J. Pugliese,
 * Copyright 2010 (c) Fusionforge Team
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012-2015, Franck Villaume - TrivialDev
 * Copyright 2016, Stéphane-Eymeric Bredthauer - TrivialDev
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

require_once $gfcommon.'tracker/ArtifactQuery.class.php';

global $ath;
global $feedback;
global $error_msg;
global $group_id;
global $atid;
global $HTML;

if (!session_loggedin()) {
	exit_not_logged_in();
}

$query_id = getIntFromRequest('query_id');
$query_action = getIntFromRequest('query_action');
if (getStringFromRequest('submit')) {
	//
	//  Create a Saved Query
	//

	if ($query_action == 1) {
		if (!form_key_is_valid(getStringFromRequest('form_key'))) {
			exit_form_double_submit('tracker');
		}

		$aq = new ArtifactQuery($ath);
		if (!$aq || !is_object($aq)) {
			exit_error($aq->getErrorMessage(),'tracker');
		}
		$query_name = trim(getStringFromRequest('query_name'));
		$query_type = getStringFromRequest('query_type',0);
		$_status = getStringFromRequest('_status');
		$_submitted_by = getStringFromRequest('_submitted_by');
		$_assigned_to = getStringFromRequest('_assigned_to');
		$_last_modified_by = getStringFromRequest('_last_modified_by');
		$_sort_col = getStringFromRequest('_sort_col');
		$_sort_ord = getStringFromRequest('_sort_ord');
		$extra_fields = getStringFromRequest('extra_fields');
		$_moddaterange = getStringFromRequest('_moddaterange');
		$_opendaterange = getStringFromRequest('_opendaterange');
		$_closedaterange = getStringFromRequest('_closedaterange');
		$_summary = getStringFromRequest('_summary');
		$_description = getStringFromRequest('_description');
		$_followups = getStringFromRequest('_followups');
		$query_options = array_keys(getArrayFromRequest('query_options'));
		if (!$aq->create($query_name,$_status,$_assigned_to,$_moddaterange,$_sort_col,$_sort_ord,$extra_fields,$_opendaterange,$_closedaterange,
			$_summary,$_description,$_followups,$query_type, $query_options, $_submitted_by, $_last_modified_by)) {
			form_release_key(getStringFromRequest('form_key'));
			exit_error($aq->getErrorMessage(),'tracker');
		} else {
			$feedback .= _('Query Successfully Created');
		}
		$aq->makeDefault();
		$query_id=$aq->getID();
		session_redirect('/tracker/?atid='.$atid.'&group_id='.$group_id.'&func=browse');
	//
/*
	// Make the displayed query the default
	//
	} elseif ($query_action == 2) {
		$aq = new ArtifactQuery($ath,$query_id);
		if (!$aq || !is_object($aq)) {
			exit_error('Error',$aq->getErrorMessage());
		}
		if (!$aq->makeDefault()) {
			$feedback .= $aq->getErrorMessage();
		} else {
			$feedback .= 'Query Made Default';
		}
*/	//
	// Update the name and or fields of the displayed saved query
	//
	} elseif ($query_action == 3) {
		if (!form_key_is_valid(getStringFromRequest('form_key'))) {
			exit_form_double_submit('tracker');
		}
		$aq = new ArtifactQuery($ath,$query_id);
		if (!$aq || !is_object($aq)) {
			exit_error($aq->getErrorMessage(),'tracker');
		}
		$query_name = getStringFromRequest('query_name');
		$query_type = getStringFromRequest('query_type',0);
		$_status = getStringFromRequest('_status');
		$_submitted_by = getStringFromRequest('_submitted_by');
		$_assigned_to = getStringFromRequest('_assigned_to');
		$_last_modified_by = getStringFromRequest('_last_modified_by');
		$_sort_col = getStringFromRequest('_sort_col');
		$_sort_ord = getStringFromRequest('_sort_ord');
		$_moddaterange = getStringFromRequest('_moddaterange');
		$_opendaterange = getStringFromRequest('_opendaterange');
		$_closedaterange = getStringFromRequest('_closedaterange');
		$_summary = getStringFromRequest('_summary');
		$_description = getStringFromRequest('_description');
		$_followups = getStringFromRequest('_followups');
		$extra_fields = getStringFromRequest('extra_fields');
		$query_options = array_keys(getArrayFromRequest('query_options'));
		if (!$aq->update($query_name,$_status,$_assigned_to,$_moddaterange,$_sort_col,$_sort_ord,$extra_fields,$_opendaterange,$_closedaterange,
			$_summary,$_description,$_followups,$query_type, $query_options, $_submitted_by,$_last_modified_by)) {
			exit_error($aq->getErrorMessage(),'tracker');
		} else {
			$feedback .= _('Query Updated');
		}
		$aq->makeDefault();
		$query_id=$aq->getID();
		session_redirect('/tracker/?atid='.$atid.'&group_id='.$group_id.'&func=browse');
	//
	//	Just load the query
	//
	} elseif ($query_action == 4) {
		$aq = new ArtifactQuery($ath,$query_id);
		if (!$aq || !is_object($aq)) {
			exit_error($aq->getErrorMessage(),'tracker');
		}
		$aq->makeDefault();
	//
	//	Delete the query
	//
	} elseif ($query_action == 5) {
		if (!form_key_is_valid(getStringFromRequest('form_key'))) {
			exit_form_double_submit('tracker');
		}
		$aq = new ArtifactQuery($ath,$query_id);
		if (!$aq || !is_object($aq)) {
			exit_error($aq->getErrorMessage(),'tracker');
		}
		if (!$aq->delete()) {
			$error_msg .= $aq->getErrorMessage();
		} else {
			$feedback .= _('Query Deleted');
		}
		session_redirect('/tracker/?atid='.$atid.'&group_id='.$group_id.'&func=browse');
	} else {
		exit_error(_('Missing Build Query Action'),'tracker');
	}
} else {
	$user=session_get_user();
	$query_id=$user->getPreference('art_query'.$ath->getID());
	$aq = new ArtifactQuery($ath,$query_id);
	if (!$aq || !is_object($aq)) {
		exit_error($aq->getErrorMessage(),'tracker');
	}
	$aq->makeDefault();
}

//
//  setup the query
//
$_submitted_by=$aq->getSubmitter();
$_assigned_to=$aq->getAssignee();
$_last_modified_by=$aq->getLastModifier();
$_status=$aq->getStatus();
$extra_fields=$aq->getExtraFields();
$_sort_col=$aq->getSortCol();
$_sort_ord=$aq->getSortOrd();
$_moddaterange=$aq->getModDateRange();
$_opendaterange=$aq->getOpenDateRange();
$_closedaterange=$aq->getCloseDateRange();
$_summary=$aq->getSummary();
$_description=$aq->getDescription();
$_followups=$aq->getFollowups();
$query_type=$aq->getQueryType();
//
//	creating a submitter box
$submitter_box = $ath->submitterBox('_submitted_by[]',$_submitted_by,true,'none','-1',false,true);
//	creating a custom technician box which includes "any" and "unassigned"
$tech_box = $ath->technicianBox('_assigned_to[]',$_assigned_to,true,'none','-1',false,true);
//	creating a last modifier box
$last_modifier_box = $ath->lastModifierBox('_last_modified_by[]',$_last_modified_by,true,'none','-1',false,true);


//
//	custom order by arrays to build a pop-up box
//
$order_name_arr=array();
$order_name_arr[]=_('Id');
$order_name_arr[]=_('Priority');
$order_name_arr[]=_('Summary');
$order_name_arr[]=_('Open Date');
$order_name_arr[]=_('Last Modified Date');
$order_name_arr[]=_('Close Date');
$order_name_arr[]=_('Submitter');
$order_name_arr[]=_('Assignee');
$order_name_arr[]=_('Last Modifier');

$order_arr=array();
$order_arr[]='artifact_id';
$order_arr[]='priority';
$order_arr[]='summary';
$order_arr[]='open_date';
$order_arr[]='last_modified_date';
$order_arr[]='close_date';
$order_arr[]='submitted_by';
$order_arr[]='assigned_to';
$order_arr[]='last_modified_by';

//
//	custom sort arrays to build pop-up box
//
$sort_name_arr=array();
$sort_name_arr[]=_('Ascending');
$sort_name_arr[]=_('Descending');

$sort_arr=array();
$sort_arr[]='ASC';
$sort_arr[]='DESC';

//
//	custom changed arrays to build pop-up box
//
$changed_name_arr=array();
$changed_name_arr[]=_('Any changes');
$changed_name_arr[]=_('Last 24 h');
$changed_name_arr[]=_('Last 7 days');
$changed_name_arr[]=_('Last 2 weeks');
$changed_name_arr[]=_('Last month');

$changed_arr=array();
$changed_arr[]= 0;
$changed_arr[]= 3600 * 24;	 // 24 hour
$changed_arr[]= 3600 * 24 * 7; // 1 week
$changed_arr[]= 3600 * 24 * 14;// 2 week
$changed_arr[]= 3600 * 24 * 30;// 1 month

//
//	get queries for this user
//
$res = db_query_params('SELECT artifact_query_id,query_name FROM artifact_query WHERE user_id=$1 AND group_artifact_id=$2',
			array(user_getid(),
			      $ath->getID()));

//	Show the new pop-up boxes to select assigned to, status, etc
//
$ath->header(array('atid'=>$ath->getID(),
				   'title' =>_('Build Query'),
				   'modal' => 1));

echo '<table style="margin-left:auto;margin-right:auto"><tr><td>' .
		'<fieldset><legend>'.
		_('Build Query').
		'</legend>';

echo $HTML->openForm(array('action' => '/tracker/?func=query&group_id='.$group_id.'&atid='.$ath->getID(), 'method' => 'post'));
echo '<input type="hidden" name="form_key" value="'.form_generate_key().'" />
<table class="tablecontent fullwidth">
	<tr>
		<td>
			<input type="submit" name="submit" value="'._('Save Changes').'" />
		</td>
		<td>';
	if(db_numrows($res)>0) {
		echo html_build_select_box($res,'query_id',$query_id,false).'';
	}

	echo '
		</td>
	</tr>
	<tr class="tablecontent">
		<td>';
	if(db_numrows($res)>0) {
		if ($query_type == 0 || ($query_type>0 && forge_check_perm ('tracker', $ath->getID(), 'manager'))) {
			$allow_update = true;
			$checked[1] = '';
			$checked[3] = ' checked="checked"';
		} else {
			$allow_update = false;
			$checked[1] = ' checked="checked"';
			$checked[3] = '';
		}
		echo '<input type="radio" id="name-save-query" name="query_action" value="1"'.$checked[1].' />'."\n";
		echo '<label for="name-save-query">';
		echo _('Name and Save Query');
		echo '</label>'."\n";
		echo '<br />'."\n";
		echo '<input type="radio" id="load-query" name="query_action" value="4" />'."\n";
		echo '<label for="load-query">';
		echo _('Load Query');
		echo '</label>'."\n";
		echo '<br />'."\n";
		if ($allow_update) {
			echo '<input type="radio" id="update-query" name="query_action" value="3"'.$checked[3].' />'."\n";
			echo '<label for="update-query">';
			echo _('Update Query');
			echo '</label>'."\n";
			echo '<br />'."\n";
			echo '<input type="radio" id="delete-query" name="query_action" value="5" />'."\n";
			echo '<label for="delete-query">';
			echo _('Delete Query');
			echo '</label>'."\n";
		}
	} else {
		echo '
		<input type="hidden" name="query_action" value="1" />'._('Name and Save Query').'<br />';
	}
	echo '
		</td>
		<td class="top">
		<input required="required" type="text" name="query_name" value="'.$aq->getName().'" size="20" maxlength="30" /></td>
	</tr>
</table>';

echo $HTML->listTableTop(array(), array(), 'tablecontent fullwidth');
if (forge_check_perm ('tracker', $ath->getID(), 'manager')) {
	$default_query = db_result(db_query_params('SELECT query_name FROM artifact_query WHERE query_type=2 AND group_artifact_id=$1',
						   array ($ath->getID())),
				   0,
				   'query_name');
	if ($default_query) {
		if ($default_query == $aq->getName()) {
			$note = '';
		} else {
			$note= '<br/>'.html_e('em', array(), sprintf(_('Note: The default project query is currently “%s”.'), $default_query));
		}
	} else {
		$note= '<br/>'.html_e('em', array(), _('Note: There is no default project query defined.'));
	}
	echo '<tr>'."\n";
	echo '<td colspan="2">'."\n";
	echo '<strong>'._('Type of query')._(': ').'</strong><br />'."\n";
	echo '<input id="private-query" name="query_type" value="0" type="radio"'.(($query_type==0) ? ' checked="checked"' : '' ).' />'."\n";
	echo '<label for="private-query">';
	echo _('Private query');
	echo '</label>';
	echo '<br />'."\n";
	echo '<input id="project-level-query" name="query_type" value="1" type="radio"'.(($query_type==1) ? ' checked="checked"' : '' ).' />'."\n";
	echo '<label for="project-level-query">';
	echo _('Project level query (query is public)');
	echo '</label>';
	echo '<br />'."\n";
	echo '<input id="default-project-query" name="query_type" value="2" type="radio"'.(($query_type==2) ? ' checked="checked"' : '' ).' />'."\n";
	echo '<label for="default-project-query">';
	echo _('Default project query (for project level query only)');
	echo '</label>';
	echo '<br />'."\n";
	echo $note."\n";
	echo '<hr/>'."\n";
	echo '</td>'."\n";
	echo '</tr>'."\n";
}
	if (!$ath->usesCustomStatuses()) {
		echo '<tr>
				<td class="top">
				<strong>'._('State')._(': ').'</strong><br />'. $ath->statusBox('_status',$_status,true,_('Any')).'
				</td>
				<td></td>
			  </tr>';
	}
	echo '<tr>
		<td class="top"><strong>'._('Submitter')._(': ').'</strong><br />'. $submitter_box .'</td>
		<td class="top"><strong>'._('Assignee')._(': ').'</strong><br />'. $tech_box .'</td>
	</tr>
	<tr>
		<td class="top"><strong>'._('Last Modifier')._(': ').'</strong><br />'. $last_modifier_box .'</td>
	</tr>';
	$ath->renderExtraFields($extra_fields,true,'none',true,'Any',array(),false,'QUERY');

	// Compute the list of fields which can be sorted.
	// Currently, only scalar artifacts are taken.
	$efarr = $ath->getExtraFields(array(ARTIFACT_EXTRAFIELDTYPE_TEXT,
					    ARTIFACT_EXTRAFIELDTYPE_TEXTAREA,
					    ARTIFACT_EXTRAFIELDTYPE_INTEGER,
					    ARTIFACT_EXTRAFIELDTYPE_SELECT,
					    ARTIFACT_EXTRAFIELDTYPE_RADIO,
					    ARTIFACT_EXTRAFIELDTYPE_STATUS,
					    ARTIFACT_EXTRAFIELDTYPE_DATE,
					    ARTIFACT_EXTRAFIELDTYPE_DATETIME,
					    ARTIFACT_EXTRAFIELDTYPE_EFFORT));
	$keys=array_keys($efarr);
	for ($k=0; $k<count($keys); $k++) {
		$i=$keys[$k];
		$order_name_arr[] = $efarr[$i]['field_name'];
		$order_arr[] = $efarr[$i]['extra_field_id'];
	}
	array_multisort($order_name_arr, $order_arr);

	$tips = html_e('em', array(), _('(%% for wildcards)'));

echo '
	<tr>
		<td colspan="2" style="white-space: nowrap;">'.
		'<p><strong>'._('Last Modified Date range')._(':').'</strong> '.html_e('em', array(), '(YYYY-MM-DD YYYY-MM-DD Format)').'<br />
		<input type="text" name="_moddaterange" size="21" maxlength="21" value="'. htmlspecialchars($_moddaterange) .'" /></p>
		<p><strong>'._('Open Date range')._(':').'</strong> '.html_e('em', array(), '(YYYY-MM-DD YYYY-MM-DD Format)').'<br />
		<input type="text" name="_opendaterange" size="21" maxlength="21" value="'. htmlspecialchars($_opendaterange) .'" /></p>
		<p><strong>'._('Close Date range')._(':').'</strong> '.html_e('em', array(), '(YYYY-MM-DD YYYY-MM-DD Format)').'<br />
		<input type="text" name="_closedaterange" size="21" maxlength="21" value="'. htmlspecialchars($_closedaterange) .'" /></p>
		</td>
	</tr>
	<tr>
		<td colspan="2">'.
		'<p><strong>'._('Summary')._(': ').'</strong> '.$tips.'<br />
		<input type="text" name="_summary" size="40" value="'. htmlspecialchars($_summary) .'" /></p>
		<p><strong>'._('Detailed description')._(': ').'</strong> '.$tips.'<br />
		<input type="text" name="_description" size="40" value="'. htmlspecialchars($_description) .'" /></p>
		<p><strong>'._('Comments')._(': ').'</strong> '.$tips.'<br />
		<input type="text" name="_followups" size="40" value="'. htmlspecialchars($_followups) .'" /></p>
		</td>
	</tr>
	<tr>
		<td><strong>'._('Order by')._(': ').'</strong><br />
		'.
		html_build_select_box_from_arrays($order_arr,$order_name_arr,'_sort_col',$_sort_col,false) .'</td>
		<td><br />
		'.html_build_select_box_from_arrays($sort_arr,$sort_name_arr,'_sort_ord',$_sort_ord,false) .'</td>
	</tr>';
echo '<tr>
		<td colspan="2">'.
		'<p><strong>'._('Options')._(':').'</strong><br />
		<input id="bargraph" type="checkbox" name="query_options[bargraph]" '.
	((in_array('bargraph', $aq->getQueryOptions())) ? 'checked="checked"' : '')
.'/><label for="bargraph">'._('Display a short summary box on top of the list (roadmap status).').'</label></p>
		</td>
	</tr>';
echo $HTML->listTableBottom();
echo $HTML->closeForm();
echo '</fieldset></td></tr></table>';
$ath->footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
