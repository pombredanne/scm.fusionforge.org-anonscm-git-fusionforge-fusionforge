<?php
/**
 * Copyright 2016-2017,2022, Franck Villaume - TrivialDev
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
require_once $gfcommon.'include/Activity.class.php';

class Widget_HomeDetailActivityMostActiveProjectWeek extends Widget {

	function __construct() {
		parent::__construct('homedetailactivitymostactiveprojectweek');
		if (forge_get_config('use_activity')) {
			$this->title = _('Detailed Activity for the 10 Most Active Projects this Week');
		}
	}

	function getContent() {
		global $HTML;
		$stats = new Stats();
		$result = $stats->getMostActiveStats('week', 0);
		$return = '';
		$count = 1;
		$selected_groups = array();
		while (($row = db_fetch_array($result)) && ($count <= 10)) {
			$group = group_get_object($row['group_id']);
			if (forge_check_perm('project_read', $row['group_id']) && $group->usesActivity()) {
				$selected_groups[] = $row['group_id'];
				$count++;
			}
		}
		$begin = (time() - (7 * 86400));
		$end = time();
		$ffactivity = new Activity();
		$activities = $ffactivity->getActivitiesForProjects($selected_groups, $begin, $end);

		foreach ($selected_groups as $group_id) {
			// If plugins wants to add activities.
			$hookParams['group_id'] = $group_id;
			$hookParams['results'] = &$activities;
			$hookParams['begin'] = $begin;
			$hookParams['end'] = $end;
			$hookParams['show'] = array();
			$hookParams['ids'] = array();
			$hookParams['texts'] = array();
			plugin_hook('activity', $hookParams);
		}
		if (count($activities) > 0) {
			$date_format = _('%Y-%m-%d');
			usort($activities, 'Activity::date_compare');
			$displayTableTop = 0;
			$last_day = 0;
			$displayed_activities = false;
			$cached_perms = array();
			foreach ($activities as $activity) {
				if (!$ffactivity->check_perm_for_activity($activity, $cached_perms)) {
					continue;
				}
				if (!$displayTableTop) {
					$theader = array();
					$theader[] = _('Time');
					$theader[] = _('Project');
					$theader[] = _('Activity');
					$theader[] = _('By');

					$return .= $HTML->listTableTop($theader);
					$displayTableTop = 1;
				}
				$displayinfo = $ffactivity->getDisplayInfo($activity);
				if (!$displayinfo) {
					continue;
				}
				$displayed_activities = true;
				if ($last_day != strftime($date_format, $activity['activity_date'])) {
					$cells = array();
					$cells[] = array(strftime($date_format, $activity['activity_date']), 'colspan' => 4);
					$return .= $HTML->multiTableRow(array('class' => 'tableheading'), $cells, true);
					$last_day=strftime($date_format, $activity['activity_date']);
				}
				$cells = array();
				$cells[][] = date('H:i:s', $activity['activity_date']);
				$group_object = group_get_object($activity['group_id']);

				$cells[][] = util_make_link_g($group_object->getUnixName(), $activity['group_id'], $group_object->getPublicName());
				$cells[][] = $displayinfo;
				if (isset($activity['user_name']) && $activity['user_name']) {
					$cells[][] = util_display_user($activity['user_name'], $activity['user_id'],$activity['realname']);
				} else {
					$cells[][] = $activity['realname'];
				}
				$return .= $HTML->multiTableRow(array(), $cells);
			}
			if ($displayTableTop) {
				$return .= $HTML->listTableBottom();
			}
			if (!$displayed_activities) {
				$return .= $HTML->warning_msg(_('No activity during the last week.'));
			}
		} else {
			$return .= $HTML->warning_msg(_('No activity during the last week.'));
		}
		$pm = plugin_manager_get_object();
		if ($pm->PluginIsInstalled('globalactivity')) {
			$return .= util_make_link('/plugins/globalactivity/', _('Browse all activities.'));
		}
		return $return;
	}

	function getTitle() {
		return $this->title;
	}

	function isAvailable() {
		return isset($this->title);
	}
}
