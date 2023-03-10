<?php
/**
 * Copyright © 2005 $ThePhpWikiProgrammingTeam
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
 * ADODB db sessions, based on pear DB Sessions.
 *
 * @author: Reini Urban
 */

class DbSession_ADODB
    extends DbSession
{
    public $_backend_type = "ADODB";

    function __construct($dbh, $table)
    {
        $this->_dbh = $dbh;
        $this->_table = $table;

        session_set_save_handler(array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc'));
    }

    function & _connect()
    {
        global $request;
        static $parsed = false;
        $dbh = &$this->_dbh;
        if (!$dbh or !is_resource($dbh->_connectionID)) {
            if (!$parsed) $parsed = parseDSN($request->_dbi->getParam('dsn'));
            $this->_dbh = ADONewConnection($parsed['phptype']); // Probably only MySql works just now
            $this->_dbh->Connect($parsed['hostspec'], $parsed['username'],
                $parsed['password'], $parsed['database']);
            $dbh = &$this->_dbh;
        }
        return $dbh;
    }

    function query($sql)
    {
        return $this->_dbh->Execute($sql);
    }

    // adds surrounding quotes
    function quote($string)
    {
        return $this->_dbh->qstr($string);
    }

    function _disconnect()
    {
        if (0 and $this->_dbh)
            $this->_dbh->close();
    }

    /**
     * Opens a session.
     *
     * Actually this function is a fake for session_set_save_handle.
     * @param  string  $save_path    a path to stored files
     * @param  string  $session_name a name of the concrete file
     * @return boolean true just a variable to notify PHP that everything
     * is good.
     */
    public function open($save_path, $session_name)
    {
        //$this->log("_open($save_path, $session_name)");
        return true;
    }

    /**
     * Closes a session.
     *
     * This function is called just after <i>write</i> call.
     *
     * @return boolean true just a variable to notify PHP that everything
     * is good.
     */
    public function close()
    {
        //$this->log("_close()");
        return true;
    }

    /**
     * Reads the session data from DB.
     *
     * @param  string $id an id of current session
     * @return string
     */
    public function read($id)
    {
        //$this->log("_read($id)");
        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->qstr($id);
        $res = '';
        $row = $dbh->GetRow("SELECT sess_data FROM $table WHERE sess_id=$qid");
        if ($row)
            $res = $row[0];
        $this->_disconnect();
        if (!empty($res) and preg_match('|^[a-zA-Z0-9/+=]+$|', $res))
            $res = base64_decode($res);
        if (strlen($res) > 4000) {
            // trigger_error("Overlarge session data! ".strlen($res). " gt. 4000", E_USER_WARNING);
            $res = preg_replace('/s:6:"_cache";O:12:"WikiDB_cache".+}$/', "", $res);
            $res = preg_replace('/s:12:"_cached_html";s:.+",s:4:"hits"/', 's:4:"hits"', $res);
            if (strlen($res) > 4000) {
                $res = '';
            }
        }
        return $res;
    }

    /**
     * Saves the session data into DB.
     *
     * Just  a  comment:       The  "write"  handler  is  not
     * executed until after the output stream is closed. Thus,
     * output from debugging statements in the "write" handler
     * will  never be seen in the browser. If debugging output
     * is  necessary, it is suggested that the debug output be
     * written to a file instead.
     *
     * @param  string  $id
     * @param  string  $sess_data
     * @return boolean true if data saved successfully  and false
     * otherwise.
     */
    public function write($id, $sess_data)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (defined("WIKI_XMLRPC") or defined("WIKI_SOAP")) return false;

        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->qstr($id);
        $qip = $dbh->qstr($request->get('REMOTE_ADDR'));
        $time = $dbh->qstr(time());

        // postgres can't handle binary data in a TEXT field.
        if (is_a($dbh, 'ADODB_postgres64'))
            $sess_data = base64_encode($sess_data);
        $qdata = $dbh->qstr($sess_data);

        $dbh->execute("DELETE FROM $table WHERE sess_id=$qid");
        $rs = $dbh->execute("INSERT INTO $table"
                . " (sess_id, sess_data, sess_date, sess_ip)"
                . " VALUES ($qid, $qdata, $time, $qip)");
        $result = !$rs->EOF;
        if ($result) $rs->free();
        $this->_disconnect();
        return $result;
    }

    /**
     * Destroys a session.
     *
     * Removes a session from the table.
     *
     * @param  string  $id
     * @return boolean true
     */
    public function destroy($id)
    {
        $dbh = $this->_connect();
        $table = $this->_table;
        $qid = $dbh->qstr($id);

        $dbh->Execute("DELETE FROM $table WHERE sess_id=$qid");

        $this->_disconnect();
        return true;
    }

    /**
     * Cleans out all expired sessions.
     *
     * @param  int     $maxlifetime session's time to live.
     * @return boolean true
     */
    public function gc($maxlifetime)
    {
        $dbh = $this->_connect();
        $table = $this->_table;
        $threshold = time() - $maxlifetime;

        $dbh->Execute("DELETE FROM $table WHERE sess_date < $threshold");

        $this->_disconnect();
        return true;
    }

    // WhoIsOnline support
    // TODO: ip-accesstime dynamic blocking API
    function currentSessions()
    {
        $sessions = array();
        $dbh = $this->_connect();
        $table = $this->_table;
        $rs = $dbh->Execute("SELECT sess_data,sess_date,sess_ip FROM $table ORDER BY sess_date DESC");
        if ($rs->EOF) {
            $rs->free();
            return $sessions;
        }
        while (!$rs->EOF) {
            $row = $rs->fetchRow();
            $data = $row[0];
            $date = $row[1];
            $ip = $row[2];
            if (preg_match('|^[a-zA-Z0-9/+=]+$|', $data))
                $data = base64_decode($data);
            if ($date < 908437560 or $date > 1588437560)
                $date = 0;
            // session_data contains the <variable name> + "|" + <packed string>
            // we need just the wiki_user object (might be array as well)
            $user = strstr($data, "wiki_user|");
            $sessions[] = array('wiki_user' => substr($user, 10), // from "O:" onwards
                'date' => $date,
                'ip' => $ip);
            $rs->MoveNext();
        }
        $rs->free();
        $this->_disconnect();
        return $sessions;
    }
}
