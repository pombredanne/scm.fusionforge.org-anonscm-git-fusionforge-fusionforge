<?php
/**
 * FusionForge project manager
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2014, Franck Villaume - TrivialDev
 * Copyright 2014, Stéphane-Eymeric Bredthauer
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
require_once $gfcommon.'pm/ProjectTask.class.php';

class ProjectTaskFactory extends FFError {

	/**
	 * The ProjectGroup object.
	 *
	 * @var	object	$ProjectGroup.
	 */
	var $ProjectGroup;

	/**
	 * The project_tasks array.
	 *
	 * @var	array	project_tasks.
	 */
	var $project_tasks;
	var $order;
	var $sort_order;
	var $status;
	var $category;
	var $assigned_to;
	var $offset;
	var $max_rows;
	var $fetched_rows;
	var $view_type;

	/**
	 * @param	ProjectGroup	$ProjectGroup	The ProjectGroup object to which this ProjectTask is associated.
	 */
	function __construct(&$ProjectGroup) {
		parent::__construct();
		if (!$ProjectGroup || !is_object($ProjectGroup)) {
			$this->setError('ProjectTask: No Valid ProjectGroup Object');
			return false;
		}
		if ($ProjectGroup->isError()) {
			$this->setError('ProjectTask: '.$ProjectGroup->getErrorMessage());
			return false;
		}
		$this->ProjectGroup =& $ProjectGroup;
		$this->order='project_task_id';
		$this->offset=0;

		return true;
	}

	/**
	 * setup - sets up limits and sorts before you call getTasks().
	 *
	 * @param	int	$offset		The offset - number of rows to skip.
	 * @param	string	$order		What to order.
	 * @param	int	$max_rows	The max number of rows to return.
	 * @param	string	$set		Whether to set these prefs into the user_prefs table - use "custom".
	 * @param	int	$_assigned_to	Include this param if you want to limit to a certain assignee.
	 * @param	int	$_status	Include this param if you want to limit to a certain category.
	 * @param	$_category_id
	 * @param	string    $_view
	 * @param	string	$_sort_order	The way to order - ASC or DESC.
	 *
	 */
	function setup($offset,$order,$max_rows,$set,$_assigned_to,$_status,$_category_id,$_view='',$_sort_order = NULL) {
		if ((!$offset) || ($offset < 0)) {
			$this->offset=0;
		} else {
			$this->offset=$offset;
		}

		if (session_loggedin()) {
			$u =& session_get_user();
		}

		if ($order) {
			if ($order=='project_task_id' || $order=='percent_complete'
				|| $order=='summary' || $order=='start_date' || $order=='end_date' || $order=='priority') {
				if (session_loggedin()) {
					$u->setPreference('pm_task_order'.$this->ProjectGroup->getID(), $order);
				}
			} else {
				$order = 'project_task_id';
			}
		} else {
			if (session_loggedin()) {
				$order = $u->getPreference('pm_task_order'.$this->ProjectGroup->getID());
			}
		}
		if (!$order) {
			$order = 'project_task_id';
		}
		if ($_sort_order) {
			if ($_sort_order=='ASC' || $_sort_order=='DESC') {
				if (session_loggedin()) {
					$u->setPreference('pm_task_sort_order'.$this->ProjectGroup->getID(), $_sort_order);
				}
			} else {
				$_sort_order = NULL;
			}
		} else {
			if (session_loggedin()) {
				$_sort_order = $u->getPreference('pm_task_sort_order'.$this->ProjectGroup->getID());
			}
		}

		$this->order=$order;
		$this->sort_order=$_sort_order;

		if ($set=='custom') {
			/*
				if this custom set is different than the stored one, reset preference
			*/
			$pref_=$_assigned_to.'|'.$_status.'|'.$_category_id.'|'.$_view;
			if (session_loggedin() && ($pref_ != $u->getPreference('pm_brow_cust'.$this->ProjectGroup->getID()))) {
				$u->setPreference('pm_brow_cust'.$this->ProjectGroup->getID(),$pref_);
			}
		} else {
			if (session_loggedin()) {
				if ($pref_=$u->getPreference('pm_brow_cust'.$this->ProjectGroup->getID())) {
					$prf_arr=explode('|',$pref_);
					$_assigned_to=$prf_arr[0];
					$_status=$prf_arr[1];
					$_category_id=$prf_arr[2];
					$_view=$prf_arr[3];
				}
			}
		}
		$this->status=$_status;
		$this->assigned_to=$_assigned_to;
		$this->category=$_category_id;
		$this->view_type=$_view;

		if (!$max_rows || $max_rows < 5) {
			$max_rows=50;
		}
		$this->max_rows=$max_rows;
	}

	/**
	 * getTasks - get an array of ProjectTask objects.
	 *
	 * @return	array|bool	ProjectTask[]	The array of ProjectTask objects.
	 */
	function &getTasks() {
		if ($this->project_tasks) {
			return $this->project_tasks;
		}
		$qpa = db_construct_qpa();
		if ($this->sort_order) {
			$orderby = "ORDER BY $this->order $this->sort_order" ;
		} else {
			if ($this->order=='priority') {
				$orderby = 'ORDER BY priority DESC' ;
			} else {
				$orderby = "ORDER BY $this->order ASC" ;
			}
		}
		if ($this->assigned_to) {
			$tat = $this->assigned_to ;
			if (! is_array ($tat)) {
				$tat = array ($tat);
			}
			$qpa = db_construct_qpa($qpa, 'SELECT project_task_vw.*, project_task_external_order.external_id
							FROM project_task_vw natural left join project_task_external_order, project_assigned_to
							WHERE project_task_vw.project_task_id = project_assigned_to.project_task_id ');
			$qpa = db_construct_qpa($qpa, 'AND project_task_vw.group_project_id = $1 AND project_assigned_to.assigned_to_id = ANY ($2) ',
							array ($this->ProjectGroup->getID(), db_int_array_to_any_clause ($tat)));
		} else {
			$qpa = db_construct_qpa($qpa, 'SELECT project_task_vw.*, project_task_external_order.external_id
							FROM project_task_vw natural left join project_task_external_order ');
			$qpa = db_construct_qpa($qpa, 'WHERE project_task_vw.group_project_id = $1 ',
							array ($this->ProjectGroup->getID()));
		}

		if ($this->status && ($this->status != 100)) {
			$qpa = db_construct_qpa($qpa, ' AND project_task_vw.status_id = $1 ', array($this->status));
		}

		if ($this->category) {
			$qpa = db_construct_qpa($qpa, ' AND project_task_vw.category_id = $1 ', array($this->category));
		}

		$qpa = db_construct_qpa($qpa, $orderby);
		$result = db_query_qpa($qpa, $this->max_rows, $this->offset);

		if (db_error()) {
			$this->setError('Database Error: '.db_error());
			return false;
		}

		$rows = db_numrows($result);
		$this->fetched_rows = $rows;
		$this->project_tasks = array();
		while ($arr = db_fetch_array($result)) {
			$this->project_tasks[] = new ProjectTask($this->ProjectGroup, $arr['project_task_id'], $arr);
		}
		return $this->project_tasks;
	}

}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
