<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 1999-2001 (c), VA Linux Systems, dtype
 * Copyright 2010, FusionForge Team
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

$group_id = getIntFromRequest("group_id");

if ((!$group_id) && $form_grp) {
	$group_id=$form_grp;
}

if (!$group_id) {
	exit_missing_param('',array(_('A project must be specified for this page.')),'');
}

if (isset ($sys_noforcetype) && $sys_noforcetype) {
	$project = &group_get_object($group_id);
	include $gfwww.'include/project_home.php';
} else {
	session_redirect('/projects/'. group_getunixname($group_id) .'/');
}
