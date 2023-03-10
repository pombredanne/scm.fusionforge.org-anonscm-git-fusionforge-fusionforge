<?php
/**
 * Copyright © 1999,2000,2001,2002,2003,2004,2005,2006 $ThePhpWikiProgrammingTeam
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

require_once 'lib/CachedMarkup.php';

/** A cacheable formatted wiki page.
 */
class TransformedText extends CacheableMarkup
{
    /**
     * @param WikiDB_Page $page
     * @param string      $text          The packed page revision content.
     * @param array       $meta          The version meta-data.
     * @param string      $type_override For markup of page using a different
     *        pagetype than that specified in its version meta-data.
     */
    function __construct($page, $text, $meta, $type_override = '')
    {
        $pagetype = false;
        if ($type_override)
            $pagetype = $type_override;
        elseif (isset($meta['pagetype']))
            $pagetype = $meta['pagetype'];
        $this->_type = PageType::GetPageType($pagetype);
        parent::__construct($this->_type->transform($page, $text, $meta),
                            $page->getName());
    }

    function getType()
    {
        return $this->_type;
    }
}

/**
 * A page type descriptor.
 *
 * Encapsulate information about page types.
 *
 * Currently the only information encapsulated is how to format
 * the specific page type.  In the future or capabilities may be
 * added, e.g. the abilities to edit different page types (differently.)
 * e.g. Support for the javascript htmlarea editor, which can only edit
 * pure HTML.
 *
 * IMPORTANT NOTE: Since the whole PageType class gets stored (serialized)
 * as of the cached marked-up page, it is important that the PageType classes
 * not have large amounts of class data.  (No class data is even better.)
 */
abstract class PageType
{
    /**
     * Get a page type descriptor.
     *
     * @param  string   $name Name of the page type.
     * @return PageType An object which is a subclass of PageType.
     */
    static function GetPageType($name = '')
    {
        if (!$name)
            $name = 'wikitext';
        $class = "PageType_" . (string)$name;
        if (class_exists($class))
            return new $class;
        trigger_error(sprintf("PageType “%s” unknown", (string)$name),
            E_USER_WARNING);
        return new PageType_wikitext;
    }

    /**
     * Get the name of this page type.
     *
     * @return string Page type name.
     */
    function getName()
    {
        if (!preg_match('/^PageType_(.+)$/i', get_class($this), $m))
            trigger_error("Bad class name for formatter(?)", E_USER_ERROR);
        return $m[1];
    }

    /**
     * Transform page text.
     *
     * @param  WikiDB_Page $page
     * @param  string      $text
     * @param  array       $meta Version meta-data
     * @return XmlContent  The transformed page text.
     */
    function transform(&$page, &$text, $meta)
    {
        $fmt_class = 'PageFormatter_' . $this->getName();
        $formatter = new $fmt_class($page, $meta);
        return $formatter->format($text);
    }
}

class PageType_wikitext extends PageType
{
}

class PageType_html extends PageType
{
}

class PageType_wikiblog extends PageType
{
}

class PageType_comment extends PageType
{
}

class PageType_wikiforum extends PageType
{
}

class PageType_MediaWiki extends PageType
{
}

/* To prevent from PHP5 Fatal error: Using $this when not in object context */
function getInterwikiMap($pagetext = false, $force = false)
{
    static $map;
    if (empty($map) or $force)
        $map = new PageType_interwikimap($pagetext);
    return $map;
}

class PageType_interwikimap extends PageType
{
    function __construct($pagetext = false)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (!$pagetext) {
            $dbi = $request->getDbh();
            $page = $dbi->getPage(__("InterWikiMap"));
            if ($page->get('locked')) {
                $current = $page->getCurrentRevision();
                $pagetext = $current->getPackedContent();
                $intermap = $this->_getMapFromWikiText($pagetext);
            } elseif ($page->exists()) {
                trigger_error(_("WARNING: InterWikiMap page is unlocked, so not using those links."));
                $intermap = false;
            } else
                $intermap = false;
        } else {
            $intermap = $this->_getMapFromWikiText($pagetext);
        }
        if (!$intermap && defined('INTERWIKI_MAP_FILE'))
            $intermap = $this->_getMapFromFile(INTERWIKI_MAP_FILE);

        $this->_map = $this->_parseMap($intermap);
        $this->_regexp = $this->_getRegexp();
    }

    function GetMap($pagetext = false)
    {
        /*PHP5 Fatal error: Using $this when not in object context */
        if (empty($this->_map)) {
            $map = new PageType_interwikimap($pagetext);
            return $map;
        } else {
            return $this;
        }
    }

    function getRegexp()
    {
        return $this->_regexp;
    }

    function link($link, $linktext = false)
    {
        global $WikiTheme;
        list ($moniker, $page) = explode(":", $link, 2);

        if (!isset($this->_map[$moniker])) {
            return HTML::span(array('class' => 'bad-interwiki'),
                $linktext ? $linktext : $link);
        }

        $url = $this->_map[$moniker];
        // localize Upload:links for WIKIDUMP
        if (!empty($WikiTheme->DUMP_MODE) and $moniker == 'Upload') {
            global $request;
            include_once 'lib/config.php';
            $url = getUploadFilePath();
            // calculate to a relative local path to /uploads for PDF images.
            $doc_root = $request->get("DOCUMENT_ROOT");
            $ldir = normalizeLocalFileName($url);
            $wikiroot = normalizeLocalFileName('');
            if (isWindows()) {
                $ldir = strtolower($ldir);
                $doc_root = strtolower($doc_root);
                $wikiroot = strtolower($wikiroot);
            }
            if (string_starts_with($ldir, $doc_root)) {
                $link_prefix = substr($url, strlen($doc_root));
            } elseif (string_starts_with($ldir, $wikiroot)) {
                $link_prefix = normalizeWebFileName(substr($url, strlen($wikiroot)));
            }
        }

        // Urlencode page only if it's a query arg.
        // FIXME: this is a somewhat broken heuristic.
        if ($moniker == 'Upload') {
            $page_enc = $page;
            $page = rawurldecode($page);
        } else {
            $page_enc = strstr($url, '?') ? rawurlencode($page) : $page;
        }
        if (strstr($url, '%s'))
            $url = sprintf($url, $page_enc);
        else
            $url .= $page_enc;

        // Encode spaces in '[[Help:Reini Urban]]'
        // but not in '[[Upload:logo.jpg size=40x25 align=center]]'
        if ($moniker != 'Upload') {
            $url = str_replace(' ', '%20', $url);
        }

        $link = HTML::a(array('href' => $url));

        if (!$linktext) {
            $link->pushContent(PossiblyGlueIconToText('interwiki', "$moniker:"),
                HTML::span(array('class' => 'wikipage'), $page));
            $link->setAttr('class', 'interwiki');
        } else {
            $link->pushContent(PossiblyGlueIconToText('interwiki', $linktext));
            $link->setAttr('class', 'named-interwiki');
        }

        return $link;
    }

    private function _parseMap($text)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        if (!preg_match_all("/^\s*(\S+)\s+(.+)$/m",
            $text, $matches, PREG_SET_ORDER)
        )
            return false;

        foreach ($matches as $m) {
            $map[$m[1]] = $m[2];
        }

        // Add virtual monikers: "Upload:" "Talk:" "User:", ":"
        // and expand special variables %u, %b, %d

        // Upload: Should be expanded later to user-specific upload dirs.
        // In the Upload plugin, not here: Upload:ReiniUrban/uploaded-file.png
        if (empty($map['Upload'])) {
            $map['Upload'] = getUploadDataPath();
        }
        // User:ReiniUrban => ReiniUrban or Users/ReiniUrban
        // Can be easily overriden by a customized InterWikiMap:
        //   User Users/%s
        if (empty($map["User"])) {
            $map["User"] = "%s";
        }
        // Talk:UserName => UserName/Discussion
        // Talk:PageName => PageName/Discussion as default, which might be overridden
        if (empty($map["Talk"])) {
            $pagename = $request->getArg('pagename');
            // against PageName/Discussion/Discussion
            if (string_ends_with($pagename, '/' . _("Discussion")))
                $map["Talk"] = "%s";
            else
                $map["Talk"] = "%s" . '/' . _("Discussion");
        }

        foreach (array('Upload', 'User', 'Talk') as $special) {
            // Expand special variables:
            //   %u => username
            //   %b => wikibaseurl
            //   %d => iso8601 DateTime
            // %s is expanded later to the pagename
            if (strstr($map[$special], '%u'))
                $map[$special] = str_replace($map[$special],
                    '%u',
                    $request->_user->_userid);
            if (strstr($map[$special], '%b'))
                $map[$special] = str_replace($map[$special],
                    '%b',
                    PHPWIKI_BASE_URL);
            if (strstr($map[$special], '%d'))
                $map[$special] = str_replace($map[$special],
                    '%d',
                    // such as 2003-01-11T14:03:02+00:00
                    Iso8601DateTime());
        }

        return $map;
    }

    private function _getMapFromWikiText($pagetext)
    {
        if (preg_match('|^<verbatim>\n(.*)^</verbatim>|ms', $pagetext, $m)) {
            return $m[1];
        }
        return false;
    }

    private function _getMapFromFile($filename)
    {
        if (defined('WARN_NONPUBLIC_INTERWIKIMAP') and WARN_NONPUBLIC_INTERWIKIMAP) {
            $error_html = sprintf(_("Loading InterWikiMap from external file %s."),
                $filename);
            trigger_error($error_html, E_USER_NOTICE);
        }
        if (!file_exists($filename)) {
            $finder = new FileFinder();
            $filename = $finder->findFile(INTERWIKI_MAP_FILE);
        }
        @$fd = fopen($filename, "rb");
        @$data = fread($fd, filesize($filename));
        @fclose($fd);

        return $data;
    }

    private function _getRegexp()
    {
        if (!$this->_map)
            return '(?:(?!a)a)'; //  Never matches.

        $qkeys = array();
        foreach (array_keys($this->_map) as $moniker)
            $qkeys[] = preg_quote($moniker, '/');
        return "(?:" . join("|", $qkeys) . ")";
    }
}

/** How to transform text.
 */
abstract class PageFormatter
{
    /**
     * @param WikiDB_Page $page
     * @param array       $meta Version meta-data hash.
     */
    function __construct(&$page, $meta)
    {
        $this->_page = $page;
        $this->_meta = $meta;
    }

    function _transform($text)
    {
        include_once 'lib/BlockParser.php';
        return TransformText($text);
    }

    /** Transform the page text.
     *
     * @param  string     $text The raw page content (e.g. wiki-text).
     * @return XmlContent Transformed content.
     */
    abstract function format($text);
}

class PageFormatter_wikitext extends PageFormatter
{
    function format($text)
    {
        return HTML::div(array('class' => 'wikitext'),
            $this->_transform($text));
    }
}

class PageFormatter_interwikimap extends PageFormatter
{
    function format($text)
    {
        return HTML::div(array('class' => 'wikitext'),
            $this->_transform($this->_getHeader($text)),
            $this->_formatMap($text),
            $this->_transform($this->_getFooter($text)));
    }

    protected function _getHeader($text)
    {
        return preg_replace('/<verbatim>.*/s', '', $text);
    }

    protected function _getFooter($text)
    {
        return preg_replace('@.*?(</verbatim>|\Z)@s', '', $text, 1);
    }

    protected function _getMap($pagetext)
    {
        $map = getInterwikiMap($pagetext, 'force');
        return $map->_map;
    }

    protected function _formatMap($pagetext)
    {
        $map = $this->_getMap($pagetext);
        if (!$map)
            return HTML::p("No interwiki map found"); // Shouldn't happen.

        $mon_attr = array('class' => 'interwiki-moniker');
        $url_attr = array('class' => 'interwiki-url');

        $thead = HTML::thead(HTML::tr(HTML::th($mon_attr, _("Moniker")),
            HTML::th($url_attr, _("InterWiki Address"))));
        $rows = array();
        foreach ($map as $moniker => $interurl) {
            $rows[] = HTML::tr(HTML::td($mon_attr, new Cached_WikiLinkIfKnown($moniker)),
                HTML::td($url_attr, HTML::samp($interurl)));
        }

        return HTML::table(array('class' => 'interwiki-map'),
            $thead,
            HTML::tbody(false, $rows));
    }
}

class FakePageRevision
{
    function __construct($meta)
    {
        $this->_meta = $meta;
    }

    function get($key)
    {
        if (empty($this->_meta[$key]))
            return false;
        return $this->_meta[$key];
    }
}

// abstract base class
class PageFormatter_attach extends PageFormatter
{
    public $type, $prefix;

    // Display templated contents for wikiblog, comment and wikiforum
    function format($text)
    {
        if (empty($this->type))
            trigger_error('PageFormatter_attach->format: $type missing');
        include_once 'lib/Template.php';
        global $request;
        $tokens['CONTENT'] = $this->_transform($text);
        $tokens['page'] = $this->_page;
        $tokens['rev'] = new FakePageRevision($this->_meta);

        $name = new WikiPageName($this->_page->getName());
        $tokens[$this->prefix . "_PARENT"] = $name->getParent();

        $meta = $this->_meta[$this->type];
        foreach (array('ctime', 'creator', 'creator_id') as $key)
            $tokens[$this->prefix . "_" . strtoupper($key)] = $meta[$key];

        return new Template($this->type, $request, $tokens);
    }
}

class PageFormatter_wikiblog extends PageFormatter_attach
{
    public $type = 'wikiblog', $prefix = "BLOG";
}

class PageFormatter_comment extends PageFormatter_attach
{
    public $type = 'comment', $prefix = "COMMENT";
}

class PageFormatter_wikiforum extends PageFormatter_attach
{
    public $type = 'wikiforum', $prefix = "FORUM";
}

/** wikiabuse for htmlarea editing. not yet used.
 *
 * Warning! Once a page is edited with a htmlarea like control it is
 * stored in HTML and cannot be converted back to WikiText as long as
 * we have no HTML => WikiText or any other interim format (WikiExchangeFormat e.g. XML)
 * converter. See lib/HtmlParser.php for ongoing work on that.
 * So it has a viral effect and certain plugins will not work anymore.
 * But a lot of wikiusers seem to like it.
 */
class PageFormatter_html extends PageFormatter
{
    function _transform($text)
    {
        return $text;
    }

    function format($text)
    {
        return $text;
    }
}

class PageFormatter_MediaWiki extends PageFormatter
{
    function _transform($text)
    {
        include_once 'lib/BlockParser.php';
        // Expand leading tabs.
        $text = expand_tabs($text);

        $input = new BlockParser_Input($text);
        $output = $this->ParsedBlock($input);
        return new XmlContent($output->getContent());
    }

    function format($text)
    {
        return HTML::div(array('class' => 'wikitext'),
            $this->_transform($text));
    }
}
