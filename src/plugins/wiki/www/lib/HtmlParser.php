<?php
/**
 * Copyright © 2004 Reini Urban
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
 * HtmlParser Class: Conversion HTML => wikimarkup
 * Requires PhpWikiXmlParser, XmlElement and the expat (or now the libxml) library. This is all in core.
 */

/**
 * Base class to implement html => wikitext converters,
 * extendable for various wiki syntax versions.
 * This is needed to be able to use htmlarea-alike editors,
 * and to import XML or HTML documents.
 *
 * See also php-html.sf.net for a php-only version, if
 * you don't have the expat/libxml extension included.
 * See also http://search.cpan.org/~diberri/HTML-WikiConverter/
 *
 */

// RssParser contains the XML (expat) and url-grabber methods
require_once 'lib/PhpWikiXmlParser.php';

class HtmlParser
    extends PhpWikiXmlParser
{
    public $dialect, $_handlers, $root;

    function __construct($dialect, $encoding = '')
    {
        $this->dialect = new HtmlParser_PhpWiki();
        $this->_handlers =& $this->dialect->_handlers;
        $this->PhpWikiXmlParser($encoding);
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->_parser, XML_OPTION_SKIP_WHITE, 1);
    }

    // The three callbacks, called on walking through the HTML tree.
    // No extensions needed from PhpWikiXmlParser.
    /*
    function tag_open($parser, $name, $attrs='') {
    }
    function tag_close($parser, $name, $attrs='') {
    }
    function cdata($parser, $data) {
    }
    function parse_url($file, $debug=false)
    */

    function output()
    {
        if (is_null($this->root))
            $this->root = $GLOBALS['xml_parser_root'];
        $output = $this->wikify($this->root);
        return $output;
    }

    /**
     * @param HtmlElement $node
     * @param HtmlElement $parent
     * @return string
     */
    function wikify($node, $parent = null)
    {
        $output = '';
        if (is_a($node, 'XmlElement')) {
            $dialect =& $this->dialect;
            $conv = $dialect->_handlers[$node->_tag];
            if (is_string($conv) and method_exists($dialect, $conv)) {
                $output = $dialect->$conv($node);
            } elseif (is_array($conv)) {
                foreach ($node->getContent() as $n) {
                    $output .= $this->wikify($n, $node);
                }
                $output = $conv[0] . $output . $conv[count($conv) - 1];
            } elseif (!empty($conv)) {
                $output = $conv;
                foreach ($node->getContent() as $n) {
                    $output .= $this->wikify($n, $node);
                }
            } else {
                foreach ($node->getContent() as $n) {
                    $output .= $this->wikify($n, $node);
                }
            }
        } else {
            $output = $node;
            if ($parent and $parent->_tag != 'pre')
                preg_replace("/ {2,}/", " ", $output);
            if (trim($output) == '')
                $output = '';
        }
        return $output;
    }

    /**
     *  $output = $parser->elem_contents( $elem );
     * Returns a wikified version of the contents of the specified
     * HTML element. This is done by passing each element of this
     * element's content list through the C<wikify()> method, and
     * returning the concatenated result.
     *
     * @param HtmlElement $node
     * @return string
     */
    function elem_contents($node)
    {
        $output = '';
        if (is_a($node, 'XmlElement')) {
            foreach ($node->getContent() as $child) {
                $output .= $this->wikify($child, isset($node->parent) ? $node->parent : null);
            }
        } else {
            $output = $this->wikify($content);
        }
        return $output;
    }

    //
    // Private function: _elem_attr_str( $elem, @attrs )
    //
    // Returns a string containing a list of attribute names and
    // values associated with the specified HTML element. Only
    // attribute names included in @attrs will be added to the
    // string of attributes that is returned. The return value
    // is suitable for inserting into an HTML document, as
    // attribute name/value pairs are specified in attr="value"
    // format.
    //
    function _elem_attr_str($node, $attrs)
    {
        $s = '';
        foreach ($node->_attr as $attr => $val) {
            $attr = strtolower($attr);
            if (in_array($attr, $attrs))
                $s .= " $attr=\"$val\"";
        }
        return $s;
    }

    //
    // Private function: _elem_has_ancestor( $elem, $tagname )
    //
    // Returns true if the specified HtmlElement has an ancestor element
    // whose element tag equals $tag. This is useful for determining if
    // an element belongs to the specified tag.
    //
    function _elem_has_ancestor($node, $tag)
    {
        if (isset($node->parent)) {
            if ($node->parent->_tag == $tag) return true;
            return $this->_elem_has_ancestor($node->parent, $tag);
        }
        return false;
    }

    //
    // Private function: _elem_is_image_div( $elem )
    //
    // Returns true $elem is a container element (P or DIV) meant only to
    // lay out an IMG.
    //
    // More specifically, returns true if the given element is a DIV or P
    // element and the only child it contains is an IMG tag or an IMG tag
    // contained within a sole A tag (not counting child elements with
    // whitespace text only).
    //
    /**
     * @param HtmlElement $node
     * @return bool
     */
    function _elem_is_image_div($node)
    {
        // Return false if node is undefined or isn't a DIV at all
        if (!$node or !in_array($node->_tag, array("div", "p")))
            return false;
        $contents = $node->getContent();
        // Returns true if sole child is an IMG tag
        if (count($contents) == 1 and isset($contents[0]) and $contents[0]->_tag == 'img')
            return true;
        // Check if child is a sole A tag that contains an IMG tag
        if (count($contents) == 1 and isset($contents[0]) and $contents[0]->_tag == 'a') {
            $children = $contents[0]->getContent();
            if (count($children) == 1 and isset($children[0]) and $children[0]->_tag == 'img')
                return true;
        }
        return false;
    }

    /**
     * preserves tags and content
     *
     * @param HtmlElement $node
     * @return mixed
     */
    function wikify_default($node)
    {
        return $this->wikify_preserve($node);
    }

    /*
     * preserves tags and content
     *
     * @param HtmlElement $node
     * @return mixed
     */
    function wikify_preserve($node)
    {
        return $node->asXML();
    }

    function log($dummy)
    {
    }
}

class HtmlParser_PhpWiki
    extends HtmlParser
{
    public $ident;

    function __construct()
    {
        $this->_handlers =
            array('html' => '',
                'head' => '',
                'title' => '',
                'meta' => '',
                'link' => '',
                'script' => '',
                'body' => '',

                'br' => "<br>",
                'b' => array("*"),
                'strong' => array("*"),
                'i' => array("_"),
                'em' => array("_"),
                'hr' => "----\n\n",

                // PRE blocks are handled specially (see tidy_whitespace and
                // wikify methods)
                'pre' => array("<pre>", "</pre>"),

                'dl' => array('', "\n\n"),
                'dt' => array(';', ''),
                'dd' => array(':', ''),

                'p' => array("\n\n", "\n\n"),
                'ul' => array('', "\n"),
                'ol' => array('', "\n"),

                'li' => "wikify_list_item",
                'table' => "wikify_table",
                'tr' => "wikify_tr",
                'td' => "wikify_td",
                'th' => "wikify_td",
                'div' => array('', "\n\n"),
                'img' => "wikify_img",
                'a' => "wikify_link",
                'span' => array('', ''),

                'h1' => "wikify_h",
                'h2' => "wikify_h",
                'h3' => "wikify_h",
                'h4' => "wikify_h",
                'h5' => "wikify_h",
                'h6' => "wikify_h",

                'font' => array('', ''),
                'sup' => "wikify_default",
                'sub' => "wikify_default",
                'nowiki' => "wikify_verbatim",
                'verbatim' => "wikify_default",
                'noinclude' => "wikify_noinclude",
            );
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_table($node)
    {
        $this->ident = '';
        return "| \n" . $this->elem_contents($node) . "|\n\n";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_tr($node)
    {
        return "\n| " . $this->elem_contents($node);
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_th($node)
    {
        $ident = empty($this->ident) ? '' : $this->ident;
        $output = "$ident| ";
        $content = $this->elem_contents($node);
        preg_replace("s/^\s+/", "", $content);
        $output .= $content;
        $this->ident .= '  ';
        return "$output |\n";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_list_item($node)
    {
        return ($this->_elem_has_ancestor($node, 'ol') ? '*' : '#') . " " . trim($this->elem_contents($node)) . "\n";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_link($node)
    {
        $url = $this->absolute_url($node->getAttr('href'));
        $title = $this->elem_contents($node);
        if (empty($url))
            $title = trim($title);

        // Just return the link title if this tag is contained
        // within an header tag
        if (isset($node->parent) and preg_match('/^h\d$/', $node->parent->_tag))
            return $title;

        // Return if this is a link to an image contained within
        if (isset($node->parent) and $this->_elem_is_image_div($node->parent))
            return $title;

        // If HREF is the same as the link title, then
        // just return the URL (it'll be converted into
        // a clickable link by the wiki engine)
        if ($url == $title) return $url;
        return "[ $url | $title ]";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_h($node)
    {
        $level = substr($node->_tag, 1);
        if ($level < 4) {
            $markup = str_repeat('!', 4 - $level);
        } else {
            $markup = '!';
        }
        return $markup . ' ' . trim($this->elem_contents($node)) . "\n\n";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_verbatim($node)
    {
        $contents = $this->elem_contents($node);
        return "\n<".'verbatim'.">\n$contents\n</"."verbatim>";
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_noinclude($node)
    {
        return $this->elem_contents($node);
    }

    /**
     * @param HtmlElement $node
     * @return string
     */
    function wikify_img($node)
    {
        $image_url = $this->absolute_url($node->getAttr('src'));
        $file = basename($image_url);
        $alignment = $node->getAttr('align');
        $this->log("Processing IMG tag for SRC: " . $image_url . "...");
        //
        // Grab attributes to be added to the [ Image ] markup (since 1.3.10)
        //
        if (!$alignment) {
            if ($this->_elem_is_image_div($node->parent))
                $image_div = $node->parent;
            elseif (isset($node->parent) and $this->_elem_is_image_div($node->parent->parent))
                $image_div = $node->parent->parent;
        }
        if (!$alignment and $image_div) {
            $css_style = $image_div->getAttr('style');
            $css_class = $image_div->getAttr('class');

            // float => align: Check for float attribute; if it's there,
            //                 then we'll add it to the [Image] syntax
            if (!$alignment and preg_match("/float\:\s*(right|left)/i", $css_style, $m))
                $alignment = $m[1];
            if (!$alignment and preg_match("/float(right|left)/i", $css_class, $m)) ;
            $alignment = $m[1];
            if ($alignment) {
                $attrs[] = "class=align-$alignment";
                $this->log("  Image is contained within a DIV that specifies $alignment alignment");
                $this->log("  Adding '$alignment' to [Image] markup attributes");
            } else {
                $this->log("  Image is not contained within a DIV for alignment");
            }
        } else {
            $this->log("  Image is not contained within a DIV");
        }
        if ($alignment)
            $attrs[] = "class=align-$alignment";
        //
        // Check if we need to request a thumbnail of this
        // image; it's needed if the specified width attribute
        // differs from the default size of the image
        //
        if ($width = $node->getAttr('width')) {
            $this->log("  Image has WIDTH attribute of $width");
            $this->log("  Checking whether resulting [Image] markup should specify a thumbnail...");

            // Download the image from the network and store
            $abs_url = $this->absolute_url($node->getAttr('src'));
            $this->log("    Fetching image '$abs_url' from the network");
            list($actual_w, $actual_h, $flag, $attr_str) = getimagesize($abs_url);

            // If the WIDTH attribute of the IMG tag is not equal
            // to the actual width of the image, then we need to
            // create a thumbnail
            if (preg_match("/^\d+$/", $width) and $width != $actual_w) {
                $this->log("    IMG tag's WIDTH attribute ($width) differs from actual width of image ($actual_w)");
                $this->log("      -- that means we're going to need a thumbnail");
                $this->log("    Adding 'width' to list of attributes for [Image] markup");
                $attrs[] = "width=$width";
                $width_added = true;
            }
            $height = $node->getAttr('height');
            if (preg_match("/^\d+$/", $height) and $height != $height_h) {
                $this->log("    IMG tag's HEIGHT attribute ($height) differs from actual height of image ($actual_h)");
                $this->log("      -- that means we're going to need a thumbnail");
                $this->log("    Adding 'height' to list of attributes for [Image] markup");
                if (isset($width_added))
                    $attrs[count($attr) - 1] = "size=" . $width . "x" . $height;
                else
                    $attrs[] = "height=$height";
            }
        }
        if ($alt = $node->getAttr('alt')) {
            $this->log("  Adding alternate text '$alt' to [Image] markup");
            $attrs[] = "alt=$alt";
        }
        $attr_str = join(' ', $attrs);
        $this->log("...done processing IMG tag\n");
        return "[ $file $attr_str ]";
    }
}
