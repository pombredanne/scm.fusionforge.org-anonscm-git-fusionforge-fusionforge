<?php
/**
 * FusionForge authentication management
 *
 * Copyright 2011, Roland Mas
 * Copyright 2014-2015,2019, Franck Villaume - TrivialDev
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
 * Default authentication mechanism based on DB user's password storage
 *
 */

class AuthBuiltinPlugin extends ForgeAuthPlugin {

	function __construct() {
		parent::__construct();

		$this->name = 'authbuiltin';
		$this->text = _('Built-in authentication');

		$this->_addHook('check_auth_session');
		$this->_addHook('fetch_authenticated_user');
		$this->_addHook('display_auth_form');
		// display_create_user_form - display a form to create a user from external auth
		// sync_account_info - sync identity from external source (realname, email, etc.)
		// get_extra_roles - add new roles not necessarily stored in the database
		// restrict_roles - filter out unwanted roles
		$this->_addHook('close_auth_session');
		$this->_addHook("refresh_auth_session");

		$this->declareConfigVars();
	}

	/**
	 * Display a form to input credentials : default login dialog ('display_auth_form' hook)
	 * @param unknown_type $params
	 * @return boolean
	 */
	function displayAuthForm(&$params) {
		global $HTML;
		if (!$this->isRequired() && !$this->isSufficient()) {
			return true;
		}
		$return_to = $params['return_to'];
		$loginname = '';
		if (isset($params['attempts']) && $params['attempts'] >= 1) {
			$loginname = $params['previousLogin'];
		}

		$result = '';

		$result .= html_e('p', array(), _('Cookies must be enabled past this point.'), false);
		$result .= $HTML->openForm(array('action' => '/plugins/'.$this->name.'/post-login.php', 'method' => 'post'), true);
		$result .= html_e('input', array('type' => 'hidden', 'name' => 'form_key', 'value' => form_generate_key()));
		$result .= html_e('input', array('type' => 'hidden', 'name' => 'return_to', 'value' => $return_to));
		$result .= html_ao('p');
		if (forge_get_config('require_unique_email')) {
			$result .= html_e('label', array('for' => 'form_loginname'), _('Login name or email address')._(':'));
		} else {
			$result .= html_e('label', array('for' => 'form_loginname'), _('Login Name')._(':'));
		}
		$result .= html_e('br').html_e('input', array('type' => 'text', 'id' => 'form_loginname', 'name' => 'form_loginname', 'value' => htmlspecialchars(stripslashes($loginname)), 'required' => 'required'));
		$result .= html_ac(html_ap() -1);
		$result .= html_ao('p').html_e('label', array('for' => 'form_pw'), _('Password')._(':'));
		$result .= html_e('br').html_e('input', array('type' => 'password', 'id' => 'form_pw', 'name' => 'form_pw', 'required' => 'required'));
		$result .= html_ac(html_ap() -1);
		if (isset($params['attempts'])) {
			$result .= html_e('input', array('type' => 'hidden', 'name' => 'attempts', 'value' => $params['attempts']));
			if (isset($params['previousLogin'])) {
				$result .= html_e('input', array('type' => 'hidden', 'name' => 'previous_login', 'value' => $params['previousLogin']));
			}
			if ($params['attempts'] > 3) {
				 plugin_hook_by_reference('captcha_form', $result);
			}
		}
		$result .= html_e('p', array(), html_e('input', array('type' => 'submit', 'name' => 'login', 'value' => _('Login'))), false);
		$result .= $HTML->closeForm();
		$result .= html_e('p', array(), util_make_link('/account/lostpw.php', _('[Lost your password?]')));
		// hide "new account" item if restricted to admin
		if (!forge_get_config ('user_registration_restricted')) {
			$result .= html_e('p', array(), util_make_link('/account/register.php', _('New Account')));
		}
		$result .= html_e('p', array(), util_make_link('/account/pending-resend.php', _('Resend confirmation email to a pending account')));

		$params['html_snippets'][$this->name] = $result;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
