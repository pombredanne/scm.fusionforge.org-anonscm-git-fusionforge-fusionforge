<?php
/**
 * MantisBT plugin
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

global $mantisbt;
global $mantisbtConf;
global $username;
global $password;
global $group_id;
global $idBug;

try {
	if (!isset($clientSOAP)) {
		$clientSOAP = new SoapClient($mantisbtConf['url'].'/api/soap/mantisconnect.php?wsdl', array('trace' => true, 'exceptions' => true));
	}
	$clientSOAP->__soapCall('mc_issue_attachment_add', array('username' => $username, 'password' => $password, 'issue_id' => $idBug, 'name' => $_FILES['attachment']['name'], 'file_type' => $_FILES['attachment']['type'], 'content' => file_get_contents($_FILES['attachment']['tmp_name'])));
	$feedback = _('Task succeeded.');
} catch (SoapFault $soapFault) {
	$error_msg = _('Task failed')._(': ').$soapFault->faultstring;
	session_redirect('/plugins/'.$mantisbt->name.'/?type=group&group_id='.$group_id.'&idBug='.$idBug.'&view=viewIssue');
}
session_redirect('/plugins/'.$mantisbt->name.'/?type=group&group_id='.$group_id.'&idBug='.$idBug.'&view=viewIssue');
