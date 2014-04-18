<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2014, Franck Villaume - TrivialDev
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

/* please do not add require here : use www/docman/index.php to add require */
/* global variables used */
global $g; // group object
global $group_id; // id of group

$doc_group = getIntFromRequest('dirid');

if (!forge_check_perm('docman', $group_id, 'approve')) {
	$warning_msg = _('Document Manager Action Denied.');
	if ($doc_group) {
		session_redirect('/docman/?group_id='.$group_id.'&view=listfile&dirid='.$doc_group);
	} else {
		session_redirect('/docman/?group_id='.$group_id);
	}
}

$uploaded_zip = getUploadedFile('uploaded_zip');
$dg = new DocumentGroup($g, $doc_group);

if ($dg->isError() || !$dg->injectArchive($uploaded_zip)) {
	$error_msg = $dg->getErrorMessage();
	session_redirect('/docman/?group_id='.$group_id);
}

$return_url = '/docman/?group_id='.$group_id;
if ($doc_group)
	$return_url .= '&dirid='.$doc_group;

$feedback = _('Archive injected successfully.');
session_redirect($return_url);
