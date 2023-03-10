<?php
/**
 * FusionForge Project Management Facility : Tasks
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2010, Franck Villaume - Capgemini
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013-2014, Franck Villaume - TrivialDev
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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'pm/include/ProjectGroupHTML.class.php';
require_once $gfwww.'pm/include/ProjectTaskHTML.class.php';
require_once $gfcommon.'pm/ProjectGroupFactory.class.php';

$group_id = getIntFromRequest('group_id');
$group_project_id = getIntFromRequest('group_project_id');
$project_task_id = getIntFromRequest('project_task_id');
$summary = getStringFromRequest('summary');
$details = getStringFromRequest('details');
$priority = getStringFromRequest('priority');
$hours = getStringFromRequest('hours');
$start_date_string = getStringFromRequest('start_date');
$end_date_string = getStringFromRequest('end_date');
$status_id = getIntFromRequest('status_id');
$category_id = getIntFromRequest('category_id');
$percent_complete = getStringFromRequest('percent_complete');
$assigned_to = getStringFromRequest('assigned_to');
$new_group_project_id = getIntFromRequest('new_group_project_id');
$dependent_on = getStringFromRequest('dependent_on');
$duration = getStringFromRequest('duration');
$parent_id = getIntFromRequest('parent_id');

if (!$group_id || !$group_project_id) {
	$redirect_url = '';
	if (isset($_SERVER['HTTP_REFERER'])) {
		$redirect_url = $_SERVER['HTTP_REFERER'];
	}
	if (!$group_id) {
		$missing_params[] = _('Group ID');
	}
	if (!$group_project_id) {
		$missing_params[] = _('Group Project ID');
	}
	exit_missing_param($redirect_url, $missing_params, 'pm');
}

$group = group_get_object($group_id);
if (!$group || !is_object($group)) {
	exit_no_group();
} elseif ($group->isError()) {
	exit_error($group->getErrorMessage(),'pm');
}

$pg = new ProjectGroupHTML($group, $group_project_id);
if (!$pg || !is_object($pg)) {
	exit_error(_('Could Not Get Factory'),'pm');
} elseif ($pg->isError()) {
	exit_error($pg->getErrorMessage(),'pm');
}

/*
	Figure out which function we're dealing with here
 */
switch (getStringFromRequest('func')) {

	//
	//	Show blank form to add new task
	//
	case 'addtask' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$pt = new ProjectTaskHTML($pg);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}
		include $gfwww.'pm/add_task.php';
		break;
	}

	//
	//	Insert the task into the database
	//
	case 'postaddtask' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$add_artifact_id = getIntFromRequest('add_artifact_id');

		$pt = new ProjectTask($pg);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get Empty ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}

		$saved_hours = $hours;
		$hours = (float) $hours;
		if ( $saved_hours !== (string)$hours ) {
			exit_error(_('Illegal format for hours: must be an integer or a float number.'),'pm');
		}

		if (!is_array($dependent_on)) {
			$dependent_on=array();
		}
		$datetime = DateTime::createFromFormat(_('Y-m-d H:i'), $start_date_string);
		$start_date = $datetime->getTimestamp();
		$datetime = DateTime::createFromFormat(_('Y-m-d H:i'), $end_date_string);
		$end_date = $datetime->getTimestamp();

		$sanitizer = new TextSanitizer();
		$details = $sanitizer->purify($details);

		if (!$pt->create($summary,$details,$priority,$hours,$start_date,$end_date,$category_id,$percent_complete,$assigned_to,$pt->convertDependentOn($dependent_on),$duration,$parent_id)) {
			exit_error($pt->getErrorMessage(),'pm');
		} else {
			if (!empty($add_artifact_id)) {
				if (!$pt->addRelatedArtifacts($add_artifact_id)) {
					exit_error('addRelatedArtifacts: '.$pt->getErrorMessage(),'pm');
				}
			}
			$feedback = _('Task Created Successfully');
			include $gfwww.'pm/browse_task.php';
		}
		break;
	}

	//
	//	Modify an existing task
	//
	case 'postmodtask' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$rem_artifact_id = getIntFromRequest('rem_artifact_id');
		$followup = getStringFromRequest('followup');

		if(!$rem_artifact_id){
			$rem_artifact_id=array();
		}

		$pt = new ProjectTask($pg,$project_task_id);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}

		$saved_hours = $hours;
		$hours = (float) $hours;
		if ( $saved_hours !== (string)$hours ) {
			exit_error(_('Illegal format for hours: must be an integer or a float number.'),'pm');
		}

		if (!$dependent_on)	{
			$dependent_on=array();
		}
		$datetime = DateTime::createFromFormat(_('Y-m-d H:i'), $start_date_string);
		$start_date = $datetime->getTimestamp();
		$datetime = DateTime::createFromFormat(_('Y-m-d H:i'), $end_date_string);
		$end_date = $datetime->getTimestamp();

		if (!$pt->update($summary,$details,$priority,$hours,$start_date,$end_date,
			$status_id,$category_id,$percent_complete,$assigned_to,$pt->convertDependentOn($dependent_on),$new_group_project_id,$duration,$parent_id)) {
			exit_error('update: '.$pt->getErrorMessage(),'pm');
		} else {
			if ($followup && !$pt->addMessage($followup)) {
				exit_error('update: '.$pt->getErrorMessage(), 'pm');
			}
			if (count($rem_artifact_id) > 0) {
				if (!$pt->removeRelatedArtifacts($rem_artifact_id)) {
					exit_error('removeRelatedArtifacts: '.$pt->getErrorMessage(),'pm');
				}
			}
			$feedback = _('Task Updated Successfully');
			include $gfwww.'pm/browse_task.php';
		}
		break;
	}

	case 'csv': {
		include $gfwww.'pm/csv.php';
		exit;
	}

	case 'format_csv': {
		include $gfwww.'pm/format_csv.php';
		exit;
	}

	case 'downloadcsv': {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		include $gfwww.'pm/downloadcsv.php';
		exit;
	}

	case 'uploadcsv': {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		include $gfwww.'pm/uploadcsv.php';
		exit;
	}

	case 'postuploadcsv': {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		include $gfwww.'pm/postuploadcsv.php';
		include $gfwww.'pm/browse_task.php';
		break;
	}

	case 'massupdate' : {
		$project_task_id_list = getArrayFromRequest('project_task_id_list');
		$count=count($project_task_id_list);

		session_require_perm ('pm', $pg->getID(), 'manager') ;

		for ($i=0; $i < $count; $i++) {
			$pt=new ProjectTask($pg,$project_task_id_list[$i]);
			if (!$pt || !is_object($pt)) {
				$error_msg .= ' ID: '.$project_task_id_list[$i].'::ProjectTask Could Not Be Created';
			} elseif ($pt->isError()) {
				$error_msg .= ' ID: '.$project_task_id_list[$i].'::'.$pt->getErrorMessage();
			} else {

				$mass_summary=addslashes(util_unconvert_htmlspecialchars($pt->getSummary()));
				$mass_details='';
				$mass_priority=(($priority != 100) ? $priority : $pt->getPriority());
				$mass_hours=$pt->getHours();
				$mass_start_date=$pt->getStartDate();
				$mass_end_date=$pt->getEndDate();
				$mass_status_id=(($status_id != 100) ? $status_id : $pt->getStatusID());
				$mass_category_id=(($category_id != 100) ? $category_id : $pt->getCategoryID());
				$mass_percent_complete=$pt->getPercentComplete();

				//yikes, we want the ability to mass-update to "un-assigned", which is the ID=100, which
				//conflicts with the "no change" ID! Sorry for messy use of 100.1
				// 100 means => no change
				// 100.1 means non assigned
				// other means assigned to ...

				if ($assigned_to == '100') {
					$mass_assigned_to = $pt->getAssignedTo();
				} elseif ($assigned_to == '100.1') {
					$mass_assigned_to = array('100');
				} else {
					$mass_assigned_to = array($assigned_to);
				}

				$mass_dependent_on=$pt->getDependentOn();
				$mass_new_group_project_id=(($new_group_project_id != 100) ? $new_group_project_id : $pt->ProjectGroup->getID() );
				$mass_duration=$pt->getDuration();
				$mass_parent_id=$pt->getParentID();

				if (!$pt->update($mass_summary,$mass_details,$mass_priority,$mass_hours,$mass_start_date,$mass_end_date,
						 $mass_status_id,$mass_category_id,$mass_percent_complete,$mass_assigned_to,$mass_dependent_on,$mass_new_group_project_id,$mass_duration,$mass_parent_id)) {
					$was_error=true;
					$feedback .= ' ID: '.$project_task_id_list[$i].'::'.$pt->getErrorMessage();

				}
				unset($pt);
			}
		}
		if ($count == 0) {
			$warning_msg = _('No task selected');
		} elseif (isset($was_error) && !$was_error) {
			$feedback = _('Task Updated Successfully');
		}
		include $gfwww.'pm/browse_task.php';
		break;
	}

	//
	//	Add an artifact relationship to an existing task
	//
	case 'addartifact' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$add_artifact_id[] = getIntFromRequest('add_artifact_id');

		$pt = new ProjectTask($pg,$project_task_id);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}
		if (!$pt->addRelatedArtifacts($add_artifact_id)) {
			exit_error('addRelatedArtifacts():: '.$pt->getErrorMessage(),'pm');
		} else {
			$feedback=_('Successfully Added Tracker Relationship');
			include $gfwww.'pm/browse_task.php';

		}
		break;
	}

	//
	//	Show delete form
	//
	case 'deletetask' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$pt= new ProjectTask($pg,$project_task_id);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}
		include $gfwww.'pm/deletetask.php';
		break;
	}

	//
	//	Handle the actual delete
	//

	case 'postdeletetask' : {
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$pt= new ProjectTask($pg, $project_task_id);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}
		if (!getStringFromRequest('confirm_delete')) {
			$warning_msg.= _('Confirmation failed. Task not deleted');
		} else {
			$deletion = $pt->delete(true);
			if (!$deletion) {
				$error_msg .= _('Delete failed')._(': ').$pt->getErrorMessage();
			} else {
				$feedback .= _('Task Successfully Deleted');
			}
		}
		include $gfwww.'pm/browse_task.php';
		break;
	}

	//
	//	Show the page surrounding the gantt chart
	//
	case 'ganttpage' : {
		include $gfwww.'pm/ganttpage.php';
		break;
	}

	//
	//	View a specific existing task
	//
	case 'detailtask' : {
		$pt=new ProjectTaskHTML($pg,$project_task_id);
		if (!$pt || !is_object($pt)) {
			exit_error(_('Could Not Get ProjectTask'),'pm');
		} elseif ($pt->isError()) {
			exit_error($pt->getErrorMessage(),'pm');
		}
		if (forge_check_perm ('pm', $pg->getID(), 'manager')) {
			include $gfwww.'pm/mod_task.php';
		} else {
			include $gfwww.'pm/detail_task.php';
		}
		break;
	}

	default : {
		include $gfwww.'pm/browse_task.php';
		break;
	}
}
