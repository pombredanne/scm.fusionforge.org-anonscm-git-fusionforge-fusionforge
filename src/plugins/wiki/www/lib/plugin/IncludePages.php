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
 * IncludePages: Include a list of multiple pages, based on IncludePage.
 * usage:   <<IncludePages pages=<!plugin-list BackLinks !> >>
 * author:  Reini Urban
 */

include_once 'lib/plugin/IncludePage.php';

class WikiPlugin_IncludePages
    extends WikiPlugin_IncludePage
{
    function getDescription()
    {
        return _("Include multiple pages.");
    }

    function getDefaultArguments()
    {
        return array_merge(array('pages' => false, // the pages to include
                'exclude' => false), // the pages to exclude
            WikiPlugin_IncludePage::getDefaultArguments()
        );
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

        if (isset($args['limit']) && !limit($args['limit'])) {
            return HTML::p(array('class' => "error"),
                           _("Illegal “limit” argument: must be an integer or two integers separated by comma"));
        }

        $html = HTML();
        if (empty($args['pages'])) {
            return $html;
        }
        $include = new WikiPlugin_IncludePage();

        if (is_string($args['exclude']) and !empty($args['exclude'])) {
            $args['exclude'] = explodePageList($args['exclude']);
            $argstr = preg_replace("/exclude=\S*\s/", "", $argstr);
        } elseif (is_array($args['exclude'])) {
            $argstr = preg_replace("/exclude=<\?plugin-list.*?\>/", "", $argstr);
        }
        if (is_string($args['pages']) and !empty($args['pages'])) {
            $args['pages'] = explodePageList($args['pages']);
            $argstr = preg_replace("/pages=\S*\s/", "", $argstr);
        } elseif (is_array($args['pages'])) {
            $argstr = preg_replace("/pages=<\?plugin-list.*?\>/", "", $argstr);
        }

        // IncludePage plugin has no "pages" argument.
        // Remove it to avoid warning.
        $argstr = preg_replace('/pages=".*?"/', "", $argstr);
        $argstr = preg_replace('/pages=\S*\s/', "", $argstr);
        $argstr = preg_replace('/pages=\S*/', "", $argstr);

        foreach ($args['pages'] as $page) {
            if (empty($args['exclude']) or !in_array($page, $args['exclude'])) {
                $html = HTML($html, $include->run($dbi, "page='$page' " . $argstr, $request, $basepage));
            }
        }
        return $html;
    }
}
