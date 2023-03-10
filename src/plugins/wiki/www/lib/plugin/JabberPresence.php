<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
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
 * A simple Jabber presence WikiPlugin.
 * http://wiki.crao.net/index.php/JabberPr%E9sence/Source
 * http://edgar.netflint.net/howto.php
 *
 * Usage:
 *  <<JabberPresence scripturl=http://edgar.netflint.net/status.php
 *                          jid=yourid@jabberserver type=html iconset=phpbb >>
 *
 * @author: Arnaud Fontaine
 */

/**
  * @var WikiRequest $request
  */
global $request;

if (!defined('MY_JABBER_ID'))
    define('MY_JABBER_ID', $request->_user->UserName() . "@jabber.com"); // or "@netflint.net"

class WikiPlugin_JabberPresence
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Display Jabber presence.");
    }

    // Establish default values for each of this plugin's arguments.
    function getDefaultArguments()
    {
        return array('scripturl' => "http://edgar.netflint.net/status.php",
            'jid' => MY_JABBER_ID,
            'type' => 'image',
            'iconset' => "gabber");
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
        extract($this->getArgs($argstr, $request));
        // Any text that is returned will not be further transformed,
        // so use html where necessary.
        if (empty($jid))
            $html = HTML();
        else
            $html = HTML::img(array('src' => urlencode($scripturl) .
                '&jid=' . urlencode($jid) .
                '&type=' . urlencode($type) .
                '&iconset=' . ($iconset),
                'alt' => ""));
        return $html;
    }
}
