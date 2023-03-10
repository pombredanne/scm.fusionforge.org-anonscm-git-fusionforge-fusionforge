<?php
/**
 * Main Tracker Content Widget Class
 *
 * Copyright 2016,2018, Franck Villaume - TrivialDev
 * Copyright 2017, Stephane-Eymeric Bredthauer - TrivialDev
 * http://fusionforge.org
 *
 * This file is a part of Fusionforge.
 *
 * Fusionforge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fusionforge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fusionforge. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'Widget.class.php';

class Widget_TrackerMain extends Widget {

	function __construct() {
		$owner_id   = (int)substr(getStringFromRequest('owner'), 1);
		parent::__construct('trackermain', $owner_id, WidgetLayoutManager::OWNER_TYPE_TRACKER);
		$this->title = _('Internal Fields');
	}

	function getTitle() {
		return $this->title;
	}

	function getDescription() {
		return _('Default widget where default fields are stored & displayed. Priority, Data Types, ...');
	}

	function isAvailable() {
		return isset($this->title);
	}

	function getContent() {
		global $ath;
		global $ah;
		global $group_id;
		global $group;
		global $aid;
		global $atid;
		global $HTML;
		global $func;

		//manage redirect in case of missing required fields
		global $assigned_to;
		global $priority;

		$return = $HTML->listTableTop();
		$atf = new ArtifactTypeFactory ($group);
		$fieldInFormula = $ath->getFieldsInFormula();
		$cells = array();
		if (forge_check_perm('tracker', $atid, 'manager') && ($func == 'detail')) {
			$tids = array();
			foreach ($atf->getArtifactTypes() as $at) {
				if (forge_check_perm ('tracker', $at->getID(), 'manager')) {
					$tids[] = $at->getID();
				}
			}

			$res = db_query_params('SELECT group_artifact_id, name
						FROM artifact_group_list
						WHERE group_artifact_id = ANY ($1)',
						array (db_int_array_to_any_clause($tids)));

			$cells[][] = html_e('strong', array(), _('Data Type')._(': '));
			$cells[][] = html_build_select_box($res, 'new_artifact_type_id', $ath->getID(), false, '', false, '', false, array('form' => 'trackerform'));
		} else {
			$cells[][] = html_e('strong', array(), _('Data Type')._(': '));
			$cells[][] = $ath->getName();
		}
		$return .= $HTML->multiTableRow(array(), $cells);
		if (forge_check_perm('tracker', $atid, 'manager')) {
			$cells = array();
			$cells[][] = html_e('strong', array(), _('Assigned to')._(': '));
			if (in_array('assigned_to', $fieldInFormula)) {
				$class = 'in-formula';
			} else {
				$class = '';
			}
			if ($func == 'detail') {
				$cells[][] = $ath->technicianBox('assigned_to', $ah->getAssignedTo(), true, 'none', -1, '', false, array('form' => 'trackerform', 'class' => $class));
			} else {
				$cells[][] = $ath->technicianBox('assigned_to', $assigned_to, true, 'none', -1, '', false, array('form' => 'trackerform', 'class' => $class));
			}
			$return .= $HTML->multiTableRow(array(), $cells);
		} elseif ($func == 'detail') {
			$cells = array();
			$cells[][] = html_e('strong', array(), _('Assigned to')._(': '));
			$cells[][] = $ah->getAssignedRealName().' ('.$ah->getAssignedUnixName().')';
			$return .= $HTML->multiTableRow(array(), $cells);
		}
		if (!$ath->usesCustomStatuses()) {
			if (forge_check_perm('tracker', $atid, 'tech')) {
				$cells = array();
				$cells[][] = html_e('strong', array(), _('State')._(': '));
				if (in_array('status', $fieldInFormula)) {
					$class = 'in-formula';
				} else {
					$class = '';
				}
				if ($func == 'detail') {
					$cells[][] = $ath->statusBox('status_id', $ah->getStatusID(), false, '', array('form' => 'trackerform', 'class' => $class));
				} else {
					$cells[][] = $ath->statusBox('status_id', 'xzxz', false, '', array('form' => 'trackerform', 'class' => $class));
				}
				$return .= $HTML->multiTableRow(array(), $cells);
			} elseif ($func == 'detail') {
				$cells = array();
				$cells[][] = html_e('strong', array(), _('State')._(': '));
				$cells[][] = $ah->getStatusName();
				$return .= $HTML->multiTableRow(array(), $cells);
			}
		}
		if (forge_check_perm('tracker', $atid, 'manager')) {
			$cells = array();
			$cells[][] = html_e('strong', array(), _('Priority')._(': '));
			if (in_array('priority', $fieldInFormula)) {
				$class = 'in-formula';
			} else {
				$class = '';
			}
			if ($func == 'detail') {
				$cells[][] = $ath->priorityBox('priority', $ah->getPriority(), false, array('form' => 'trackerform', 'class' => $class));
			} else {
				$cells[][] = $ath->priorityBox('priority', $priority, false, array('form' => 'trackerform', 'class' => $class));
			}
			$return .= $HTML->multiTableRow(array(), $cells);
		} elseif ($func == 'detail') {
			$cells = array();
			$cells[][] = html_e('strong', array(), _('Priority')._(': '));
			$cells[][] = $ah->getPriority();
			$return .= $HTML->multiTableRow(array(), $cells);
		}
		$return .= $HTML->listTableBottom();
		if (forge_check_perm('tracker', $atid, 'tech')) {
			$return .= html_e('p', array('class' => 'middleRight'), html_e('input', array('form' => 'trackerform', 'type' => 'submit', 'name' => 'submit', 'value' => _('Save Changes'), 'title' => _('Save is validating the complete form'), 'onClick' => 'iefixform()')));
		}
		return $return;
	}

	function canBeRemove() {
		return false;
	}

	function canBeMinize() {
		return false;
	}

	function getCategory() {
		return _('Trackers');
	}
}
