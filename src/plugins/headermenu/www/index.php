<?php
/**
 * headermenu plugin : index page
 *
 * Copyright 2012,2022, Franck Villaume - TrivialDev
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

$type = getStringFromRequest('type');

if (!$type) {
	exit_missing_param($_SERVER['HTTP_REFERER'], array('No TYPE specified'), 'headermenu');
}

$headermenu = plugin_get_object('headermenu');

switch ($type) {
	case 'globaladmin': {
		if (!session_loggedin()) {
			exit_not_logged_in();
		}
		session_require_global_perm('forge_admin');
		$action = getStringFromRequest('action');
		$view = getStringFromRequest('view');
		switch ($action) {
			case 'addLink':
			case 'updateLinkValue':
			case 'deleteLink':
			case 'updateLinkStatus':
			case 'validateOrder': {
				global $gfplugins;
				include($gfplugins.$headermenu->name.'/action/'.$action.'.php');
				break;
			}
		}
		$headermenu->getHeader($type);
		switch ($view) {
			case 'updateLinkValue':
				global $gfplugins;
				include($gfplugins.$headermenu->name.'/view/admin/'.$view.'.php');
				break;
			default:
				$headermenu->getGlobalAdminView();
				break;
		}
		break;
	}
	case 'pageview': {
		$headermenu->pageid = getIntFromRequest('pageid');
		$headermenu->getHeader($type);
		echo $headermenu->pageView($headermenu->pageid);
		break;
	}
	case 'iframeview': {
		$headermenu->pageid = getIntFromRequest('pageid');
		$headermenu->getHeader($type);
		echo $headermenu->iframeView($headermenu->pageid);
		break;
	}
	case 'projectadmin': {
		if (!session_loggedin()) {
			exit_not_logged_in();
		}
		$group_id = getIntFromRequest('group_id');
		session_require_perm('project_admin', $group_id);
		$action = getStringFromRequest('action');
		$view = getStringFromRequest('view');

		switch ($action) {
			case 'addLink':
			case 'updateLinkValue':
			case 'deleteLink':
			case 'updateLinkStatus':
			case 'validateOrder': {
				global $gfplugins;
				include($gfplugins.$headermenu->name.'/action/'.$action.'.php');
				break;
			}
		}
		$headermenu->getHeader($type);
		switch ($view) {
			case 'updateLinkValue':
				global $gfplugins;
				include($gfplugins.$headermenu->name.'/view/admin/'.$view.'.php');
				break;
			default:
				$headermenu->getProjectAdminView();
				break;
		}
		break;
	}
}

site_project_footer();
