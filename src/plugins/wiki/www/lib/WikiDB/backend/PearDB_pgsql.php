<?php
/**
 * Copyright © 2001,2003 Jeff Dairiki
 * Copyright © 2004-2007 Reini Urban
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

require_once 'lib/ErrorManager.php';
require_once 'lib/WikiDB/backend/PearDB.php';

if (!defined("USE_BYTEA")) // see schemas/psql-initialize.sql
    define("USE_BYTEA", true);

/*
Since 1.3.12 changed to use:
 * Foreign Keys
 * ON DELETE CASCADE
 * tsearch2
*/

class WikiDB_backend_PearDB_pgsql
    extends WikiDB_backend_PearDB
{
    /**
     * Pack tables.
     * NOTE: Only the table owner can do this. Either fix the schema or setup autovacuum.
     */
    function optimize()
    {
        return true; // if the wikiuser is not the table owner

        /*
        foreach ($this->_table_names as $table) {
            $this->_dbh->query("VACUUM ANALYZE $table");
        }
        return 1;
        */
    }

    function _quote($s)
    {
        if (USE_BYTEA)
            return pg_escape_bytea($s);
        if (function_exists('pg_escape_string'))
            return pg_escape_string($s);
        else
            return base64_encode($s);
    }

    function _unquote($s)
    {
        if (USE_BYTEA)
            return pg_unescape_bytea($s);
        if (function_exists('pg_escape_string'))
            return $s;
        else
            return base64_decode($s);
    }

    // Until the binary escape problems on pear pgsql are solved */
    function get_cached_html($pagename)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        $data = $dbh->GetOne(sprintf("SELECT cached_html FROM $page_tbl WHERE pagename='%s'",
            $dbh->escapeSimple($pagename)));
        if ($data) return $this->_unquote($data);
        else return '';
    }

    function set_cached_html($pagename, $data)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        if (USE_BYTEA)
            $dbh->query(sprintf("UPDATE $page_tbl"
                    . " SET cached_html='%s'"
                    . " WHERE pagename='%s'",
                $this->_quote($data),
                $dbh->escapeSimple($pagename)));
        else
            $dbh->query("UPDATE $page_tbl"
                    . " SET cached_html=?"
                    . " WHERE pagename=?",
                // PearDB does NOT use pg_escape_string()! Oh dear.
                array($this->_quote($data), $pagename));
    }

    /*
     * Lock all tables we might use.
     */
    protected function _lock_tables($write_lock = true)
    {
        $this->_dbh->query("BEGIN");
    }

    /*
     * Unlock all tables.
     */
    protected function _unlock_tables()
    {
        $this->_dbh->query("COMMIT");
    }

    /*
     * Serialize data
     */
    function _serialize($data)
    {
        if (empty($data))
            return '';
        assert(is_array($data));
        return $this->_quote(serialize($data));
    }

    /*
     * Unserialize data
     */
    function _unserialize($data)
    {
        if (empty($data))
            return array();
        // Base64 encoded data does not contain colons.
        //  (only alphanumerics and '+' and '/'.)
        if (substr($data, 0, 2) == 'a:')
            return unserialize($data);
        return unserialize($this->_unquote($data));
    }

    /*
     * Text search (title or full text)
     */
    public function text_search($search, $fulltext = false,
                                $sortby = '', $limit = '', $exclude = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $orderby = $this->sortby($sortby, 'db');
        if ($sortby and $orderby) $orderby = ' ORDER BY ' . $orderby;

        $searchclass = get_class($this) . "_search";
        // no need to define it everywhere and then fallback. memory!
        if (!class_exists($searchclass))
            $searchclass = "WikiDB_backend_PearDB_search";
        $searchobj = new $searchclass($search, $dbh);

        $table = "$nonempty_tbl, $page_tbl";
        $join_clause = "$nonempty_tbl.id=$page_tbl.id";
        $fields = $this->page_tbl_fields;

        if ($fulltext) {
            $table .= ", $recent_tbl";
            $join_clause .= " AND $page_tbl.id=$recent_tbl.id";

            $table .= ", $version_tbl";
            $join_clause .= " AND $page_tbl.id=$version_tbl.id AND latestversion=version";

            $fields .= ", $page_tbl.pagedata as pagedata, " . $this->version_tbl_fields;
            // TODO: title still ignored, need better rank and subselect
            $callback = new WikiMethodCb($searchobj, "_fulltext_match_clause");
            $search_string = $search->makeTsearch2SqlClauseObj($callback);
            $search_string = str_replace(array("%", " "), array("", "&"), $search_string);
            $search_clause = "idxFTI @@ to_tsquery('$search_string')";
            if (!$orderby)
                $orderby = " ORDER BY rank(idxFTI, to_tsquery('$search_string')) DESC";
        } else {
            $callback = new WikiMethodCb($searchobj, "_pagename_match_clause");
            $search_clause = $search->makeSqlClauseObj($callback);
        }
        $sql = "SELECT $fields FROM $table"
            . " WHERE $join_clause"
            . "  AND ($search_clause)"
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }

        $iter = new WikiDB_backend_PearDB_iter($this, $result);
        $iter->stoplisted = @$searchobj->stoplisted;
        return $iter;
    }

}

class WikiDB_backend_PearDB_pgsql_search
    extends WikiDB_backend_PearDB_search
{
    function _pagename_match_clause($node)
    {
        $word = $node->sql();
        if ($node->op == 'REGEX') { // posix regex extensions
            return ($this->_case_exact
                ? "pagename ~* '$word'"
                : "pagename ~ '$word'");
        } else {
            return ($this->_case_exact
                ? "pagename LIKE '$word'"
                : "pagename ILIKE '$word'");
        }
    }

    /*
     * use tsearch2. See schemas/psql-tsearch2.sql and /usr/share/postgresql/contrib/tsearch2.sql
     * TODO: don't parse the words into nodes. rather replace "[ +]" with & and "-" with "!" and " or " with "|"
     * tsearch2 query language: @@ "word | word", "word & word", ! word
     * ~* '.*something that does not exist.*'
     *
     * phrase search for "history lesson":
     *
     * SELECT id FROM tab WHERE ts_idx_col @@ to_tsquery('history&lesson')
     * AND text_col ~* '.*history\\s+lesson.*';
     *
     * The full-text index will still be used, and the regex will be used to
     * prune the results afterwards.
     */
    function _fulltext_match_clause($node)
    {
        $word = strtolower($node->word);
        $word = str_replace(" ", "&", $word); // phrase fix
        return $word;

        // clause specified above.
        // return $this->_pagename_match_clause($node) . " OR idxFTI @@ to_tsquery('$word')";
    }
}
