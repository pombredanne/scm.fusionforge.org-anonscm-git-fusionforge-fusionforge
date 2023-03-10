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
 * A simple plugin for <div class="boxright"> with wikimarkup
 */

class WikiPlugin_BoxRight
    extends WikiPlugin
{
    function getDescription()
    {
        return _("A simple plugin for <div class=boxright> with wikimarkup.");
    }

    function getDefaultArguments()
    {
        return array();
    }

    function managesValidators()
    {
        // The plugin output will only change if the plugin
        // invocation (page text) changes --- so the necessary
        // validators have already been handled by displayPage.
        return true;
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
        if (!$basepage) {
            return $this->error("$basepage unset?");
        }
        include_once 'lib/BlockParser.php';
        $page = $request->getPage($basepage);
        return HTML::div(array('class' => 'boxright'), TransformText($argstr));
    }

}
