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

require_once 'lib/BlockParser.php';

/**
 * CategoryPage plugin.
 *
 * This puts boilerplate text on a category page to make it easily usable
 * by novices.
 *
 * Usage:
 * <?plugin-form CategoryPage ?>
 *
 * It finds the file templates/categorypage.tmpl, then loads it with a few
 * variables substituted.
 *
 * This has only been used in wikilens.org.
 */

class WikiPlugin_CategoryPage
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Create a Wiki Category Page.");
    }

    function getDefaultArguments()
    {
        return array( // Assume the categories are listed on the HomePage
            'exclude' => false,
            'pagename' => '[pagename]',
            'plural' => false,
            'singular' => false,
            'self_on_create' => true,
            'showbuds' => false);
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

        if (empty($args['singular'])) {
            $args['singular'] = $args['pagename'];
        }
        if (empty($args['plural'])) {
            $args['plural'] = $args['singular'] . 's';
        }

        return new Template('categorypage', $request,
            array('EXCLUDE' => $args['exclude'],
                'PAGENAME' => $args['pagename'],
                'PLURAL' => $args['plural'],
                'SHOWBUDS' => $args['showbuds'],
                'SELF_ON_CREATE' => $args['self_on_create'],
                'SINGULAR' => $args['singular']));
    }
}
