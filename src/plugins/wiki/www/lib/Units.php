<?php
/**
 * Copyright © 2007 Reini Urban
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
 *
 * Interface to man units(1), /usr/share/units.dat
 *
 * $ units "372.0 mi2"
 *         Definition: 9.6347558e+08 m^2
 * $ units "372.0 mi2" m^2
 *         Definition: 9.6347558e+08 m^2
 *
 * Called by:
 *    CachedMarkup.php: Cached_SemanticLink::_expandurl()
 *    SemanticWeb.php: class SemanticAttributeSearchQuery
 *
 * Windows requires the cygwin /usr/bin/units.
 * All successfully parsed unit definitions are stored in the wikidb,
 * so that subsequent expansions will not require /usr/bin/units be called again.
 * So far even on Windows (cygwin) the process is fast enough.
 *
 * TODO: understand dates and maybe times
 *   YYYY-MM-DD, "CW"ww/yy (CalendarWeek)
 */

class Units
{
    function __construct()
    {
        if (defined('DISABLE_UNITS') and DISABLE_UNITS)
            $this->errcode = 1;
        elseif (defined("UNITS_EXE")) // ignore dynamic check
            $this->errcode = 0;
        else
            exec("units m2", $o, $this->errcode);
    }

    /**
     * $this->_attribute_base = $units->Definition($this->_attribute);
     *
     * @param string $query
     * @return string
     */
    function Definition($query)
    {
        static $Definitions = array();
        if (isset($Definitions[$query])) return $Definitions[$query];
        if ($this->errcode)
            return $query;
        $query = preg_replace("/,/", "", $query);
        if ($query == '' or $query == '*')
            return ($Definitions[$query] = '');
        // detect date values, currently only ISO: YYYY-MM-DD or YY-MM-DD
        if (preg_match("/^(\d{2,4})-(\d{1,2})-(\d{1,2})$/", $query, $m)) {
            $date = mktime(0, 0, 0, $m[2], $m[3], $m[1]);
            return ($Definitions[$query] = "$date date");
        }
        if (preg_match("/^(\d{2,4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{2}):?(\d{2})?$/", $query, $m)) {
            $date = mktime($m[4], $m[5], @$m[6], $m[2], $m[3], $m[1]);
            return ($Definitions[$query] = "$date date");
        }
        $def = $this->_cmd("\"$query\"");
        if (preg_match("/Definition: (.+)$/", $def, $m))
            return ($Definitions[$query] = $m[1]);
        else {
            trigger_error("units: " . $def, E_USER_WARNING);
            return '';
        }
    }

    /**
     * We must ensure that the same baseunits are matched against.
     * We cannot compare m^2 to m or ''
     * $val_base = $this->_units->basevalue($value); // SemanticAttributeSearchQuery
     *
     * @param string $query
     * @param bool $def
     * @return bool|string
     */
    function basevalue($query, $def = false)
    {
        if (!$def)
            $def = $this->Definition($query);
        if ($def) {
            if (is_numeric($def)) // e.g. "1 million"
                return $def;
            if (preg_match("/^([-0-9].*) \w.*$/", $def, $m))
                return $m[1];
        }
        return '';
    }

    /**
     * $this->_unit = $units->baseunit($this->_attribute);  // SemanticAttributeSearchQuery
     * and Cached_SemanticLink::_expandurl()
     *
     * @param string $query
     * @param bool $def
     * @return string
     */
    function baseunit($query, $def = false)
    {
        if (!$def)
            $def = $this->Definition($query);
        if ($def) {
            if (preg_match("/ (.+)$/", $def, $m))
                return $m[1];
        }
        return '';
    }

    private function _cmd($args)
    {
        if ($this->errcode)
            return $args;
        if (defined("UNITS_EXE")) {
            $s = UNITS_EXE . " $args";
            $result = `$s`;
        } else
            $result = `units $args`;
        return trim($result);
    }
}
