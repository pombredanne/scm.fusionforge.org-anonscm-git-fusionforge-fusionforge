<?php
/**
 * Copyright © 2006 $ThePhpWikiProgrammingTeam
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

/**
 * 1. User forgot password but has email in the prefs.
 *    => action=email&user=username will send the password per email in plaintext.
 *
 *    If no email is stored, because user might not exist,
 *    => "No e-mail stored for user %s.
 *        You need to ask an Administrator to reset this password."
 *       Problem: How to contact Admin? Present a link to ADMIN_USER
 *
 *    If no email exists but is not verified,
 *    => "Warning: This users email address is unverified!"
 *
 * 2. Admin may reset any users password, with verification.
 *    => action=reset&user=username
 */

class WikiPlugin_PasswordReset
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Allow admin to reset any users password, allow user to request his password by e-mail.");
    }

    function getDefaultArguments()
    {
        return array('user' => '');
    }

    /* reset password, verified */
    private function doReset($userid)
    {

        $user = WikiUser($userid);
        $prefs = $user->getPreferences();
        $prefs->set('passwd', '');
        if ($user->setPreferences($prefs)) {
            $alert = new Alert(_("Message"),
                fmt("The password for user %s has been deleted.", $userid));
        } else {
            $alert = new Alert(_("Error"),
                fmt("The password for user %s could not be deleted.", $userid));
        }
        $alert->show();
    }

    /**
     * @param WikiRequest $request
     * @param string $userid
     */
    private function doEmail(&$request, $userid)
    {

        $thisuser = WikiUser($userid);
        $prefs = $thisuser->getPreferences();
        $email = $prefs->get('email');
        $passwd = $prefs->get('passwd'); // plain?
        $from = $request->_user->getId() . '@' . $request->get('REMOTE_HOST');
        if (mail($email,
            "[" . WIKI_NAME . "] PasswortReset",
            "PasswortReset requested by $from\r\n" .
                "Password for " . WIKI_NAME . ": $passwd",
            "From: $from")
        )
            $alert = new Alert(_("Message"),
                fmt("E-mail sent to the stored e-mail address for user %s", $userid));
        else
            $alert = new Alert(_("Error"),
                fmt("Error sending e-mail with password for user %s.", $userid));
        $alert->show();
    }

    /**
     * @param WikiRequest $request
     * @param string $userid
     * @param string $header
     * @param string $footer
     * @return HtmlElement
     */
    private function doForm(&$request, $userid = '', $header = '', $footer = '')
    {
        if (!$header) {
            $header = HTML::p(_("Reset password of user: "),
                HTML::raw('&nbsp;'),
                HTML::input(array('type' => 'text',
                    'required' => 'required',
                    'name' => 'user',
                    'value' => $userid))
            );
        }
        if (!$footer) {
            $isadmin = $request->_user->isAdmin();
            $footer = HTML::p(Button('submit:admin_reset[reset]',
                    $isadmin ? _("Yes") : _("Send e-mail"),
                    $isadmin ? 'wikiadmin' : 'button'),
                HTML::raw('&nbsp;'),
                Button('submit:admin_reset[cancel]', _("Cancel"), 'button', array('formnovalidate' => 'formnovalidate')));
        }
        return HTML::form(array('action' => $request->getPostURL(),
                'method' => 'post'),
            $header,
            HiddenInputs($request->getArgs(), false, array('admin_reset', 'user')),
            ENABLE_PAGEPERM ? '' : HiddenInputs(array('require_authority_for_post' => WIKIAUTH_ADMIN)),
            $footer);
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        $args = $this->getArgs($argstr, $request);
        $user =& $request->_user;
        $post_args = $request->getArg('admin_reset');
        $userid = $args['user'];
        if (!$userid) $userid = $request->getArg('user');
        $isadmin = $user->isAdmin();
        if ($request->isPost()) {
            if ($post_args === false) {
                return $this->doForm($request, $userid);
            }
            if (!array_key_exists('reset', $post_args)) {
                return $this->doForm($request, $userid);
            }
            if (!$userid) {
                $alert = new Alert(_("Warning:"),
                    _("You need to specify the userid!"));
                $alert->show();
                return $this->doForm($request);
            }
            if ($userid and !empty($post_args['verify'])) {
                if ($user->isAdmin()) {
                    $this->doReset($userid);
                    return '';
                } else {
                    $this->doEmail($request, $userid);
                    return '';
                }
            } elseif (empty($post_args['verify'])) {
                //TODO: verify should check if the user exists, his prefs can be read/saved
                //      and the email is verified, even if admin.
                $buttons = HTML::p(Button('submit:admin_reset[reset]',
                        $isadmin ? _("Yes") : _("Send e-mail"),
                        $isadmin ? 'wikiadmin' : 'button'),
                    HTML::raw('&nbsp;'),
                    Button('submit:admin_reset[cancel]', _("Cancel"), 'button'));
                $header = HTML::strong(_("Verify"));
                if (!$user->isAdmin()) {
                    // check for email
                    if ($userid == $user->UserName() and $user->isAuthenticated()) {
                        $alert = new Alert(_("Already logged in"),
                            HTML(fmt("Changing passwords is done at "), WikiLink(_("UserPreferences"))));
                        $alert->show();
                        return '';
                    }
                    $thisuser = WikiUser($userid);
                    $prefs = $thisuser->getPreferences();
                    $email = $prefs->get('email');
                    if (!$email) {
                        $alert = new Alert(_("Error"),
                            HTML(fmt("No e-mail stored for user %s.", $userid),
                                HTML::br(),
                                fmt("You need to ask an Administrator to reset this password. See below: "),
                                HTML::br(), WikiLink(ADMIN_USER)));
                        $alert->show();
                        return '';
                    }
                    $verified = $thisuser->_prefs->_prefs['email']->getraw('emailVerified');
                    if (!$verified)
                        $header->pushContent(HTML::br(), _("Warning: This users email address is unverified!"));
                }
                return $this->doForm($request, $userid,
                    $header,
                    HTML(HTML::hr(),
                        fmt("Do you really want to reset the password of user %s?", $userid),
                        $isadmin ? '' : _("An e-mail will be sent."),
                        HiddenInputs(array('admin_reset[verify]' => 1, 'user' => $userid)),
                        $buttons));
            } else { // verify ok, but no userid
                return $this->doForm($request, $userid);
            }
        } else {
            return $this->doForm($request, $userid);
        }
    }
}
