<?php
/**
 * Copyright © 2004-2005,2007 Reini Urban
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
 * Oracle extensions for the ADODB DB backend.
 * @author: Philippe.Vanhaesendonck@topgame.be
 */

require_once 'lib/WikiDB/backend/ADODB.php';

class WikiDB_backend_ADODB_oci8po
    extends WikiDB_backend_ADODB
{
    public $_prefix;

    function __construct($dbparams)
    {
        // Lowercase Assoc arrays
        define('ADODB_ASSOC_CASE', 0);

        // Backend constructor
        parent::__construct($dbparams);

        // Empty strings are NULLS in Oracle
        $this->_expressions['notempty'] = "IS NOT NULL";
        // CLOB handling
        $this->_expressions['iscontent'] = "DECODE(DBMS_LOB.GETLENGTH(content), NULL, 0, 0, 0, 1)";

        $this->_prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';
    }

    /**
     * Pack tables.
     */
    function optimize()
    {
        // Do nothing here -- Leave that for the DBA
        // Cost Based Optimizer tuning vary from version to version
        return true;
    }

    /*
     * Lock tables.
     *
     * We don't really need to lock exclusive, but I'll relax it when I fully
     * understand phpWiki locking ;-)
     *
     */
    protected function _lock_tables($tables, $write_lock = true)
    {
        if (!$tables) return;

        $dbh = &$this->_dbh;
        if ($write_lock) {
            // Next line is default behaviour, so just skip it
            // $dbh->Execute("SET TRANSACTION READ WRITE");
            foreach ($tables as $table) {
                if ($this->_prefix && !strstr($table, $this->_prefix)) {
                    $table = $this->_prefix . $table;
                }
                $dbh->Execute("LOCK TABLE $table IN EXCLUSIVE MODE");
            }
        } else {
            // Just ensure read consistency
            $dbh->Execute("SET TRANSACTION READ ONLY");
        }
    }

    /*
     * Release the locks.
     */
    protected function _unlock_tables($tables)
    {
        $dbh = &$this->_dbh;
        $dbh->Execute("COMMIT WORK");
    }

    // Search callbacks (replaced by class below)
    // Page name
    /*
    function _sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $word = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $word";
    }
    */

    // Fulltext -- case sensitive :-\
    // If we want case insensitive search, one need to create a Context
    // Index on the CLOB. While it is very efficient, it requires the
    // Intermedia Text option, so let's stick to the 'simple' thing
    /*
    function _fullsearch_sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);
        $wordq = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $wordq "
               . "OR DBMS_LOB.INSTR(content, '$word') > 0";
    }
    */

    /*
     * Serialize data
     */
    function _serialize($data)
    {
        if (empty($data))
            return '';
        assert(is_array($data));
        return $this->_dbh->BlobEncode(serialize($data));
    }

    /*
     * Unserialize data
     */
    function _unserialize($data)
    {
        if (empty($data))
            return array();
        $d = $this->_dbh->BlobDecode($data);
        if (!is_string($d)) {
            print_r($d);
        }
        return unserialize($this->_dbh->BlobDecode($data));
    }

    function write_accesslog(&$entry)
    {
        $dbh = &$this->_dbh;
        $log_tbl = $entry->_accesslog->logtable;
        $dbh->query("INSERT INTO $log_tbl"
                . " (time_stamp,remote_host,remote_user,request_method,request_line,request_uri,"
                . "request_args,request_time,status,bytes_sent,referer,agent,request_duration)"
                . " VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array(
                // Problem: date formats are backend specific. Either use unixtime as %d (long),
                // or the native timestamp format.
                date('d-M-Y H:i:s', $entry->time),
                $entry->host,
                $entry->user,
                $entry->request_method,
                $entry->request,
                $entry->request_uri,
                $entry->request_args,
                $entry->_ncsa_time($entry->time),
                $entry->status,
                $entry->size,
                $entry->referer,
                $entry->user_agent,
                $entry->duration));
    }

}

class WikiDB_backend_ADODB_oci8_search
    extends WikiDB_backend_ADODB_search
{
    // If we want case insensitive search, one need to create a Context
    // Index on the CLOB. While it is very efficient, it requires the
    // Intermedia Text option, so let's stick to the 'simple' thing
    // Note that this does only an exact fulltext search, not using MATCH or LIKE.
    function _fulltext_match_clause($node)
    {
        if ($this->isStoplisted($node))
            return "1=1";
        $page = $node->sql();
        $exactword = $node->sql_quote($node->word);
        return ($this->_case_exact
            ? "pagename LIKE '$page' OR DBMS_LOB.INSTR(content, '$exactword') > 0"
            : "LOWER(pagename) LIKE '$page' OR DBMS_LOB.INSTR(content, '$exactword') > 0");
    }
}
