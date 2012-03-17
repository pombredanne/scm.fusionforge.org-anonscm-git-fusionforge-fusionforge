<?php
/**
 * FusionForge Exports
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2010, Franck Villaume
 * http://fusionforge.org
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once('../env.inc.php');
require_once $gfcommon.'include/pre.php';

$HTML->header(array(title=>_('Exports Available')));
?>
<p><?php echo forge_get_config ('forge_name'); ?> data is exported in a variety of standard formats. Many of
the export URLs can also accept form/get data to customize the output. All
data generated by these pages is realtime.</p>

<h3>
RSS/XML Exports
</h3>

<h4>News Data</h4>
<ul>
<li><a href="rss_sfnews.php"><?php echo forge_get_config ('forge_name'); ?> Front Page/Project News</a>

(<a href="http://web.resource.org/rss/1.0/spec">RSS 1.0</a>)</li>
<li><a href="rss_sfnewreleases.php"><?php echo forge_get_config ('forge_name'); ?> New Releases</a>

(<a href="http://my.netscape.com/publish/formats/rss-spec-0.91.html">RSS 0.91</a>,
<a href="http://my.netscape.com/publish/formats/rss-0.91.dtd">&lt;rss-0.91.dtd&gt;</a>)</li>
</ul>

<ul>
<li><a href="rss20_news.php"><?php print forge_get_config ('forge_name') ?> Front Page News/Project News</a>

(<a href="http://blogs.law.harvard.edu/tech/rss">RSS 2.0</a>)
<li><a href="rss20_newreleases.php"><?php print forge_get_config ('forge_name') ?> New Releases/New Project Releases</a>

(<a href="http://blogs.law.harvard.edu/tech/rss">RSS 2.0</a>)</li>
</ul>

<h4>Site Information</h4>
<ul>
<li><a href="rss_sfprojects.php"><?php echo forge_get_config ('forge_name'); ?> Full Project Listing</a>

(<a href="http://my.netscape.com/publish/formats/rss-spec-0.91.html">RSS 0.91</a>,
<a href="http://my.netscape.com/publish/formats/rss-0.91.dtd">&lt;rss-0.91.dtd&gt;</a>)</li>
<li><a href="rss20_projects.php"><?php print forge_get_config ('forge_name') ?> Full Project Listing</a>

(<a href="http://blogs.law.harvard.edu/tech/rss">RSS 2.0</a>)</li>
<li><a href="trove_tree.php"><?php echo forge_get_config ('forge_name'); ?> Trove Categories Tree</a>

(<a href="http://www.w3.org/XML">XML</a>,<a href="trove_tree_0.1.dtd">&lt;trove_tree_0.1.dtd&gt;</a>)</li>
</ul>

<!-- Disabled Until Security Audited and Using Proper Accessor Functions
<h4>Project Information</h4>
<p>
All links below require <span class="tt">?group_id=</span> parameter with id of specific
group. Exports which provide en masse access to data which otherwise
project property, require project admin privilege.
</p>

<ul>
<li><a href="forum.php">Project Forums</a>(<a href="forum_0.1.dtd">&lt;forum_0.1.dtd&gt;</a>)</li>
<li><a href="bug_dump.php">Project Bugs</a>(<a href="bug_0.1.dtd">&lt;bug_0.1.dtd&gt;</a>)</li>
<li><a href="patch_dump.php">Project Patches</a>(<a href="patch_0.1.dtd">&lt;patch_0.1.dtd&gt;</a>)</li>
</ul>
-->

<h3>
HTML Exports
</h3>
<p>
While XML data allows for arbitrary processing and formatting, many
projects will find ready-to-use HTML exports suitable for their needs.
For details, check <a href="http://sourceforge.net/docman/display_doc.php?docid=1502&amp;group_id=1">this</a> out.
</p>

<?php
$HTML->footer(array());
?>
