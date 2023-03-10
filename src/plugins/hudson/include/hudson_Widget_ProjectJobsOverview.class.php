<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright 2014,2016,2019,2021, Franck Villaume - TrivialDev
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

require_once 'HudsonOverviewWidget.class.php';
require_once 'common/include/HTTPRequest.class.php';
require_once 'PluginHudsonJobDao.class.php';
require_once 'HudsonJob.class.php';

class hudson_Widget_ProjectJobsOverview extends HudsonOverviewWidget {

	var $plugin;
	var $group_id;
	var $_not_monitored_jobs;
	var $_use_global_status = true;
	var $_all_status;
	var $_global_status;
	var $_global_status_icon;
	var $content;

	function __construct($plugin) {
		parent::__construct('plugin_hudson_project_jobsoverview');
		$this->plugin = $plugin;

		$this->group_id = getIntFromRequest('group_id');

		if ($this->_use_global_status === true) {
			$this->_all_status = array(
				'grey' => 0,
				'blue' => 0,
				'yellow' => 0,
				'red' => 0,
			);
			$this->computeGlobalStatus();
		}
		if (forge_check_perm('hudson', $this->group_id, 'read')) {
			$this->content['title'] = '';
			if ($this->_use_global_status === true) {
				$this->content['title'] = '<img src="'.$this->_global_status_icon.'" title="'.$this->_global_status.'" alt="'.$this->_global_status.'" /> ';
			}
			$this->content['title'] .= _("Hudson Jobs");
		}
	}

	function computeGlobalStatus() {
		$jobs = $this->getJobsByGroup($this->group_id);
		if (count($jobs)) {
			foreach ($jobs as $job) {
				$this->_all_status[(string)$job->getColorNoAnime()] = $this->_all_status[(string)$job->getColorNoAnime()] + 1;
			}
			if ($this->_all_status['grey'] > 0 || $this->_all_status['red'] > 0) {
				$this->_global_status = _("One or more failure or pending job");
				$this->_global_status_icon = '/'.$this->plugin->getIconsPath() . "status_red.png";
			} elseif ($this->_all_status['yellow'] > 0) {
				$this->_global_status = _("One or more unstable job");
				$this->_global_status_icon = '/'.$this->plugin->getIconsPath() . "status_yellow.png";
			} else {
				$this->_global_status = _("Success");
				$this->_global_status_icon = '/'.$this->plugin->getIconsPath() . "status_blue.png";
			}
		} else {
			$this->_use_global_status = false;
		}
	}

	function getTitle() {
		return $this->content['title'];
	}

	function getDescription() {
		return _("Shows an overview of all the jobs associated with this project. You can always choose the ones you want to display in the widget (preferences link).");
	}

	function getContent() {
		global $HTML;
		$jobs = $this->getJobsByGroup($this->group_id);
		$html = '';
		if (sizeof($jobs) > 0) {
			$html .= $HTML->listTableTop();
			foreach ($jobs as $job_id => $job) {
				try {
					$cells = array();
					$cells[][] = html_abs_image($job->getStatusIcon(), '15', '15', array('title' => $job->getStatus()));
					$cells[] = array(util_make_link('/plugins/hudson/?action=view_job&group_id='.$this->group_id.'&job_id='.$job_id, $job->getName()), 'style' => 'width: 99%');
					$html .= $HTML->multiTableRow(array(), $cells);
				} catch (Exception $e) {
					// Do not display wrong jobs
				}
			}
			$html .= $HTML->listTableBottom();
		} else {
			$html .= $HTML->information(_('No job available.'));
		}
		$html .= html_e('div', array('class' => 'underline-link'), util_make_link('/plugins/hudson/?group_id='.$this->group_id, _('Browse Hudson/Jenkins plugin')));
		return $html;
	}

	function isAvailable() {
		return isset($this->content['title']);
	}
}
