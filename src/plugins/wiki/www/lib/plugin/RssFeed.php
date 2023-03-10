<?php
/**
 * Copyright © 2003 Arnaud Fontaine
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
 * @author: Arnaud Fontaine
 */

include 'lib/RssParser.php';

class WikiPlugin_RssFeed
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Simple RSS Feed aggregator.");
    }

    // Establish default values for each of this plugin's arguments.
    function getDefaultArguments()
    {
        return array('feed' => "",
            'description' => "",
            'url' => "", // "http://phpwiki.demo.free.fr/index.php/RecentChanges?format=rss",
            'maxitem' => 0,
            'titleonly' => false,
            'debug' => false,
        );
    }

    function handle_plugin_args_cruft($argstr, $args)
    {
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

        if (($titleonly == '0') || ($titleonly == 'false')) {
            $titleonly = false;
        } elseif (($titleonly == '1') || ($titleonly == 'true')) {
            $titleonly = true;
        } else {
            return $this->error(sprintf(_("Argument '%s' must be a boolean"), "titleonly"));
        }

        $rss_parser = new RSSParser();

        if (!empty($url))
            $rss_parser->parse_url($url, $debug);

        if (!empty($rss_parser->channel['title'])) $feed = $rss_parser->channel['title'];
        if (!empty($rss_parser->channel['link'])) $url = $rss_parser->channel['link'];
        if (!empty($rss_parser->channel['description']))
            $description = $rss_parser->channel['description'];

        if (!empty($feed)) {
            if (!empty($url)) {
                $titre = HTML::span(HTML::a(array('href' => $rss_parser->channel['link']),
                    $rss_parser->channel['title']));
            } else {
                $titre = HTML::span($rss_parser->channel['title']);
            }
            $th = HTML::div(array('class' => 'feed'), $titre);
            if (!empty($description))
                $th->pushContent(HTML::p(array('class' => 'chandesc'),
                    HTML::raw($description)));
        } else {
            $th = HTML();
        }

        if (!empty($rss_parser->channel['date']))
            $th->pushContent(HTML::raw("<!--" . $rss_parser->channel['date'] . "-->"));
        $html = HTML::div(array('class' => 'rss'), $th);
        if ($rss_parser->items) {
            // only maxitem's
            if ($maxitem > 0)
                $rss_parser->items = array_slice($rss_parser->items, 0, $maxitem);
            foreach ($rss_parser->items as $item) {
                $cell = HTML::div(array('class' => 'rssitem'));
                if ($item['link'] and empty($item['title']))
                    $item['title'] = $item['link'];
                $cell_title = HTML::div(array('class' => 'itemname'),
                    HTML::a(array('href' => $item['link']),
                        HTML::raw($item['title'])));
                $cell->pushContent($cell_title);
                $cell_author = HTML::raw($item['author']);
                $cell_pubDate = HTML::raw($item['pubDate']);
                $cell_authordate = HTML::div(array('class' => 'authordate'),
                    $cell_author, HTML::raw(" - "), $cell_pubDate);
                $cell->pushContent($cell_authordate);
                if ((!$titleonly) && (!empty($item['description'])))
                    $cell->pushContent(HTML::div(array('class' => 'itemdesc'),
                        HTML::raw($item['description'])));
                $html->pushContent($cell);
            }
        } else {
            $html = HTML::div(array('class' => 'rss'), HTML::em(_("no RSS items")));
        }
        return $html;
    }

    /**
     * @param string $args
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function box($args = '', $request = null, $basepage = '')
    {
        if (!$request) $request =& $GLOBALS['request'];
        extract($args);
        if (empty($title))
            $title = _("RssFeed");
        if (empty($url))
            $url = 'http://phpwiki.demo.free.fr/RecentChanges?format=rss';
        $argstr = "url=$url";
        if (isset($maxitem) and is_numeric($maxitem))
            $argstr .= " maxitem=$maxitem";
        return $this->makeBox($title,
            $this->run($request->_dbi, $argstr, $request, $basepage));
    }

}
