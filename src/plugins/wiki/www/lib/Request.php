<?php
/**
 * Copyright © 2002,2004,2005,2006,2009 $ThePhpWikiProgrammingTeam
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

class Request
{
    public $args = array();
    public $_validators;
    private $_is_compressing_output;
    public $_is_buffering_output;
    public $_ob_get_length;
    private $_ob_initial_level;
    private $_do_chunked_output;
    public $_finishing;

    function __construct()
    {
        global $request;

        $this->_ob_initial_level = ob_get_level();

        $this->_fix_multipart_form_data();

        switch ($this->get('REQUEST_METHOD')) {
            case 'GET':
            case 'HEAD':
                $this->args = &$GLOBALS['HTTP_GET_VARS'];
                break;
            case 'POST':
                $this->args = &$GLOBALS['HTTP_POST_VARS'];
                break;
            default:
                $this->args = array();
                break;
        }

        $this->session = new Request_SessionVars();
        $this->cookies = new Request_CookieVars();

        if (ACCESS_LOG or ACCESS_LOG_SQL) {
            $this->_accesslog = new Request_AccessLog(ACCESS_LOG, ACCESS_LOG_SQL);
        }

        $request = $this;
    }

    function get($key)
    {
        if (!empty($GLOBALS['HTTP_SERVER_VARS']))
            $vars = &$GLOBALS['HTTP_SERVER_VARS'];
        elseif (!empty($GLOBALS['HTTP_ENV_VARS']))
            $vars = &$GLOBALS['HTTP_ENV_VARS']; // cgi or other servers than Apache
        else
            trigger_error("Serious php configuration error!"
                    . " No HTTP_SERVER_VARS and HTTP_ENV_VARS vars available."
                    . " These should get defined in lib/prepend.php",
                E_USER_WARNING);

        if (isset($vars[$key]))
            return $vars[$key];

        switch ($key) {
            case 'REMOTE_HOST':
                $addr = $vars['REMOTE_ADDR'];
                if (defined('ENABLE_REVERSE_DNS') && ENABLE_REVERSE_DNS)
                    return $vars[$key] = gethostbyaddr($addr);
                else
                    return $addr;
            default:
                return false;
        }
    }

    /**
     * @param $key
     * @return string|bool
     */
    function getArg($key)
    {
        if (isset($this->args[$key]))
            return $this->args[$key];
        return false;
    }

    function getArgs()
    {
        return $this->args;
    }

    function setArg($key, $val)
    {
        if ($val === false)
            unset($this->args[$key]);
        else
            $this->args[$key] = $val;
    }

    // Well oh well. Do we really want to pass POST params back as GET?
    function getURLtoSelf($args = array(), $exclude = array())
    {
        $get_args = $this->args;
        if ($args)
            $get_args = array_merge($get_args, $args);

        // leave out empty arg values
        foreach ($get_args as $g => $v) {
            if ($v === false or $v === '') unset($get_args[$g]);
        }

        // Err... good point...
        // sortby buttons
        if ($this->isPost()) {
            $exclude = array_merge($exclude, array('action', 'auth'));
            //$get_args = $args; // or only the provided
            /*
            trigger_error("Request::getURLtoSelf() should probably not be from POST",
                          E_USER_NOTICE);
            */
        }

        foreach ($exclude as $ex) {
            if (!empty($get_args[$ex])) unset($get_args[$ex]);
        }

        $pagename = $get_args['pagename'];
        unset ($get_args['pagename']);
        if (!empty($get_args['action']) and $get_args['action'] == 'browse')
            unset($get_args['action']);

        return WikiURL($pagename, $get_args);
    }

    function isPost()
    {
        return $this->get("REQUEST_METHOD") == "POST";
    }

    function isGetOrHead()
    {
        return in_array($this->get('REQUEST_METHOD'),
            array('GET', 'HEAD'));
    }

    function httpVersion()
    {
        if (!preg_match('@HTTP\s*/\s*(\d+.\d+)@', $this->get('SERVER_PROTOCOL'), $m))
            return false;
        return (float)$m[1];
    }

    /* Redirects after edit may fail if no theme signature image is defined.
     */
    function redirect($url, $noreturn = true)
    {

        header("Location: $url");
        /*
         * "302 Found" is not really meant to be sent in response
         * to a POST.  Worse still, according to (both HTTP 1.0
         * and 1.1) spec, the user, if it is sent, the user agent
         * is supposed to use the same method to fetch the
         * redirected URI as the original.
         *
         * That means if we redirect from a POST, the user-agent
         * supposed to generate another POST.  Not what we want.
         * (We do this after a page save after all.)
         *
         * Fortunately, most/all browsers don't do that.
         *
         * "303 See Other" is what we really want.  But it only
         * exists in HTTP/1.1
         *
         * FIXME: this is still not spec compliant for HTTP
         * version < 1.1.
         */
        $status = $this->httpVersion() >= 1.1 ? 303 : 302;
        $this->setStatus($status);

        if ($noreturn) {
            $this->discardOutput(); // This might print the gzip headers. Not good.
            $this->buffer_output(false);

            include_once 'lib/Template.php';
            $tmpl = new Template('redirect', $this, array('REDIRECT_URL' => $url));
            $tmpl->printXML();
            $this->finish();
        }
    }

    /** Set validators for this response.
     *
     * This sets a (possibly incomplete) set of validators
     * for this response.
     *
     * The validator set can be extended using appendValidators().
     *
     * When you're all done setting and appending validators, you
     * must call checkValidators() to check them and set the
     * appropriate headers in the HTTP response.
     *
     * Example Usage:
     *  ...
     *  $request->setValidators(array('pagename' => $pagename,
     *                                '%mtime' => $rev->get('mtime')));
     *  ...
     *  // Wups... response content depends on $otherpage, too...
     *  $request->appendValidators(array('otherpage' => $otherpagerev->getPageName(),
     *                                   '%mtime' => $otherpagerev->get('mtime')));
     *  ...
     *  // After all validators have been set:
     *  $request->checkValidators();
     */
    function setValidators($validator_set)
    {
        if (is_array($validator_set))
            $validator_set = new HTTP_ValidatorSet($validator_set);
        $this->_validators = $validator_set;
    }

    /** Append more validators for this response.
     *  i.e dependencies on other pages mtimes
     *  now it may be called in init also to simplify client code.
     */
    function appendValidators($validator_set)
    {
        if (!isset($this->_validators)) {
            $this->setValidators($validator_set);
            return;
        }
        $this->_validators->append($validator_set);
    }

    /** Check validators and set headers in HTTP response
     *
     * This sets the appropriate "Last-Modified" and "ETag"
     * headers in the HTTP response.
     *
     * Additionally, if the validators match any(all) conditional
     * headers in the HTTP request, this method will not return, but
     * instead will send "304 Not Modified" or "412 Precondition
     * Failed" (as appropriate) back to the client.
     */
    function checkValidators()
    {
        $validators = &$this->_validators;

        // Set validator headers
        if (!empty($this->_is_buffering_output) or !headers_sent()) {
            if (($etag = $validators->getETag()) !== false)
                header("ETag: " . $etag->asString());
            if (($mtime = $validators->getModificationTime()) !== false)
                header("Last-Modified: " . Rfc1123DateTime($mtime));

            // Set cache control headers
            $this->cacheControl();
        }

        if (CACHE_CONTROL == 'NO_CACHE')
            return; // don't check conditionals...

        // Check conditional headers in request
        $status = $validators->checkConditionalRequest($this);
        if ($status) {
            // Return short response due to failed conditionals
            $this->setStatus($status);
            echo "\n\n";
            $this->discardOutput();
            $this->finish();
            exit();
        }
    }

    /** Set the cache control headers in the HTTP response.
     */
    function cacheControl($strategy = CACHE_CONTROL, $max_age = CACHE_CONTROL_MAX_AGE)
    {
        if ($strategy == 'NO_CACHE') {
            $cache_control = "no-cache"; // better set private. See Pear HTTP_Header
            $max_age = -20;
        } elseif ($strategy == 'ALLOW_STALE' && $max_age > 0) {
            $cache_control = sprintf("max-age=%d", $max_age);
        } else {
            $cache_control = "must-revalidate";
            $max_age = -20;
        }
        header("Cache-Control: $cache_control");
        header("Expires: " . Rfc1123DateTime(time() + $max_age));
        header("Vary: Cookie"); // FIXME: add more here?
    }

    function setStatus($status)
    {
        if (preg_match('|^HTTP/.*?\s(\d+)|i', $status, $m)) {
            header($status);
            $status = $m[1];
        } else {
            $status = (integer)$status;
            $reason = array('200' => 'OK',
                '302' => 'Found',
                '303' => 'See Other',
                '304' => 'Not Modified',
                '400' => 'Bad Request',
                '401' => 'Unauthorized',
                '403' => 'Forbidden',
                '404' => 'Not Found',
                '412' => 'Precondition Failed');
            // FIXME: is it always okay to send HTTP/1.1 here, even for older clients?
            header(sprintf("HTTP/1.1 %d %s", $status, $reason[$status]));
        }

        if (isset($this->_log_entry))
            $this->_log_entry->setStatus($status);
    }

    function buffer_output($compress = true)
    {
        // FIXME: disables sessions (some byte before all headers_sent())
        /*if (defined('USECACHE') and !USECACHE) {
            $this->_is_buffering_output = false;
            return;
        }*/
        if (defined('COMPRESS_OUTPUT')) {
            if (!COMPRESS_OUTPUT)
                $compress = false;
        } elseif (isCGI()) // necessary?
            $compress = false;

        if ($this->getArg('start_debug')) $compress = false;
        if ($this->getArg('nocache'))
            $compress = false;

        // Should we compress even when apache_note is not available?
        // sf.net bug #933183 and http://bugs.php.net/17557
        // This effectively eliminates CGI, but all other servers also. hmm.
        if ($compress
            and (!function_exists('ob_gzhandler')
                or !function_exists('apache_note'))
        )
            $compress = false;

        // "output handler 'ob_gzhandler' cannot be used twice"
        // https://www.php.net/ob_gzhandler
        if ($compress and ini_get("zlib.output_compression"))
            $compress = false;

        // New: we check for the client Accept-Encoding: "gzip" presence also
        // This should eliminate a lot or reported problems.
        if ($compress
            and (!$this->get("HTTP_ACCEPT_ENCODING")
                or !strstr($this->get("HTTP_ACCEPT_ENCODING"), "gzip"))
        )
            $compress = false;

        // Most RSS clients are NOT(!) application/xml gzip compatible yet.
        // Even if they are sending the accept-encoding gzip header!
        // wget is, Mozilla, and MSIE no.
        // Of the RSS readers only MagpieRSS 0.5.2 is. http://www.rssgov.com/rssparsers.html
        if ($compress
            and $this->getArg('format')
                and strstr($this->getArg('format'), 'rss')
        )
            $compress = false;

        if ($compress) {
            ob_start('phpwiki_gzhandler');

            // TODO: dont send a length or get the gzip'ed data length.
            $this->_is_compressing_output = true;
            header("Content-Encoding: gzip");
            /*
             * Attempt to prevent Apache from doing the dreaded double-gzip.
             *
             * It would be better if we could detect when apache was going
             * to zip for us, and then let it ... but I have yet to figure
             * out how to do that.
             */
            if (function_exists('apache_note'))
                @apache_note('no-gzip', 1);
        } else {
            // Now we alway buffer output.
            // This is so we can set HTTP headers (e.g. for redirect)
            // at any point.
            // FIXME: change the name of this method.
            ob_start();
            $this->_is_compressing_output = false;
        }
        $this->_is_buffering_output = true;
        $this->_ob_get_length = 0;
    }

    function discardOutput()
    {
        if (!empty($this->_is_buffering_output)) {
            if (ob_get_length()) ob_clean();
            $this->_is_buffering_output = false;
        } else {
            trigger_error(_("Not buffering output"), E_USER_NOTICE);
        }
    }

    /**
     * Longer texts need too much memory on tiny or memory-limit=8MB systems.
     * We might want to flush our buffer and restart again.
     * (This would be fine if php would release its memory)
     * Note that this must not be called inside Template expansion or other
     * sections with ob_buffering.
     */
    function chunkOutput()
    {
        if (!empty($this->_is_buffering_output)
            or (@ob_get_level() > $this->_ob_initial_level)
        ) {
            $this->_do_chunked_output = true;
            if (empty($this->_ob_get_length)) $this->_ob_get_length = 0;
            $this->_ob_get_length += ob_get_length();
            while (ob_get_level() > $this->_ob_initial_level) {
                ob_end_flush();
            }
            if (ob_get_level() > $this->_ob_initial_level) {
                ob_end_clean();
            }
            ob_start();
        }
    }

    function finish()
    {
        $this->_finishing = true;
        if (!empty($this->_accesslog)) {
            $this->_accesslog->push($this);
            if (empty($this->_do_chunked_output) and empty($this->_ob_get_length))
                $this->_ob_get_length = ob_get_length();
            $this->_accesslog->setSize($this->_ob_get_length);
            global $RUNTIMER;
            if ($RUNTIMER) $this->_accesslog->setDuration($RUNTIMER->getTime());
            // sql logging must be done before the db is closed.
            if (isset($this->_accesslog->logtable))
                $this->_accesslog->write_sql();
        }

        if (!empty($this->_is_buffering_output)) {
            // if _is_compressing_output then ob_get_length() returns
            // the uncompressed length, not the gzip'ed as required.
            if (!headers_sent() and !$this->_is_compressing_output) {
                // php url-rewriting miscalculates the ob length. fixes bug #1376007
                if (ini_get('use_trans_sid') == 'off') {
                    if (empty($this->_do_chunked_output)) {
                        $this->_ob_get_length = ob_get_length();
                    }
                    header(sprintf("Content-Length: %d", $this->_ob_get_length));
                }
            }
            $this->_is_buffering_output = false;
            ob_end_flush();
        } elseif (@ob_get_level() > $this->_ob_initial_level) {
            ob_end_flush();
        }
        session_write_close();
        if (!empty($this->_dbi)) {
            $this->_dbi->close();
            unset($this->_dbi);
        }

        exit;
    }

    function getSessionVar($key)
    {
        return $this->session->get($key);
    }

    function setSessionVar($key, $val)
    {
        if ($key == 'wiki_user') {
            if (empty($val->page))
                $val->page = $this->getArg('pagename');
            if (empty($val->action))
                $val->action = $this->getArg('action');
            // avoid recursive objects and session resource handles
            // avoid overlarge session data (max 4000 byte!)
            if (isset($val->_group)) {
                unset($val->_group->_request);
                unset($val->_group->user);
            }
            unset($val->_HomePagehandle);
            unset($val->_auth_dbi);
        }
        $this->session->set($key, $val);
    }

    function getCookieVar($key)
    {
        return $this->cookies->get($key);
    }

    function setCookieVar($key, $val, $lifetime_in_days = false, $path = false)
    {
        $this->cookies->set($key, $val, $lifetime_in_days, $path);
    }

    function deleteCookieVar($key)
    {
        $this->cookies->delete($key);
    }

    function getUploadedFile($key)
    {
        return Request_UploadedFile::getUploadedFile($key);
    }

    private function _stripslashes(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_stripslashes($var[$key]);
        } elseif (is_string($var))
            $var = stripslashes($var);
    }

    private function _fix_multipart_form_data()
    {
        if (preg_match('|^multipart/form-data|', $this->get('CONTENT_TYPE')))
            $this->_strip_leading_nl($GLOBALS['HTTP_POST_VARS']);
    }

    private function _strip_leading_nl(&$var)
    {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_strip_leading_nl($var[$key]);
        } elseif (is_string($var))
            $var = preg_replace('|^\r?\n?|', '', $var);
    }
}

class Request_SessionVars
{
    function __construct()
    {
        // Prevent cacheing problems with IE 5
        session_cache_limiter('none');

        // Avoid to get a notice if session is already started,
        // for example if session.auto_start is activated
        if (!session_id())
            session_start();
    }

    function get($key)
    {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (isset($vars[$key]))
            return $vars[$key];
        if (isset($_SESSION) and isset($_SESSION[$key])) // php-5.2
            return $_SESSION[$key];
        return false;
    }

    function set($key, $val)
    {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (!function_usable('get_cfg_var') or get_cfg_var('register_globals')) {
            // This is funky but necessary, at least in some PHP's
            $GLOBALS[$key] = $val;
        }
        $vars[$key] = $val;
        if (isset($_SESSION)) // php-5.2
            $_SESSION[$key] = $val;
    }

    function delete($key)
    {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (!function_usable('ini_get') or ini_get('register_globals'))
            unset($GLOBALS[$key]);
        if (DEBUG)
           trigger_error("delete session $key", E_USER_WARNING);
        unset($vars[$key]);
        if (isset($_SESSION)) // php-5.2
            unset($_SESSION[$key]);
    }
}

class Request_CookieVars
{

    function get($key)
    {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        if (isset($vars[$key])) {
            @$decode = base64_decode($vars[$key]);
            if (strlen($decode) > 3 and substr($decode, 1, 1) == ':') {
                @$val = unserialize($decode);
                if (!empty($val))
                    return $val;
            }
            @$val = urldecode($vars[$key]);
            if (!empty($val))
                return $val;
        }
        return false;
    }

    function set($key, $val, $persist_days = false, $path = false)
    {
        // if already defined, ignore
        if (defined('MAIN_setUser') and $key = getCookieName()) return;
        if (defined('WIKI_XMLRPC') and WIKI_XMLRPC) return;

        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        if (is_numeric($persist_days)) {
            $expires = time() + (24 * 3600) * $persist_days;
        } else {
            $expires = 0;
        }
        if (is_array($val) or is_object($val))
            $packedval = base64_encode(serialize($val));
        else
            $packedval = urlencode($val);
        $vars[$key] = $packedval;
        @$_COOKIE[$key] = $packedval;
        if ($path)
            @setcookie($key, $packedval, $expires, $path);
        else
            @setcookie($key, $packedval, $expires);
    }

    function delete($key)
    {
        static $deleted = array();
        if (isset($deleted[$key])) return;
        if (defined('WIKI_XMLRPC') and WIKI_XMLRPC) return;

        if (!defined('COOKIE_DOMAIN'))
            @setcookie($key, '', 0);
        else
            @setcookie($key, '', 0, COOKIE_DOMAIN);
        unset($GLOBALS['HTTP_COOKIE_VARS'][$key]);
        unset($_COOKIE[$key]);
        $deleted[$key] = 1;
    }
}

/* Win32 Note:
   [\winnt\php.ini]
   You must set "upload_tmp_dir" = "/tmp/" or "C:/tmp/"
   Best on the same drive as apache, with forward slashes
   and with ending slash!
   Otherwise "\\" => "" and the uploaded file will not be found.
*/
class Request_UploadedFile
{
    function __construct($fileinfo)
    {
        $this->_info = $fileinfo;
    }

    static function getUploadedFile($postname)
    {
        global $HTTP_POST_FILES;

        // Against php5 with !ini_get('register-long-arrays'). See Bug #1180115
        if (empty($HTTP_POST_FILES) and !empty($_FILES))
            $HTTP_POST_FILES =& $_FILES;
        if (!isset($HTTP_POST_FILES[$postname]))
            return false;

        $fileinfo =& $HTTP_POST_FILES[$postname];
        if ($fileinfo['error']) {
            // See https://sourceforge.net/forum/message.php?msg_id=3093651
            $err = (int)$fileinfo['error'];
            // errmsgs by Shilad Sen
            switch ($err) {
                case 1:
                    trigger_error(_("Upload error: file too big"), E_USER_WARNING);
                    break;
                case 2:
                    trigger_error(_("Upload error: file too big"), E_USER_WARNING);
                    break;
                case 3:
                    trigger_error(_("Upload error: file only partially received"), E_USER_WARNING);
                    break;
                case 4:
                    trigger_error(_("Upload error: no file selected"), E_USER_WARNING);
                    break;
                default:
                    trigger_error(_("Upload error: unknown error #") . $err, E_USER_WARNING);
            }
            return false;
        }

        // With windows/php 4.2.1 is_uploaded_file() always returns false.
        // Be sure that upload_tmp_dir ends with a slash!
        if (!is_uploaded_file($fileinfo['tmp_name'])) {
            if (isWindows()) {
                if (!$tmp_file = get_cfg_var('upload_tmp_dir')) {
                    $tmp_file = dirname(tempnam('', ''));
                }
                $tmp_file .= '/' . basename($fileinfo['tmp_name']);
                /* ending slash in php.ini upload_tmp_dir is required. */
                if (realpath(preg_replace('#/+#', '/', $tmp_file)) != realpath($fileinfo['tmp_name'])) {
                    trigger_error(sprintf("Uploaded tmpfile illegal: %s != %s.", $tmp_file, $fileinfo['tmp_name']) .
                            "\n" .
                            "Probably illegal TEMP environment or upload_tmp_dir setting. " .
                            "Esp. on WINDOWS be sure to set upload_tmp_dir in php.ini to use forward slashes and " .
                            "end with a slash. upload_tmp_dir = \"C:/WINDOWS/TEMP/\" is good suggestion.",
                        E_USER_ERROR);
                    return false;
                }
            } else {
                trigger_error(sprintf("Uploaded tmpfile %s not found.", $fileinfo['tmp_name']) . "\n" .
                        " Probably illegal TEMP environment or upload_tmp_dir setting.",
                    E_USER_WARNING);
            }
        }
        return new Request_UploadedFile($fileinfo);
    }

    function getSize()
    {
        return $this->_info['size'];
    }

    function getName()
    {
        return $this->_info['name'];
    }

    function getType()
    {
        return $this->_info['type'];
    }

    function getTmpName()
    {
        return $this->_info['tmp_name'];
    }

    function open()
    {
        if (($fd = fopen($this->_info['tmp_name'], "rb"))) {
            if ($this->getSize() < filesize($this->_info['tmp_name'])) {
                // FIXME: Some PHP's (or is it some browsers?) put
                //    HTTP/MIME headers in the file body, some don't.
                //
                // At least, I think that's the case.  I know I used
                // to need this code, now I don't.
                //
                // This code is more-or-less untested currently.
                //
                // Dump HTTP headers.
                while (($header = fgets($fd, 4096))) {
                    if (trim($header) == '') {
                        break;
                    } elseif (!preg_match('/^content-(length|type):/i', $header)) {
                        rewind($fd);
                        break;
                    }
                }
            }
        }
        return $fd;
    }

    function getContents()
    {
        $fd = $this->open();
        $data = fread($fd, $this->getSize());
        fclose($fd);
        return $data;
    }
}

/**
 * Create NCSA "combined" log entry for current request.
 * Also needed for advanced spam prevention.
 * global object holding global state (sql or file, entries, to dump)
 */
class Request_AccessLog
{
    public $reader;
    public $sqliter;

    /**
     * @param string $logfile Log file name.
     * @param bool $do_sql
     */
    function __construct($logfile, $do_sql = false)
    {
        $this->logfile = $logfile;
        if ($logfile and !is_writeable($logfile)) {
            trigger_error
            (sprintf(_("%s is not writable."), _("The PhpWiki access log file"))
                    . "\n"
                    . sprintf(_("Please ensure that %s is writable, or redefine %s in config/config.ini."),
                        sprintf(_("the file “%s”"), ACCESS_LOG),
                        'ACCESS_LOG')
                , E_USER_NOTICE);
        }
        register_shutdown_function("Request_AccessLogEntry_shutdown_function");

        if ($do_sql) {
            global $DBParams;
            if (!in_array($DBParams['dbtype'], array('SQL', 'ADODB', 'PDO'))) {
                trigger_error(_("Unsupported database backend for ACCESS_LOG_SQL. Need DATABASE_TYPE=SQL or ADODB or PDO."));
            } else {
                $this->logtable = (!empty($DBParams['prefix']) ? $DBParams['prefix'] : '') . "accesslog";
            }
        }
        $this->entries = array();
        $this->entries[] = new Request_AccessLogEntry($this);
    }

    function _do($cmd, &$arg)
    {
        if ($this->entries)
            for ($i = 0; $i < count($this->entries); $i++)
                $this->entries[$i]->$cmd($arg);
    }

    function push(&$request)
    {
        $this->_do('push', $request);
    }

    function setSize($arg)
    {
        $this->_do('setSize', $arg);
    }

    function setStatus($arg)
    {
        $this->_do('setStatus', $arg);
    }

    function setDuration($arg)
    {
        $this->_do('setDuration', $arg);
    }

    /**
     * Read sequentially all previous entries from the beginning.
     * while ($logentry = Request_AccessLogEntry::read()) ;
     * For internal log analyzers: RecentReferrers, WikiAccessRestrictions
     */
    function read()
    {
        return $this->logtable ? $this->read_sql() : $this->read_file();
    }

    /**
     * Return iterator of referrer items reverse sorted (latest first).
     *
     * @param int $limit
     * @param bool $external_only
     * @return WikiDB_Array_generic_iter
     */
    function get_referer($limit = 15, $external_only = false)
    {
        if ($external_only) { // see stdlin.php:isExternalReferrer()
            $base = SERVER_URL;
        } else {
            $base = '';
        }
        $blen = strlen($base);
        if (!empty($this->_dbi)) {
            // check same hosts in referer and request and remove them
            $ext_where = " AND LEFT(referer,$blen) <> " . $this->_dbi->quote($base)
                . " AND LEFT(referer,$blen) <> LEFT(CONCAT(" . $this->_dbi->quote(SERVER_URL) . ",request_uri),$blen)";
            return $this->_read_sql_query("(referer <>'' AND NOT(ISNULL(referer)))"
                . ($external_only ? $ext_where : '')
                . " ORDER BY time_stamp DESC"
                . ($limit ? " LIMIT $limit" : ""));
        } else {
            $iter = new WikiDB_Array_generic_iter(0);
            $logs =& $iter->_array;
            while ($logentry = $this->read_file()) {
                if (!empty($logentry->referer)
                    and (!$external_only or (substr($logentry->referer, 0, $blen) != $base))
                ) {
                    $iter->_array[] = $logentry;
                    if ($limit and count($logs) > $limit)
                        array_shift($logs);
                }
            }
            $logs = array_reverse($logs);
            $logs = array_slice($logs, 0, min($limit, count($logs)));
            return $iter;
        }
    }

    /**
     * Read sequentially backwards all previous entries from log file.
     * FIXME!
     */
    function read_file()
    {
        if ($this->logfile) $this->logfile = ACCESS_LOG; // support Request_AccessLog::read

        if (empty($this->reader)) // start at the beginning
            $this->reader = fopen($this->logfile, "r");
        if ($s = fgets($this->reader)) {
            $entry = new Request_AccessLogEntry($this);
            if (preg_match('/^(\S+)\s(\S+)\s(\S+)\s\[(.+?)\] "([^"]+)" (\d+) (\d+) "([^"]*)" "([^"]*)"$/', $s, $m)) {
                list(, $entry->host, $entry->ident, $entry->user, $entry->time,
                    $entry->request, $entry->status, $entry->size,
                    $entry->referer, $entry->user_agent) = $m;
            }
            return $entry;
        } else { // until the end
            fclose($this->reader);
            return false;
        }
    }

    function _read_sql_query($where = '')
    {
        global $request;
        $dbh =& $request->_dbi;
        $log_tbl =& $this->logtable;
        return $dbh->genericSqlIter("SELECT *,request_uri as request,request_time as time,remote_user as user,"
            . "remote_host as host,agent as user_agent"
            . " FROM $log_tbl"
            . ($where ? " WHERE $where" : ""));
    }

    function read_sql($where = '')
    {
        if (empty($this->sqliter))
            $this->sqliter = $this->_read_sql_query($where);
        return $this->sqliter->next();
    }

    /* done in request->finish() before the db is closed */
    function write_sql()
    {
        global $request;
        $dbh =& $request->_dbi;
        if (isset($this->entries) and $dbh and $dbh->isOpen())
            foreach ($this->entries as $entry) {
                $entry->write_sql();
            }
    }

    /* done in the shutdown callback */
    function write_file()
    {
        if (isset($this->entries) and $this->logfile)
            foreach ($this->entries as $entry) {
                $entry->write_file();
            }
        unset($this->entries);
    }

    /* in an ideal world... */
    function write()
    {
        if ($this->logfile) $this->write_file();
        if ($this->logtable) $this->write_sql();
        unset($this->entries);
    }
}

class Request_AccessLogEntry
{
    public $host;
    public $ident;
    public $user;
    public $request;
    public $referer;
    public $user_agent;
    public $duration;
    public $request_args;
    public $request_method;
    public $request_uri;

    /**
     * The log entry will be automatically appended to the log file or
     * SQL table when the current request terminates.
     *
     * If you want to modify a Request_AccessLogEntry before it gets
     * written (e.g. via the setStatus and setSize methods) you should
     * use an '&' on the constructor, so that you're working with the
     * original (rather than a copy) object.
     *
     * <pre>
     *    $log_entry = & new Request_AccessLogEntry("/tmp/wiki_access_log");
     *    $log_entry->setStatus(401);
     *    $log_entry->push($request);
     * </pre>
     *
     */
    function __construct(&$accesslog)
    {
        $this->_accesslog = $accesslog;
        $this->logfile = $accesslog->logfile;
        $this->time = time();
        $this->status = 200; // see setStatus()
        $this->size = 0; // see setSize()
    }

    /**
     * @param $request object  Request object for current request.
     */
    function push(&$request)
    {
        $this->host = $request->get('REMOTE_HOST');
        $this->ident = $request->get('REMOTE_IDENT');
        if (!$this->ident)
            $this->ident = '-';
        $user = $request->getUser();
        if ($user->isAuthenticated())
            $this->user = $user->UserName();
        else
            $this->user = '-';
        $this->request = join(' ', array($request->get('REQUEST_METHOD'),
            $request->get('REQUEST_URI'),
            $request->get('SERVER_PROTOCOL')));
        $this->referer = (string)$request->get('HTTP_REFERER');
        $this->user_agent = (string)$request->get('HTTP_USER_AGENT');
    }

    /**
     * Set result status code.
     *
     * @param $status integer  HTTP status code.
     */
    function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Set response size.
     *
     * @param $size integer
     */
    function setSize($size = 0)
    {
        $this->size = (int)$size;
    }

    function setDuration($seconds)
    {
        // Pear DB does not correctly quote , in floats using ?. e.g. in european locales.
        // Workaround:
        $this->duration = strtr(sprintf("%f", $seconds), ",", ".");
    }

    /**
     * Get time zone offset.
     *
     * @param int $time Unix timestamp (defaults to current time).
     * @return string Zone offset, e.g. "-0800" for PST.
     */
    static function _zone_offset($time = 0)
    {
        if (!$time)
            $time = time();
        $offset = date("Z", $time);
        $negoffset = "";
        if ($offset < 0) {
            $negoffset = "-";
            $offset = -$offset;
        }
        $offhours = floor($offset / 3600);
        $offmins = $offset / 60 - $offhours * 60;
        return sprintf("%s%02d%02d", $negoffset, $offhours, $offmins);
    }

    /**
     * Format time in NCSA format.
     *
     * @param int $time Unix timestamp (defaults to current time).
     * @return string Formatted date & time.
     */
    function _ncsa_time($time = 0)
    {
        if (!$time)
            $time = time();
        return date("d/M/Y:H:i:s", $time) .
            " " . $this->_zone_offset();
    }

    function write()
    {
        if ($this->_accesslog->logfile) $this->write_file();
        if ($this->_accesslog->logtable) $this->write_sql();
    }

    /**
     * Write entry to log file.
     */
    function write_file()
    {
        $entry = sprintf('%s %s %s [%s] "%s" %d %d "%s" "%s"',
            $this->host, $this->ident, $this->user,
            $this->_ncsa_time($this->time),
            $this->request, $this->status, $this->size,
            $this->referer, $this->user_agent);
        if (!empty($this->_accesslog->reader)) {
            fclose($this->_accesslog->reader);
            unset($this->_accesslog->reader);
        }
        //Error log doesn't provide locking.
        //error_log("$entry\n", 3, $this->logfile);
        // Alternate method
        if (($fp = fopen($this->logfile, "a"))) {
            flock($fp, LOCK_EX);
            fputs($fp, "$entry\n");
            fclose($fp);
        }
    }

    /* This is better been done by apache mod_log_sql */
    /* If ACCESS_LOG_SQL & 2 we do write it by our own */
    function write_sql()
    {
        global $request;

        $dbh =& $request->_dbi;
        if ($dbh and $dbh->isOpen() and $this->_accesslog->logtable) {
            //$log_tbl =& $this->_accesslog->logtable;
            if ($request->get('REQUEST_METHOD') == "POST") {
                // strangely HTTP_POST_VARS doesn't contain all posted vars.
                $args = $_POST; // copy not ref. clone not needed on hashes
                // garble passwords
                if (!empty($args['auth']['passwd'])) $args['auth']['passwd'] = '<not displayed>';
                if (!empty($args['dbadmin']['passwd'])) $args['dbadmin']['passwd'] = '<not displayed>';
                if (!empty($args['pref']['passwd'])) $args['pref']['passwd'] = '<not displayed>';
                if (!empty($args['pref']['passwd2'])) $args['pref']['passwd2'] = '<not displayed>';
                $this->request_args = substr(serialize($args), 0, 254); // if VARCHAR(255) is used.
            } else {
                $this->request_args = $request->get('QUERY_STRING');
            }
            $this->request_method = $request->get('REQUEST_METHOD');
            $this->request_uri = $request->get('REQUEST_URI');
            // duration problem: sprintf "%f" might use comma e.g. "100,201" in european locales
            $dbh->_backend->write_accesslog($this);
        }
    }
}

/**
 * Shutdown callback. Ensures that the file is written.
 *
 * @access private
 * @see Request_AccessLogEntry
 */
function Request_AccessLogEntry_shutdown_function()
{
    global $request;

    if (isset($request->_accesslog->entries) and $request->_accesslog->logfile)
        foreach ($request->_accesslog->entries as $entry) {
            $entry->write_file();
        }
    unset($request->_accesslog->entries);
}

class HTTP_ETag
{
    function __construct($val, $is_weak = false)
    {
        $this->_val = wikihash($val);
        $this->_weak = $is_weak;
    }

    /** Comparison
     *
     * Strong comparison: If either (or both) tag is weak, they
     *  are not equal.
     */
    function equals($that, $strong_match = false)
    {
        if ($this->_val != $that->_val)
            return false;
        if ($strong_match and ($this->_weak or $that->_weak))
            return false;
        return true;
    }

    function asString()
    {
        $quoted = '"' . addslashes($this->_val) . '"';
        return $this->_weak ? "W/$quoted" : $quoted;
    }

    /** Parse tag from header.
     *
     */
    static function parse($strval)
    {
        if (!preg_match(':^(W/)?"(.+)"$:i', trim($strval), $m))
            return false; // parse failed
        list(, $weak, $str) = $m;
        return new HTTP_ETag(stripslashes($str), $weak);
    }

    function matches($taglist, $strong_match = false)
    {
        $taglist = trim($taglist);

        if ($taglist == '*') {
            if ($strong_match)
                return !$this->_weak;
            else
                return true;
        }

        while (preg_match('@^(W/)?"((?:\\\\.|[^"])*)"\s*,?\s*@i',
            $taglist, $m)) {
            list($match, $weak, $str) = $m;
            $taglist = substr($taglist, strlen($match));
            $tag = new HTTP_ETag(stripslashes($str), $weak);
            if ($this->equals($tag, $strong_match)) {
                return true;
            }
        }
        return false;
    }
}

// Possible results from the HTTP_ValidatorSet::_check*() methods.
// (Higher numerical values take precedence.)
define ('_HTTP_VAL_PASS', 0); // Test is irrelevant
define ('_HTTP_VAL_NOT_MODIFIED', 1); // Test passed, content not changed
define ('_HTTP_VAL_MODIFIED', 2); // Test failed, content changed
define ('_HTTP_VAL_FAILED', 3); // Precondition failed.

class HTTP_ValidatorSet
{
    function __construct($validators)
    {
        $this->_mtime = $this->_weak = false;
        $this->_tag = array();

        foreach ($validators as $key => $val) {
            if ($key == '%mtime') {
                $this->_mtime = $val;
            } elseif ($key == '%weak') {
                if ($val)
                    $this->_weak = true;
            } else {
                $this->_tag[$key] = $val;
            }
        }
    }

    function append($that)
    {
        if (is_array($that))
            $that = new HTTP_ValidatorSet($that);

        // Pick the most recent mtime
        if (isset($that->_mtime))
            if (!isset($this->_mtime) || $that->_mtime > $this->_mtime)
                $this->_mtime = $that->_mtime;

        // If either is weak, we're weak
        if (!empty($that->_weak))
            $this->_weak = true;
        if (is_array($this->_tag))
            $this->_tag = array_merge($this->_tag, $that->_tag);
        else
            $this->_tag = $that->_tag;
    }

    function getETag()
    {
        if (!$this->_tag)
            return false;
        return new HTTP_ETag($this->_tag, $this->_weak);
    }

    function getModificationTime()
    {
        return $this->_mtime;
    }

    /**
     * @param Request $request
     * @return int
     */
    function checkConditionalRequest(&$request)
    {
        $result = max($this->_checkIfUnmodifiedSince($request),
            $this->_checkIfModifiedSince($request),
            $this->_checkIfMatch($request),
            $this->_checkIfNoneMatch($request));

        if ($result == _HTTP_VAL_PASS || $result == _HTTP_VAL_MODIFIED)
            return false; // "please proceed with normal processing"
        elseif ($result == _HTTP_VAL_FAILED)
            return 412; // "412 Precondition Failed"
        elseif ($result == _HTTP_VAL_NOT_MODIFIED)
            return 304; // "304 Not Modified"

        trigger_error("Ack, shouldn't get here", E_USER_ERROR);
        return false;
    }

    /**
     * @param Request $request
     * @return int
     */
    function _checkIfUnmodifiedSince(&$request)
    {
        if ($this->_mtime !== false) {
            $since = ParseRfc1123DateTime($request->get("HTTP_IF_UNMODIFIED_SINCE"));
            if ($since !== false && $this->_mtime > $since)
                return _HTTP_VAL_FAILED;
        }
        return _HTTP_VAL_PASS;
    }

    /**
     * @param Request $request
     * @return int
     */
    function _checkIfModifiedSince(&$request)
    {
        if ($this->_mtime !== false and $request->isGetOrHead()) {
            $since = ParseRfc1123DateTime($request->get("HTTP_IF_MODIFIED_SINCE"));
            if ($since !== false) {
                if ($this->_mtime <= $since)
                    return _HTTP_VAL_NOT_MODIFIED;
                return _HTTP_VAL_MODIFIED;
            }
        }
        return _HTTP_VAL_PASS;
    }

    /**
     * @param Request $request
     * @return int
     */
    function _checkIfMatch(&$request)
    {
        if ($this->_tag && ($taglist = $request->get("HTTP_IF_MATCH"))) {
            $tag = $this->getETag();
            if (!$tag->matches($taglist, 'strong'))
                return _HTTP_VAL_FAILED;
        }
        return _HTTP_VAL_PASS;
    }

    /**
     * @param Request $request
     * @return int
     */
    function _checkIfNoneMatch(&$request)
    {
        if ($this->_tag && ($taglist = $request->get("HTTP_IF_NONE_MATCH"))) {
            $tag = $this->getETag();
            $strong_compare = !$request->isGetOrHead();
            if ($taglist) {
                if ($tag->matches($taglist, $strong_compare)) {
                    if ($request->isGetOrHead())
                        return _HTTP_VAL_NOT_MODIFIED;
                    else
                        return _HTTP_VAL_FAILED;
                }
                return _HTTP_VAL_MODIFIED;
            }
        }
        return _HTTP_VAL_PASS;
    }
}
