<?php
/**
 * FusionForge project manager
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2014, Franck Villaume - TrivialDev
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

require_once $gfcommon.'include/FFError.class.php';

/**
 * Fetches a ProjectGroup object from the database
 *
 * @param	array   $group_project_id	whether or not the db result handle is passed in
 * @param	bool	$data
 * @return	ProjectGroup|bool	the ProjectGroup object
 */
function &projectgroup_get_object($group_project_id, $data = false) {
	global $PROJECTGROUP_OBJ;
	if (!isset($PROJECTGROUP_OBJ["_".$group_project_id."_"])) {
		if ($data) {
			//the db result handle was passed in
		} else {
			$res = db_query_params('SELECT * FROM project_group_list_vw WHERE group_project_id=$1',
						array($group_project_id));
		}
		if (!$res || db_numrows($res) <1 ) {
			$PROJECTGROUP_OBJ["_".$group_project_id."_"] = false;
		} else {
			$data = db_fetch_array($res);
			$Group = group_get_object($data["group_id"]);
			$PROJECTGROUP_OBJ["_".$group_project_id."_"] = new ProjectGroup($Group,$group_project_id,$data);
		}
	}
	return $PROJECTGROUP_OBJ["_".$group_project_id."_"];
}

function projectgroup_get_groupid($group_project_id) {
	global $PROJECTGROUP_OBJ;
	if (isset($PROJECTGROUP_OBJ["_".$group_project_id."_"])) {
		return $PROJECTGROUP_OBJ["_".$group_project_id."_"]->Group->getID() ;
	}

	$res = db_query_params('SELECT group_id FROM project_group_list WHERE group_project_id=$1',
				array($group_project_id));
	if (!$res || db_numrows($res) < 1) {
		return false;
	}
	$arr = db_fetch_array($res);
	return $arr['group_id'];
}

class ProjectGroup extends FFError {

	/**
	 * Associative array of data from db.
	 *
	 * @var	array	$data_array.
	 */
	var $data_array;

	/**
	 * The Group object.
	 *
	 * @var	Group $Group.
	 */
	var $Group;
	var $statuses;
	var $categories;
	var $technicians;

	/**
	 * @param	$Group
	 * @param	bool	$group_project_id
	 * @param	bool	$arr
	 */
	function __construct(&$Group, $group_project_id = false, $arr = false) {
		parent::__construct();
		if (!$Group || !is_object($Group)) {
			$this->setError(_('Invalid Project'));
			return;
		}
		if ($Group->isError()) {
			$this->setError('ProjectGroup: '.$Group->getErrorMessage());
			return;
		}
		$this->Group =& $Group;

		if ($group_project_id) {
			if (!$arr || !is_array($arr)) {
				if (!$this->fetchData($group_project_id)) {
					return;
				}
			} else {
				$this->data_array =& $arr;
				if ($this->data_array['group_id'] != $this->Group->getID()) {
					$this->setError('Group_id in db result does not match Group Object');
					return;
				}
			}
			//
			//  Make sure they can even access this object
			//
			if (!forge_check_perm ('pm', $this->getID(), 'read')) {
				$this->setPermissionDeniedError();
				$this->data_array = null;
				return;
			}
		}
	}

	/**
	 * create - create a new ProjectGroup in the database.
	 *
	 * @param	string	$project_name		The project name.
	 * @param	string	$description		The project description.
	 * @param	string	$send_all_posts_to	The email address to send new notifications to.
	 * @return	bool	success.
	 */
	function create($project_name, $description, $send_all_posts_to = '') {
		if (strlen($project_name) < 3) {
			$this->setError(sprintf(_('Title Must Be At Least %d Characters'), 3));
			return false;
		}
		if (strlen($description) < 10) {
			$this->setError(_('Subproject Description Must Be At Least 10 Characters'));
			return false;
		}
		if ($send_all_posts_to) {
			$invalid_mails = validate_emails($send_all_posts_to);
			if (count($invalid_mails) > 0) {
				$this->setInvalidEmailError($send_all_posts_to);
				return false;
			}
		}

		if (!forge_check_perm ('pm_admin', $this->Group->getID())) {
			$this->setPermissionDeniedError();
			return false;
		}

		db_begin();
		$result = db_query_params('INSERT INTO project_group_list (group_id,project_name,description,send_all_posts_to) VALUES ($1,$2,$3,$4)',
					  array($this->Group->getID(),
						htmlspecialchars($project_name),
						htmlspecialchars($description),
						$send_all_posts_to));
		if (!$result) {
			$this->setError('Error Adding ProjectGroup: '.db_error());
			db_rollback();
			return false;
		}
		$group_project_id = db_insertid($result,'project_group_list','group_project_id');
		$this->fetchData($group_project_id);
		$this->Group->normalizeAllRoles();

		db_commit();

		return true;
	}

	/**
	 * fetchData - re-fetch the data for this ProjectGroup from the database.
	 *
	 * @param	int	$group_project_id	The project group ID.
	 * @return	bool	success.
	 */
	function fetchData($group_project_id) {
		$res = db_query_params ('SELECT * FROM project_group_list_vw WHERE group_project_id=$1 AND group_id=$2',
					array ($group_project_id,
					       $this->Group->getID())) ;
		if (!$res || db_numrows($res) < 1) {
			$this->setError(_('Invalid group_project_id'));
			return false;
		}
		$this->data_array = db_fetch_array($res);
		db_free_result($res);
		return true;
	}

	/**
	 * getGroup - get the Group object this ProjectGroup is associated with.
	 *
	 * @return	Group	The Group object.
	 */
	function &getGroup() {
		return $this->Group;
	}

	/**
	 * getID - get this GroupProjectID.
	 *
	 * @return	int	The group_project_id #.
	 */
	function getID() {
		return $this->data_array['group_project_id'];
	}

	/**
	 * getOpenCount - get the count of open tracker items in this tracker type.
	 *
	 * @return	int	The count.
	 */
	function getOpenCount() {
		return $this->data_array['open_count'];
	}

	/**
	 * getTotalCount - get the total number of tracker items in this tracker type.
	 *
	 * @return	int	The total count.
	 */
	function getTotalCount() {
		return $this->data_array['count'];
	}

	/**
	 * getCount - get the number of tasks item in this pm type per status and category
	 *
	 * @param	int	$status_id	the status id
	 * @param	int	$category_id	the category id
	 * @return	int	the count
	 */
	function getCount($status_id, $category_id) {
		$qpa = db_construct_qpa();
		$qpa = db_construct_qpa($qpa, 'SELECT count(project_task_id) FROM project_task WHERE group_project_id = $1', array($this->getID()));
		if ($category_id) {
			$qpa = db_construct_qpa($qpa, '  AND category_id = $1 ', array($category_id));
		}
		if (is_int($status_id) && $status_id != 100) {
			$qpa = db_construct_qpa($qpa, ' AND status_id = $1 ', array($status_id));
		}
		$res = db_query_qpa($qpa);
		$arr = db_fetch_array($res);
		return $arr[0];
	}

	/**
	 * getName - get the name of this projectGroup.
	 *
	 * @return	string	The name of this projectGroup.
	 */
	function getName() {
		return $this->data_array['project_name'];
	}

	/**
	 * getSendAllPostsTo - an optional email address to send all task updates to.
	 *
	 * @return	string	The email address.
	 */
	function getSendAllPostsTo() {
		return $this->data_array['send_all_posts_to'];
	}

	/**
	 * getDescription - the description of this ProjectGroup.
	 *
	 * @return	string	The description.
	 */
	function getDescription() {
		return $this->data_array['description'];
	}

	/**
	 * getStatuses - Return result set of statuses.
	 *
	 * @return	object	Database result set.
	 */
	function getStatuses() {
		if (!$this->statuses) {
			$this->statuses = db_query_params('SELECT * FROM project_status',
							  array());
		}
		return $this->statuses;
	}

	/**
	 * getCategories - Return result set of categories.
	 *
	 * @return	object	Database result set.
	 */
	function getCategories() {
		if (!$this->categories) {
			$this->categories = db_query_params('SELECT category_id,category_name FROM project_category WHERE group_project_id=$1',
							    array($this->getID()));
		}
		return $this->categories;
	}

	/**
	 * getCategoryObjects - Array of ProjectCategory objects set up for this artifact type.
	 *
	 * @return	array	Of ProjectCategory objects.
	 */
	function &getCategoryObjects() {
		$res = $this->getCategories();
		$cats = array();
		while ($arr = db_fetch_array($res)) {
			$cats[] = new ProjectCategory($this,$arr);
		}
		return $cats;
	}

	/**
	 * update - update a ProjectGroup in the database.
	 *
	 * @param	string	$project_name	The project name.
	 * @param	string	$description	The project description.
	 * @param	string	$send_all_posts_to	The email address to send new notifications to.
	 * @return	bool	success.
	 */
	function update($project_name, $description, $send_all_posts_to = '') {
		if (strlen($project_name) < 3) {
			$this->setError(sprintf(_('Title Must Be At Least %d Characters'), 3));
			return false;
		}
		if (strlen($description) < 10) {
			$this->setError(_('Subproject Description Must Be At Least 10 Characters'));
			return false;
		}

		if ($send_all_posts_to) {
			$invalid_mails = validate_emails($send_all_posts_to);
			if (count($invalid_mails) > 0) {
				$this->setInvalidEmailError($send_all_posts_to);
				return false;
			}
		}

		if (!forge_check_perm ('pm', $this->getID(), 'manager')) {
			$this->setPermissionDeniedError();
			return false;
		}

		$res = db_query_params('UPDATE project_group_list SET project_name=$1,
			description=$2, send_all_posts_to=$3
			WHERE group_id=$4 AND group_project_id=$5',
					array(htmlspecialchars($project_name),
					      htmlspecialchars($description),
					      $send_all_posts_to,
					      $this->Group->getID(),
					      $this->getID()));

		if (!$res || db_affected_rows($res) < 1) {
			$this->setError(_('Error On Update')._(': ').db_error());
			return false;
		}
		return true;
	}

	/**
	 * delete - delete this subproject and all its related data.
	 *
	 * @param	bool	$sure		I'm Sure.
	 * @param	bool	$really_sure	I'm REALLY sure.
	 * @return	bool	true/false;
	 */
	function delete($sure, $really_sure) {
		if (!$sure || !$really_sure) {
			$this->setMissingParamsError(_('Please tick all checkboxes.'));
			return false;
		}
		if (!forge_check_perm('pm', $this->getID(), 'manager')) {
			$this->setPermissionDeniedError();
			return false;
		}

		db_begin();

		$res = db_query_params('DELETE FROM project_assigned_to
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=project_assigned_to.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params('DELETE FROM project_dependencies
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=project_dependencies.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params('DELETE FROM project_history
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=project_history.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params('DELETE FROM project_messages
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=project_messages.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params('DELETE FROM project_task_artifact
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=project_task_artifact.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params('DELETE FROM rep_time_tracking
			WHERE EXISTS (SELECT project_task_id FROM project_task
			WHERE group_project_id=$1
			AND project_task.project_task_id=rep_time_tracking.project_task_id)',
					array($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params ('DELETE FROM project_task
			WHERE group_project_id=$1',
					array ($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params ('DELETE FROM project_category WHERE group_project_id=$1',
					array ($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params ('DELETE FROM project_group_list WHERE group_project_id=$1',
					array ($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		$res = db_query_params ('DELETE FROM project_counts_agg WHERE group_project_id=$1',
					array ($this->getID()));

		if (!$res) {
			$this->setError('DATABASE '.db_error());
			return false;
		}

		db_commit();

		$this->Group->normalizeAllRoles();
		return true;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
