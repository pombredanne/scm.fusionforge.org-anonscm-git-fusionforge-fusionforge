<?php
/**
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2001-2003 Carsten Klapp
 * Copyright © 2002,2004-2005,2007,2010 Reini Urban
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

require_once(dirname(__FILE__) . '/stdlib.php');

/**
 * A class for finding files.
 *
 * This should really provided by pear. We don't want really to mess around
 * with all the lousy systems. (WindowsNT, Win95, Mac, ...)
 * But pear has only System and File, which do nothing.
 * Anyway, in good PHP style we ignore the rest of the world and try to behave
 * as on unix only. That means we use / as pathsep in all our constants.
 */
class FileFinder
{
    public $_pathsep, $_path;

    /**
     * @param $path array A list of directories in which to search for files.
     */
    function __construct($path = array())
    {
        $this->_pathsep = $this->_get_syspath_separator();
        if (!isset($this->_path) and $path === false)
            $path = $this->_get_include_path();
        $this->_path = $path;
    }

    /**
     * Find file.
     *
     * @param $file string File to search for.
     * @param bool $missing_okay
     * @return string The filename (including path), if found, otherwise false.
     */
    public function findFile($file, $missing_okay = false)
    {
        if ($this->_is_abs($file)) {
            if (file_exists($file))
                return $file;
        } elseif (($dir = $this->_search_path($file))) {
            return $dir . $this->_use_path_separator($dir) . $file;
        }
        return $missing_okay ? false : $this->_not_found($file);
    }

    /**
     * Unify used pathsep character.
     * Accepts array of paths also.
     * This might not work on Windows95 or FAT volumes. (not tested)
     *
     * @param string $path
     * @return array|string
     */
    public function slashifyPath($path)
    {
        return $this->forcePathSlashes($path, $this->_pathsep);
    }

    /**
     * Force using '/' as path separator.
     *
     * @param string $path
     * @param string $sep
     * @return array|string
     */
    public function forcePathSlashes($path, $sep = '/')
    {
        if (is_array($path)) {
            $result = array();
            foreach ($path as $dir) {
                $result[] = $this->forcePathSlashes($dir, $sep);
            }
            return $result;
        } else {
            if (isWindows() or $this->_isOtherPathsep()) {
                if (isWindows()) $from = "\\";
                else $from = "\\";
                // PHP is stupid enough to use \\ instead of \
                if (isWindows()) {
                    if (substr($path, 0, 2) != '\\\\')
                        $path = str_replace('\\\\', '\\', $path);
                    else // UNC paths
                        $path = '\\\\' . str_replace('\\\\', '\\', substr($path, 2));
                }
                return strtr($path, $from, $sep);
            } else
                return $path;
        }
    }

    private function _isOtherPathsep()
    {
        return $this->_pathsep != '/';
    }

    /**
     * The system-dependent path-separator character.
     * UNIX,WindowsNT,MacOSX: /
     * Windows95: \
     * Mac:       :
     *
     * @return string path_separator.
     */
    public function _get_syspath_separator()
    {
        if (!empty($this->_pathsep)) return $this->_pathsep;
        elseif (isWindowsNT()) return "/"; // we can safely use '/'
        elseif (isWindows()) return "\\"; // FAT might use '\'
        // VMS or LispM is really weird, we ignore it.
        else return '/';
    }

    /**
     * The path-separator character of the given path.
     * Windows accepts "/" also, but gets confused with mixed path_separators,
     * e.g "C:\Apache\phpwiki/locale/button"
     * > dir "C:\Apache\phpwiki/locale/button" =>
     *       Parameterformat nicht korrekt - "locale"
     * So if there's any '\' in the path, either fix them to '/' (not in Win95 or FAT?)
     * or use '\' for ours.
     *
     * @param string $path
     * @return string path_separator.
     */
    public function _use_path_separator($path)
    {
        if (isWindows95()) {
            if (empty($path)) return "\\";
            else return (strchr($path, "\\")) ? "\\" : '/';
        } else {
            return $this->_get_syspath_separator();
        }
    }

    /**
     * Determine if path is absolute.
     *
     * @param $path string Path.
     * @return bool True if path is absolute.
     */
    public function _is_abs($path)
    {
        if (substr($path, 0, 1) == '/') {
            return true;
        } elseif (isWindows() and preg_match("/^[a-z]:/i", $path)
            and (substr($path, 2, 1) == "/" or substr($path, 2, 1) == "\\")
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Strip ending '/' or '\' from path.
     *
     * @param $path string Path.
     * @return bool New path (destructive)
     */
    public function _strip_last_pathchar(&$path)
    {
        if (substr($path, -1) == '/' or substr($path, -1) == "\\")
            $path = substr($path, 0, -1);
        return $path;
    }

    /**
     * Report a "file not found" error.
     *
     * @param $file string Name of missing file.
     * @return bool false.
     */
    private function _not_found($file)
    {
        trigger_error(sprintf(_("File “%s” not found."), $file), E_USER_ERROR);
        return false;
    }

    /**
     * Search our path for a file.
     *
     * @param $file string File to find.
     * @return string Directory which contains $file, or false.
     * [5x,44ms]
     */
    private function _search_path($file)
    {
        foreach ($this->_path as $dir) {
            // ensure we use the same pathsep
            if ($this->_isOtherPathsep()) {
                $dir = $this->slashifyPath($dir);
                $file = $this->slashifyPath($file);
                if (file_exists($dir . $this->_pathsep . $file))
                    return $dir;
            } elseif (@file_exists($dir . $this->_pathsep . $file))
                return $dir;
        }
        return false;
    }

    /**
     * The system-dependent path-separator character. On UNIX systems,
     * this character is ':'; on Win32 systems it is ';'.
     *
     * @return string path_separator.
     */
    public function _get_ini_separator()
    {
        return isWindows() ? ';' : ':';
    }

    /**
     * Get the value of PHP's include_path.
     *
     * @return array Include path.
     */
    public function _get_include_path()
    {
        if (defined("INCLUDE_PATH"))
            $path = INCLUDE_PATH;
        else {
            $path = @get_cfg_var('include_path'); // FIXME: report warning
            if (empty($path)) $path = @ini_get('include_path');
        }
        if (empty($path))
            $path = '.';
        return explode($this->_get_ini_separator(), $this->slashifyPath($path));
    }

    /**
     * Add a directory to the end of PHP's include_path.
     *
     * The directory is appended only if it is not already listed in
     * the include_path.
     *
     * @param $dir string Directory to add.
     */
    public function _append_to_include_path($dir)
    {
        $dir = $this->slashifyPath($dir);
        if (!in_array($dir, $this->_path)) {
            $this->_path[] = $dir;
        }
        /*
         * Some (buggy) PHP's (notable SourceForge's PHP 4.0.6)
         * sometimes don't seem to heed their include_path.
         * I.e. sometimes a file is not found even though it seems to
         * be in the current include_path. A simple
         * ini_set('include_path', ini_get('include_path')) seems to
         * be enough to fix the problem
         *
         * This following line should be in the above if-block, but we
         * put it here, as it seems to work-around the bug.
         */
        $GLOBALS['INCLUDE_PATH'] = implode($this->_get_ini_separator(), $this->_path);
        @ini_set('include_path', $GLOBALS['INCLUDE_PATH']);
    }

    /**
     * Add a directory to the front of PHP's include_path.
     *
     * The directory is prepended, and removed from the tail if already existing.
     *
     * @param $dir string Directory to add.
     */
    public function _prepend_to_include_path($dir)
    {
        $dir = $this->slashifyPath($dir);
        // remove duplicates
        if ($i = array_search($dir, $this->_path) !== false) {
            array_splice($this->_path, $i, 1);
        }
        array_unshift($this->_path, $dir);
        $GLOBALS['INCLUDE_PATH'] = implode($this->_get_ini_separator(), $this->_path);
        @ini_set('include_path', $GLOBALS['INCLUDE_PATH']);
    }

    // Return all the possible shortened locale specifiers for the given locale.
    // Most specific first.
    // de_DE.iso8859-1@euro => de_DE.iso8859-1, de_DE, de
    // This code might needed somewhere else also.
    function locale_versions($lang)
    {
        // Try less specific versions of the locale
        $langs[] = $lang;
        foreach (array('@', '.', '_') as $sep) {
            if (($tail = strchr($lang, $sep)))
                $langs[] = substr($lang, 0, -strlen($tail));
        }
        return $langs;
    }

    /**
     * Try to figure out the appropriate value for $LANG.
     *
     * @return string The value of $LANG.
     */
    public static function _get_lang()
    {
        if (!empty($GLOBALS['LANG']))
            return $GLOBALS['LANG'];

        foreach (array('LC_ALL', 'LC_MESSAGES', 'LC_RESPONSES') as $var) {
            $lang = setlocale(constant($var), 0);
            if (!empty($lang))
                return $lang;
        }

        foreach (array('LC_ALL', 'LC_MESSAGES', 'LC_RESPONSES', 'LANG') as $var) {
            $lang = getenv($var);
            if (!empty($lang))
                return $lang;
        }

        return "C";
    }
}

/**
 * Find PhpWiki localized files.
 *
 * This is a subclass of FileFinder which searches PHP's include_path
 * for files. It looks first for "locale/$LANG/$file", then for
 * "$file".
 *
 * If $LANG is something like "de_DE.iso8859-1@euro", this class will
 * also search under various less specific variations like
 * "de_DE.iso8859-1", "de_DE" and "de".
 */
class LocalizedFileFinder
    extends FileFinder
{
    function __construct()
    {
        $this->_pathsep = $this->_get_syspath_separator();
        $include_path = $this->_get_include_path();
        $path = array();

        $lang = $this->_get_lang();
        assert(!empty($lang));

        if ($locales = $this->locale_versions($lang)) {
            foreach ($locales as $lang) {
                if ($lang == 'C') $lang = 'en';
                foreach ($include_path as $dir) {
                    $path[] = $this->slashifyPath($dir . "/locale/$lang");
                }
            }
        }
        parent::__construct(array_merge($path, $include_path));
    }
}

/**
 * Find PhpWiki localized theme buttons.
 *
 * This is a subclass of FileFinder which searches PHP's include_path
 * for files. It looks first for "buttons/$LANG/$file", then for
 * "$file".
 *
 * If $LANG is something like "de_DE.iso8859-1@euro", this class will
 * also search under various less specific variations like
 * "de_DE.iso8859-1", "de_DE" and "de".
 */
class LocalizedButtonFinder
    extends FileFinder
{
    function __construct()
    {
        global $WikiTheme;
        $this->_pathsep = $this->_get_syspath_separator();
        $include_path = $this->_get_include_path();
        $path = array();

        $lang = $this->_get_lang();
        assert(!empty($lang));
        assert(!empty($WikiTheme));

        if (is_object($WikiTheme)) {
            $langs = $this->locale_versions($lang);
            foreach ($langs as $lang) {
                if ($lang == 'C') $lang = 'en';
                foreach ($include_path as $dir) {
                    $path[] = $this->slashifyPath($WikiTheme->file("buttons/$lang"));
                }
            }
        }

        parent::__construct(array_merge($path, $include_path));
    }
}

// Search PHP's include_path to find file or directory.
function findFile($file, $missing_okay = false, $slashify = false)
{
    static $finder;
    if (!isset($finder)) {
        $finder = new FileFinder();
        // remove "/lib" from dirname(__FILE__)
        $wikidir = preg_replace('/.lib$/', '', dirname(__FILE__));
        // let the system favor its local pear?
        $finder->_append_to_include_path(dirname(__FILE__) . "/pear");
        $finder->_prepend_to_include_path($wikidir);
        // Don't override existing INCLUDE_PATH config.
        if (!defined("INCLUDE_PATH"))
            define("INCLUDE_PATH", implode($finder->_get_ini_separator(), $finder->_path));
    }
    $s = $finder->findFile($file, $missing_okay);
    if ($slashify)
        $s = $finder->slashifyPath($s);
    return $s;
}

// Search PHP's include_path to find file or directory.
// Searches for "locale/$LANG/$file", then for "$file".
function findLocalizedFile($file, $missing_okay = false, $re_init = false)
{
    static $finder;
    if ($re_init or !isset($finder))
        $finder = new LocalizedFileFinder();
    return $finder->findFile($file, $missing_okay);
}

function findLocalizedButtonFile($file, $missing_okay = false, $re_init = false)
{
    static $buttonfinder;
    if ($re_init or !isset($buttonfinder))
        $buttonfinder = new LocalizedButtonFinder();
    return $buttonfinder->findFile($file, $missing_okay);
}

/**
 * Prefixes with PHPWIKI_DIR and slashify.
 * For example to unify with
 *   require_once dirname(__FILE__).'/lib/file.php'
 *   require_once 'lib/file.php' loading style.
 * Doesn't expand "~" or symlinks yet. truename would be perfect.
 *
 * normalizeLocalFileName("lib/config.php") => /home/user/phpwiki/lib/config.php
 */
function normalizeLocalFileName($file)
{
    static $finder;
    if (!isset($finder)) {
        $finder = new FileFinder();
    }
    // remove "/lib" from dirname(__FILE__)
    if ($finder->_is_abs($file))
        return $finder->slashifyPath($file);
    else {
        if (defined("PHPWIKI_DIR")) $wikidir = PHPWIKI_DIR;
        else $wikidir = preg_replace('/.lib$/', '', dirname(__FILE__));
        $wikidir = $finder->_strip_last_pathchar($wikidir);
        $pathsep = $finder->_use_path_separator($wikidir);
        return $finder->slashifyPath($wikidir . $pathsep . $file);
        // return PHPWIKI_DIR . "/" . $file;
    }
}

/**
 * Prefixes with DATA_PATH and slashify
 */
function normalizeWebFileName($file)
{
    static $finder;
    if (!isset($finder)) {
        $finder = new FileFinder();
    }
    if (defined("DATA_PATH")) {
        $wikipath = DATA_PATH;
        $wikipath = $finder->_strip_last_pathchar($wikipath);
        if (!$file)
            return $finder->forcePathSlashes($wikipath);
        else
            return $finder->forcePathSlashes($wikipath . '/' . $file);
    } else {
        return $finder->forcePathSlashes($file);
    }
}

function isWindows()
{
    static $win;
    if (isset($win)) return $win;
    return (substr(PHP_OS, 0, 3) == 'WIN');
}

function isWindows95()
{
    static $win95;
    if (isset($win95)) return $win95;
    $win95 = isWindows() and !isWindowsNT();
    return $win95;
}

function isWindowsNT()
{
    static $winnt;
    if (isset($winnt)) return $winnt;
    // FIXME: Do this using PHP_OS instead of php_uname().
    // $winnt = (PHP_OS == "WINNT"); // example from https://www.php.net/manual/en/ref.readline.php
    if (function_usable('php_uname'))
        $winnt = preg_match('/^Windows NT/', php_uname());
    else
        $winnt = false; // FIXME: punt.
    return $winnt;
}
