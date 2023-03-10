<?php
/**
 * Copyright © 2010 Reini Urban
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
 * MS SQL extensions for the ADODB DB backend.
 */

require_once 'lib/WikiDB/backend/ADODB.php';

class WikiDB_backend_ADODB_mssqlnative
    extends WikiDB_backend_ADODB
{
    function __construct($dbparams)
    {
        // Lowercase Assoc arrays
        define('ADODB_ASSOC_CASE', 0);

        // Backend constructor
        parent::__construct($dbparams);

        // Empty strings in MSSQL?  NULLS?
        $this->_expressions['notempty'] = "NOT LIKE ''";
        //doesn't work if content is of the "text" type http://msdn2.microsoft.com/en-us/library/ms188074.aspx
        $this->_expressions['iscontent'] = "dbo.hasContent({$this->_table_names['version_tbl']}.content)";

        $this->_prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';

    }

    /**
     * Pack tables.
     */
    function optimize()
    {
        // Do nothing here -- Leave that for the DB
        // Cost Based Optimizer tuning vary from version to version
        return true;
    }

    // Search callabcks
    // Page name
    function _sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $word";
    }

    // Fulltext -- case sensitive :-\
    function _fullsearch_sql_match_clause($word)
    {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $wordq = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $wordq "
            . "OR CHARINDEX(content, '$word') > 0";
    }

    /*
     * Serialize data
     */
    function _serialize($data)
    {
        if (empty($data))
            return '';
        assert(is_array($data));
        return addslashes(serialize($data));
    }

    /*
     * Unserialize data
     */
    function _unserialize($data)
    {
        return empty($data) ? array() : unserialize(stripslashes($data));
    }

    /**
     * Set links for page.
     *
     * @param string $pagename Page name
     * @param array  $links    List of page(names) which page links to.
     *
     * on DEBUG: delete old, deleted links from page
     */
    function set_links($pagename, $links)
    {
        // FIXME: optimize: mysql can do this all in one big INSERT/REPLACE.

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock(array('link'));
        $pageid = $this->_get_pageid($pagename, true);

        $oldlinks = $dbh->getAssoc("SELECT $link_tbl.linkto as id, page.pagename FROM $link_tbl"
            . " JOIN page ON ($link_tbl.linkto = page.id)"
            . " WHERE linkfrom=$pageid");
        // Delete current links,
        $dbh->Execute("DELETE FROM $link_tbl WHERE linkfrom=$pageid");
        // and insert new links. Faster than checking for all single links
        if ($links) {
            foreach ($links as $link) {
                $linkto = $link['linkto'];
                if (isset($link['relation']))
                    $relation = $this->_get_pageid($link['relation'], true);
                else
                    $relation = 0;
                if ($linkto === "") { // ignore attributes
                    continue;
                }
                // avoid duplicates
                if (isset($linkseen[$linkto]) and !$relation) {
                    continue;
                }
                if (!$relation) {
                    $linkseen[$linkto] = true;
                }
                $linkid = $this->_get_pageid($linkto, true);
                assert($linkid);
                if ($relation) {
                    $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto, relation)"
                        . " VALUES ($pageid, $linkid, $relation)");
                } else {
                    $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto)"
                        . " VALUES ($pageid, $linkid)");
                }
                if ($oldlinks and array_key_exists($linkid, $oldlinks)) {
                    // This was also in the previous page
                    unset($oldlinks[$linkid]);
                }
            }
        }
        // purge page table: delete all non-referenced pages
        // for all previously linked pages, which have no other linkto links
        if (DEBUG and $oldlinks) {
            // trigger_error("purge page table: delete all non-referenced pages...", E_USER_NOTICE);
            foreach ($oldlinks as $id => $name) {
                // ...check if the page is empty and has no version
                if ($id != '') {
                    $result = $dbh->getRow("SELECT $page_tbl.id FROM $page_tbl"
                        . " LEFT JOIN $nonempty_tbl ON ($nonempty_tbl.id = $page_tbl.id)" //'"id" is not a recognized table hints option'
                        . " LEFT JOIN $version_tbl ON ($version_tbl.id = $page_tbl.id)" //'"id" is not a recognized table hints option'
                        . " WHERE $nonempty_tbl.id is NULL"
                        . " AND $version_tbl.id is NULL"
                        . " AND $page_tbl.id=$id");
                    $linkto = $dbh->getRow("SELECT linkfrom FROM $link_tbl WHERE linkto=$id");
                    if ($result and empty($linkto)) {
                        trigger_error("delete empty and non-referenced link $name ($id)", E_USER_NOTICE);
                        $dbh->Execute("DELETE FROM $recent_tbl WHERE id=$id"); // may fail
                        $dbh->Execute("DELETE FROM $link_tbl WHERE linkto=$id");
                        $dbh->Execute("DELETE FROM $page_tbl WHERE id=$id"); // this purges the link
                    }
                }
            }
        }
        $this->unlock(array('link'));
        return true;
    }

    /* get all oldlinks in hash => id, relation
       check for all new links
     */
    function set_links1($pagename, $links)
    {

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock(array('link'));
        $pageid = $this->_get_pageid($pagename, true);

        $oldlinks = $dbh->getAssoc("SELECT $link_tbl.linkto as linkto, $link_tbl.relation, page.pagename"
            . " FROM $link_tbl"
            . " JOIN page ON ($link_tbl.linkto = page.id)"
            . " WHERE linkfrom=$pageid");
        /*      old                  new
         *      X => [1,0 2,0 1,1]   X => [1,1 3,0]
         * => delete 1,0 2,0 + insert 3,0
         */
        if ($links) {
            foreach ($links as $link) {
                $linkto = $link['linkto'];
                if ($link['relation'])
                    $relation = $this->_get_pageid($link['relation'], true);
                else
                    $relation = 0;
                // avoid duplicates
                if (isset($linkseen[$linkto]) and !$relation) {
                    continue;
                }
                if (!$relation) {
                    $linkseen[$linkto] = true;
                }
                $linkid = $this->_get_pageid($linkto, true);
                assert($linkid);
                $skip = 0;
                // find linkfrom,linkto,relation triple in oldlinks
                foreach ($oldlinks as $l) {
                    if ($relation) { // relation NOT NULL
                        if ($l['linkto'] == $linkid and $l['relation'] == $relation) {
                            // found and skip
                            $skip = 1;
                        }
                    }
                }
                if (!$skip) {
                    if ($relation) {
                        $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto, relation)"
                            . " VALUES ($pageid, $linkid, $relation)");
                    } else {
                        $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto)"
                            . " VALUES ($pageid, $linkid)");
                    }
                }

                if (array_key_exists($linkid, $oldlinks)) {
                    // This was also in the previous page
                    unset($oldlinks[$linkid]);
                }
            }
        }
        // purge page table: delete all non-referenced pages
        // for all previously linked pages...
        if (DEBUG and $oldlinks) {
            // trigger_error("purge page table: delete all non-referenced pages...", E_USER_NOTICE);
            foreach ($oldlinks as $id => $name) {
                // ...check if the page is empty and has no version
                if ($id != '') {
                    if ($dbh->getRow("SELECT $page_tbl.id FROM $page_tbl"
                        . " LEFT JOIN $nonempty_tbl ON ($nonempty_tbl.id = $page_tbl.id)" //'"id" is not a recognized table hints option'
                        . " LEFT JOIN $version_tbl ON ($version_tbl.id = $page_tbl.id)" //'"id" is not a recognized table hints option'
                        . " WHERE $nonempty_tbl.id is NULL"
                        . " AND $version_tbl.id is NULL"
                        . " AND $page_tbl.id=$id")
                    ) {
                        trigger_error("delete empty and non-referenced link $name ($id)", E_USER_NOTICE);
                        $dbh->Execute("DELETE FROM $page_tbl WHERE id=$id"); // this purges the link
                        $dbh->Execute("DELETE FROM $recent_tbl WHERE id=$id"); // may fail
                    }
                }
            }
        }
        $this->unlock(array('link'));
        return true;
    }

}
