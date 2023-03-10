<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
 * Copyright © 2009 Marc-Etienne Vargenau, Alcatel-Lucent
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

require_once 'lib/PageList.php';

class WikiPlugin_MostPopular
    extends WikiPlugin
{
    function getDescription()
    {
        return _("List the most popular pages.");
    }

    function getDefaultArguments()
    {
        return array_merge
        (
            PageList::supportedArgs(),
            array('pagename' => '[pagename]', // hackish
                //'exclude'  => '',
                'limit' => 20, // limit <0 returns least popular pages
                'noheader' => false,
                'sortby' => '-hits',
                'info' => false,
                //'paging'   => 'auto'
            ));
    }

    // info arg allows multiple columns
    // info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges
    // sortby: only pagename or hits. mtime not!

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

        extract($args);

        if (($noheader == '0') || ($noheader == 'false')) {
            $noheader = false;
        } elseif (($noheader == '1') || ($noheader == 'true')) {
            $noheader = true;
        } else {
            return $this->error(sprintf(_("Argument '%s' must be a boolean"), "noheader"));
        }

        if (isset($limit) && !is_limit($limit)) {
            return HTML::p(array('class' => "error"),
                           _("Illegal “limit” argument: must be an integer or two integers separated by comma"));
        }
        if (strstr($sortby, 'mtime')) {
            return HTML::p(array('class' => "error"),
                           _("sortby=mtime not supported with MostPopular"));
        }

        $columns = $info ? explode(",", $info) : array();
        array_unshift($columns, 'hits');

        if (!$request->getArg('count')) {
            //$args['count'] = $dbi->numPages(false,$exclude);
            $allpages = $dbi->mostPopular(0, $sortby);
            $args['count'] = $allpages->count();
        } else {
            $args['count'] = $request->getArg('count');
        }
        $pages = $dbi->mostPopular($limit, $sortby);
        $pagelist = new PageList($columns, $exclude, $args);
        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            // don't show pages with no hits if most popular pages wanted
            if ($hits == 0 && $limit > 0) {
                break;
            }
            $pagelist->addPage($page);
        }
        $pages->free();

        if (!$noheader) {
            if ($limit > 0) {
                $pagelist->setCaption(fmt("The %d most popular pages of this wiki:", $limit));
            } elseif ($limit < 0) {
                $pagelist->setCaption(fmt("The %d least popular pages of this wiki:", -$limit));
            } else {
                $pagelist->setCaption(_("Visited pages on this wiki, ordered by popularity:"));
            }
        }

        return $pagelist;
    }
}
