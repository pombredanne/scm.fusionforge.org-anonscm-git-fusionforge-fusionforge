<?php
/**
 * MantisBT plugin
 *
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2012, Franck Villaume - TrivialDev
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

/* addVersion action page */
$version = getStringFromRequest('version');
if (!empty($version)) {
	$versionStruct = array();
	$versionStruct['name'] = $version;
	$versionStruct['project_id'] = $mantisbtConf['id_mantisbt'];
	$versionStruct['released'] = 0;
	$versionStruct['description'] = getStringFromRequest('description');
	$versionStruct['date_order'] = '';
	try {
		if (!isset($clientSOAP)) {
			$clientSOAP = new SoapClient($mantisbtConf['url']."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
		}
		$clientSOAP->__soapCall('mc_project_version_add', array("username" => $username, "password" => $password, "version" => $versionStruct));
		// currently transverse is not implemented... need to rely on projects-hierarchy plugin.
// 		if (isset($_POST['transverse'])) {
// 			$listChild = $clientSOAP->__soapCall('mc_project_get_all_subprojects', array("username" => $username, "password" => $password, "project_id" => $idProjetMantis));
// 			foreach ($listChild as $key => $child) {
// 				$listVersions = $clientSOAP->__soapCall('mc_project_get_versions', array("username" => $username, "password" => $password, "project_id" => $child));
// 				$todo = 1;
// 				foreach ($listVersions as $key => $version ) {
// 					if ($version->name == $versionStruct['name'])
// 						$todo = 0;
// 				}
// 				if ($todo) {
// 					try {
// 						$versionStruct['project_id'] = $child;
// 						$clientSOAP->__soapCall('mc_project_version_add', array("username" => $username, "password" => $password, "version" => $versionStruct));
// 					} catch (SoapFault $soapFault) {
// 						$error_msg = _('Task failed')._(': ').$versionStruct['name'].' '.$soapFault->faultstring;
// 						session_redirect('plugins/mantisbt/?type=admin&group_id='.$group_id.'&pluginname='.$mantisbt->name);
// 					}
// 				}
// 			}
// 		}
	} catch (SoapFault $soapFault) {
		$error_msg = _('Task failed')._(': ').$versionStruct['name'].' '.$soapFault->faultstring;
		session_redirect('plugins/mantisbt/?type=admin&group_id='.$group_id.'&pluginname='.$mantisbt->name);
	}
	$feedback = _('Task succeeded.');
	session_redirect('plugins/mantisbt/?type=admin&group_id='.$group_id.'&pluginname='.$mantisbt->name);
}
$warning_msg = _('Missing version.');
session_redirect('plugins/mantisbt/?type=admin&group_id='.$group_id.'&pluginname='.$mantisbt->name);
