<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
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
 * A simple demonstration WikiPlugin.
 *
 * Usage:
 * <<HelloWorld>>
 * <<HelloWorld
 *          salutation="Greetings"
 *          name=Wikimeister
 * >>
 * <<HelloWorld salutation=Hi >>
 * <<HelloWorld name=WabiSabi >>
 */

// Constants are defined before the class.
if (!defined('THE_END'))
    define('THE_END', "!");

class WikiPlugin_HelloWorld
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Simple Sample Plugin.");
    }

    // Establish default values for each of this plugin's arguments.
    function getDefaultArguments()
    {
        return array('salutation' => "Hello",
                     'name' => "World");
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
        $html = HTML::samp(fmt('%s, %s', $salutation, WikiLink($name, 'auto')),
            THE_END);
        return $html;
    }
}
