<?php
/**
 * Projects Hierarchy Plugin
 * action: set docman hierarchy
 *
 * Copyright 2011, Franck Villaume - Capgemini
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

global $projectsHierarchy; // the projects-hierarchy plugin object
global $_SERVER;
global $id;

$status = getIntFromRequest('status');

if ($projectsHierarchy->setDocmanStatus($id, $status)) {
	$projectsHierarchy->redirect($_SERVER['HTTP_REFERER'], 'feedback', _('Successfully update status of hierarchical browsing'));
}
$projectsHierarchy->redirect($_SERVER['HTTP_REFERER'], 'error_msg', _('Failed to update status of hierarchical browsing'));
