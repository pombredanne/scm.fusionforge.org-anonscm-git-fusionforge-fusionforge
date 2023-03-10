<?php
/**
 * External authentication via OpenID for FusionForge
 * Copyright 2011, Roland Mas
 * Copyright 2011, Olivier Berger & Institut Telecom
 * Copyright 2016, Franck Villaume - TrivialDev
 *
 * This program was developped in the frame of the COCLICO project
 * (http://www.coclico-project.org/) with financial support of the Paris
 * Region council.
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

require_once $GLOBALS['gfcommon'].'include/User.class.php';

// from lightopenid (http://code.google.com/p/lightopenid/)
//require_once 'openid.php';

/**
 * Authentication manager for FusionForge CASification
 *
 */
class AuthOpenIDPlugin extends ForgeAuthPlugin {
	var $openid;

	var $openid_identity;

	function __construct() {
		global $gfconfig;
		parent::__construct();
		$this->name = "authopenid";
		$this->text = "OpenID authentication";

		$this->_addHook('display_auth_form');
		$this->_addHook("check_auth_session");
		$this->_addHook("fetch_authenticated_user");
		$this->_addHook("close_auth_session");
		$this->_addHook("usermenu");
		$this->_addHook("userisactivecheckbox"); // The "use ..." checkbox in user account
		$this->_addHook("userisactivecheckboxpost"); //

		$this->saved_login = '';
		$this->saved_user = NULL;

		$this->openid = FALSE;

		$this->openid_identity = FALSE;

		$this->declareConfigVars();
	}

	/**
	 * Display a form to input credentials
	 * @param unknown_type $params
	 * @return boolean
	 */
	function displayAuthForm(&$params) {
		if (!$this->isRequired() && !$this->isSufficient()) {
			return true;
		}
		global $HTML;
		$return_to = $params['return_to'];

		$result = html_e('p', array(), _('Cookies must be enabled past this point.'));

		$result .= $HTML->openForm(array('action' => '/plugins/'.$this->name.'/post-login.php', 'method' => 'get'));
		$result .= '<input type="hidden" name="form_key" value="' . form_generate_key() . '"/>
<input type="hidden" name="return_to" value="' . htmlspecialchars(stripslashes($return_to)) . '" />
'._('Your OpenID identifier')._(': ').'<input type="text" name="openid_identifier" />
<input type="submit" name="login" value="' . _('Login via OpenID') . '" />';
		$result .= $HTML->closeForm();

		$params['html_snippets'][$this->name] = $result;

	}

	/**
	 * Is there a valid session?
	 * @param	array	$params
	 */

	function checkAuthSession(&$params) {
		$this->saved_user = NULL;
		$user = NULL;

		if (isset($params['auth_token']) && $params['auth_token'] != '') {
			$user_id = $this->checkSessionToken($params['auth_token']);
		} else {
			$user_id = $this->checkSessionCookie();
		}
		if ($user_id) {
			$user = user_get_object($user_id);
		} else {
			if ($this->openid && $this->openid->identity) {
				$username = $this->getUserNameFromOpenIDIdentity($this->openid->identity);
				if ($username) {
					$user = $this->startSession($username);
				}
			}
		}

		$this->saved_user = $user;
		$this->setAuthStateResult($params, $user);
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $openid_identity
	 * @return Ambigous <boolean, associative>
	 */
	public function getUserNameFromOpenIDIdentity($openid_identity) {
		$user_name = FALSE;
		$res = db_query_params('SELECT users.user_name FROM users, plugin_authopenid_user_identities WHERE users.user_id = plugin_authopenid_user_identities.user_id AND openid_identity=$1',
							    array($openid_identity));
		if($res) {
			$row = db_fetch_array_by_row($res, 0);
			if($row) {
				$user_name = $row['user_name'];
			}
		}
		return $user_name;
	}

	/**
	 * Terminate an authentication session
	 * @param unknown_type $params
	 * @return boolean
	 */
	protected function declareConfigVars() {
		parent::declareConfigVars();

		// Change vs default
		forge_define_config_item_bool ('required', $this->name, false);

		// Change vs default
		forge_define_config_item_bool ('sufficient', $this->name, false);
	}

	/**
	 * Displays link to OpenID identities management tab in user's page ('usermenu' hook)
	 * @param unknown_type $params
	 */
	public function usermenu($params) {
		global $G_SESSION, $HTML;
		$text = $this->text; // this is what shows in the tab
		if ($G_SESSION->usesPlugin($this->name)) {
			//$param = '?type=user&id=' . $G_SESSION->getId() . "&pluginname=" . $this->name; // we indicate the part we're calling is the user one
			echo $HTML->PrintSubMenu (array ($text), array ('/plugins/'.$this->name.'/index.php'), array(_('coin pan')));
		}
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
