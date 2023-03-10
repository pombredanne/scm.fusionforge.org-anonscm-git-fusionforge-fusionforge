<?php
/**
 * Copyright © 1999,2000,2001,2002,2004 $ThePhpWikiProgrammingTeam
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

// Moved to IniConfig and config-default.ini
// Define ENABLE_RAW_HTML to false (in config.ini) to disable the RawHtml
// plugin completely
/*
if (!defined('ENABLE_RAW_HTML'))
    define('ENABLE_RAW_HTML', true);
// must be locked
if (!defined('ENABLE_RAW_HTML_LOCKEDONLY'))
    define('ENABLE_RAW_HTML_LOCKEDONLY', true);
// sanitize to safe html code
if (!defined('ENABLE_RAW_HTML_SAFE'))
    define('ENABLE_RAW_HTML_SAFE', true);
*/

/** We defined a better policy when to allow RawHtml:
 *   ENABLE_RAW_HTML_LOCKEDONLY:
 *  - Allowed if page is locked by ADMIN_USER.
 *   ENABLE_RAW_HTML_SAFE:
 *  - Allow some sort of "safe" html tags and attributes.
 *    Unsafe attributes are automatically stripped. (Experimental!)
 */

/**
 * Provide for raw HTML within wiki pages.
 */

class WikiPlugin_RawHtml
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Provide for raw HTML within wiki pages.");
    }

    function getDefaultArguments()
    {
        return array();
    }

    function managesValidators()
    {
        // The plugin output will only change if the plugin
        // invocation (page text) changes --- so the necessary
        // validators have already been handled by displayPage.
        return true;
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        if (!defined('ENABLE_RAW_HTML') || !ENABLE_RAW_HTML) {
            return $this->disabled(_("Raw HTML is disabled in this wiki."));
        }
        if (!$basepage) {
            return $this->error("$basepage unset?");
        }

        $page = $request->getPage($basepage);
        if (ENABLE_RAW_HTML_LOCKEDONLY) {
            if (!$page->get('locked')) {
                return $this->disabled(fmt("%s is only allowed in locked pages.",
                    _("Raw HTML")));
            }
        }
        if (ENABLE_RAW_HTML_SAFE) {
            // check for javascript handlers (on*) and style tags with external urls. no javascript urls.
            // See also http://simon.incutio.com/archive/2003/02/23/safeHtmlChecker
            // But we should allow not only code semantic meaning,  presentational markup also.

            // http://chxo.com/scripts/safe_html-test.php looks better
            $argstr = $this->safe_html($argstr);
        }

        return HTML::raw($argstr);
    }

    // From http://chxo.com/scripts/safe_html-test.php
    // safe_html by Chris Snyder (csnyder@chxo.com) for http://pcoms.net
    //   - Huge thanks to James Wetterau for testing and feedback!

    /*
    Copyright © 2003 Chris Snyder. All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

       1. Redistributions of source code must retain the above copyright
       notice, this list of conditions and the following disclaimer.

       2. Redistributions in binary form must reproduce the above
       copyright notice, this list of conditions and the following
       disclaimer in the documentation and/or other materials provided
       with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
    FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
    SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
    PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;  LOSS OF USE, DATA, OR PROFITS;
    OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
    WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
    OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
    ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
    */

    /*
     set of functions for sanitizing user input:
        keeps "friendly" tags but strips javascript events and style attributes
        closes any open comment tags
        closes any open HTML tags - results may not be valid HTML, but
            at least they will keep the rest of the page from breaking

        treats the following as malicious conditions and returns text stripped
        of all html tags:
            any instances of ='javascript:
            event or style attributes remaining after initial replacement
    */

    function strip_attributes($html, $attrs)
    {
        if (!is_array($attrs)) {
            $array = array("$attrs");
            unset($attrs);
            $attrs = $array;
        }

        foreach ($attrs AS $attribute) {
            // once for ", once for ', s makes the dot match linebreaks, too.
            $search[] = "/" . $attribute . '\s*=\s*".+"/Uis';
            $search[] = "/" . $attribute . "\s*=\s*'.+'/Uis";
            // and once more for unquoted attributes
            $search[] = "/" . $attribute . "\s*=\s*\S+/i";
        }
        $html = preg_replace($search, "", $html);

        // check for additional matches and strip all tags if found
        foreach ($search AS $pattern) {
            if (preg_match($pattern, $html)) {
                $html = strip_tags($html);
                break;
            }
        }

        return $html;
    }

    function safe_html($html, $allowedtags = "")
    {
        $version = "safe_html.php/0.4";

        // anything with ="javascript: is right out -- strip all tags and return if found
        $pattern = "/=\s*\S+script:\S+/Ui";
        if (preg_match($pattern, $html)) {
            $html = strip_tags($html);
            return $html;
        }

        // setup -- $allowedtags is an array of $tag=>$closeit pairs, where $tag is an HTML tag to allow and $closeit is 1 if the tag requires a matching, closing tag
        if ($allowedtags == "") {
            $allowedtags = array("p" => 1, "br" => 0, "a" => 1, "img" => 0, "li" => 1,
                "ol" => 1, "ul" => 1, "b" => 1, "i" => 1, "em" => 1, "strong" => 1, "del" => 1, "ins" => 1,
                "sub" => 1, "sup" => 1, "u" => 1, "blockquote" => 1, "pre" => 1, "hr" => 0,
                "table" => 1, "thead" => 1, "tfoot" => 1, "tbody" => 1, "tr" => 1, "td" => 1, "th" => 1,
            );
        } elseif (!is_array($allowedtags)) {
            $array = array("$allowedtags");
            unset($allowedtags);
            $allowedtags = $array;
        }

        // there's some debate about this.. is strip_tags() better than rolling your own regex?
        // note: a bug in PHP 4.3.1 caused improper handling of ! in tag attributes when using strip_tags()
        $stripallowed = "";
        foreach ($allowedtags AS $tag => $closeit) {
            $stripallowed .= "<$tag>";
        }

        //print "Stripallowed: $stripallowed -- ".print_r($allowedtags,1);
        $html = strip_tags($html, $stripallowed);

        // also, lets get rid of some pesky attributes that may be set on the remaining tags...
        $badattrs = array("on\w+", "style");
        $html = $this->strip_attributes($html, $badattrs);

        // close html tags if necessary -- note that this WON'T be graceful formatting-wise, it just has to fix any maliciousness
        foreach ($allowedtags AS $tag => $closeit) {
            if (!$closeit) continue;
            $patternopen = "/<$tag\b[^>]*>/Ui";
            $patternclose = "/<\/$tag\b[^>]*>/Ui";
            $totalopen = preg_match_all($patternopen, $html, $matches);
            $totalclose = preg_match_all($patternclose, $html, $matches2);
            if ($totalopen > $totalclose) {
                $html .= str_repeat("</$tag>", ($totalopen - $totalclose));
            }
        }

        // close any open <!--'s and identify version just in case
        $html .= "<!-- $version -->";
        return $html;
    }
}
