<?php
/**
 * Copyright © 2002,2003,2005,2008-2010 $ThePhpWikiProgrammingTeam
 * Copyright © 2002 Martin Geisler <gimpster@gimpster.com>
 * Copyright © 2008-2009 Marc-Etienne Vargenau, Alcatel-Lucent
 *
 * This file is part of PhpWiki.
 * Parts of this file were based on PHPWeather's configurator.php file.
 *   http://sourceforge.net/projects/phpweather/
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
 * Starts automatically the first time by IniConfig("config/config.ini")
 * if it doesn't exist.
 *
 * DONE:
 * o Initial expand ?show=_part1 (the part id)
 * o read config-default.ini and use this as default_values
 * o commented / optional: non-default values should not be commented!
 *                         default values if optional can be omitted.
 * o validate input (fix javascript, add POST checks)
 * o start this automatically the first time
 * o fix include_path
 *
 * 1.3.11 TODO: (or 1.3.12?)
 * o parse_ini_file("config-dist.ini") for the commented vars
 * o check automatically for commented and optional vars
 * o fix _optional, to ignore existing config.ini and only use config-default.ini values
 * o mixin class for commented
 * o fix SQL quotes, AUTH_ORDER quotes and file forward slashes
 * o posted values validation, extend js validation for sane DB values
 * o read config-dist.ini into sections, comments, and optional/required settings
 *
 * A file config/config.ini will be automatically generated, if writable.
 *
 * NOTE: If you have a starterscript outside PHPWIKI_DIR but no
 * config/config.ini yet (very unlikely!), you must define DATA_PATH in the
 * starterscript, otherwise the webpath to configurator is unknown, and
 * subsequent requests will fail. (POST to save the INI)
 */

global $HTTP_POST_VARS;
if (empty($_SERVER)) $_SERVER =& $GLOBALS['HTTP_SERVER_VARS'];
if (empty($_GET)) $_GET =& $GLOBALS['HTTP_GET_VARS'];
if (empty($_ENV)) $_ENV =& $GLOBALS['HTTP_ENV_VARS'];
if (empty($_POST)) $_POST =& $GLOBALS['HTTP_POST_VARS'];

if (empty($configurator))
    $configurator = "configurator.php";
if (!strstr($_SERVER["SCRIPT_NAME"], $configurator) and defined('DATA_PATH'))
    $configurator = DATA_PATH . "/" . $configurator;
$scriptname = str_replace('configurator.php', 'index.php', $_SERVER["SCRIPT_NAME"]);
if (strstr($_SERVER["SCRIPT_NAME"], "/php")) { // cgi got this different
    if (defined('DATA_PATH'))
        $scriptname = DATA_PATH . "/index.php";
    else
        $scriptname = str_replace('configurator.php', 'index.php', $_SERVER["PHP_SELF"]);
}

$config_file = (substr(PHP_OS, 0, 3) == 'WIN') ? 'config\\config.ini' : 'config/config.ini';
$fs_config_file = dirname(__FILE__) . (substr(PHP_OS, 0, 3) == 'WIN' ? '\\' : '/') . $config_file;
if (isset($_POST['create'])) header('Location: ' . $configurator . '?show=_part1&create=1#create');

if (!function_exists('dba_handlers')) {
    function dba_handlers()
    {
        return array('none (function dba_handlers does not exist)');
    }
}

// helpers from lib/WikiUser/HttpAuth.php
if (!function_exists('_http_user')) {
    function _http_user()
    {
        if (!isset($_SERVER))
            $_SERVER = $GLOBALS['HTTP_SERVER_VARS'];
        if (!empty($_SERVER['PHP_AUTH_USER']))
            return array($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if (!empty($_SERVER['REMOTE_USER']))
            return array($_SERVER['REMOTE_USER'], $_SERVER['PHP_AUTH_PW']);
        if (!empty($GLOBALS['HTTP_ENV_VARS']['REMOTE_USER']))
            return array($GLOBALS['HTTP_ENV_VARS']['REMOTE_USER'],
                $GLOBALS['HTTP_ENV_VARS']['PHP_AUTH_PW']);
        if (!empty($GLOBALS['REMOTE_USER']))
            return array($GLOBALS['REMOTE_USER'], $GLOBALS['PHP_AUTH_PW']);

        // MsWindows IIS:
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            list($userid, $passwd) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            return array($userid, $passwd);
        }
        return array('', '');
    }

    function _http_logout()
    {
        if (!isset($_SERVER))
            $_SERVER =& $GLOBALS['HTTP_SERVER_VARS'];
        // maybe we should random the realm to really force a logout. but the next login will fail.
        header('WWW-Authenticate: Basic realm="' . WIKI_NAME . '"');
        if (strstr(php_sapi_name(), 'apache'))
            header('HTTP/1.0 401 Unauthorized');
        else
            header("Status: 401 Access Denied"); //IIS and CGI need that
        unset($GLOBALS['REMOTE_USER']);
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);

        trigger_error("Permission denied. Require ADMIN_USER.", E_USER_ERROR);
        exit();
    }
}

// If config.ini exists, we require ADMIN_USER access by faking HttpAuth.
// So nobody can see or reset the password(s).
if (file_exists($fs_config_file)) {
    // Require admin user
    if (!defined('ADMIN_USER') or !defined('ADMIN_PASSWD')) {
        if (!function_exists("IniConfig")) {
            include_once 'lib/prepend.php';
            include_once 'lib/IniConfig.php';
        }
        IniConfig($fs_config_file);
    }
    if (!defined('ADMIN_USER') or ADMIN_USER == '') {
        trigger_error("Configuration problem:\nADMIN_USER not defined in \"$fs_config_file\".\n"
            . "Cannot continue: You have to fix that manually.", E_USER_ERROR);
        exit();
    }

    list($admin_user, $admin_pw) = _http_user();
    //$required_user = ADMIN_USER;
    if (empty($admin_user) or $admin_user != ADMIN_USER) {
        _http_logout();
    }
    // check password
    if (ENCRYPTED_PASSWD) {
        if (crypt($admin_pw, ADMIN_PASSWD) != ADMIN_PASSWD)
            _http_logout();
    } elseif ($admin_pw != ADMIN_PASSWD) {
        _http_logout();
    }
} else {
    if (!function_exists("IniConfig")) {
        include_once 'lib/prepend.php';
        include_once 'lib/IniConfig.php';
    }
    $def_file = (substr(PHP_OS, 0, 3) == 'WIN') ? 'config\\config-default.ini' : 'config/config-default.ini';
    $fs_def_file = dirname(__FILE__) . (substr(PHP_OS, 0, 3) == 'WIN' ? '\\' : '/') . $def_file;
    IniConfig($fs_def_file);
}

?>
<!DOCTYPE html>
<html xml:lang="en" lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Configuration tool for PhpWiki <?php echo $config_file ?></title>
    <style type="text/css" media="screen">
        <!--
        body {
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: 80%;
        }

        pre {
            font-size: 120%;
        }

        table {
            border-spacing: 0;
        }

        td {
            border: thin solid black;
            padding: 4px;
        }

        tr {
            border: none;
        }

        div.hint {
            background-color: #eeeeee;
        }

        tr.hidden {
            border: none;
            display: none;
        }

        td.part {
            background-color: #eeeeee;
            color: inherit;
        }

        td.instructions {
            background-color: #ffffee;
            width: 700px;
            color: inherit;
        }

        td.unchangeable-variable-top {
            border-bottom: none;
            background-color: #ffffee;
            color: inherit;
        }

        td.unchangeable-variable-left {
            border-top: none;
            background-color: #ffffee;
            width: 700px;
            color: inherit;
        }

        .green {
            color: green;
        }

        .red {
            color: red;
        }
        -->
    </style>
    <script type="text/javascript">
        <!--
                function update(accepted, error, value, output) {
                    var msg = document.getElementById(output);
                    if (accepted) {
                        if (msg && msg.innerHTML) {
                            msg.innerHTML = "<span class=\"green\">Input accepted.</span>";
                        }
                    } else {
                        var index;
                        while ((index = error.indexOf("%s")) > -1) {
                            error = error.substring(0, index) + value + error.substring(index + 2);
                        }
                        if (msg) {
                            msg.innerHTML = "<span class=\"red\">" + error + "</span>";
                        }
                    }
                    var submit;
                    if (submit = document.getElementById('submit')) submit.disabled = accepted ? false : true;
                }

        function validate(error, value, output, field) {
            update(field.value == value, error, field.value, output);
        }

        function validate_ereg(error, ereg, output, field) {
            var regex = new RegExp(ereg);
            update(regex.test(field.value), error, field.value, output);
        }

        function validate_range(error, low, high, empty_ok, output, field) {
            update((empty_ok == 1 && field.value == "") ||
                    (field.value >= low && field.value <= high),
                    error, field.value, output);
        }

        function toggle_group(id) {
            var text = document.getElementById(id + "_text");
            var do_hide = false;
            if (text.innerHTML == "Hide options.") {
                do_hide = true;
                text.innerHTML = "Show options.";
            } else {
                text.innerHTML = "Hide options.";
            }

            var rows = document.getElementsByTagName('tr');
            var i;
            var tr;
            for (i = 0; i < rows.length; i++) {
                tr = rows[i];
                if (tr.className == 'header' && tr.id == id) {
                    i++;
                    break;
                }
            }
            for (; i < rows.length; i++) {
                tr = rows[i];
                if (tr.className == 'header')
                    break;
                tr.className = do_hide ? 'hidden' : 'nonhidden';
            }
        }

        function do_init() {
            // Hide all groups.  We do this via JavaScript to avoid
            // hiding the groups if JavaScript is not supported...
            var rows = document.getElementsByTagName('tr');
            var show = '<?php echo $_GET["show"] ?>';
            for (var i = 0; i < rows.length; i++) {
                var tr = rows[i];
                if (tr.className == 'header')
                    if (!show || tr.id != show)
                        toggle_group(tr.id);
            }

            // Select text in textarea upon focus
            var area = document.getElementById('config-output');
            if (area) {
                var listener = { handleEvent:function (e) {
                    area.select();
                } };
                area.addEventListener('focus', listener, false);
            }
        }

        // -->
    </script>
</head>
<body onload="do_init();">

<h1>Configuration for PhpWiki <?php echo $config_file ?></h1>

<div class="hint">
    Using this configurator.php is experimental!<br/>
    On any configuration problems, please edit the resulting config.ini manually.
</div>

<?php
/**
 * The Configurator is a php script to aid in the configuration of PhpWiki.
 * Parts of this file were based on PHPWeather's configurator.php file.
 *   https://sourceforge.net/projects/phpweather/
 *
 * TO CHANGE THE CONFIGURATION OF YOUR PHPWIKI, DO *NOT* MODIFY THIS FILE!
 * more instructions go here
 *
 * Todo:
 *   * fix include_path
 *   * eval config.ini to get the actual settings.
 */

//////////////////////////////
// begin configuration options

/**
 * Notes for the description parameter of $property:
 *
 * - Descriptive text will be changed into comments (preceeded by ; )
 *   for the final output to config.ini.
 *
 * - Only a limited set of HTML is allowed: pre, dl dt dd; it will be
 *   stripped from the final output.
 *
 * - Line breaks and spacing will be preserved for the final output.
 *
 * - Double line breaks are automatically converted to paragraphs
 *   for the HTML version of the descriptive text.
 *
 * - Double-quotes and dollar signs in the descriptive text must be
 *   escaped: \" and \$. Instead of escaping double-quotes you can use
 *   single (') quotes for the enclosing quotes.
 *
 * - Special characters like < and > must use HTML entities,
 *   they will be converted back to characters for the final output.
 */

$SEPARATOR = ";=========================================================================";

$preamble = "
; This is the main configuration file for PhpWiki in INI-style format.
; Note that certain characters are used as comment char and therefore
; these entries must be in double-quotes. Such as \":\", \";\", \",\" and \"|\"
; Take special care for DBAUTH_ sql statements. (Part 3a)
;
; This file is divided into several parts: Each one has different configuration
; settings you can change; in all cases the default should work on your system,
; however, we recommend you tailor things to your particular setting.
; Here undefined definitions get defined by config-default.ini settings.
";

// Detect not here listed configs:
// for x in `perl -ne 'print $1,"\n" if /^;(\w+) =/' config/config-dist.ini`; do \
//   grep \'$x\' configurator.php >/dev/null || echo $x ; done

$properties["Part Zero"] =
    new part('_part0', $SEPARATOR . "\n", "
Part Zero: Latest Development and Tricky Options");

if (defined('INCLUDE_PATH'))
    $include_path = INCLUDE_PATH;
else {
    if (substr(PHP_OS, 0, 3) == 'WIN') {
        $include_path = dirname(__FILE__) . ';' . ini_get('include_path');
        if (strchr(ini_get('include_path'), '/'))
            $include_path = strtr($include_path, '\\', '/');
    } else {
        $include_path = dirname(__FILE__) . ':' . ini_get('include_path');
    }
}

$properties["PHP include_path"] =
    new _define('INCLUDE_PATH', $include_path);

// TODO: Convert this to a checkbox row as in tests/unit/test.php
$properties["DEBUG"] =
    new numeric_define_optional('DEBUG', DEBUG);

$properties["ENABLE_EDIT_TOOLBAR"] =
    new boolean_define_commented_optional('ENABLE_EDIT_TOOLBAR');

$properties["JS_SEARCHREPLACE"] =
    new boolean_define_commented_optional('JS_SEARCHREPLACE');

// TESTME: use config-default:  = false
$properties["ENABLE_DOUBLECLICKEDIT"] =
    new boolean_define_commented_optional('ENABLE_DOUBLECLICKEDIT');

$properties["ENABLE_WYSIWYG"] =
    new boolean_define_commented_optional('ENABLE_WYSIWYG');

$properties["WYSIWYG_BACKEND"] =
    new _define_selection(
        'WYSIWYG_BACKEND',
        array('Wikiwyg' => 'Wikiwyg',
            'tinymce' => 'tinymce',
            'CKeditor' => 'CKeditor',
            'spaw' => 'spaw',
            'htmlarea3' => 'htmlarea3',
            'htmlarea2' => 'htmlarea2',
        ));

$properties["WYSIWYG_DEFAULT_PAGETYPE_HTML"] =
    new boolean_define_commented_optional('WYSIWYG_DEFAULT_PAGETYPE_HTML');

$properties["UPLOAD_USERDIR"] =
    new boolean_define_commented_optional('UPLOAD_USERDIR');

$properties["DISABLE_UNITS"] =
    new boolean_define_commented_optional('DISABLE_UNITS');

$properties["UNITS_EXE"] =
    new _define_commented_optional('UNITS_EXE');

$properties["ENABLE_XHTML_XML"] =
    new boolean_define_commented_optional('ENABLE_XHTML_XML');

$properties["ENABLE_OPEN_GRAPH"] =
    new boolean_define_commented_optional('ENABLE_OPEN_GRAPH');

$properties["ENABLE_SPAMASSASSIN"] =
    new boolean_define_commented_optional('ENABLE_SPAMASSASSIN');

$properties["ENABLE_SPAMBLOCKLIST"] =
    new boolean_define_optional('ENABLE_SPAMBLOCKLIST');

$properties["NUM_SPAM_LINKS"] =
    new numeric_define_optional('NUM_SPAM_LINKS');

$properties["DISABLE_UPLOAD_ONLY_ALLOWED_EXTENSIONS"] =
    new boolean_define_commented_optional('DISABLE_UPLOAD_ONLY_ALLOWED_EXTENSIONS');

$properties["GOOGLE_LINKS_NOFOLLOW"] =
    new boolean_define_commented_optional('GOOGLE_LINKS_NOFOLLOW');

$properties["ENABLE_AJAX"] =
    new boolean_define_commented_optional('ENABLE_AJAX');

$properties["ENABLE_DISCUSSION_LINK"] =
    new boolean_define_commented_optional('ENABLE_DISCUSSION_LINK');

$properties["ENABLE_CAPTCHA"] =
    new boolean_define_commented_optional('ENABLE_CAPTCHA');

$properties["USE_CAPTCHA_RANDOM_WORD"] =
    new boolean_define_commented_optional('USE_CAPTCHA_RANDOM_WORD');

$properties["BLOG_DEFAULT_EMPTY_PREFIX"] =
    new boolean_define_commented_optional('BLOG_DEFAULT_EMPTY_PREFIX');

$properties["ENABLE_SEARCHHIGHLIGHT"] =
    new boolean_define_commented_optional('ENABLE_SEARCHHIGHLIGHT');

$properties["ENABLE_MAILNOTIFY"] =
    new boolean_define_commented_optional('ENABLE_MAILNOTIFY');

$properties["ENABLE_RECENTCHANGESBOX"] =
    new boolean_define_commented_optional('ENABLE_RECENTCHANGESBOX');

$properties["ENABLE_PAGE_PUBLIC"] =
    new boolean_define_commented_optional('ENABLE_PAGE_PUBLIC');

$properties["READONLY"] =
    new boolean_define_commented_optional('READONLY');

$properties["Part One"] =
    new part('_part1', $SEPARATOR . "\n", "
Part One: Authentication and security settings. See Part Three for more.");

$properties["Wiki Name"] =
    new _define_optional('WIKI_NAME', WIKI_NAME);

$properties["Admin Username"] =
    new _define_notempty('ADMIN_USER', ADMIN_USER, "
You must set this! Username and password of the administrator.",
        "onchange=\"validate_ereg('Sorry, ADMIN_USER cannot be empty.', '^.+$', 'ADMIN_USER', this);\"");

$properties["Admin Password"] =
    new _define_password('ADMIN_PASSWD', ADMIN_PASSWD, "
You must set this!
For heaven's sake pick a good password.

If your version of PHP supports encrypted passwords, your password will be
automatically encrypted within the generated config file.
Use the \"Create Random Password\" button to create a good (random) password.

ADMIN_PASSWD is ignored on HttpAuth",
        "onchange=\"validate_ereg('Sorry, ADMIN_PASSWD must be at least 4 chars long.', '^....+$', 'ADMIN_PASSWD', this);\"");

$properties["Encrypted Passwords"] =
    new boolean_define
    ('ENCRYPTED_PASSWD',
        array('true' => "true.  use crypt for all passwords",
            'false' => "false. use plaintest passwords (not recommended)"));

$properties["Reverse DNS"] =
    new boolean_define_optional
    ('ENABLE_REVERSE_DNS',
        array('true' => "true. perform additional reverse dns lookups",
            'false' => "false. just record the address as given by the httpd server"));

$properties["ZIP Dump Authentication"] =
    new boolean_define_optional('ZIPDUMP_AUTH',
        array('false' => "false. Everyone may download zip dumps",
            'true' => "true. Only admin may download zip dumps"));

$properties["Enable RawHtml Plugin"] =
    new boolean_define_commented_optional('ENABLE_RAW_HTML');

$properties["Allow RawHtml Plugin only on locked pages"] =
    new boolean_define_commented_optional('ENABLE_RAW_HTML_LOCKEDONLY');

$properties["Allow RawHtml Plugin if safe HTML code"] =
    new boolean_define_commented_optional('ENABLE_RAW_HTML_SAFE', '', "
If this is set, all unsafe HTML code is stripped automatically (experimental!)
See <a href=\"http://chxo.com/scripts/safe_html-test.php\">chxo.com/scripts/safe_html-test.php</a>
");

$properties["Maximum Upload Size"] =
    new numeric_define_optional('MAX_UPLOAD_SIZE', MAX_UPLOAD_SIZE);

$properties["Minor Edit Timeout"] =
    new numeric_define_optional('MINOR_EDIT_TIMEOUT', MINOR_EDIT_TIMEOUT);

$properties["Disabled Actions"] =
    new array_define('DISABLED_ACTIONS', array("dumpserial", "loadfile"), "Actions listed in this array will not be allowed.  The complete list
of actions can be found in lib/main.php with the function
getActionDescription.

purge, remove, revert, xmlrpc, soap, upload, browse, create, diff, dumphtml,
dumpserial, edit, loadfile, lock, unlock, viewsource, zip, ziphtml, ...
");

$properties["Moderate all Pagechanges"] =
    new boolean_define_commented_optional('ENABLE_MODERATEDPAGE_ALL');

$properties["Access Log File"] =
    new _define_commented_optional('ACCESS_LOG', ACCESS_LOG);

$properties["Access Log SQL"] =
    new _define_selection(
        'ACCESS_LOG_SQL',
        array('0' => 'disabled',
            '1' => 'read only',
            '2' => 'read + write'));

$properties["Compress Output"] =
    new boolean_define_commented_optional
    ('COMPRESS_OUTPUT',
        array('' => 'undefined - GZIP compress when appropriate.',
            'false' => 'Never compress output.',
            'true' => 'Always try to compress output.'));

$properties["HTTP Cache Control"] =
    new _define_selection_optional
    ('CACHE_CONTROL',
        array('LOOSE' => 'LOOSE',
            'STRICT' => 'STRICT',
            'NO_CACHE' => 'NO_CACHE',
            'ALLOW_STALE' => 'ALLOW_STALE'),
        "
HTTP CACHE_CONTROL

This controls how PhpWiki sets the HTTP cache control
headers (Expires: and Cache-Control:)

Choose one of:
<dl>
<dt>NO_CACHE</dt>
<dd>This is roughly the old (pre 1.3.4) behaviour.  PhpWiki will
    instruct proxies and browsers never to cache PhpWiki output.</dd>
<dt>STRICT</dt>
<dd>Cached pages will be invalidated whenever the database global
    timestamp changes.  This should behave just like NONE (modulo
    bugs in PhpWiki and your proxies and browsers), except that
    things will be slightly more efficient.</dd>
<dt>LOOSE</dt>
<dd>Cached pages will be invalidated whenever they are edited,
    or, if the pages include plugins, when the plugin output could
    conceivably have changed.
    <p>Behavior should be much like STRICT, except that sometimes
       wikilinks will show up as undefined (with the question mark)
       when in fact they refer to (recently) created pages.
       (Hitting your browsers reload or perhaps shift-reload button
       should fix the problem.)</p></dd>
<dt>ALLOW_STALE</dt>
<dd>Proxies and browsers will be allowed to used stale pages.
    (The timeout for stale pages is controlled by CACHE_CONTROL_MAX_AGE.)
    <p>This setting will result in quirky behavior.  When you edit a
       page your changes may not show up until you shift-reload the
       page, etc...</p>
    <p>This setting is generally not advisable, however it may be useful
       in certain cases (e.g. if your wiki gets lots of page views,
       and few edits by knowledgeable people who won't freak over the quirks.)</p>
</dd>
</dl>
The default is currently LOOSE.");

$properties["HTTP Cache Control Max Age"] =
    new numeric_define_optional('CACHE_CONTROL_MAX_AGE', CACHE_CONTROL_MAX_AGE);

$properties["Markup Caching"] =
    new boolean_define_commented_optional
    ('WIKIDB_NOCACHE_MARKUP',
        array('false' => 'Enable markup cache',
            'true' => 'Disable markup cache'));

$properties["COOKIE_EXPIRATION_DAYS"] =
    new numeric_define_optional('COOKIE_EXPIRATION_DAYS', COOKIE_EXPIRATION_DAYS);

$properties["COOKIE_DOMAIN"] =
    new _define_commented_optional('COOKIE_DOMAIN', COOKIE_DOMAIN);

$properties["Path for PHP Session Support"] =
    new _define_optional('SESSION_SAVE_PATH', defined('SESSION_SAVE_PATH') ? SESSION_SAVE_PATH : ini_get('session.save_path'));

$properties["Force PHP Database Sessions"] =
    new boolean_define_commented_optional
    ('USE_DB_SESSION',
        array('false' => 'Disable database sessions, use files',
            'true' => 'Enable database sessions'));

///////// database selection

$properties["Part Two"] =
    new part('_part2', $SEPARATOR . "\n", "

Part Two:
Database Configuration
");

$properties["Database Type"] =
    new _define_selection("DATABASE_TYPE",
        array('dba' => "dba",
            'SQL' => "SQL PEAR",
            'ADODB' => "SQL ADODB",
            'PDO' => "PDO",
            'file' => "flatfile")/*, "
Select the database backend type:
Choose dba (default) to use one of the standard UNIX dba libraries. This is the fastest.
Choose ADODB or SQL to use an SQL database with ADODB or PEAR.
Choose PDO to use an SQL database. (experimental, no paging yet)
flatfile is simple and slow.
Recommended is dba or SQL: PEAR or ADODB."*/);

$properties["SQL DSN Setup"] =
    new unchangeable_variable('_sqldsnstuff', "", "
For SQL based backends, specify the database as a DSN
The most general form of a DSN looks like:
<pre>
  phptype(dbsyntax)://username:password@protocol+hostspec/database?option=value
</pre>
For a MySQL database, the following should work:
<pre>
   mysqli://user:password@host/databasename
</pre>
To connect over a Unix socket, use something like
<pre>
   mysqli://user:password@unix(/path/to/socket)/databasename
</pre>
<pre>
  DATABASE_DSN = mysqli://guest@:/var/lib/mysql/mysql.sock/phpwiki
  DATABASE_DSN = mysqli://guest@localhost/phpwiki
  DATABASE_DSN = pgsql://localhost/user_phpwiki
</pre>");

// Choose ADODB or SQL to use an SQL database with ADODB or PEAR.
// Choose dba to use one of the standard UNIX dbm libraries.

$properties["SQL Type"] =
    new _variable_selection('_dsn_sqltype',
        array('mysql' => "MySQL",
            'pgsql' => "PostgreSQL",
            'mssql' => "Microsoft SQL Server",
            'mssqlnative' => "Microsoft SQL Server (native)",
            'oci8' => "Oracle 8",
            'mysqli' => "mysqli (only ADODB)",
            'mysqlt' => "mysqlt (only ADODB)",
            'ODBC' => "ODBC (only ADODB or PDO)",
            'firebird' => "Firebird (only PDO)",
            'oracle' => "Oracle (only PDO)",
        ), "
SQL DB types. The DSN hosttype.");

$properties["SQL User"] =
    new _variable('_dsn_sqluser', "wikiuser", "
SQL User Id:");

$properties["SQL Password"] =
    new _variable('_dsn_sqlpass', "", "
SQL Password:");

$properties["SQL Database Host"] =
    new _variable('_dsn_sqlhostorsock', "localhost", "
SQL Database Hostname:

To connect over a local named socket, use something like
<pre>
  unix(/var/lib/mysql/mysql.sock)
</pre>
here.
mysql on Windows via named pipes might need 127.0.0.1");

$properties["SQL Database Name"] =
    new _variable('_dsn_sqldbname', "phpwiki", "
SQL Database Name:");

$dsn_sqltype = $properties["SQL Type"]->value();
$dsn_sqluser = $properties["SQL User"]->value();
$dsn_sqlpass = $properties["SQL Password"]->value();
$dsn_sqlhostorsock = $properties["SQL Database Host"]->value();
$dsn_sqldbname = $properties["SQL Database Name"]->value();
$dsn_sqlstring = $dsn_sqltype . "://".$dsn_sqluser.":".$dsn_sqlpass."@".$dsn_sqlhostorsock."/".$dsn_sqldbname;

$properties["SQL dsn"] =
    new unchangeable_define("DATABASE_DSN",
        $dsn_sqlstring, "
Calculated from the settings above:");

$properties["Filename / Table name Prefix"] =
    new _define_commented('DATABASE_PREFIX', DATABASE_PREFIX, "
Used by all DB types:

Prefix for filenames or table names, e.g. \"phpwiki_\"

Currently <b>you MUST EDIT THE SQL file too!</b> (in the schemas/
directory because we aren't doing on the fly sql generation
during the installation.

Note: This prefix is NOT prepended to the default DBAUTH_
      tables user, pref and member!
");

$properties["DATABASE_PERSISTENT"] =
    new boolean_define_commented_optional
    ('DATABASE_PERSISTENT',
        array('false' => "Disabled",
            'true' => "Enabled"));

$properties["DB Session table"] =
    new _define_optional("DATABASE_SESSION_TABLE", DATABASE_SESSION_TABLE, "
Tablename to store session information. Only supported by SQL backends.

A word of warning - any prefix defined above will be prepended to whatever is given here.
");

//TODO: $TEMP
$temp = !empty($_ENV['TEMP']) ? $_ENV['TEMP'] : "/tmp";
$properties["dba directory"] =
    new _define("DATABASE_DIRECTORY", $temp);

// TODO: list the available methods
$properties["dba handler"] =
    new _define_selection('DATABASE_DBA_HANDLER',
        array('gdbm' => "gdbm - GNU database manager (not recommended anymore)",
            'dbm' => "DBM - Redhat default. On sf.net there's dbm and not gdbm anymore",
            'db2' => "DB2 - BerkeleyDB (Sleepycat) DB2",
            'db3' => "DB3 - BerkeleyDB (Sleepycat) DB3. Default on Windows but not on every Linux",
            'db4' => "DB4 - BerkeleyDB (Sleepycat) DB4."), "
Use 'gdbm', 'dbm', 'db2', 'db3' or 'db4' depending on your DBA handler methods supported: <br />  "
            . join(", ", dba_handlers())
            . "\n\nBetter not use other hacks such as inifile, flatfile or cdb");

$properties["dba timeout"] =
    new numeric_define("DATABASE_TIMEOUT", DATABASE_TIMEOUT, "
Recommended values are 10-20 seconds. The more load the server has, the higher the timeout.");

$properties["DATABASE_OPTIMISE_FREQUENCY"] =
    new numeric_define_optional('DATABASE_OPTIMISE_FREQUENCY', DATABASE_OPTIMISE_FREQUENCY);

$properties["DBADMIN_USER"] =
    new _define_optional('DBADMIN_USER', DBADMIN_USER);

$properties["DBADMIN_PASSWD"] =
    new _define_password_optional('DBADMIN_PASSWD', DBADMIN_PASSWD);

$properties["USECACHE"] =
    new boolean_define_commented_optional('USECACHE');

/////////////////////////////////////////////////////////////////////

$properties["Part Three"] =
    new part('_part3', $SEPARATOR . "\n", "

Part Three: (optional)
Basic User Authentication Setup
");

$properties["Publicly viewable"] =
    new boolean_define_optional('ALLOW_ANON_USER',
        array('true' => "true. Permit anonymous view. (Default)",
            'false' => "false. Force login even on view (strictly private)"), "
If ALLOW_ANON_USER is false, you have to login before viewing any page or doing any other action on a page.");

$properties["Allow anonymous edit"] =
    new boolean_define_optional('ALLOW_ANON_EDIT',
        array('true' => "true. Permit anonymous users to edit. (Default)",
            'false' => "false. Force login on edit (moderately locked)"), "
If ALLOW_ANON_EDIT is false, you have to login before editing or changing any page. See below.");

$properties["Allow Bogo Login"] =
    new boolean_define_optional('ALLOW_BOGO_LOGIN',
        array('true' => "true. Users may Sign In with any WikiWord, without password. (Default)",
            'false' => "false. Require stricter authentication."), "
If ALLOW_BOGO_LOGIN is false, you may not login with any wikiword username and empty password.
If true, users are allowed to create themselves with any WikiWord username. See below.");

$properties["Allow User Passwords"] =
    new boolean_define_optional('ALLOW_USER_PASSWORDS',
        array('true' => "True user authentication with password checking. (Default)",
            'false' => "false. Ignore authentication settings below."), "
If ALLOW_USER_PASSWORDS is true, the authentication settings below define where and how to
check against given username/passwords. For completely security disable BOGO_LOGIN and ANON_EDIT above.");

$properties["User Authentication Methods"] =
    new array_define('USER_AUTH_ORDER', array("PersonalPage", "Db"), "
Many different methods can be used to check user's passwords.
Try any of these in the given order:
<dl>
<dt>BogoLogin</dt>
        <dd>WikiWord username, with no *actual* password checking,
        although the user will still have to enter one.</dd>
<dt>PersonalPage</dt>
        <dd>Store passwords in the users homepage metadata (simple)</dd>
<dt>Db</dt>
        <dd>Use DBAUTH_AUTH_* (see below) with PearDB or ADODB only.</dd>
<dt>LDAP</dt>
        <dd>Authenticate against LDAP_AUTH_HOST with LDAP_BASE_DN.</dd>
<dt>IMAP</dt>
        <dd>Authenticate against IMAP_AUTH_HOST (email account)</dd>
<dt>POP3</dt>
        <dd>Authenticate against POP3_AUTH_HOST (email account)</dd>
<dt>Session</dt>
        <dd>Get username and level from a PHP session variable. (e.g. for FusionForge)</dd>
<dt>File</dt>
        <dd>Store username:crypted-passwords in .htaccess like files.
         Use Apache's htpasswd to manage this file.</dd>
<dt>HttpAuth</dt>
        <dd>Use the protection by the webserver (.htaccess/.htpasswd) (experimental)
        Enforcing HTTP Auth not yet. Note that the ADMIN_USER should exist also.
        Using HttpAuth disables all other methods and no userauth sessions are used.</dd>
</dl>

Several of these methods can be used together, in the manner specified by
USER_AUTH_POLICY, below.  To specify multiple authentication methods,
separate the name of each one with colons.
<pre>
  USER_AUTH_ORDER = 'PersonalPage : Db'
  USER_AUTH_ORDER = 'BogoLogin : PersonalPage'
</pre>");

$properties["ENABLE_AUTH_OPENID"] =
    new boolean_define('ENABLE_AUTH_OPENID');

$properties["PASSWORD_LENGTH_MINIMUM"] =
    new numeric_define('PASSWORD_LENGTH_MINIMUM', PASSWORD_LENGTH_MINIMUM);

$properties["USER_AUTH_POLICY"] =
    new _define_selection('USER_AUTH_POLICY',
        array('first-only' => "first-only - use only the first method in USER_AUTH_ORDER",
            'old' => "old - ignore USER_AUTH_ORDER (legacy)",
            'strict' => "strict - check all methods for userid + password (recommended)",
            'stacked' => "stacked - check all methods for userid, and if found for password"), "
The following policies are available for user authentication:
<dl>
<dt>first-only</dt>
        <dd>use only the first method in USER_AUTH_ORDER</dd>
<dt>old</dt>
        <dd>ignore USER_AUTH_ORDER and try to use all available
        methods as in the previous PhpWiki releases (slow)</dd>
<dt>strict</dt>
        <dd>check if the user exists for all methods:
        on the first existing user, try the password.
        dont try the other methods on failure then</dd>
<dt>stacked</dt>
        <dd>check the given user - password combination for all
        methods and return true on the first success.</dd></dl>");

$properties["ENABLE_PAGEPERM"] =
    new boolean_define_commented_optional('ENABLE_PAGEPERM');

///////////////////

$properties["Part Three A"] =
    new part('_part3a', $SEPARATOR . "\n", "

Part Three A: (optional)
Group Membership");

$properties["Group membership"] =
    new _define_selection("GROUP_METHOD",
        array('WIKIPAGE' => "WIKIPAGE - List at \"CategoryGroup\". (Slowest, but easiest to maintain)",
            'NONE' => "NONE - Disable group membership (Fastest)",
            'DB' => "DB - SQL Database, Optionally external. See USERS/GROUPS queries",
            'FILE' => "Flatfile. See AUTH_GROUP_FILE below.",
            'LDAP' => "LDAP - See \"LDAP authentication options\" above. (Experimental)"), "
Group membership.  PhpWiki supports defining permissions for a group as
well as for individual users.  This defines how group membership information
is obtained.  Supported values are:
<dl>
<dt>\"NONE\"</dt>
          <dd>Disable group membership (Fastest). Note the required quoting.</dd>
<dt>WIKIPAGE</dt>
          <dd>Define groups as list at \"CategoryGroup\". (Slowest, but easiest to maintain)</dd>
<dt>DB</dt>
          <dd>Stored in an SQL database. Optionally external. See USERS/GROUPS queries</dd>
<dt>FILE</dt>
          <dd>Flatfile. See AUTH_GROUP_FILE below.</dd>
<dt>LDAP</dt>
          <dd>LDAP groups. See \"LDAP authentication options\" above and
          lib/WikiGroup.php. (experimental)</dd></dl>");

$properties["CATEGORY_GROUP_PAGE"] =
    new _define_optional('CATEGORY_GROUP_PAGE', _("CategoryGroup"), "
If GROUP_METHOD = WIKIPAGE:

Page where all groups are listed.");

$properties["AUTH_GROUP_FILE"] =
    new _define_optional('AUTH_GROUP_FILE', "/etc/groups", "
For GROUP_METHOD = FILE, the file given below is referenced to obtain
group membership information.  It should be in the same format as the
standard unix /etc/groups(5) file.");

$properties["Part Three B"] =
    new part('_part3b', $SEPARATOR . "\n", "

Part Three B: (optional)
External database authentication and authorization.

If USER_AUTH_ORDER includes Db, or GROUP_METHOD = DB, the options listed
below define the SQL queries used to obtain the information out of the
database, and (optionally) store the information back to the DB.");

$properties["DBAUTH_AUTH_DSN"] =
    new _define_optional('DBAUTH_AUTH_DSN', $dsn_sqlstring, "
A database DSN to connect to.  Defaults to the DSN specified for the Wiki as a whole.");

$properties["User Exists Query"] =
    new _define('DBAUTH_AUTH_USER_EXISTS', "SELECT userid FROM user WHERE userid='\$userid'", "
USER/PASSWORD queries:

For USER_AUTH_POLICY=strict and the Db method is required");

$properties["Check Query"] =
    new _define_optional('DBAUTH_AUTH_CHECK', "SELECT IF(passwd='\$password',1,0) AS ok FROM user WHERE userid='\$userid'", "

Check to see if the supplied username/password pair is OK

Plaintext passwords: (DBAUTH_AUTH_CRYPT_METHOD = plain)<br />
; DBAUTH_AUTH_CHECK = \"SELECT IF(passwd='\$password',1,0) AS ok FROM user WHERE userid='\$userid'\"

database-hashed passwords (more secure):<br />
; DBAUTH_AUTH_CHECK = \"SELECT IF(passwd=PASSWORD('\$password'),1,0) AS ok FROM user WHERE userid='\$userid'\"");

$properties["Crypt Method"] =
    new _define_selection_optional
    ('DBAUTH_AUTH_CRYPT_METHOD',
        array('plain' => 'plain',
            'crypt' => 'crypt'), "
If you want to use Unix crypt()ed passwords, you can use DBAUTH_AUTH_CHECK
to get the password out of the database with a simple SELECT query, and
specify DBAUTH_AUTH_USER_EXISTS and DBAUTH_AUTH_CRYPT_METHOD:

; DBAUTH_AUTH_CHECK = \"SELECT passwd FROM user where userid='\$userid'\" <br />
; DBAUTH_AUTH_CRYPT_METHOD = crypt");

$properties["Update the user's authentication credential"] =
    new _define('DBAUTH_AUTH_UPDATE', "UPDATE user SET passwd='\$password' WHERE userid='\$userid'", "
If this is not defined but DBAUTH_AUTH_CHECK is, then the user will be unable to update their
password.

Plaintext passwords:<br />
  DBAUTH_AUTH_UPDATE = \"UPDATE user SET passwd='\$password' WHERE userid='\$userid'\"<br />
Database-hashed passwords:<br />
  DBAUTH_AUTH_UPDATE = \"UPDATE user SET passwd=PASSWORD('\$password') WHERE userid='\$userid'\"");

$properties["Allow the user to create their own account"] =
    new _define_optional('DBAUTH_AUTH_CREATE', "INSERT INTO user SET passwd=PASSWORD('\$password'),userid='\$userid'", "
If this is empty, Db users cannot subscribe by their own.");

$properties["USER/PREFERENCE queries"] =
    new _define_optional('DBAUTH_PREF_SELECT', "SELECT prefs FROM user WHERE userid='\$userid'", "
If you choose to store your preferences in an external database, enable
the following queries.  Note that if you choose to store user preferences
in the 'user' table, only registered users get their prefs from the database,
self-created users do not.  Better to use the special 'pref' table.

The prefs field stores the serialized form of the user's preferences array,
to ease the complication of storage.
<pre>
  DBAUTH_PREF_SELECT = \"SELECT prefs FROM user WHERE userid='\$userid'\"
  DBAUTH_PREF_SELECT = \"SELECT prefs FROM pref WHERE userid='\$userid'\"
</pre>");

$properties["Update the user's preferences"] =
    new _define_optional('DBAUTH_PREF_UPDATE', "UPDATE user SET prefs='\$pref_blob' WHERE userid='\$userid'", "
Note that REPLACE works only with mysql and destroy all other columns!

Mysql: DBAUTH_PREF_UPDATE = \"REPLACE INTO pref SET prefs='\$pref_blob',userid='\$userid'\"");

$properties["Create new user's preferences"] =
    new _define_optional('DBAUTH_PREF_INSERT', "INSERT INTO pref (userid,prefs) VALUES ('\$userid','\$pref_blob')", "
Define this if new user can be create by themselves.
");

$properties["USERS/GROUPS queries"] =
    new _define_optional('DBAUTH_IS_MEMBER', "SELECT user FROM user WHERE user='\$userid' AND group='\$groupname'", "
You can define 1:n or n:m user<=>group relations, as you wish.

Sample configurations:

only one group per user (1:n):<br />
   DBAUTH_IS_MEMBER = \"SELECT user FROM user WHERE user='\$userid' AND group='\$groupname'\"<br />
   DBAUTH_GROUP_MEMBERS = \"SELECT user FROM user WHERE group='\$groupname'\"<br />
   DBAUTH_USER_GROUPS = \"SELECT group FROM user WHERE user='\$userid'\"<br />
multiple groups per user (n:m):<br />
   DBAUTH_IS_MEMBER = \"SELECT userid FROM member WHERE userid='\$userid' AND groupname='\$groupname'\"<br />
   DBAUTH_GROUP_MEMBERS = \"SELECT DISTINCT userid FROM member WHERE groupname='\$groupname'\"<br />
   DBAUTH_USER_GROUPS = \"SELECT groupname FROM member WHERE userid='\$userid'\"<br />");
$properties["DBAUTH_GROUP_MEMBERS"] =
    new _define_optional('DBAUTH_GROUP_MEMBERS', "SELECT user FROM user WHERE group='\$groupname'", "");
$properties["DBAUTH_USER_GROUPS"] =
    new _define_optional('DBAUTH_USER_GROUPS', "SELECT group FROM user WHERE user='\$userid'", "");

$properties["LDAP AUTH Host"] =
    new _define_optional('LDAP_AUTH_HOST', "ldap://localhost:389", "
If USER_AUTH_ORDER contains Ldap:

The LDAP server to connect to.  Can either be a hostname, or a complete
URL to the server (useful if you want to use ldaps or specify a different
port number).");

$properties["LDAP BASE DN"] =
    new _define_optional('LDAP_BASE_DN', "ou=mycompany.com,o=My Company", "
The organizational or domain BASE DN: e.g. \"dc=mydomain,dc=com\".

Note: ou=Users and ou=Groups are used for GroupLdap Membership
Better use LDAP_OU_USERS and LDAP_OU_GROUP with GROUP_METHOD=LDAP.");

$properties["LDAP SET OPTION"] =
    new _define_optional('LDAP_SET_OPTION', "LDAP_OPT_PROTOCOL_VERSION=3:LDAP_OPT_REFERRALS=0", "
Some LDAP servers need some more options, such as the Windows Active
Directory Server.  Specify the options (as allowed by the PHP LDAP module)
and their values as NAME=value pairs separated by colons.");

$properties["LDAP AUTH USER"] =
    new _define_optional('LDAP_AUTH_USER', "CN=ldapuser,ou=Users,o=Development,dc=mycompany.com", "
DN to initially bind to the LDAP server as. This is needed if the server doesn't
allow anonymous queries. (Windows Active Directory Server)");

$properties["LDAP AUTH PASSWORD"] =
    new _define_optional('LDAP_AUTH_PASSWORD', "secret", "
Password to use to initially bind to the LDAP server, as the DN
specified in the LDAP_AUTH_USER option (above).");

$properties["LDAP SEARCH FIELD"] =
    new _define_optional('LDAP_SEARCH_FIELD', "uid", "
If you want to match usernames against an attribute other than uid,
specify it here. Default: uid

e.g.: LDAP_SEARCH_FIELD = sAMAccountName");

$properties["LDAP SEARCH FILTER"] =
    new _define_optional('LDAP_SEARCH_FILTER', "(uid=\$userid)", "
If you want to check against special attributes, such as external partner, employee status.
Default: undefined. This overrides LDAP_SEARCH_FIELD.
Example (&(uid=\$userid)(employeeType=y)(myCompany=My Company*)(!(myCompany=My Company Partner*)))");

$properties["LDAP OU USERS"] =
    new _define_optional('LDAP_OU_USERS', "ou=Users", "
If you have an organizational unit for all users, define it here.
This narrows the search, and is needed for LDAP group membership (if GROUP_METHOD=LDAP)
Default: ou=Users");

$properties["LDAP OU GROUP"] =
    new _define_optional('LDAP_OU_GROUP', "ou=Groups", "
If you have an organizational unit for all groups, define it here.
This narrows the search, and is needed for LDAP group membership (if GROUP_METHOD=LDAP)
The entries in this ou must have a gidNumber and cn attribute.
Default: ou=Groups");

$properties["IMAP Auth Host"] =
    new _define_optional('IMAP_AUTH_HOST', 'localhost:143/imap/notls', "
If USER_AUTH_ORDER contains IMAP:

The IMAP server to check usernames from. Defaults to localhost.

Some IMAP_AUTH_HOST samples:
  localhost, localhost:143/imap/notls,
  localhost:993/imap/ssl/novalidate-cert (SuSE refuses non-SSL conections)");

$properties["POP3 Authentication"] =
    new _define_optional('POP3_AUTH_HOST', 'localhost:110', "
If USER_AUTH_ORDER contains POP3:

The POP3 mail server to check usernames and passwords against.");
$properties["File Authentication"] =
    new _define_optional('AUTH_USER_FILE', '/etc/shadow', "
If USER_AUTH_ORDER contains File:

File to read for authentication information.
Popular choices are /etc/shadow and /etc/httpd/.htpasswd");

$properties["File Storable?"] =
    new boolean_define_commented_optional('AUTH_USER_FILE_STORABLE');

$properties["Session Auth USER"] =
    new _define_optional('AUTH_SESS_USER', 'userid', "
If USER_AUTH_ORDER contains Session:

Name of the session variable which holds the already authenticated username.
Sample: 'userid', 'user[username]', 'user->username'");

$properties["Session Auth LEVEL"] =
    new numeric_define('AUTH_SESS_LEVEL', '2', "
Which level will the user be? 1 = Bogo or 2 = Pass");

/////////////////////////////////////////////////////////////////////

$properties["Part Four"] =
    new part('_part4', $SEPARATOR . "\n", "

Part Four:
Page appearance and layout");

$properties["Theme"] =
    new _define_selection_optional('THEME',
        array('default' => "default",
            'MacOSX' => "MacOSX",
            'smaller' => 'smaller',
            'Wordpress' => 'Wordpress',
            'Portland' => "Portland",
            'Sidebar' => "Sidebar",
            'Crao' => 'Crao',
            'wikilens' => 'wikilens (Ratings)',
            'shamino_com' => 'shamino_com',
            'SpaceWiki' => "SpaceWiki",
            'Hawaiian' => "Hawaiian",
            'MonoBook' => 'MonoBook [experimental]',
            'blog' => 'blog [experimental]',
        ), "
THEME

Most of the page appearance is controlled by files in the theme
subdirectory.

There are a number of pre-defined themes shipped with PhpWiki.
Or you may create your own, deriving from existing ones.
<pre>
  THEME = Sidebar (default)
  THEME = default
  THEME = MacOSX
  THEME = MonoBook (WikiPedia)
  THEME = smaller
  THEME = Wordpress
  THEME = Portland
  THEME = Crao
  THEME = wikilens (with Ratings)
  THEME = Hawaiian
  THEME = SpaceWiki
  THEME = Hawaiian
  THEME = blog     (Kubrick)   [experimental]
</pre>");

$properties["Language"] =
    new _define_selection_optional('DEFAULT_LANGUAGE',
        array('en' => "English",
            '' => "&lt;empty&gt; (user-specific)",
            'fr' => "Français",
            'de' => "Deutsch",
            'nl' => "Nederlands",
            'es' => "Español",
            'sv' => "Svenska",
            'it' => "Italiano",
            'ja' => "Japanese",
            'zh' => "Chinese"), "
Select your language/locale - default language is \"en\" for English.
Other languages available:<pre>
English  \"en\" (English    - HomePage)
German   \"de\" (Deutsch    - StartSeite)
French   \"fr\" (Français   - PageAccueil)
Dutch    \"nl\" (Nederlands - ThuisPagina)
Spanish  \"es\" (Español    - PáginaPrincipal)
Swedish  \"sv\" (Svenska    - Framsida)
Italian  \"it\" (Italiano   - PaginaPrincipale)
Japanese \"ja\" (Japanese   - ホームページ)
Chinese  \"zh\" (Chinese    - 首頁)
</pre>
If you set DEFAULT_LANGUAGE to the empty string, your systems default language
(as determined by the applicable environment variables) will be
used.");

$properties["Wiki Page Source"] =
    new _define_optional('WIKI_PGSRC', 'pgsrc', "
WIKI_PGSRC -- specifies the source for the initial page contents of
the Wiki. The setting of WIKI_PGSRC only has effect when the wiki is
accessed for the first time (or after clearing the database.)
WIKI_PGSRC can either name a directory or a zip file. In either case
WIKI_PGSRC is scanned for files -- one file per page.
<pre>
// Default (old) behavior:
define('WIKI_PGSRC', 'pgsrc');
// New style:
define('WIKI_PGSRC', 'wiki.zip');
define('WIKI_PGSRC',
       '../Logs/Hamwiki/hamwiki-20010830.zip');
</pre>");

$properties["Default Wiki Page Source"] =
    new _define('DEFAULT_WIKI_PGSRC', 'pgsrc', "
DEFAULT_WIKI_PGSRC is only used when the language is *not* the
default (English) and when reading from a directory: in that case
some English pages are inserted into the wiki as well.
DEFAULT_WIKI_PGSRC defines where the English pages reside.
");

$properties["Generic Pages"] =
    new array_variable('DEFAULT_WIKI_PAGES', array('ReleaseNotes', 'TestPage'), "
These are ':'-separated pages which will get loaded untranslated from DEFAULT_WIKI_PGSRC.
");

///////////////////

$properties["Part Five"] =
    new part('_part5', $SEPARATOR . "\n", "

Part Five:
Mark-up options");

$properties["Allowed Protocols"] =
    new list_define('ALLOWED_PROTOCOLS', 'http|https|mailto|ftp|news|nntp|ssh|gopher', "
Allowed protocols for links - be careful not to allow \"javascript:\"
URL of these types will be automatically linked.
within a named link [name|uri] one more protocol is defined: phpwiki");

$properties["Inline Images"] =
    new list_define('INLINE_IMAGES', 'png|jpg|jpeg|gif');

$properties["WikiName Regexp"] =
    new _define('WIKI_NAME_REGEXP', "(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])", "
Perl regexp for WikiNames (\"bumpy words\")
(?&lt;!..) &amp; (?!...) used instead of '\b' because \b matches '_' as well");

$properties["InterWiki Map File"] =
    new _define('INTERWIKI_MAP_FILE', 'lib/interwiki.map', "
InterWiki linking -- wiki-style links to other wikis on the web

The map will be taken from a page name InterWikiMap.
If that page is not found (or is not locked), or map
data can not be found in it, then the file specified
by INTERWIKI_MAP_FILE (if any) will be used.");

$properties["WARN_NONPUBLIC_INTERWIKIMAP"] =
    new boolean_define('WARN_NONPUBLIC_INTERWIKIMAP');

$properties["Keyword Link Regexp"] =
    new _define_optional('KEYWORDS', '\"Category* OR Topic*\"', "
Search term used for automatic page classification by keyword extraction.

Any links on a page to pages whose names match this search
will be used keywords in the keywords HTML meta tag. This is an aid to
classification by search engines. The value of the match is
used as the keyword.

The default behavior is to match Category* or Topic* links.");

$properties["Author and Copyright Site Navigation Links"] =
    new _define_commented_optional('COPYRIGHTPAGE_TITLE', "GNU General Public License", "

These will be inserted as &lt;link rel&gt; tags in the HTML header of
every page, for search engines and for browsers like Mozilla which
take advantage of link rel site navigation.

If you have your own copyright and contact information pages change
these as appropriate.");

$properties["COPYRIGHTPAGE URL"] =
    new _define_commented_optional('COPYRIGHTPAGE_URL', "https://www.gnu.org/copyleft/gpl.html#SEC1", "

Other useful alternatives to consider:
<pre>
 COPYRIGHTPAGE_TITLE = \"GNU Free Documentation License\"
 COPYRIGHTPAGE_URL = \"https://www.gnu.org/copyleft/fdl.html\"
 COPYRIGHTPAGE_TITLE = \"Creative Commons License 2.0\"
 COPYRIGHTPAGE_URL = \"https://creativecommons.org/licenses/by/2.0/\"</pre>
See https://creativecommons.org/learn/licenses/ for variations");

$properties["AUTHORPAGE_TITLE"] =
    new _define_commented_optional('AUTHORPAGE_TITLE', "The PhpWiki Programming Team", "
Default Author Names");
$properties["AUTHORPAGE_URL"] =
    new _define_commented_optional('AUTHORPAGE_URL', "http://phpwiki.demo.free.fr/index.php/The%20PhpWiki%20programming%20team", "
Default Author URL");

$properties["TOC_FULL_SYNTAX"] =
    new boolean_define_optional('TOC_FULL_SYNTAX');

$properties["ENABLE_MARKUP_COLOR"] =
    new boolean_define_optional('ENABLE_MARKUP_COLOR');

$properties["DISABLE_MARKUP_WIKIWORD"] =
    new boolean_define_optional('DISABLE_MARKUP_WIKIWORD');

$properties["ENABLE_MARKUP_DIVSPAN"] =
    new boolean_define_optional('ENABLE_MARKUP_DIVSPAN');

///////////////////

$properties["Part Six"] =
    new part('_part6', $SEPARATOR . "\n", "

Part Six (optional):
URL options -- you can probably skip this section.

For a pretty wiki (no index.php in the url) set a separate DATA_PATH.");

$properties["Server Name"] =
    new _define_commented_optional('SERVER_NAME', $_SERVER['SERVER_NAME'], "
Canonical name of the server on which this PhpWiki resides.");

$properties["Server Port"] =
    new numeric_define_commented('SERVER_PORT', $_SERVER['SERVER_PORT'], "
Canonical httpd port of the server on which this PhpWiki resides.",
        "onchange=\"validate_ereg('Sorry, \'%s\' is no valid port number.', '^[0-9]+$', 'SERVER_PORT', this);\"");

$properties["Server Protocol"] =
    new _define_selection_optional_commented('SERVER_PROTOCOL',
        array('http' => 'http',
            'https' => 'https'));

$properties["Script Name"] =
    new _define_commented_optional('SCRIPT_NAME', $scriptname);

$properties["Data Path"] =
    new _define_commented_optional('DATA_PATH', dirname($scriptname));

$properties["PhpWiki Install Directory"] =
    new _define_commented_optional('PHPWIKI_DIR', dirname(__FILE__));

$properties["Use PATH_INFO"] =
    new _define_selection_optional_commented('USE_PATH_INFO',
        array('' => 'automatic',
            'true' => 'use PATH_INFO',
            'false' => 'do not use PATH_INFO'), "
PhpWiki will try to use short urls to pages, eg
http://www.example.com/index.php/HomePage
If you want to use urls like
http://www.example.com/index.php?pagename=HomePage
then define 'USE_PATH_INFO' as false by uncommenting the line below.
NB:  If you are using Apache >= 2.0.30, then you may need to to use
the directive \"AcceptPathInfo On\" in your Apache configuration file
(or in an appropriate <.htaccess> file) for the short urls to work:
See https://httpd.apache.org/docs-2.0/mod/core.html#acceptpathinfo

Default: PhpWiki will try to divine whether use of PATH_INFO
is supported in by your webserver/PHP configuration, and will
use PATH_INFO if it thinks that is possible.");

$properties["Virtual Path"] =
    new _define_commented_optional('VIRTUAL_PATH', '/SomeWiki', "
VIRTUAL_PATH is the canonical URL path under which your your wiki
appears. Normally this is the same as dirname(SCRIPT_NAME), however
using e.g. separate starter scripts, apaches mod_actions (or mod_rewrite),
you can make it something different.

If you do this, you should set VIRTUAL_PATH here or in the starter scripts.

E.g. your phpwiki might be installed at at /scripts/phpwiki/index.php,
but you've made it accessible through eg. /wiki/HomePage.

One way to do this is to create a directory named 'wiki' in your
server root. The directory contains only one file: an .htaccess
file which reads something like:
<pre>
    Action x-phpwiki-page /scripts/phpwiki/index.php
    SetHandler x-phpwiki-page
    DirectoryIndex /scripts/phpwiki/index.php
</pre>
In that case you should set VIRTUAL_PATH to '/wiki'.

(VIRTUAL_PATH is only used if USE_PATH_INFO is true.)
");

$upload_file_path = defined('UPLOAD_FILE_PATH') ? UPLOAD_FILE_PATH : getUploadFilePath();
new _define_optional('UPLOAD_FILE_PATH', $temp);

$upload_data_path = defined('UPLOAD_DATA_PATH') ? UPLOAD_DATA_PATH : getUploadDataPath();
new _define_optional('UPLOAD_DATA_PATH', $temp);

$temp = !empty($_ENV['TEMP']) ? $_ENV['TEMP'] : "/tmp";
$properties["TEMP_DIR"] =
    new _define_optional('TEMP_DIR', $temp);

$properties["Allowed Load"] =
    new _define_commented_optional('ALLOWED_LOAD', '/tmp',
        'List of directories from which it is allowed to load pages. Directories are separated with ":"');

///////////////////

$properties["Part Seven"] =
    new part('_part7', $SEPARATOR . "\n", "

Part Seven:

Miscellaneous settings
");

$properties["Strict Mailable Pagedumps"] =
    new boolean_define_optional('STRICT_MAILABLE_PAGEDUMPS',
        array('false' => "binary",
            'true' => "quoted-printable"));

$properties["Default local Dump Directory"] =
    new _define_optional('DEFAULT_DUMP_DIR');

$properties["Default local HTML Dump Directory"] =
    new _define_optional('HTML_DUMP_DIR');

$properties["HTML Dump Filename Suffix"] =
    new _define_optional('HTML_DUMP_SUFFIX');

$properties["Pagename of Recent Changes"] =
    new _define_optional('RECENT_CHANGES',
        "RecentChanges");

$properties["Disable GETIMAGESIZE"] =
    new boolean_define_commented_optional('DISABLE_GETIMAGESIZE');

$properties["EDITING_POLICY"] =
    new _define_optional('EDITING_POLICY');

$properties["TOOLBAR_PAGELINK_PULLDOWN"] =
    new _define_commented_optional('TOOLBAR_PAGELINK_PULLDOWN');
$properties["TOOLBAR_TEMPLATE_PULLDOWN"] =
    new _define_commented_optional('TOOLBAR_TEMPLATE_PULLDOWN');
$properties["TOOLBAR_IMAGE_PULLDOWN"] =
    new _define_commented_optional('TOOLBAR_IMAGE_PULLDOWN');
$properties["FULLTEXTSEARCH_STOPLIST"] =
    new _define_commented_optional('FULLTEXTSEARCH_STOPLIST');

$properties["Part Seven A"] =
    new part('_part7a', $SEPARATOR . "\n", "

Part Seven A:

Optional Plugin Settings and external executables
");

$properties["FORTUNE_DIR"] =
    new _define_commented_optional('FORTUNE_DIR', "/usr/share/fortune");
$properties["USE_EXTERNAL_HTML2PDF"] =
    new _define_commented_optional('USE_EXTERNAL_HTML2PDF', "htmldoc --quiet --format pdf14 --no-toc --no-title %s");
$properties["EXTERNAL_HTML2PDF_PAGELIST"] =
    new _define_commented_optional('EXTERNAL_HTML2PDF_PAGELIST');
$properties["BABYCART_PATH"] =
    new _define_commented_optional('BABYCART_PATH', "/usr/local/bin/babycart");
$properties["GOOGLE_LICENSE_KEY"] =
    new _define_commented_optional('GOOGLE_LICENSE_KEY');
$properties["ENABLE_RATEIT"] =
    new boolean_define_commented_optional('ENABLE_RATEIT');
$properties["RATEIT_IMGPREFIX"] =
    new _define_commented_optional('RATEIT_IMGPREFIX'); //BStar
$properties["GRAPHVIZ_EXE"] =
    new _define_commented_optional('GRAPHVIZ_EXE', "/usr/bin/dot");

if (PHP_OS == "Darwin") // Mac OS X
    $ttfont = "/System/Library/Frameworks/JavaVM.framework/Versions/1.3.1/Home/lib/fonts/LucidaSansRegular.ttf";
elseif (isWindows()) {
    $ttfont = $_ENV['windir'] . '\Fonts\Arial.ttf';
} else {
    $ttfont = 'luximr'; // This is the only what sourceforge offered.
    //$ttfont = 'Helvetica';
}
$properties["TTFONT"] =
    new _define_commented_optional('TTFONT', $ttfont);
$properties["VISUALWIKIFONT"] =
    new _define_commented_optional('VISUALWIKIFONT'); // Arial
$properties["VISUALWIKI_ALLOWOPTIONS"] =
    new boolean_define_commented_optional('VISUALWIKI_ALLOWOPTIONS'); // false
$properties["PLOTICUS_EXE"] =
    new _define_commented_optional('PLOTICUS_EXE'); // /usr/local/bin/pl
$properties["PLOTICUS_PREFABS"] =
    new _define_commented_optional('PLOTICUS_PREFABS'); // /usr/share/ploticus
$properties["MY_JABBER_ID"] =
    new _define_commented_optional('MY_JABBER_ID'); //

$properties["Part Eight"] =
    new part('_part8', $SEPARATOR . "\n", "

Part Eight:

Cached Plugin Settings. (pear Cache)
");

$properties["pear Cache USECACHE"] =
    new boolean_define_optional('PLUGIN_CACHED_USECACHE',
        array('true' => 'Enabled',
            'false' => 'Disabled'), "
Enable or disable pear caching of plugins.");
$properties["pear Cache Database Container"] =
    new _define_selection_optional('PLUGIN_CACHED_DATABASE',
        array('file' => 'file'), "
Curently only file is supported.
db, trifile and imgfile might be supported, but you must hack that by yourself.");

$properties["pear Cache cache directory"] =
    new _define_commented_optional('PLUGIN_CACHED_CACHE_DIR', "/tmp/cache", "
Should be writable to the webserver.");
$properties["pear Cache Filename Prefix"] =
    new _define_optional('PLUGIN_CACHED_FILENAME_PREFIX', "phpwiki", "");
$properties["pear Cache HIGHWATER"] =
    new numeric_define_optional('PLUGIN_CACHED_HIGHWATER', "4194304", "
Garbage collection parameter.");
$properties["pear Cache LOWWATER"] =
    new numeric_define_optional('PLUGIN_CACHED_LOWWATER', "3145728", "
Garbage collection parameter.");
$properties["pear Cache MAXLIFETIME"] =
    new numeric_define_optional('PLUGIN_CACHED_MAXLIFETIME', "2592000", "
Garbage collection parameter.");
$properties["pear Cache MAXARGLEN"] =
    new numeric_define_optional('PLUGIN_CACHED_MAXARGLEN', "1000", "
max. generated url length.");
$properties["pear Cache FORCE_SYNCMAP"] =
    new boolean_define_optional('PLUGIN_CACHED_FORCE_SYNCMAP',
        array('true' => 'Enabled',
            'false' => 'Disabled'), "");
$properties["pear Cache IMGTYPES"] =
    new list_define('PLUGIN_CACHED_IMGTYPES', "png|gif|gd|gd2|jpeg|wbmp|xbm|xpm", "
Handle those image types via GD handles. Check your GD supported image types.");

$end = "\n" . $SEPARATOR . "\n";

// performance hack
text_from_dist("_MAGIC_CLOSE_FILE");

// end of configuration options
///////////////////////////////
// begin class definitions

/**
 * A basic config-dist.ini configuration line in the form of a variable.
 * (not needed anymore, we have only defines)
 *
 * Produces a string in the form "$name = value;"
 * e.g.:
 * $WikiNameRegexp = "value";
 */
class _variable
{
    var $config_item_name;
    var $default_value;
    var $description;
    var $prefix;
    var $jscheck;
    var $values;

    function __construct($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        $this->config_item_name = $config_item_name;
        if (!$description)
            $description = text_from_dist($config_item_name);
        $this->description = $description;
        if (defined($config_item_name)
            and !preg_match("/(selection|boolean)/", get_class($this))
                and !preg_match("/^(SCRIPT_NAME|VIRTUAL_PATH|TEMP_DIR)$/", $config_item_name)
        )
            $this->default_value = constant($config_item_name); // ignore given default value
        elseif ($config_item_name == $default_value)
            $this->default_value = ''; else
            $this->default_value = $default_value;
        $this->jscheck = $jscheck;
        if (preg_match("/variable/i", get_class($this)))
            $this->prefix = "\$";
        elseif (preg_match("/ini_set/i", get_class($this)))
            $this->prefix = "ini_get: "; else
            $this->prefix = "";
    }

    function _define($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        _variable::__construct($config_item_name, $default_value, $description, $jscheck);
    }

    function value()
    {
        global $HTTP_POST_VARS;
        if (isset($HTTP_POST_VARS[$this->config_item_name]))
            return $HTTP_POST_VARS[$this->config_item_name];
        else
            return $this->default_value;
    }

    function _config_format($value)
    {
        return '';
    }

    function get_config_item_name()
    {
        return $this->config_item_name;
    }

    function get_config_item_id()
    {
        return str_replace('|', '-', $this->config_item_name);
    }

    function get_config_item_header()
    {
        if (strchr($this->config_item_name, '|')) {
            list($var, $param) = explode('|', $this->config_item_name);
            return "<b>" . $this->prefix . $var . "['" . $param . "']</b><br />";
        } elseif ($this->config_item_name[0] != '_')
            return "<b>" . $this->prefix . $this->config_item_name . "</b><br />"; else
            return '';
    }

    function _get_description()
    {
        return $this->description;
    }

    function _get_config_line($posted_value)
    {
        return "\n" . $this->_config_format($posted_value);
    }

    function get_config($posted_value)
    {
        $d = stripHtml($this->_get_description());
        return str_replace("\n", "\n; ", $d) . $this->_get_config_line($posted_value) . "\n";
    }

    function get_instructions($title)
    {
        $i = "<h3>" . $title . "</h3>\n    " . nl2p($this->_get_description()) . "\n";
        return "<tr>\n<td class=\"instructions\">\n" . $i . "</td>\n";
    }

    function get_html()
    {
        $size = strlen($this->default_value) > 45 ? 90 : 50;
        return $this->get_config_item_header() .
            "<input type=\"text\" size=\"$size\" name=\"" . $this->get_config_item_name()
            . '" value="' . htmlspecialchars($this->default_value) . '" ' . $this->jscheck . " />"
            . "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
    }
}

class unchangeable_variable
    extends _variable
{
    function _config_format($value)
    {
        return "";
    }

    function get_html()
    {
        return $this->get_config_item_header() .
            "<em>Not editable.</em>" .
            "<pre>" . $this->default_value . "</pre>";
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        return "$n" . $this->default_value;
    }

    function get_instructions($title)
    {
        $i = "<h3>" . $title . "</h3>\n    " . nl2p($this->_get_description()) . "\n";
        // $i .= "<em>Not editable.</em><br />\n<pre>" . $this->default_value."</pre>";
        return '<tr><td style="width:100%;" class="unchangeable-variable-top" colspan="2">' . "\n" . $i . "</td></tr>\n"
            . '<tr style="border-top: none;"><td class="unchangeable-variable-left">&nbsp;</td>';
    }
}

class unchangeable_define
    extends unchangeable_variable
{
    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if (!$posted_value)
            $posted_value = $this->default_value;
        return "$n" . $this->_config_format($posted_value);
    }

    function _config_format($value)
    {
        return sprintf("%s = \"%s\"", $this->get_config_item_name(), $value);
    }
}

class _variable_selection
    extends _variable
{
    function value()
    {
        global $HTTP_POST_VARS;
        if (!empty($HTTP_POST_VARS[$this->config_item_name])) {
            return $HTTP_POST_VARS[$this->config_item_name];
        } else {
            if (is_array($this->default_value)) {
                $option = key($this->default_value);
                next($this->default_value);
                return $option;
            } else {
                return '';
            }
        }
    }

    function get_html()
    {
        $output = $this->get_config_item_header();
        $output .= '<select name="' . $this->get_config_item_name() . "\">\n";
        /* The first option is the default */
        $values = $this->default_value;
        if (defined($this->get_config_item_name()))
            $this->default_value = constant($this->get_config_item_name());
        else
            $this->default_value = null;

        foreach ($values as $option => $label) {
            if (!is_null($this->default_value) && $this->default_value === $option)
                $output .= "  <option value=\"$option\" selected=\"selected\">$label</option>\n";
            else
                $output .= "  <option value=\"$option\">$label</option>\n";
        }
        $output .= "</select>\n";
        return $output;
    }
}

class _define
    extends _variable
{
    function _config_format($value)
    {
        return sprintf("%s = \"%s\"", $this->get_config_item_name(), $value);
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "$n;" . $this->_config_format("");
        else
            return "$n" . $this->_config_format($posted_value);
    }

    function get_html()
    {
        $size = strlen($this->default_value) > 45 ? 90 : 50;
        return $this->get_config_item_header()
            . "<input type=\"text\" size=\"$size\" name=\"" . htmlentities($this->get_config_item_name())
            . '" value="' . htmlentities($this->default_value) . '" ' . $this->jscheck . " />"
            . "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
    }
}

class _define_commented
    extends _define
{
    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "$n;" . $this->_config_format($posted_value);
        elseif ($posted_value == '')
            return "$n;" . $this->_config_format("");
        else
            return "$n" . $this->_config_format($posted_value);
    }
}

/**
 * We don't use _optional anymore, because INI-style config's don't need that.
 * IniConfig.php does the optional logic now.
 * But we use _optional for config-default.ini options
 */
class _define_commented_optional
    extends _define_commented
{
}

class _define_optional
    extends _define
{
}

class _define_notempty
    extends _define
{
    function get_html()
    {
        $s = $this->get_config_item_header()
            . "<input type=\"text\" size=\"50\" name=\"" . $this->get_config_item_name()
            . '" value="' . $this->default_value . '" ' . $this->jscheck . " />";
        if (empty($this->default_value))
            return $s . "<p id=\"" . $this->get_config_item_id() . "\" class=\"red\">Cannot be empty.</p>";
        else
            return $s . "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
    }
}

class numeric_define
    extends _define
{

    function __construct($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        parent::__construct($config_item_name, $default_value, $description, $jscheck);
        if (!$jscheck)
            $this->jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' is not an integer.', '^[-+]?[0-9]+$', '" . $this->get_config_item_name() . "', this);\"";
    }

    function _config_format($value)
    {
        return sprintf("%s = %s", $this->get_config_item_name(), $value);
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == '')
            return "$n;" . $this->_config_format('0');
        else
            return "$n" . $this->_config_format($posted_value);
    }
}

class numeric_define_optional
    extends numeric_define
{
}

class numeric_define_commented
    extends numeric_define
{
    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "$n;" . $this->_config_format($posted_value);
        elseif ($posted_value == '')
            return "$n;" . $this->_config_format('0');
        else
            return "$n" . $this->_config_format($posted_value);
    }
}

class _define_selection
    extends _variable_selection
{
    function _config_format($value)
    {
        return sprintf("%s = %s", $this->get_config_item_name(), $value);
    }

}

class _define_selection_optional
    extends _define_selection
{
}

class _define_selection_optional_commented
    extends _define_selection_optional
{
    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == $this->default_value)
            return "$n;" . $this->_config_format($posted_value);
        elseif ($posted_value == '')
            return "$n;" . $this->_config_format("");
        else
            return "$n" . $this->_config_format($posted_value);
    }
}

class _define_password
    extends _define
{
    function __construct($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        if ($config_item_name == $default_value) $default_value = '';
        parent::__construct($config_item_name, $default_value, $description, $jscheck);
        if (!$jscheck)
            $this->jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' cannot be empty.', '^.+$', '"
                . $this->get_config_item_name() . "', this);\"";
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == '') {
            $p = "$n;" . $this->_config_format("");
            $p .= "\n; If you used the passencrypt.php utility to encode the password";
            $p .= "\n; then uncomment this line:";
            $p .= "\n;ENCRYPTED_PASSWD = true";
            return $p;
        } else {
            $salt_length = max(CRYPT_SALT_LENGTH,
                2 * CRYPT_STD_DES,
                9 * CRYPT_EXT_DES,
                12 * CRYPT_MD5,
                16 * CRYPT_BLOWFISH);
            // generate an encrypted password
            $crypt_pass = crypt($posted_value, rand_ascii($salt_length));
            $p = "$n" . $this->_config_format($crypt_pass);
            return $p . "\nENCRYPTED_PASSWD = true";
        }
    }

}

class _define_password_optional
    extends _define_password
{

    function __construct($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        if ($config_item_name == $default_value) $default_value = '';
        if (!$jscheck) $this->jscheck = " ";
        parent::__construct($config_item_name, $default_value, $description, $jscheck);
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if ($posted_value == '') {
            return "$n;" . $this->_config_format("");
        } else {
            return "$n" . $this->_config_format($posted_value);
        }
    }

    function get_html()
    {
        $s = $this->get_config_item_header();
        // dont re-encrypt already encrypted passwords
        $value = $this->value();
        $encrypted = !empty($GLOBALS['properties']["Encrypted Passwords"]) and
            $GLOBALS['properties']["Encrypted Passwords"]->value();
        if (empty($value))
            $encrypted = false;
        $s .= '<input type="' . ($encrypted ? "text" : "password") . '" name="' . $this->get_config_item_name()
            . '" value="' . $value . '" ' . $this->jscheck . " />";
        return $s;
    }
}

class _variable_password
    extends _variable
{
    function __construct($config_item_name, $default_value = '', $description = '', $jscheck = '')
    {
        if ($config_item_name == $default_value) $default_value = '';
        parent::__construct($config_item_name, $default_value, $description, $jscheck);
        if (!$jscheck)
            $this->jscheck = "onchange=\"validate_ereg('Sorry, \'%s\' cannot be empty.', '^.+$', '" . $this->get_config_item_name() . "', this);\"";
    }

    function get_html()
    {
        global $HTTP_POST_VARS, $HTTP_GET_VARS;
        $s = $this->get_config_item_header();
        if (isset($HTTP_POST_VARS['create']) or isset($HTTP_GET_VARS['create'])) {
            $new_password = random_good_password();
            $this->default_value = $new_password;
            $s .= "Created password: <strong>$new_password</strong><br />&nbsp;<br />";
        }
        // do not re-encrypt already encrypted passwords
        $value = $this->value();
        $encrypted = !empty($GLOBALS['properties']["Encrypted Passwords"]) and
            $GLOBALS['properties']["Encrypted Passwords"]->value();
        if (empty($value))
            $encrypted = false;
        $s .= '<input type="' . ($encrypted ? "text" : "password") . '" name="' . $this->get_config_item_name()
            . '" value="' . $value . '" ' . $this->jscheck . " />"
            . "&nbsp;&nbsp;<input type=\"submit\" name=\"create\" value=\"Create Random Password\" />";
        if (empty($value))
            $s .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"red\">Cannot be empty.</p>";
        elseif (strlen($this->default_value) < 4)
            $s .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"red\">Must be longer than 4 chars.</p>";
        else
            $s .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
        return $s;
    }
}

class list_define
    extends _define
{
    function _get_config_line($posted_value)
    {
        $list_values = preg_split("/[\s,]+/", $posted_value, -1, PREG_SPLIT_NO_EMPTY);
        if ($list_values)
            $list_values = join("|", $list_values);
        return _variable::_get_config_line($list_values);
    }

    function get_html()
    {
        $list_values = explode("|", $this->default_value);
        $rows = max(3, count($list_values) + 1);
        if ($list_values)
            $list_values = join("\n", $list_values);
        $ta = $this->get_config_item_header();
        $ta .= '<textarea cols="18" rows="' . $rows . '" name="';
        $ta .= $this->get_config_item_name() . '" ' . $this->jscheck . '>';
        $ta .= $list_values . "</textarea>";
        $ta .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
        return $ta;
    }
}

class array_variable
    extends _variable
{
    function _config_format($value)
    {
        return sprintf("%s = \"%s\"", $this->get_config_item_name(),
            is_array($value) ? join(':', $value) : $value);
    }

    function _get_config_line($posted_value)
    {
        // split the phrase by any number of commas or space characters,
        // which include " ", \r, \t, \n and \f
        $list_values = preg_split("/[\s,]+/", $posted_value, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($list_values)) {
            $list_values = "'" . join("', '", $list_values) . "'";
            return "\n" . $this->_config_format($list_values);
        } else
            return "\n;" . $this->_config_format('');
    }

    function get_html()
    {
        if (is_array($this->default_value)) {
            $list_values = join("\n", $this->default_value);
            $count = count($this->default_value);
        } else {
            $list_values = $this->default_value;
            $count = 1;
        }
        $rows = max(3, $count + 1);
        $ta = $this->get_config_item_header();
        $ta .= '<textarea cols="18" rows="' . $rows . '" name="';
        $ta .= $this->get_config_item_name() . '" ' . $this->jscheck . '>';
        $ta .= $list_values . "</textarea>";
        $ta .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
        return $ta;
    }
}

class array_define
    extends _define
{
    function _config_format($value)
    {
        return sprintf("%s = \"%s\"", $this->get_config_item_name(),
            is_array($value) ? join(' : ', $value) : $value);
    }

    function _get_config_line($posted_value)
    {
        // split the phrase by any number of commas or space characters,
        // which include " ", \r, \t, \n and \f
        $list_values = preg_split("/[\s,:]+/", $posted_value, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($list_values)) {
            $list_values = join(" : ", $list_values);
            return "\n" . $this->_config_format($list_values);
        } else
            return "\n;" . $this->_config_format('');
    }

    function get_html()
    {
        if (!$this->default_value)
            $this->default_value = array();
        elseif (is_string($this->default_value))
            $this->default_value = preg_split("/[\s,:]+/", $this->default_value, -1, PREG_SPLIT_NO_EMPTY);
        $list_values = join(" : \n", $this->default_value);
        $rows = max(3, count($this->default_value) + 1);
        $ta = $this->get_config_item_header();
        $ta .= '<textarea cols="18" rows="' . $rows . '" name="';
        $ta .= $this->get_config_item_name() . '" ' . $this->jscheck . '>';
        $ta .= $list_values . "</textarea>";
        $ta .= "<p id=\"" . $this->get_config_item_id() . "\" class=\"green\">Input accepted.</p>";
        return $ta;
    }
}

class boolean_define
    extends _define
{
    // adds ->values property, instead of ->default_value
    function __construct($config_item_name, $values = false, $description = '', $jscheck = '')
    {
        $this->config_item_name = $config_item_name;
        if (!$description) {
            $description = text_from_dist($config_item_name);
        }
        $this->description = $description;
        // TESTME: get boolean default value from config-default.ini
        if (defined($config_item_name)) {
            $this->default_value = constant($config_item_name); // ignore given default value
        } elseif (is_array($values)) {
            list($this->default_value, $dummy) = $values[""];
        }
        if (!$values) {
            $values = array('false' => "Disabled", 'true' => "Enabled");
        }
        $this->values = $values;
        $this->jscheck = $jscheck;
        $this->prefix = "";
    }

    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        return "$n" . $this->_config_format($posted_value);
    }

    function _config_format($value)
    {
        if (strtolower(trim($value)) == 'false')
            $value = false;
        return sprintf("%s = %s", $this->get_config_item_name(),
            $value ? 'true' : 'false');
    }

    //TODO: radiobuttons, no list
    function get_html()
    {
        $output = $this->get_config_item_header();
        $name = $this->get_config_item_name();
        $output .= '<select name="' . $name . '" ' . $this->jscheck . ">\n";
        $values = $this->values;
        $default_value = $this->default_value ? 'true' : 'false';
        /* There can usually only be two options, there can be
         * three options in the case of a boolean_define_commented_optional */
        foreach ($values as $option => $label) {
            if (!is_null($this->default_value) and $option === $default_value)
                $output .= "  <option value=\"$option\" selected=\"selected\">$label</option>\n";
            else
                $output .= "  <option value=\"$option\">$label</option>\n";
        }
        $output .= "</select>\n";
        return $output;
    }
}

class boolean_define_optional
    extends boolean_define
{
}

class boolean_define_commented
    extends boolean_define
{
    function _get_config_line($posted_value)
    {
        $n = "";
        if ($this->description)
            $n = "\n";
        if (is_array($this->default_value)) {
            $default_value = key($this->default_value);
            next($this->default_value);
        } else {
            $default_value = $this->default_value;
        }
        if ($posted_value == $default_value)
            return "$n;" . $this->_config_format($posted_value);
        elseif ($posted_value == '')
            return "$n;" . $this->_config_format('false');
        else
            return "$n" . $this->_config_format($posted_value);
    }
}

class boolean_define_commented_optional
    extends boolean_define_commented
{
}

class part
    extends _variable
{
    function value()
    {
        return "";
    }

    function get_config($posted_value)
    {
        $d = stripHtml($this->_get_description());
        global $SEPARATOR;
        return "\n" . $SEPARATOR . str_replace("\n", "\n; ", $d) . "\n" . $this->default_value;
    }

    function get_instructions($title)
    {
        $id = preg_replace("/\W/", "", $this->config_item_name);
        $i = '<tr class="header" id="'.$id.'">'."\n";
        $i .= '<td class="part" style="width:100%;background-color:#eee;" colspan="2">'."\n";
        $i .= "<h2>" . $title . "</h2>\n    " . nl2p($this->_get_description()) . "\n";
        $i .= "<p><a href=\"javascript:toggle_group('$id')\" id=\"{$id}_text\">Hide options.</a></p>";
        return $i . "</td>\n";
    }

    function get_html()
    {
        return "";
    }
}

// HTML utility functions
function nl2p($text)
{
    preg_match_all("@\s*(<pre>.*?</pre>|<dl>.*?</dl>|.*?(?=\n\n|<pre>|<dl>|$))@s",
        $text, $m);

    $text = '';
    foreach ($m[1] as $par) {
        if (!($par = trim($par)))
            continue;
        if (!preg_match('/^<(pre|dl)>/', $par))
            $par = "<p>$par</p>";
        $text .= $par;
    }
    return $text;
}

function text_from_dist($var)
{
    static $distfile = 0;
    static $f;

    if (!$distfile) {
        $sep = (substr(PHP_OS, 0, 3) == 'WIN' ? '\\' : '/');
        $distfile = dirname(__FILE__) . $sep . "config" . $sep . "config-dist.ini";
        $f = fopen($distfile, "r");
    }
    if ($var == '_MAGIC_CLOSE_FILE') {
        fclose($f);
        return '';
    }
    // if all vars would be in natural order as in the config-dist this would not be needed.
    fseek($f, 0);
    $par = "\n";
    while (!feof($f)) {
        $s = fgets($f);
        if (preg_match("/^; \w/", $s)) {
            $par .= (substr($s, 2) . " ");
        } elseif (preg_match("/^;\s*$/", $s)) {
            $par .= "\n\n";
        }
        if (preg_match("/^;?" . preg_quote($var) . "\s*=/", $s))
            return $par;
        if (preg_match("/^\s*$/", $s)) // new paragraph
            $par = "\n";
    }
    return '';
}

function stripHtml($text)
{
    $d = str_replace("<pre>", "", $text);
    $d = str_replace("</pre>", "", $d);
    $d = str_replace("<dl>", "", $d);
    $d = str_replace("</dl>", "", $d);
    $d = str_replace("<dt>", "", $d);
    $d = str_replace("</dt>", "", $d);
    $d = str_replace("<dd>", "", $d);
    $d = str_replace("</dd>", "", $d);
    $d = str_replace("<p>", "", $d);
    $d = str_replace("</p>", "", $d);
    //restore HTML entities into characters
    // https://www.php.net/manual/en/function.htmlentities.php
    $trans = get_html_translation_table(HTML_ENTITIES);
    $trans = array_flip($trans);
    return strtr($d, $trans);
}

include_once(dirname(__FILE__) . "/lib/stdlib.php");

////
// Function to create better user passwords (much larger keyspace),
// suitable for user passwords.
// Sequence of random ASCII numbers, letters and some special chars.
// Note: There exist other algorithms for easy-to-remember passwords.
function random_good_password($minlength = 5, $maxlength = 8)
{
    $newpass = '';
    // assume ASCII ordering (not valid on EBCDIC systems!)
    $valid_chars = "!#%&+-.0123456789=@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz";
    $start = ord($valid_chars);
    $end = ord(substr($valid_chars, -1));
    $length = mt_rand($minlength, $maxlength);
    while ($length > 0) {
        $newchar = mt_rand($start, $end);
        if (!strrpos($valid_chars, $newchar)) continue; // skip holes
        $newpass .= sprintf("%c", $newchar);
        $length--;
    }
    return ($newpass);
}

// end of class definitions
/////////////////////////////
// begin auto generation code

if (!empty($HTTP_POST_VARS['action'])
    and $HTTP_POST_VARS['action'] == 'make_config'
        and !empty($HTTP_POST_VARS['ADMIN_USER'])
            and !empty($HTTP_POST_VARS['ADMIN_PASSWD'])
) {

    $timestamp = date('dS \of F, Y H:i:s');

    $config = "; This is a local configuration file for PhpWiki.\n"
            . "; It was automatically generated by the configurator script\n"
            . "; on the $timestamp.\n"
            .  $preamble;

    $posted = $GLOBALS['HTTP_POST_VARS'];

    foreach ($properties as $option_name => $a) {
        $posted_value = stripslashes($posted[$a->config_item_name]);
        $config .= $a->get_config($posted_value);
    }

    $config .= $end;

    if (is_writable($fs_config_file)) {
        // We first check if the config-file exists.
        if (file_exists($fs_config_file)) {
            // We make a backup copy of the file
            $new_filename = preg_replace('/\.ini$/', '-' . time() . '.ini', $fs_config_file);
            if (@copy($fs_config_file, $new_filename)) {
                $fp = @fopen($fs_config_file, 'w');
            }
        } else {
            $fp = @fopen($fs_config_file, 'w');
        }
    } else {
        $fp = false;
    }

    if ($fp) {
        fputs($fp, utf8_encode($config));
        fclose($fp);
        echo "<p>The configuration was written to <code><b>$config_file</b></code>.</p>\n";
        if ($new_filename) {
            echo "<p>A backup was made to <code><b>$new_filename</b></code>.</p>\n";
        }
    } else {
        echo "<p>The configuration file could <b>not</b> be written.<br />\n",
        "You should copy the above configuration to a file, ",
        "and manually save it as <code><b>config/config.ini</b></code>.</p>\n";
    }

    echo "<hr />\n<p>Here's the configuration file based on your answers:</p>\n";
    echo "<form method=\"get\" action=\"", $configurator, "\">\n";
    echo "<textarea id='config-output' readonly='readonly' style='width:100%;' rows='30' cols='100'>\n";
    echo htmlentities($config, ENT_COMPAT, "UTF-8");
    echo "</textarea></form>\n";
    echo "<hr />\n";

    echo "<p>To make any corrections, <a href=\"configurator.php\">edit the settings again</a>.</p>\n";

} else { // first time or create password
    $posted = $GLOBALS['HTTP_POST_VARS'];
    // No action has been specified - we make a form.

    if (!empty($GLOBALS['HTTP_GET_VARS']['start_debug']))
        $configurator .= ("?start_debug=" . $GLOBALS['HTTP_GET_VARS']['start_debug']);
    echo '
<form action="', $configurator, '" method="post">
<input type="hidden" name="action" value="make_config" />
<table>
';

    foreach ($properties as $property => $obj) {
        echo $obj->get_instructions($property);
        if ($h = $obj->get_html()) {
            echo "<td>" . $h . "</td>\n";
        }
        echo '</tr>';
    }

    echo '
</table>
<p><input type="submit" id="submit" value="Save ', $config_file, '" /> <input type="reset" value="Clear" /></p>
</form>
';
}
?>
</body>
</html>
