<?php
/**
 * External authentication via A-Select for FusionForge
 *
 * This file is part of FusionForge
 *
 * This plugin, like FusionForge, is free software; you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
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

global $gfplugins;
require_once $gfplugins.'aselectextauth/common/Aselect.class.php';

class ASelectextauthPlugin extends Plugin {
	function __construct() {
		parent::__construct();
		$this->name = "aselectextauth";
		$this->text = _("A-select external authentication");
		$this->pkg_desc =
_("A system plugin for authenticating users in fusionforge. A-Select is a framework
where users can be authenticated by several means with Authentication
Service Providers.");
		$this->hooks[] = "session_set_entry";
	}

	function CallHook($hookname, &$params) {
		global $HTML ;

		switch ($hookname) {
		case "session_set_entry":
			$Aselect = new Aselect();
			$loginname = strtolower($Aselect->getUserName());//Since A-Select UserID is
																											//not case sensitive we pass it to lower case
			$passwd = '' ;

			$this->AuthUser($loginname, $passwd) ;
			break;
		default:
			// Forgot something
		}
	}

	function AuthUser ($loginname, $passwd) {
		global $feedback;

		if(!$loginname) {
			return false;
		}

		$u = user_get_object_by_name ($loginname) ;
		if ($u) {
			// User exists in DB
			if($u->getStatus()=='A'){ //we check if it's active
				$user_id = $u->getID();
				session_set_new($user_id); //create session cookie
				$GLOBALS['aselect_auth_failed']=false;
				return true ;
			} else {
				$GLOBALS['aselect_auth_failed']=true;
				return false ;
			}
		} else {
			$GLOBALS['aselect_auth_failed']=true;
			return false;
		}

	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
