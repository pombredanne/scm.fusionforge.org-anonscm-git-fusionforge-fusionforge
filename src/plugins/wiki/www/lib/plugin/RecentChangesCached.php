<?php
/**
 * Copyright © 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam
 * Copyright © 2002 Johannes Große                                   |
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

/* There is a bug in it:
 * When the cache is empty and you safe the wikipages,
 * an immediately created cached output of
 * RecentChanges will at the rss-image-link include
 * an action=edit
 */

require_once 'lib/WikiPluginCached.php';
require_once 'lib/plugin/RecentChanges.php';

class WikiPlugin_RecentChangesCached
    extends WikiPluginCached
{
    public $_args;
    public $_type;
    public $_static;
    public $_dbi;

    function getPluginType()
    {
        return PLUGIN_CACHED_HTML;
    }

    function getDescription()
    {
        return _('Cache output of RecentChanges called with default arguments.');
    }

    function getDefaultArguments()
    {
        $rc = new WikiPlugin_RecentChanges();
        return $rc->getDefaultArguments();
    }

    function getExpire($dbi, $argarray, $request)
    {
        return '+900'; // 15 minutes
    }

    /**
     * We don't go through pi parsing, instead we go directly to the
     * better plugin methods.
     *
     * @param WikiDB $dbi
     * @param string $argarray
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    protected function getHtml($dbi, $argarray, $request, $basepage)
    {
        $plugin = new WikiPlugin_RecentChanges();
        $changes = $plugin->getChanges($dbi, $argarray);
        return $plugin->format($changes, $argarray);
        /*
        $loader = new WikiPluginLoader();
        return $loader->expandPI('<?plugin RecentChanges '
            . WikiPluginCached::glueArgs($argarray)
                                 . ' ?>', $request, $this, $basepage);
        */
    }

    protected function getImage($dbi, $argarray, $request)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    protected function getMap($dbi, $argarray, $request)
    {
        trigger_error('pure virtual', E_USER_ERROR);
    }

    /**
     * ->box is used to display a fixed-width, narrow version with common header.
     * Just a limited list of pagenames, without date.
     * This does not use ->run, to avoid pi construction and deconstruction
     *
     * @param string $args
     * @param WikiRequest $request
     * @param string $basepage
     * @param bool $do_save
     * @return $this|HtmlElement|XmlContent
     */
    function box($args = '', $request = null, $basepage = '', $do_save = false)
    {
        if (!$request) $request =& $GLOBALS['request'];
        if (!isset($args['limit'])) $args['limit'] = 12;
        $args['format'] = 'box';
        $args['show_minor'] = false;
        $args['show_major'] = true;
        $args['show_deleted'] = 'sometimes';
        $args['show_all'] = false;
        $args['days'] = 90;

        $cache = $this->newCache();
        if (is_array($args))
            ksort($args);
        $argscopy = $args;
        unset($argscopy['limit']);
        $this->_args =& $args;
        $this->_type = $this->getPluginType();
        $this->_static = false;

        /* OLD: */
        //list($id, $url) = $this->genUrl($cache, $args);

        /* NEW: This cache entry needs an update on major changes.
         * So we should rather use an unique ID, because there will only be
         * one global cached box.
         */
        $id = $cache->generateId(serialize(array("RecentChangesCachedBox", $argscopy)));
        $content = $cache->get($id, 'imagecache');
        if ($do_save || !$content || !$content['html']) {
            $this->resetError();
            $plugin = new WikiPlugin_RecentChanges();
            $title = WikiLink($this->getName(), '', SplitPagename($this->getName()));
            $changes = $plugin->getChanges($request->_dbi, $args);
            $content['html'] =
                $this->makeBox($title,
                    $plugin->format($changes, $args));
            if ($errortext = $this->getError()) {
                $this->printError($errortext, 'html');
                return HTML();
            }
            $do_save = true;
        }
        if ($do_save) {
            $content['args'] = md5($this->_pi);
            $expire = $this->getExpire($request->_dbi, $content['args'], $request);
            $cache->save($id, $content, $expire, 'imagecache');
        }
        if ($content['html'])
            return $content['html'];
        return HTML();
    }

    // force box cache update on major changes.
    function box_update($args = '', $request = null, $basepage = '')
    {
        $this->box($args, $request, $basepage, true);
    }

}
