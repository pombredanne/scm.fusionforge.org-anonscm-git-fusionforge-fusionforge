<?php
/**
 * FusionForge authentication management
 *
 * Copyright 2011, Roland Mas
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

// See for details http://lists.fusionforge.org/pipermail/fusionforge-general/2011-February/001335.html

define('FORGE_AUTH_AUTHORITATIVE_ACCEPT', 1);
define('FORGE_AUTH_AUTHORITATIVE_REJECT', 2);
define('FORGE_AUTH_NOT_AUTHORITATIVE', 3);

/**
 * Pluggable Authentication plugins base class
 *
 * By default, the session cookie is used
 *
 */
abstract class ForgeAuthPlugin extends Plugin {
	function __construct() {
		parent::__construct();
		// Common hooks that can be enabled per plugin:
		// check_auth_session - is there a valid session?
		// fetch_authenticated_user - what FFUser is logged in?
		// display_auth_form - display a form to input credentials
		// display_create_user_form - display a form to create a user from external auth
		// sync_account_info - sync identity from external source (realname, email, etc.)
		// get_extra_roles - add new roles not necessarily stored in the database
		// restrict_roles - filter out unwanted roles
		// close_auth_session - terminate an authentication session

		$this->saved_user = NULL;
	}

	// Hook dispatcher
	function CallHook($hookname, &$params) {
		switch ($hookname) {
		case 'check_auth_session':
			$this->checkAuthSession($params);
			break;
		case 'fetch_authenticated_user':
			$this->fetchAuthUser($params);
			break;
		case 'display_auth_form':
			// no default implementation, but see AuthBuiltinPlugin::displayAuthForm()
			//  $params can be passed with a 'return_to' attribute
			//  it should return an HTML dialog appened to passed $params['html_snippets']
			//  it may return a redirection URL appened to  $params['transparent_redirect_urls']
			$this->displayAuthForm($params);
			break;
		case 'display_create_user_form':
			// no default implementation
			$this->displayCreateUserForm($params);
			break;
		case 'sync_account_info':
			// no default implementation
			$this->syncAccountInfo($params);
			break;
		case 'get_extra_roles':
			$this->getExtraRoles($params);
			break;
		case 'restrict_roles':
			$this->restrictRoles($params);
			break;
		case 'close_auth_session':
			$this->closeAuthSession($params);
			break;
		case 'refresh_auth_session':
			$this->refreshAuthSession($params);
			break;
		default:
			// Forgot something
		}
	}

	// Default mechanisms

	/**
	 * Current forge user
	 *
	 * @var object FFUser
	 */
	protected $saved_user;

	/**
	 * checkAuthSession - Is there a valid session?
	 *
	 * @param	array	$params
	 * @return	see setAuthStateResult()
	 * TODO : document 'auth_token' param
	 */
	function checkAuthSession(&$params) {
		// check the session cookie/token to get a user_id
		if (isset($params['auth_token']) && $params['auth_token'] != '') {
			$user_id = $this->checkSessionToken($params['auth_token']);
		} else {
			$user_id = $this->checkSessionCookie();
		}
		$this->saved_user = $user_id ? user_get_object($user_id) : NULL;
		$this->setAuthStateResult($params, $this->saved_user);
	}

	/**
	 * What FFUser is logged in?
	 *
	 * This will generate a valid forge user (by default, it was generated and cached already in saved_user)
	 *
	 * @param	array	$params
	 * @return	array	$params['results'] containing user object
	 */
	function fetchAuthUser(&$params) {
		if ($this->saved_user && $this->isSufficient()) {
			$params['results'] = $this->saved_user;
		}
	}

	/**
	 * Terminate an authentication session
	 * @param	array	$params
	 */
	function closeAuthSession($params) {
		if ($this->isSufficient() || $this->isRequired()) {
			$this->unsetSessionCookie();
		}
	}

	/**
	 * Add new roles not necessarily stored in the database
	 * @param	array	$params
	 */
	function getExtraRoles(&$params) {
		// $params['new_roles'][] = RBACEngine::getInstance()->getRoleById(123);
	}

	/**
	 * Filter out unwanted roles
	 * @param	array	$params
	 */
	function restrictRoles(&$params) {
		// $params['dropped_roles'][] = RBACEngine::getInstance()->getRoleById(123);
	}

	// Helper functions for individual plugins
	// FIXME : where is $this->cookie_name set ?
	protected $cookie_name;

	/**
	 * Returns the session cookie name for the auth plugin (by default forge_session_AUTHPLUGINNAME)
	 *
	 * @return	string
	 */
	protected function getCookieName() {
		if ($this->cookie_name) {
			return $this->cookie_name;
		}
		return 'forge_session_'.$this->name;
	}

	protected function checkSessionToken($token) {
		return session_check_session_token($token);
	}

	protected function checkSessionCookie() {
		$token = getStringFromCookie($this->getCookieName());
		return $this->checkSessionToken($token);
	}

	/**
	 * setSessionCookie - Sets the session cookie according to the user in $this->saved_user
	 */
	protected function setSessionCookie() {
		if($this->saved_user) {
			$cookie = session_build_session_token($this->saved_user->getID());
			session_cookie($this->getCookieName(), $cookie, "", forge_get_config('session_expire'));
		}
	}

	/**
	 * startSession - Start a new session for a user
	 *
	 * @param	string	$username
	 * @return	bool
	 */
	function startSession($username) {
		if ($this->isSufficient() || $this->isRequired()) {
			$params = array();
			$params['username'] = $username;
			$params['event'] = 'login';
			plugin_hook('sync_account_info', $params);
			$user = user_get_object_by_name_or_email($username);
			$this->saved_user = $user;
			$this->setSessionCookie();
			return $user;
		} else {
			return false;
		}
	}

	function refreshAuthSession() {
		$this->setSessionCookie();
	}

	protected function unsetSessionCookie() {
		session_cookie($this->getCookieName(), '');
	}

	/**
	 * TODO: Enter description here ...
	 * @return	Ambigous	<Ambigous, NULL, bool>
	 */
	public function isRequired() {
		return forge_get_config('required', $this->name);
	}

	/**
	 * TODO: Enter description here ...
	 * @return	Ambigous	<Ambigous, NULL, bool>
	 */
	public function isSufficient() {
		return forge_get_config('sufficient', $this->name);
	}

	/**
	 * TODO: Enter description here ...
	 * @param	unknown_type	$event
	 * @return	bool
	 */
	public function syncDataOn($event) {
		$configval = forge_get_config('sync_data_on', $this->name);
		$events = array();

		switch ($configval) {
		case 'every-page':
			$events = array('every-page','login','user-creation');
			break;
		case 'login':
			$events = array('login','user-creation');
			break;
		case 'user-creation':
			$events = array('user-creation');
			break;
		case 'never':
			$events = array();
			break;
		}

		return in_array($event, $events);
	}

	/**
	 * TODO: Enter description here ...
	 */
	protected function declareConfigVars() {
		forge_define_config_item_bool ('required', $this->name, false);
		forge_define_config_item_bool ('sufficient', $this->name, false);
		forge_define_config_item ('sync_data_on', $this->name, 'never');
	}

	/**
	 * Set 'results' array in the given array to a value expected by the auth support
	 *
	 * Auth support requires as a result of some functions that in the given $params array,
	 * the ['results'][<plugin_name>] key is set to one of the following values
	 *
	 *  - FORGE_AUTH_AUTHORITATIVE_ACCEPT
	 *  - FORGE_AUTH_AUTHORITATIVE_REJECT
	 *  - FORGE_AUTH_NOT_AUTHORITATIVE
	 *
	 *  depending on the given $state.
	 *
	 * @param array $params
	 * @param bool $state
	 * @return given state
	 * @return $param['results'][<plugin_name>] set
	 */
	protected function setAuthStateResult(&$params, $state)
	{
		if ($state) {
			if ($this->isSufficient()) {
				$params['results'][$this->name] = FORGE_AUTH_AUTHORITATIVE_ACCEPT;
			} else {
				$params['results'][$this->name] = FORGE_AUTH_NOT_AUTHORITATIVE;
			}
		} else {
			if ($this->isRequired()) {
				$params['results'][$this->name] = FORGE_AUTH_AUTHORITATIVE_REJECT;
			} else {
				$params['results'][$this->name] = FORGE_AUTH_NOT_AUTHORITATIVE;
			}
		}
		return $state;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
