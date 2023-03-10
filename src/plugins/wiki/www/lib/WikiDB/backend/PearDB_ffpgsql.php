<?php
/**
 * Copyright © 2001-2009 $ThePhpWikiProgrammingTeam
 * Copyright © 2010 Alain Peyrat, Alcatel-Lucent
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

/*
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The Fusionforge backend ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

require_once 'lib/ErrorManager.php';
require_once 'lib/WikiDB/backend/PearDB_pgsql.php';

class WikiDB_backend_PearDB_ffpgsql
    extends WikiDB_backend_PearDB_pgsql
{
    function __construct($dbparams)
    {
        $dbparams['dsn'] = str_replace('ffpgsql:', 'pgsql:', $dbparams['dsn']);
        parent::__construct($dbparams);

        global $page_prefix;
        $p = strlen($page_prefix) + 1;
        $page_tbl = $this->_table_names['page_tbl'];
        $this->page_tbl_fields = "$page_tbl.id AS id, substring($page_tbl.pagename from $p) AS pagename, $page_tbl.hits AS hits";

        pg_set_client_encoding("iso-8859-1");
    }

    function get_all_pagenames()
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        global $page_prefix;
        $p = strlen($page_prefix) + 1;
        return $dbh->getCol("SELECT substring(pagename from $p)"
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id"
            . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'");
    }

    /*
     * filter (nonempty pages) currently ignored
     */
    function numPages($filter = false, $exclude = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        global $page_prefix;
        $p = strlen($page_prefix) + 1;
        return $dbh->getOne("SELECT count(*)"
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id"
            . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'");
    }

    /*
     * Read page information from database.
     */
    function get_pagedata($pagename)
    {
        global $page_prefix;
        return parent::get_pagedata($page_prefix . $pagename);
    }

    function update_pagedata($pagename, $newdata)
    {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        // Hits is the only thing we can update in a fast manner.
        if (count($newdata) == 1 && isset($newdata['hits'])) {
            // Note that this will fail silently if the page does not
            // have a record in the page table.  Since it's just the
            // hit count, who cares?
            global $page_prefix;
            $pagename = $page_prefix . $pagename;
            $dbh->query(sprintf("UPDATE $page_tbl SET hits=%d WHERE pagename='%s'",
                $newdata['hits'], $dbh->escapeSimple($pagename)));
            return;
        }

        $this->lock(array($page_tbl));
        $data = $this->get_pagedata($pagename);
        if (!$data) {
            $data = array();
            $this->_get_pageid($pagename, true); // Creates page record
        }

        if (isset($data['hits'])) {
            $hits = (int)$data['hits'];
            unset($data['hits']);
        } else {
            $hits = 0;
        }

        foreach ($newdata as $key => $val) {
            if ($key == 'hits')
                $hits = (int)$val;
            else if (empty($val))
                unset($data[$key]);
            else
                $data[$key] = $val;
        }

        /* Portability issue -- not all DBMS supports huge strings
         * so we need to 'bind' instead of building a simple SQL statment.
         * Note that we do not need to escapeSimple when we bind
        $dbh->query(sprintf("UPDATE $page_tbl"
                            . " SET hits=%d, pagedata='%s'"
                            . " WHERE pagename='%s'",
                            $hits,
                            $dbh->escapeSimple($this->_serialize($data)),
                            $dbh->escapeSimple($pagename)));
        */
        global $page_prefix;
        $pagename = $page_prefix . $pagename;
        $dbh->query("UPDATE $page_tbl"
                . " SET hits=?, pagedata=?"
                . " WHERE pagename=?",
            array($hits, $this->_serialize($data), $pagename));
        $this->unlock(array($page_tbl));
    }

    function get_latest_version($pagename)
    {
        global $page_prefix;
        return parent::get_latest_version($page_prefix . $pagename);
    }

    function get_previous_version($pagename, $version)
    {
        global $page_prefix;
        return parent::get_previous_version($page_prefix . $pagename, $version);
    }

    /**
     * Get version data.
     *
     * @param string $pagename Name of the page
     * @param int $version Which version to get
     * @param bool $want_content Do we need content?
     *
     * @return array The version data, or false if specified version does not exist.
     */
    function get_versiondata($pagename, $version, $want_content = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        extract($this->_expressions);

        // assert(is_string($pagename) and $pagename != "");
        // assert($version > 0);

        // FIXME: optimization: sometimes don't get page data?
        if ($want_content) {
            $fields = $this->page_tbl_fields
                . ",$page_tbl.pagedata AS pagedata,"
                . $this->version_tbl_fields;
        } else {
            $fields = $this->page_tbl_fields . ","
                . "mtime, minor_edit, versiondata,"
                . "$iscontent AS have_content";
        }

        global $page_prefix;
        $pagename = $page_prefix . $pagename;
        $result = $dbh->getRow(sprintf("SELECT $fields"
                    . " FROM $page_tbl, $version_tbl"
                    . " WHERE $page_tbl.id=$version_tbl.id"
                    . "  AND pagename='%s'"
                    . "  AND version=%d",
                $dbh->escapeSimple($pagename), $version),
            DB_FETCHMODE_ASSOC);

        return $this->_extract_version_data($result);
    }

    function get_cached_html($pagename)
    {
        global $page_prefix;
        return parent::get_cached_html($page_prefix . $pagename);
    }

    function set_cached_html($pagename, $data)
    {
        global $page_prefix;
        parent::set_cached_html($page_prefix . $pagename, $data);
    }

    function _get_pageid($pagename, $create_if_missing = false)
    {

        // check id_cache
        global $request;
        $cache =& $request->_dbi->_cache->_id_cache;
        if (isset($cache[$pagename])) {
            if ($cache[$pagename] or !$create_if_missing) {
                return $cache[$pagename];
            }
        }

        // attributes play this game.
        if ($pagename === '') return 0;

        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        global $page_prefix;
        $pagename = $page_prefix . $pagename;

        $query = sprintf("SELECT id FROM $page_tbl WHERE pagename='%s'",
            $dbh->escapeSimple($pagename));

        if (!$create_if_missing)
            return $dbh->getOne($query);

        $id = $dbh->getOne($query);
        if (empty($id)) {
            $this->lock(array($page_tbl)); // write lock
            $max_id = $dbh->getOne("SELECT MAX(id) FROM $page_tbl");
            $id = $max_id + 1;
            // requires createSequence and on mysql lock the interim table ->getSequenceName
            //$id = $dbh->nextId($page_tbl . "_id");
            $dbh->query(sprintf("INSERT INTO $page_tbl"
                    . " (id,pagename,hits)"
                    . " VALUES (%d,'%s',0)",
                $id, $dbh->escapeSimple($pagename)));
            $this->unlock(array($page_tbl));
        }
        return $id;
    }

    /*
     * Delete page completely from the database.
     */
    function purge_page($pagename)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if (($id = $this->_get_pageid($pagename))) {
            $dbh->query("DELETE FROM $nonempty_tbl WHERE id=$id");
            $dbh->query("DELETE FROM $recent_tbl   WHERE id=$id");
            $dbh->query("DELETE FROM $version_tbl  WHERE id=$id");
            $dbh->query("DELETE FROM $link_tbl     WHERE linkfrom=$id");
            $nlinks = $dbh->getOne("SELECT COUNT(*) FROM $link_tbl WHERE linkto=$id");
            if ($nlinks) {
                // We're still in the link table (dangling link) so we can't delete this
                // altogether.
                $dbh->query("UPDATE $page_tbl SET hits=0, pagedata='' WHERE id=$id");
                $result = 0;
            } else {
                $dbh->query("DELETE FROM $page_tbl WHERE id=$id");
                $result = 1;
            }
        } else {
            $result = -1; // already purged or not existing
        }
        $this->unlock();
        return $result;
    }

    /**
     * Find pages which link to or are linked from a page.
     *
     * @param string    $pagename       Page name
     * @param bool      $reversed       True to get backlinks
     * @param bool      $include_empty  True to get empty pages
     * @param string    $sortby
     * @param string    $limit
     * @param string    $exclude        Pages to exclude
     * @param bool      $want_relations
     *
     * FIXME: array or iterator?
     * @return object A WikiDB_backend_iterator.
     *
     * TESTME relations: get_links is responsible to add the relation to the pagehash
     * as 'linkrelation' key as pagename. See WikiDB_PageIterator::next
     *   if (isset($next['linkrelation']))
     */
    function get_links($pagename, $reversed = true, $include_empty = false,
                       $sortby = '', $limit = '', $exclude = '',
                       $want_relations = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed)
            list($have, $want) = array('linkee', 'linker');
        else
            list($have, $want) = array('linker', 'linkee');
        $orderby = $this->sortby($sortby, 'db', array('pagename'));
        if ($orderby) $orderby = " ORDER BY $want." . $orderby;
        if ($exclude) // array of pagenames
            $exclude = " AND $want.pagename NOT IN " . $this->_sql_set($exclude);
        else
            $exclude = '';

        global $page_prefix;
        $p = strlen($page_prefix) + 1;

        $qpagename = $dbh->escapeSimple($pagename);
        // MeV+APe 2007-11-14
        // added "dummyname" so that database accepts "ORDER BY"
        $sql = "SELECT DISTINCT $want.id AS id, substring($want.pagename from $p) AS pagename, $want.pagename AS dummyname,"
            . ($want_relations ? " related.pagename as linkrelation" : " $want.hits AS hits")
            . " FROM "
            . (!$include_empty ? "$nonempty_tbl, " : '')
            . " $page_tbl linkee, $page_tbl linker, $link_tbl "
            . ($want_relations ? " JOIN $page_tbl related ON ($link_tbl.relation=related.id)" : '')
            . " WHERE linkfrom=linker.id AND linkto=linkee.id"
            . " AND $have.pagename='$page_prefix$qpagename'"
            . " AND substring($want.pagename from 0 for $p) = '$page_prefix'"
            . (!$include_empty ? " AND $nonempty_tbl.id=$want.id" : "")
            //. " GROUP BY $want.id"
            . $exclude
            . $orderby;
        if ($limit) {
            // extract from,count from limit
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }

        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    public function get_all_pages($include_empty = false,
                                  $sortby = '', $limit = '', $exclude = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        global $page_prefix;
        $p = strlen($page_prefix) + 1;

        $orderby = $this->sortby($sortby, 'db');
        if ($orderby) $orderby = ' ORDER BY ' . $orderby;
        if ($exclude) // array of pagenames
            $exclude = " AND $page_tbl.pagename NOT IN " . $this->_sql_set($exclude);
        else
            $exclude = '';

        if (strstr($orderby, 'mtime ')) { // multiple columns possible
            if ($include_empty) {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $page_tbl, $recent_tbl, $version_tbl"
                    . " WHERE $page_tbl.id=$recent_tbl.id"
                    . " AND $page_tbl.id=$version_tbl.id AND latestversion=version"
                    . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
                    . $exclude
                    . $orderby;
            } else {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $nonempty_tbl, $page_tbl, $recent_tbl, $version_tbl"
                    . " WHERE $nonempty_tbl.id=$page_tbl.id"
                    . " AND $page_tbl.id=$recent_tbl.id"
                    . " AND $page_tbl.id=$version_tbl.id AND latestversion=version"
                    . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
                    . $exclude
                    . $orderby;
            }
        } else {
            if ($include_empty) {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $page_tbl"
                    . ($exclude ? " WHERE $exclude" : '')
                    . ($exclude ? " AND " : " WHERE ")
                    . " substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
                    . $orderby;
            } else {
                $sql = "SELECT "
                    . $this->page_tbl_fields
                    . " FROM $nonempty_tbl, $page_tbl"
                    . " WHERE $nonempty_tbl.id=$page_tbl.id"
                    . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
                    . $exclude
                    . $orderby;
            }
        }
        if ($limit && $orderby) {
            // extract from,count from limit
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find highest or lowest hit counts.
     */
    public function most_popular($limit = 20, $sortby = '-hits')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        global $page_prefix;
        $p = strlen($page_prefix) + 1;
        if ($limit < 0) {
            $order = "hits ASC";
            $limit = -$limit;
            $where = "";
        } else {
            $order = "hits DESC";
            $where = " AND hits > 0";
        }
        $orderby = '';
        if ($sortby != '-hits') {
            if ($order = $this->sortby($sortby, 'db'))
                $orderby = " ORDER BY " . $order;
        } else {
            $orderby = " ORDER BY $order";
        }
        //$limitclause = $limit ? " LIMIT $limit" : '';
        $sql = "SELECT "
            . $this->page_tbl_fields
            . " FROM $nonempty_tbl, $page_tbl"
            . " WHERE $nonempty_tbl.id=$page_tbl.id"
            . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
            . $where
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find recent changes.
     */
    public function most_recent($params)
    {
        $limit = 0;
        $since = 0;
        $include_minor_revisions = false;
        $exclude_major_revisions = false;
        $include_all_revisions = false;
        extract($params);

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pick = array();
        if ($since)
            $pick[] = "mtime >= $since";

        if ($include_all_revisions) {
            // Include all revisions of each page.
            $table = "$page_tbl, $version_tbl";
            $join_clause = "$page_tbl.id=$version_tbl.id";

            if ($exclude_major_revisions) {
                // Include only minor revisions
                $pick[] = "minor_edit <> 0";
            } elseif (!$include_minor_revisions) {
                // Include only major revisions
                $pick[] = "minor_edit = 0";
            }
        } else {
            $table = "$page_tbl, $recent_tbl";
            $join_clause = "$page_tbl.id=$recent_tbl.id";
            $table .= ", $version_tbl";
            $join_clause .= " AND $version_tbl.id=$page_tbl.id";

            if ($exclude_major_revisions) {
                // Include only most recent minor revision
                $pick[] = 'version=latestminor';
            } elseif (!$include_minor_revisions) {
                // Include only most recent major revision
                $pick[] = 'version=latestmajor';
            } else {
                // Include only the latest revision (whether major or minor).
                $pick[] = 'version=latestversion';
            }
        }
        $order = "DESC";
        if ($limit < 0) {
            $order = "ASC";
            $limit = -$limit;
        }
        // $limitclause = $limit ? " LIMIT $limit" : '';
        $where_clause = $join_clause;
        if ($pick)
            $where_clause .= " AND " . join(" AND ", $pick);

        global $page_prefix;
        $p = strlen($page_prefix) + 1;

        // FIXME: use SQL_BUFFER_RESULT for mysql?
        $sql = "SELECT "
            . $this->page_tbl_fields . ", " . $this->version_tbl_fields
            . " FROM $table"
            . " WHERE $where_clause"
            . " AND substring($page_tbl.pagename from 0 for $p) = '$page_prefix'"
            . " ORDER BY mtime $order";
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_iter($this, $result);
    }

    /*
     * Find referenced empty pages.
     */
    function wanted_pages($exclude_from = '', $exclude = '', $sortby = '', $limit = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        global $page_prefix;
        $p = strlen($page_prefix) + 1;
        if ($orderby = $this->sortby($sortby, 'db', array('pagename', 'wantedfrom')))
            $orderby = 'ORDER BY ' . $orderby;

        if ($exclude_from) // array of pagenames
            $exclude_from = " AND substring(p.pagename from $p) NOT IN " . $this->_sql_set($exclude_from);
        if ($exclude) // array of pagenames
            $exclude = " AND substring(p.pagename from $p) NOT IN " . $this->_sql_set($exclude);
        $sql = "SELECT substring(pp.pagename from $p) AS wantedfrom, substring(p.pagename from $p) AS pagename"
            . " FROM $page_tbl AS p, $link_tbl AS linked"
            . " LEFT JOIN $page_tbl AS pp ON linked.linkto = pp.id"
            . " LEFT JOIN $nonempty_tbl AS ne ON linked.linkto = ne.id"
            . " WHERE ne.id IS NULL"
            . " AND p.id = linked.linkfrom"
            . " AND substring(p.pagename from 0 for $p) = '$page_prefix'"
            . " AND substring(pp.pagename from 0 for $p) = '$page_prefix'"
            . $exclude_from
            . $exclude
            . $orderby;
        if ($limit) {
            list($from, $count) = $this->limit($limit);
            $result = $dbh->limitQuery($sql, $from, $count * 3);
        } else {
            $result = $dbh->query($sql);
        }
        return new WikiDB_backend_PearDB_generic_iter($this, $result);
    }

    /**
     * Rename page in the database.
     *
     * @param string $pagename Current page name
     * @param string $to       Future page name
     */

    function rename_page($pagename, $to)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if (($id = $this->_get_pageid($pagename))) {
            if ($new = $this->_get_pageid($to)) {
                // Cludge Alert!
                // This page does not exist (already verified before), but exists in the page table.
                // So we delete this page.
                $dbh->query("DELETE FROM $nonempty_tbl WHERE id=$new");
                $dbh->query("DELETE FROM $recent_tbl WHERE id=$new");
                $dbh->query("DELETE FROM $version_tbl WHERE id=$new");
                // We have to fix all referring tables to the old id
                $dbh->query("UPDATE $link_tbl SET linkfrom=$id WHERE linkfrom=$new");
                $dbh->query("UPDATE $link_tbl SET linkto=$id WHERE linkto=$new");
                $dbh->query("DELETE FROM $page_tbl WHERE id=$new");
            }
            global $page_prefix;
            $dbh->query(sprintf("UPDATE $page_tbl SET pagename='%s' WHERE id=$id",
                $dbh->escapeSimple($page_prefix . $to)));
        }
        $this->unlock();
        return $id;
    }

    function is_wiki_page($pagename)
    {
        global $page_prefix;
        return parent::is_wiki_page($page_prefix . $pagename);
    }

    function increaseHitCount($pagename)
    {
        global $page_prefix;
        parent::increaseHitCount($page_prefix . $pagename);
    }

    /*
     * Serialize data
     */
    function _serialize($data)
    {
        return WikiDB_backend_PearDB::_serialize($data);
    }

    /**
     * Pack tables.
     * NOTE: Disable vacuum, wikiuser is not the table owner
     */
    function optimize()
    {
        return true;
    }

    /*
     * Text search (title or full text)
     */
    public function text_search($search, $fulltext = false,
                                $sortby = '', $limit = '', $exclude = '')
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        global $page_prefix;
        $len = strlen($page_prefix) + 1;
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
            $search_string = str_replace('%', '', $search_string);
            $search_clause = "substring(plugin_wiki_page.pagename from 0 for $len) = '$page_prefix') AND (";

            $search_clause .= "idxFTI @@ plainto_tsquery('english', '$search_string')";
            if (!$orderby)
                $orderby = " ORDER BY ts_rank(idxFTI, plainto_tsquery('english', '$search_string')) DESC";
        } else {
            $callback = new WikiMethodCb($searchobj, "_pagename_match_clause");
            $search_clause = "substring(plugin_wiki_page.pagename from 0 for $len) = '$page_prefix') AND (";
            $search_clause .= $search->makeSqlClauseObj($callback);
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

    function exists_link($pagename, $link, $reversed = false)
    {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed)
            list($have, $want) = array('linkee', 'linker');
        else
            list($have, $want) = array('linker', 'linkee');
        $qpagename = $dbh->escapeSimple($pagename);
        $qlink = $dbh->escapeSimple($link);
        $row = $dbh->GetRow("SELECT $want.pagename as result"
            . " FROM $link_tbl, $page_tbl linker, $page_tbl linkee, $nonempty_tbl"
            . " WHERE linkfrom=linker.id AND linkto=linkee.id"
            . " AND $have.pagename='$qpagename'"
            . " AND $want.pagename='$qlink'"
            . " LIMIT 1");
        return $row['result'] ? 1 : 0;
    }
}

class WikiDB_backend_PearDB_ffpgsql_search
    extends WikiDB_backend_PearDB_pgsql_search
{
    function _pagename_match_clause($node)
    {
        $word = $node->sql();
        // @alu: use _quote maybe instead of direct pg_escape_string
        $word = pg_escape_string($word);
        global $page_prefix;
        $len = strlen($page_prefix) + 1;
        if ($node->op == 'REGEX') { // posix regex extensions
            return ($this->_case_exact
                ? "substring(pagename from $len) ~* '$word'"
                : "substring(pagename from $len) ~ '$word'");
        } else {
            return ($this->_case_exact
                ? "substring(pagename from $len) LIKE '$word'"
                : "substring(pagename from $len) ILIKE '$word'");
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
        // $word = str_replace(" ", "&", $word); // phrase fix

        // @alu: use _quote maybe instead of direct pg_escape_string
        return pg_escape_string($word);
    }
}
