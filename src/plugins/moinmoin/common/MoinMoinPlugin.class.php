<?php

/**
 * MoinMoinPlugin Class
 *
 *
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

forge_define_config_item('src_path','moinmoin', "/usr/share/moin");
forge_define_config_item('wiki_data_path','moinmoin', '$core/data_path/plugins/moinmoin/wikidata');
forge_define_config_item('use_frame', 'moinmoin', false);
forge_set_config_item_bool('use_frame', 'moinmoin');

class MoinMoinPlugin extends Plugin {
	function MoinMoinPlugin () {
		$this->Plugin() ;
		$this->name = "moinmoin" ;
		$this->text = "MoinMoinWiki" ; // To show in the tabs, use...
		$this->hooks[] = "groupmenu" ;	// To put into the project tabs
		$this->hooks[] = "groupisactivecheckbox" ; // The "use ..." checkbox in editgroupinfo
		$this->hooks[] = "groupisactivecheckboxpost" ; //
		$this->hooks[] = "project_public_area";
		$this->hooks[] = "role_get";
		$this->hooks[] = "role_normalize";
		$this->hooks[] = "role_translate_strings";
		$this->hooks[] = "role_has_permission";
		$this->hooks[] = "role_get_setting";
		$this->hooks[] = "list_roles_by_permission";
		$this->hooks[] = "project_admin_plugins"; // to show up in the admin page for group
		$this->hooks[] = "clone_project_from_template" ;
	}

	function getWikiUrl ($project) {
		if (forge_get_config('use_frame', 'moinmoin')){
			return util_make_uri('/plugins/moinmoin/frame.php?group_id=' . $project->getID()) ; 
		} else {
			return util_make_uri('/plugins/moinmoin/'.$project->getUnixName().'/FrontPage');
		}
	}

	function CallHook ($hookname, &$params) {
		if (isset($params['group_id'])) {
			$group_id=$params['group_id'];
		} elseif (isset($params['group'])) {
			$group_id=$params['group'];
		} else {
			$group_id=null;
		}
		if ($hookname == "groupmenu") {
			$project = group_get_object($group_id);
			if (!$project || !is_object($project)) {
				return;
			}
			if ($project->isError()) {
				return;
			}
			if (!$project->isProject()) {
				return;
			}
			if ( $project->usesPlugin ( $this->name ) ) {
				$params['TITLES'][]=$this->text;
				$params['DIRS'][]=$this->getWikiUrl($project);
			}
			(($params['toptab'] == $this->name) ? $params['selected']=(count($params['TITLES'])-1) : '' );
		} elseif ($hookname == "groupisactivecheckbox") {
			//Check if the group is active
			// this code creates the checkbox in the project edit public info page to activate/deactivate the plugin
			$group = group_get_object($group_id);
			echo "<tr>";
			echo "<td>";
			echo ' <input type="checkbox" name="use_moinmoinplugin" value="1" ';
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
			$group = group_get_object($group_id);
			$use_moinmoinplugin = getStringFromRequest('use_moinmoinplugin');
			if ( $use_moinmoinplugin == 1 ) {
				$group->setPluginUse ( $this->name );
			} else {
				$group->setPluginUse ( $this->name, false );
			}
		} elseif ($hookname == "project_public_area") {
			$project = group_get_object($group_id);
			if (!$project || !is_object($project)) {
				return;
			}
			if ($project->isError()) {
				return;
			}
			if (!$project->isProject()) {
				return;
			}
			if ( $project->usesPlugin ( $this->name ) ) {
				echo '<div class="public-area-box">';
				print '<a href="'. $this->getWikiUrl($project).'">';
				print 'MoinMoin';
				print '</a>';
				echo '</div>';
			}
		}
	}
  }

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
