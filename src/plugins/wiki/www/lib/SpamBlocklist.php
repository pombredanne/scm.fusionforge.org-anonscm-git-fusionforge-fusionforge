<?php
/**
 * Copyright © 2005 Reini Urban
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
 *
 */

/*
 * See http://www.surbl.org/
 *
 * Perform a name lookup (A) for any link tld against multi.surbl.org and bl.spamcop.net,
 * like: domainundertest.com.multi.surbl.org
 *     or 40.30.20.10.multi.surbl.org (for http://10.20.30.40/)
 * This is the same, but a bit lighter than PEAR::Net_DNSBL_SURBL
 */

/**
 * Strip domain prefixes so that either the last two name parts are returned,
 * or if it's a known tld (like "co.uk") the last three.
 *
 * @param string $host
 * @return string
 */
function stripDomainPrefixes($host)
{
    static $twoleveltlds = array();
    $host_elements = explode('.', $host);
    while (count($host_elements) > 3) {
        array_shift($host_elements);
    }
    $host_3_elements = implode('.', $host_elements);
    if (count($host_elements) > 2) {
        array_shift($host_elements);
    }
    $host_2_elements = implode('.', $host_elements);
    if (empty($twoleveltlds)) {
        $data = @file(dirname(__FILE__) . "/../config/two-level-tlds");
        $twoleveltlds = $data ? array_flip($data) : array();
    }
    if (array_key_exists($host_2_elements, $twoleveltlds))
        //IS_IN_2LEVEL: we want the last three names
        $host = $host_3_elements;
    else
        // IS_NOT_2LEVEL: we want the last two names
        $host = $host_2_elements;
    return $host;
}

/**
 * @param string $uri
 * @return int
 */

function IsBlackListed($uri)
{
    static $blacklists = array("multi.surbl.org", "bl.spamcop.net");
    /* "sbl-xbl.spamhaus.net" */
    static $whitelist = array();
    if (empty($whitelist)) { // list of domains
        $data = @file(dirname(__FILE__) . "/../config/whitelist");
        $whitelist = $data ? array_flip($data) : array();
    }

    $parsed_uri = parse_url($uri);
    if (!empty($parsed_uri['host']))
        $host = $parsed_uri['host'];
    else
        $host = $parsed_uri['path'];
    if (array_key_exists($host, $whitelist))
        return 0;
    if (preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $host)) {
        $host = implode('.', array_reverse(explode('.', $host)));
        $revip = 1;
    } else {
        $revip = 0;
    }
    foreach ($blacklists as $bl) {
        if (!$revip and $bl == "multi.surbl.org") {
            $host = stripDomainPrefixes($host); // strip domain prefixes
            if (array_key_exists($host, $whitelist))
                return 0;
        } elseif (!$revip) {
            // convert to IP addr and revert it.
            $host = implode('.', array_reverse(explode('.', gethostbyname($host))));
        }
        //echo "($host.$bl)";
        $res = gethostbyname($host . "." . $bl);
        if (preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $res))
            return array($bl, $res, $host);
    }
    return 0;
}
