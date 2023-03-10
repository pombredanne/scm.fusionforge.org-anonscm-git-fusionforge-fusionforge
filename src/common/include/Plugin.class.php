<?php
/**
 * FusionForge plugin system
 *
 * Copyright 2002, Roland Mas
 * Copyright 2010, Franck Villaume - Capgemini
 * Copyright 2001-2009, Xerox Corporation, Codendi Team
 * Copyright 2010, Mélanie Le Bail
 * Copyright 2011, Alain Peyrat - Alcatel-Lucent
 * Copyright 2013,2014 Franck Villaume - TrivialDev
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

/**
 * Plugin base class
 */

class Plugin extends FFError {
	var $name;
	var $text;
	var $hooks;
	var $provides;
	var $id = NULL;
	var $pkg_desc = 'No description available.';

	/**
	 * @param	int	$id
	 */
	function __construct($id=0) {
		parent::__construct();
		$this->name = false;
		$this->hooks = array();
	}

	/**
	 * GetHooks() - get list of hooks to subscribe to.
	 *
	 * @return	array	List of strings.
	 */
	function GetHooks() {
		return $this->hooks;
	}
	/**
	 * _addHooks() - add a hook to the list of hooks.
	 *
	 * @param	string	$name
	 * @return	string	name of the added hook
	 */
	function _addHook($name) {
		return $this->hooks[]=$name;
	}

	/**
	 * GetName() - get plugin name.
	 *
	 * @return	string	the plugin name.
	 */
	function GetName() {
		return $this->name;
	}

	/**
	 * getInstallDir() - get installation dir for the plugin.
	 *
	 * @return	string	the directory where the plugin should be linked.
	 */
	function getInstallDir() {
		if (isset($this->installdir) && $this->installdir) {
			return $this->installdir;
		} else {
			return 'plugins/'.$this->name;
		}
	}

	/**
	 * provide() - return true if plugin provides the feature.
	 *
	 * @param	string	$feature
	 * @return	bool	if feature is provided or not.
	 */
	function provide($feature) {
		return (isset($this->provides[$feature]) && $this->provides[$feature]);
	}

	/**
	 * CallHook() - call a particular hook.
	 *
	 * @param	string	$hookname	the "handle" of the hook.
	 * @param	array	$params		array of parameters to pass the hook.
	 * @return	bool	true only
	 */
	function CallHook($hookname, &$params) {
		return true;
	}

	/**
	 * getID - get the numeric ID of a plugin
	 *
	 * @return	int	identifier of the plugin
	 */
	function getID() {
		if ($this->id) {
			return $this->id;
		}

		$res = db_query_params('SELECT plugin_id FROM plugins WHERE plugin_name=$1',
					array($this->name));
		$this->id = db_result($res,0,'plugin_id');
		return $this->id;
	}

	/**
	 * getGroups - get a list of all groups using a plugin.
	 *
	 * @return	array	array containing group objects.
	 */
	function getGroups() {
		$result = array();
		$res = db_query_params('SELECT group_plugin.group_id
					FROM group_plugin, plugins
					WHERE group_plugin.plugin_id=plugins.plugin_id
					AND plugins.plugin_name=$1
					ORDER BY group_plugin.group_id ASC',
					array($this->name));
		$rows = db_numrows($res);

		for ($i=0; $i<$rows; $i++) {
			$group_id = db_result($res,$i,'group_id');
			$result[] = group_get_object($group_id);
		}
		return $result;
	}

	/*
	 * getThemePath - returns the directory of the theme for this plugin
	 *
	 * @return	string	the directory
	 */
	function getThemePath(){
		return 'plugins/'.$this->name.'/themes/default';
	}

	function install() {
		$this->installCode();
		$this->installConfig();
		$this->installDatabase();
	}

	function installCode() {
		$path = forge_get_config('plugins_path') . '/' . $this->name;
		$installdir = $this->getInstallDir();

		// Create a symbolic links to plugins/<plugin>/www (if directory exists).
		if (is_dir($path . '/www')) { // if the plugin has a www dir make a link to it
			// The apache group or user should have write perms the www/plugins folder...
			$www = dirname(dirname(dirname(__FILE__))).'/www';
			if (!is_link($www.'/'.$installdir)) {
				$code = symlink($path . '/www', $www.'/'.$installdir);
				if (!$code) {
					$this->setError('['.$www.'/'.$installdir.'->'.$path . '/www]<br />'.
						_('Soft link to www could not be created. Check the write permissions for apache in fusionforge www/plugins dir or create the link manually.'));
				}
			}
		}

		// Create a symbolic links to plugins/<plugin>/etc/plugins/<plugin> (if directory exists).
		if (is_dir($path . '/etc/plugins/' . $this->name)) {
			// The apache group or user should have write perms in /etc/fusionforge/plugins folder...
			if (!is_link(forge_get_config('config_path'). '/plugins/'.$this->name) && !is_dir(forge_get_config('config_path'). '/plugins/'.$this->name)) {
				$code = symlink($path . '/etc/plugins/' . $this->name, forge_get_config('config_path'). '/plugins/'.$this->name);
				if (!$code) {
					$this->setError('['.forge_get_config('config_path'). '/plugins/'.$this->name.'->'.$path . '/etc/plugins/' . $this->name . ']'.'<br />'.
					sprintf(_('Config file could not be linked to %s. Check the write permissions for apache in /etc/fusionforge/plugins or create the link manually.'), forge_get_config('config_path').'/plugins/'.$this->name));
				}
			}
		}
	}

	function installConfig() {
		$path = forge_get_config('plugins_path') . '/' . $this->name;

		// Create a symbolic links to plugins/<plugin>/etc/plugins/<plugin> (if directory exists).
		if (is_dir($path . '/etc/plugins/' . $this->name)) {
			// The apache group or user should have write perms in /etc/fusionforge/plugins folder...
			if (!is_link(forge_get_config('config_path'). '/plugins/'.$this->name) && !is_dir(forge_get_config('config_path'). '/plugins/'.$this->name)) {
				$code = symlink($path . '/etc/plugins/' . $this->name, forge_get_config('config_path'). '/plugins/'.$this->name);
				if (!$code) {
					$this->setError('['.forge_get_config('config_path'). '/plugins/'.$this->name.'->'.$path . '/etc/plugins/' . $this->name . ']'.'<br />'.
					sprintf(_('Config file could not be linked to %s. Check the write permissions for apache in /etc/fusionforge/plugins or create the link manually.'), forge_get_config('config_path').'/plugins/'.$this->name));
				}
			}
		}
	}

	function installDatabase() {
		$path = forge_get_config('plugins_path') . '/' . $this->name . '/db';

		require_once $GLOBALS['gfcommon'].'include/DatabaseInstaller.class.php';
		$di = new DatabaseInstaller($this->name, $path);

		// Search for database tables, if present then upgrade.
		$tablename = str_replace('-', '_', $this->name);
		$res=db_query_params ('SELECT COUNT(*) FROM pg_class WHERE (relname=$1 OR relname like $2) AND relkind=$3',
			array ('plugin_'.$tablename, 'plugin_'.$tablename.'_%', 'r'));
		$count = db_result($res,0,0);
		if ($count == 0) {
			$di->install();
		} else {
			$di->upgrade();
		}
	}

	function groupisactivecheckbox (&$params) {
		// Check if the group is active
		// This code creates the checkbox in the project edit public info page
		// to activate/deactivate the plugin
		$display = 1;
		$title = _('Current plugin status is').' '.forge_get_config('plugin_status', $this->name);
		$imgStatus = 'plugin_status_valid.png';

		$group = group_get_object($params['group']);

		if ( forge_get_config('plugin_status', $this->name) !== 'valid' ) {
			$display = 0;
			$imgStatus = 'plugin_status_broken.png';
		}
		if ( forge_get_config('installation_environment') === 'development' || $group->usesPlugin($this->name)) {
			$display = 1;
		}

		if ($display) {
			$flag = strtolower('use_'.$this->name);
			echo "<tr>\n";
			echo "<td>\n";
			echo ' <input id="'.$flag.'" type="checkbox" name="'.$flag.'" value="1" ';
			// checked or unchecked?
			if ($group->usesPlugin($this->name)) {
				echo 'checked="checked"';
			}
			echo ' />';
			echo "</td>\n";
			echo '<td title="'.$this->pkg_desc.'">';
			echo "<label for='".$flag."'><strong>";
			printf(_("Use %s"), $this->text);
			echo "</strong></label>";
			echo " ";
			echo html_image($imgStatus, 16, 16, array('alt'=>$title, 'title'=>$title));
			echo "</td>\n";
			echo "</tr>\n";
		}
	}

	/*
	 * @return	bool	actually only true ...
	 */
	function groupisactivecheckboxpost(&$params) {
		// this code actually activates/deactivates the plugin after the form was submitted in the project edit public info page
		$group = group_get_object($params['group']);
		$flag = strtolower('use_'.$this->name);
		if (getIntFromRequest($flag) == 1) {
			$group->setPluginUse($this->name);
		} else {
			$group->setPluginUse($this->name, false);
		}
		return true;
	}

	function userisactivecheckbox(&$params) {
		// Check if user is active
		// This code creates the checkbox in the user account maintenance page
		// to activate/deactivate the plugin
		$display = 1;
		$title = _('Current plugin status is').' '.forge_get_config('plugin_status', $this->name);
		$imgStatus = 'plugin_status_valid.png';

		$user = $params['user'];

		if ( forge_get_config('plugin_status', $this->name) !== 'valid' ) {
			$display = 0;
			$imgStatus = 'plugin_status_broken.png';
		}
		if ( forge_get_config('installation_environment') === 'development' || $user->usesPlugin($this->name)) {
			$display = 1;
		}
		if ($display) {
			$flag = strtolower('use_'.$this->name);
			echo '<div>';
			echo ' <input id="'.$flag.'" type="checkbox" name="'.$flag.'" value="1" ';
			// checked or unchecked?
			if ($user->usesPlugin($this->name)) {
				echo 'checked="checked"';
			}
			echo " />\n";
			echo "<label for='".$flag."'><strong>";
			printf(_("Use %s"), $this->text);
			echo "</strong></label>";
			echo html_image($imgStatus, 16, 16, array('alt'=>$title, 'title'=>$title));
			echo '</div>';
		}
	}

	function userisactivecheckboxpost(&$params) {
		// this code actually activates/deactivates the plugin after the form was submitted in the user account manteinance page
		$user = $params['user'];
		$flag = strtolower('use_'.$this->name);
		if (getIntFromRequest($flag) == 1) {
			$user->setPluginUse($this->name);
		} else {
			$user->setPluginUse($this->name, false);
		}
	}

	function getPluginDescription() {
		return $this->pkg_desc;
	}
}

class PluginSpecificRoleSetting {
	var $role;
	var $name = '';
	var $section = '';
	var $values = array();
	var $default_values = array();
	var $global = false;

	function __construct(&$role, $name, $global = false) {
		$this->global = $global;
		$this->role =& $role;
		$this->name = $name;
	}

	function SetAllowedValues($values) {
		$this->role->role_values = array_replace_recursive($this->role->role_values,
								   array($this->name => $values));
		if ($this->global) {
			$this->role->global_settings[] = $this->name;
		}
	}

	function SetDefaultValues($defaults) {
		foreach ($defaults as $rname => $v) {
			$this->role->defaults[$rname][$this->name] = $v;
		}
	}

	function setValueDescriptions($descs) {
		global $rbac_permission_names ;
		foreach ($descs as $k => $v) {
			$rbac_permission_names[$this->name.$k] = $v;
		}
	}

	function setDescription($desc) {
		global $rbac_edit_section_names ;
		$rbac_edit_section_names[$this->name] = $desc;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
