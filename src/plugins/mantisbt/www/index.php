<?php
/**
 * MantisBT plugin
 *
 * Copyright 2009-2011, Franck Villaume - Capgemini
 * Copyright 2009, Fabien Dubois - Capgemini
 * Copyright 2010, Antoine Mercadal - Capgemini
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2011,2014, Franck Villaume - TrivialDev
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

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfconfig.'plugins/mantisbt/config.php';

$type = getStringFromRequest('type');

if (!$type) {
	exit_missing_param($_SERVER['HTTP_REFERER'], array('No TYPE specified'), 'mantisbt');
}

$editable = 1;
$mantisbt = plugin_get_object('mantisbt');

switch ($type) {
	case 'group': {
		$group_id = getIntFromRequest('group_id');
		if (!$group_id) {
			exit_missing_param($_SERVER['HTTP_REFERER'], array('No GROUP_ID specified'), 'mantisbt');
		}
		$group = group_get_object($group_id);
		if (!$group) {
			exit_no_group();
		}
		if (!$group->usesPlugin($mantisbt->name)) {//check if the group has the MantisBT plugin active
			exit_error(sprintf(_('First activate the %s plugin through the Project\'s Admin Interface'), $mantisbt->name), 'home');
		}
		if ( $group->isError()) {
			$error_msg .= $group->getErrorMessage();
		}

		if (session_loggedin()) {
			$user = session_get_user(); // get the session user

			if (!$user || !is_object($user)) {
				exit_error(_('Invalid User'), 'home');
			} elseif ( $user->isError()) {
				exit_error($user->isError(), 'home');
			} elseif ( !$user->isActive()) {
				exit_error(_('User not active'), 'home');
			}
		}

		$mantisbtConf = $mantisbt->getMantisBTConf($group_id);
		$view = getStringFromRequest('view');
		if ($mantisbtConf['id_mantisbt'] === 0) {
			$warning_msg = _('The mantisbt plugin for this project is not initialized.');
			$redirect_url = '/plugins/'.$mantisbt->name.'/?type=admin&group_id='.$group_id.'&view=init';
			session_redirect($redirect_url);
		}

		$action = '';
		if (isset($user)) {
			$userperm = $group->getPermission();
			if ($userperm->IsMember()) {
				$mantisbtUserConf = $mantisbt->getUserConf($mantisbtConf['url']);
				if ($mantisbtUserConf) {
					$username = $mantisbtUserConf['user'];
					$password = $mantisbtUserConf['password'];
				} else {
					$warning_msg = _('Your mantisbt user is not initialized for this URL.');
					session_redirect('/plugins/'.$mantisbt->name.'/?type=user&view=inituser&urlsetup='.urlencode($mantisbtConf['url']));
				}
				$action = getStringFromRequest('action');
			}
		}

		if (!isset($username) || !isset($password)) {
			$username = $mantisbtConf['soap_user'];
			$password = $mantisbtConf['soap_password'];
			$editable = 0;
		}

		$sort = getStringFromRequest('sort');
		$dir = getStringFromRequest('dir');
		$idBug = getStringFromRequest('idBug');
		$idNote = getStringFromRequest('idNote');
		$idAttachment = getStringFromRequest('idAttachment');
		$actionAttachment = getStringFromRequest('actionAttachment');
		$page = getStringFromRequest('page');
		global $gfplugins;

		switch ($action) {
			case 'updateIssue':
			case 'addNote':
			case 'addIssue':
			case 'deleteNote':
			case 'addAttachment':
			case 'deleteAttachment': {
				include($gfplugins.$mantisbt->name.'/action/'.$action.'.php');
				break;
			}
			case 'updateNote':
			case 'privateNote':
			case 'publicNote': {
				include($gfplugins.$mantisbt->name.'/action/updateNote.php');
				break;
			}
		}

		$mantisbt->getHeader('project');
		// URL analysis

		// Si la variable $_GET['page'] existe...
		if($page != null && $page != ''){
			$pageActuelle=intval($page);
		} else {
			$pageActuelle=1; // La page actuelle est la n??1
		}

		$format = "%07d";
		// do the job
		include ($mantisbt->name.'/www/group/index.php');
		break;
	}
	case 'user': {
		global $gfplugins;
		if (!session_loggedin()) {
			exit_not_logged_in();
		}
		$user = session_get_user();
		if (!($user) || !($user->usesPlugin($mantisbt->name))) {
			exit_error(sprintf(_('First activate the User\'s %s plugin through Account Maintenance Page'), $mantisbt->name), 'my');
		}
		$projects = $user->getGroups();
		$validProjectIds = array();
		$urlsMantisBTConf = array();
		foreach ($projects as $project) {
			if ($project->usesPlugin($mantisbt->name) && $mantisbt->isProjectMantisCreated($project->getID())) {
				$validProjects[] = $project;
				$projectMantisBTConf = $mantisbt->getMantisBTConf($project->getID());
				$urlsMantisBTConf[] = $projectMantisBTConf['url'];
			}
		}

		if (count($validProjects)) {
			$urlsMantisBTConf = array_unique($urlsMantisBTConf);
			$action = getStringFromRequest('action');
			$view = getStringFromRequest('view');
			$sort = getStringFromRequest('sort');
			$dir = getStringFromRequest('dir');
			$action = getStringFromRequest('action');
			$idBug = getStringFromRequest('idBug');
			$idNote = getStringFromRequest('idNote');
			$page = getStringFromRequest('page');

			if ($view != 'inituser' && $action != 'inituser') {
				$userMantisBTConf = array();
				foreach ($urlsMantisBTConf as $urlMantisBTConf) {
					$mantisbtConf = $mantisbt->getUserConf($urlMantisBTConf);
					if ($mantisbtConf) {
						$userMantisBTConf[] = $mantisbtConf;
					}
				}

				if (!count($userMantisBTConf)) {
					$warning_msg = _('Your mantisbt user is not initialized.');
					$redirect_url = '/plugins/'.$mantisbt->name.'/?type=user&view=inituser';
					session_redirect($redirect_url);
				}
			}

			switch ($action) {
				case 'addAttachment':
				case 'addNote':
				case 'deleteAttachment':
				case 'deleteNote':
				case 'deleteuserConf':
				case 'inituser':
				case 'updateIssue':
				case 'updateNote':
				case 'updateuserConf': {
					include($gfplugins.$mantisbt->name.'/action/'.$action.'.php');
					break;
				}
			}

			// Si la variable $_GET['page'] existe...
			if($page != null && $page != '') {
				$pageActuelle=intval($page);
			} else {
				$pageActuelle=1; // La page actuelle est la n??1
			}

			$format = "%07d";
			// do the job

			$mantisbt->getHeader('user');
			include($gfplugins.$mantisbt->name.'/www/user/index.php');
		} else {
			echo $HTML->information(_('None of your projects are using MantisBT plugin.'));
		}
		break;
	}
	case 'admin': {
		if (!session_loggedin()) {
			exit_not_logged_in();
		}
		$user = session_get_user();

		if (!($user) || !($user->usesPlugin($mantisbt->name))) {
			exit_error(sprintf(_('First activate the User\'s %s plugin through Account Maintenance Page'), $mantisbt->name), 'my');
		}

		$group_id = getIntFromRequest('group_id');
		if (!$group_id) {
			exit_missing_param($_SERVER['HTTP_REFERER'], array('No GROUP_ID specified'), 'mantisbt');
		}

		$group = group_get_object($group_id);
		if (!$group) {
			exit_no_group();
		}
		if (!$group->usesPlugin($mantisbt->name)) {//check if the group has the MantisBT plugin active
			exit_error(sprintf(_('First activate the %s plugin through the Project\'s Admin Interface'),$mantisbt->name), 'home');
		}
		if ($group->isError()) {
			$error_msg .= $group->getErrorMessage();
		}
		session_require_perm('project_admin', $group_id);

		$mantisbtConf = $mantisbt->getMantisBTConf($group_id);
		$action = getStringFromRequest('action');
		$view = getStringFromRequest('view');
		if ($view != 'init' && $action != 'init') {
			if ($mantisbtConf['id_mantisbt'] === 0) {
				$warning_msg = _('The mantisbt plugin for this project is not initialized.');
				$redirect_url = '/plugins/'.$mantisbt->name.'/?type=admin&group_id='.$group_id.'&view=init';
				session_redirect($redirect_url);
			}

			if (isset($user)) {
				$mantisbtUserConf = $mantisbt->getUserConf($mantisbtConf['url']);
				if ($mantisbtUserConf) {
					$username = $mantisbtUserConf['user'];
					$password = $mantisbtUserConf['password'];
				}
			}

			// no user init ? we shoud force this user to init his account
			if (!isset($username) || !isset($password)) {
				$warning_msg = _('Your mantisbt user is not initialized.');
				session_redirect('/plugins/'.$mantisbt->name.'/?type=user&view=inituser');
			}
		}

		switch ($action) {
			case 'init': {
				global $gfplugins;
				include($gfplugins.$mantisbt->name.'/action/'.$action.'.php');
				break;
			}
			case 'addCategory':
			case 'addVersion':
			case 'renameCategory':
			case 'deleteCategory':
			case 'deleteVersion':
			case 'updateVersion':
			case 'updateConf': {
				global $gfplugins;
				include($gfplugins.$mantisbt->name.'/action/'.$action.'.php');
				break;
			}
		}

		$mantisbt->getHeader('project');
		//only project admin can access here

		switch ($view) {
			case 'init': {
				$mantisbt->getInitDisplay();
				break;
			}
			default: {
				$mantisbt->getAdminView();
				break;
			}
		}
		break;
	}
	case 'globaladmin': {
		if (!session_loggedin()) {
			exit_not_logged_in();
		}
		session_require_global_perm('forge_admin');
		$action = getStringFromRequest('action');
		switch ($action) {
			case 'updateGlobalConf': {
				global $gfplugins;
				include($gfplugins.$mantisbt->name.'/action/'.$action.'.php');
				break;
			}
		}
		$mantisbt->getHeader('globaladmin');
		$mantisbt->getGlobalAdminView();
		break;
	}
}

site_project_footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
