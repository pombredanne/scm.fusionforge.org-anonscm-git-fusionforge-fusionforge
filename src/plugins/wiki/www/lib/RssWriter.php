<?php
/**
 * Copyright © 2001-2002 Jeff Dairiki
 * Copyright © 2002 Carsten Klapp
 * Copyright © 2004,2007,2009 Reini Urban
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

/*
 * Code for creating RSS 1.0.
 */

/**
 * A class for writing RSS 1.0.
 *
 * @see http://purl.org/rss/1.0/spec,
 *      http://www.usemod.com/cgi-bin/mb.pl?ModWiki
 */
class RssWriter extends XmlElement
{
    public $_channel;
    public $_image;
    public $_textinput;

    function __construct()
    {
        parent::__construct('rdf:RDF',
            array('xmlns' => "http://purl.org/rss/1.0/",
                'xmlns:rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'));

        $this->_modules = array(
            //Standards
            'content' => "http://purl.org/rss/1.0/modules/content/",
            'dc' => "http://purl.org/dc/elements/1.1/",
            'sy' => "http://purl.org/rss/1.0/modules/syndication/",
            //Proposed
            'wiki' => "http://purl.org/rss/1.0/modules/wiki/",
            'ag' => "http://purl.org/rss/1.0/modules/aggregation/",
            'annotate' => "http://purl.org/rss/1.0/modules/annotate/",
            'audio' => "http://media.tangent.org/rss/1.0/",
            'cp' => "http://my.theinfo.org/changed/1.0/rss/",
            'rss091' => "http://purl.org/rss/1.0/modules/rss091/",
            'slash' => "http://purl.org/rss/1.0/modules/slash/",
            'taxo' => "http://purl.org/rss/1.0/modules/taxonomy/",
            'thr' => "http://purl.org/rss/1.0/modules/threading/"
        );

        $this->_uris_seen = array();
        $this->_items = array();
    }

    function registerModule($alias, $uri)
    {
        assert(!isset($this->_modules[$alias]));
        $this->_modules[$alias] = $uri;
    }

    // Args should include:
    //  'title', 'link', 'description'
    // and can include:
    //  'URI'
    function channel($properties, $uri = false)
    {
        $this->_channel = $this->__node('channel', $properties, $uri);
    }

    // Args should include:
    //  'title', 'link'
    // and can include:
    //  'description', 'URI'
    function addItem($properties, $uri = false)
    {
        $this->_items[] = $this->__node('item', $properties, $uri);
    }

    // Args should include:
    //  'url', 'title', 'link'
    // and can include:
    //  'URI'
    function image($properties, $uri = false)
    {
        $this->_image = $this->__node('image', $properties, $uri);
    }

    // Args should include:
    //  'title', 'description', 'name', and 'link'
    // and can include:
    //  'URI'
    function textinput($properties, $uri = false)
    {
        $this->_textinput = $this->__node('textinput', $properties, $uri);
    }

    /**
     * Finish construction of RSS.
     */
    function finish()
    {
        if (isset($this->_finished))
            return;

        $channel = &$this->_channel;
        $items = &$this->_items;

        $seq = new XmlElement('rdf:Seq');
        if ($items) {
            foreach ($items as $item)
                $seq->pushContent($this->__ref('rdf:li', $item));
        }
        $channel->pushContent(new XmlElement('items', false, $seq));

        if (isset($this->_image)) {
            $channel->pushContent($this->__ref('image', $this->_image));
            $items[] = $this->_image;
        }
        if (isset($this->_textinput)) {
            $channel->pushContent($this->__ref('textinput', $this->_textinput));
            $items[] = $this->_textinput;
        }

        $this->pushContent($channel);
        if ($items)
            $this->pushContent($items);

        $this->__spew();
        $this->_finished = true;
    }

    /**
     * Write output to HTTP client.
     */
    function __spew()
    {
        header("Content-Type: application/xml; charset=UTF-8");
        echo('<' . '?xml version="1.0" encoding="UTF-8" ?' . ">\n");
        $this->printXML();
    }

    /**
     * Create a new RDF <em>typedNode</em>.
     *
     * @param $type
     * @param $properties
     * @param bool $uri
     * @return XmlElement
     */
    function __node($type, $properties, $uri = false)
    {
        if (!$uri)
            $uri = $properties['link'];
        $attr['rdf:about'] = $this->__uniquify_uri($uri);
        return new XmlElement($type, $attr,
            $this->__elementize($properties));
    }

    /**
     * Check object URI for uniqueness, create a unique URI if needed.
     *
     * @param string $uri
     * @return string
     */
    function __uniquify_uri($uri)
    {
        if (!$uri || isset($this->_uris_seen[$uri])) {
            $n = count($this->_uris_seen);
            $uri = $this->_channel->getAttr('rdf:about') . "#uri$n";
            assert(!isset($this->_uris_seen[$uri]));
        }
        $this->_uris_seen[$uri] = true;
        return $uri;
    }

    /**
     * Convert hash of RDF properties to <em>propertyElt</em>s.
     *
     * @param $elements
     * @return array
     */
    function __elementize($elements)
    {
        $out = array();
        foreach ($elements as $prop => $val) {
            $this->__check_predicate($prop);
            if (is_array($val))
                $out[] = new XmlElement($prop, $val);
            elseif (is_object($val))
                $out[] = $val; else
                $out[] = new XmlElement($prop, false, $val);
        }
        return $out;
    }

    /**
     * Check property predicates for XMLNS sanity.
     *
     * @param string $name
     */
    function __check_predicate($name)
    {
        if (preg_match('/^([^:]+):[^:]/', $name, $m)) {
            $ns = $m[1];
            if (!$this->getAttr("xmlns:$ns")) {
                if (!isset($this->_modules[$ns]))
                    die("$name: unknown namespace ($ns)");
                $this->setAttr("xmlns:$ns", $this->_modules[$ns]);
            }
        }
    }

    /**
     * Create a <em>propertyElt</em> which references another node in the RSS.
     *
     * @param $predicate
     * @param $reference
     * @return XmlElement
     */
    function __ref($predicate, $reference)
    {
        $attr['rdf:resource'] = $reference->getAttr('rdf:about');
        return new XmlElement($predicate, $attr);
    }
}

/* Taken from mediawiki.
 * See http://www.atomenabled.org/developers/syndication/
 */
class AtomFeed extends RssWriter
{

    // Args should include:
    //  'title', 'link', 'description'
    // and can include:
    //  'URI'
    function feed($properties, $uri = false)
    {
        global $LANG;
        $attr = array('xmlns' => 'http://www.w3.org/2005/Atom',
            'version' => '0.3', // or 1.0
            'lang' => $LANG);
        $this->_channel = $this->__node('feed', $attr, $properties, $uri);
    }

    /**
     * Write output to HTTP client.
     */
    function __spew()
    {
        header("Content-Type: application/atom+xml; charset=UTF-8");
        echo('<' . '?xml version="1.0" encoding="UTF-8" ?' . ">\n");
        $this->printXML();
    }

    /**
     * Create a new entry
     *
     * @param $type
     * @param $attr
     * @param bool $properties
     * @param bool $uri
     * @return XmlElement
     */
    function __atom_node($type, $attr, $properties, $uri = false)
    {
        if (!$uri)
            $uri = $properties['link'];
        //$attr['rdf:about'] = $this->__uniquify_uri($uri);
        return new XmlElement($type, $attr,
            $this->__elementize($properties));
    }

    // Args should include:
    //  'title', 'link', author, modified, issued, created, summary,
    // and can include:
    //  comment
    function addItem($properties, $attr = false, $uri = false)
    {
        $this->_items[] = $this->__atom_node('entry', $attr, $properties, $uri);
    }

    /**
     * Print it.
     */
    function finish()
    {
        if (isset($this->_finished))
            return;

        $channel = &$this->_channel;
        $items = &$this->_items;
        if ($items)
            $channel->pushContent($items);
        $this->pushContent($channel);

        $this->__spew();
        $this->_finished = true;
    }
}
