<?php
/**
 * oslcPlugin Class
 *
 * Copyright 2016, Franck Villaume - TrivialDev
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once 'common/include/rdfutils.php';

class oslcPlugin extends Plugin {
	function __construct($id=0) {
		parent::__construct($id) ;
		$this->name = "oslc";
		$this->text = "oslc"; // To show in the tabs, use...
		$this->_addHook("user_personal_links");//to make a link to the user's personal part of the plugin
		$this->_addHook("usermenu");
		$this->_addHook("groupmenu");	// To put into the project tabs
		$this->_addHook("groupisactivecheckbox"); // The "use ..." checkbox in editgroupinfo
		$this->_addHook("groupisactivecheckboxpost"); //
		$this->_addHook("userisactivecheckbox"); // The "use ..." checkbox in user account
		$this->_addHook("userisactivecheckboxpost"); //
		$this->_addHook("project_admin_plugins"); // to show up in the admin page fro group
		$this->_addHook("project_rdf_metadata"); // will provide some RDF metadata for the project's DOAP profile to 'doaprdf' plugin
	}

	function CallHook($hookname, &$params) {
		global $use_oslcplugin,$G_SESSION,$HTML;
		if ($hookname == "usermenu") {
			$text = $this->text; // this is what shows in the tab
			if ($G_SESSION->usesPlugin("oslc")) {
				$param = '?type=user&id=' . $G_SESSION->getId() . '&pluginname=' . $this->name; // we indicate the part we're calling is the user one
				echo $HTML->PrintSubMenu (array ($text),
						  array ('/plugins/oslc/index.php' . $param ));
			}
		} elseif ($hookname == "groupmenu") {
			$group_id=$params['group'];
			$project = group_get_object($group_id);
			if (!$project || !is_object($project)) {
				return false;
			}
			if ($project->isError()) {
				return false;
			}
			if ( $project->usesPlugin ( $this->name ) ) {
				$params['TITLES'][]=$this->text;
				$params['DIRS'][]=util_make_uri('/plugins/oslc/');
				if (session_loggedin()) {
					$userperm = $project->getPermission();
					if ($userperm->isAdmin()) {
						$params['ADMIN'][]='';
					}
				}
				$params['TOOLTIPS'][] = NULL;
			} else {
				$params['TITLES'][]=$this->text." is [Off]";
				$params['DIRS'][]='';
				if (session_loggedin()) {
					$userperm = $project->getPermission();
					if ($userperm->isAdmin()) {
						$params['ADMIN'][]='';
					}
				}
				$params['TOOLTIPS'][] = NULL;
			}
			(($params['toptab'] == $this->name) ? $params['selected']=(count($params['TITLES'])-1) : '' );
		} elseif ($hookname == "groupisactivecheckbox") {
			//Check if the group is active
			// this code creates the checkbox in the project edit public info page to activate/deactivate the plugin
			$group_id=$params['group'];
			$group = group_get_object($group_id);
			echo "<tr>";
			echo "<td>";
			echo ' <input type="checkbox" name="use_oslcplugin" value="1" ';
			// checked or unchecked?
			if ( $group->usesPlugin ( $this->name ) ) {
				echo "checked";
			}
			echo " /><br/>";
			echo "</td>";
			echo "<td>";
			echo "<strong>Use ".$this->text." Plugin</strong>";
			echo "</td>";
			echo "</tr>";
		} elseif ($hookname == "groupisactivecheckboxpost") {
			// this code actually activates/deactivates the plugin after the form was submitted in the project edit public info page
			$group_id=$params['group'];
			$group = group_get_object($group_id);
			$use_oslcplugin = getStringFromRequest('use_oslcplugin');
			if ( $use_oslcplugin == 1 ) {
				$group->setPluginUse ( $this->name );
			} else {
				$group->setPluginUse ( $this->name, false );
			}
		} elseif ($hookname == "userisactivecheckbox") {
			//check if user is active
			// this code creates the checkbox in the user account manteinance page to activate/deactivate the plugin
			$user = $params['user'];
			echo "<tr>";
			echo "<td>";
			echo ' <input type="checkbox" name="use_oslcplugin" value="1" ';
			// checked or unchecked?
			if ( $user->usesPlugin ( $this->name ) ) {
				echo "checked";
 			}
			echo " />    Use ".$this->text." Plugin";
			echo "</td>";
			echo "</tr>";
		} elseif ($hookname == "userisactivecheckboxpost") {
			// this code actually activates/deactivates the plugin after the form was submitted in the user account manteinance page
			$user = $params['user'];
			$use_oslcplugin = getStringFromRequest('use_oslcplugin');
			if ( $use_oslcplugin == 1 ) {
				$user->setPluginUse ( $this->name );
			} else {
				$user->setPluginUse ( $this->name, false );
			}
			echo "<tr>";
			echo "<td>";
			echo ' <input type="checkbox" name="use_oslcplugin" value="1" ';
			// checked or unchecked?
			if ( $user->usesPlugin ( $this->name ) ) {
				echo "checked";
			}
			echo " />    Use ".$this->text." Plugin";
			echo "</td>";
			echo "</tr>";
		} elseif ($hookname == "user_personal_links") {
			// this displays the link in the user's profile page to it's personal oslc (if you want other sto access it, youll have to change the permissions in the index.php
			$userid = $params['user_id'];
			$user = user_get_object($userid);
			$text = $params['text'];
			//check if the user has the plugin activated
			if ($user->usesPlugin($this->name)) {
				echo '	<p>' ;
				echo util_make_link("/plugins/oslc/index.php?id=$userid&type=user&pluginname=".$this->name,
						     _('View Personal oslc')
					);
				echo '</p>';
			}
		} elseif ($hookname == "project_admin_plugins") {
			// this displays the link in the project admin options page to its oslc administration
			$group_id = $params['group_id'];
			$group = group_get_object($group_id);
			if ( $group->usesPlugin ( $this->name ) ) {
				echo '<p>'.util_make_link ("/plugins/oslc/admin/index.php?id=".$group->getID().'&type=admin&pluginname='.$this->name,
						     _('oslc Admin')).'</p>' ;
			}
		} elseif($hookname == "project_rdf_metadata") {

			# TODO : create another resource
			$group_id=$params['group'];

			if (! isset($params['prefixes']['oslc'])) {
				$params['prefixes']['oslc'] = 'http://open-services.net/ns/core#';
			}

			$serviceprovider = util_make_url ("/plugins/oslc/cm/oslc-cm-services/".$group_id);
			$res = $params['in_Resource'];
			rdfutils_setPropToUri($res, 'oslc:serviceProvider', $serviceprovider);
			$params['out_Resources'][] = $res;
		}
		return true;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
