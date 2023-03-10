<?php
/**
 * FusionForge Exports: Export new releases info in RSS
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://fusionforge.org

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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'export/rss_utils.inc';

$limit = getIntFromRequest('limit', 10);
if ($limit > 100) {
	$limit = 100;
}

header("Content-Type: text/xml; charset=utf-8");
print '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE rss SYSTEM "http://my.netscape.com/publish/formats/rss-0.91.dtd">
<rss version="0.91">
';

$res=db_query_params ('SELECT
					groups.group_id,
					groups.group_name,
					news_bytes.forum_id,
					news_bytes.summary,
					news_bytes.post_date,
					news_bytes.details
				FROM
					news_bytes,
					groups
				WHERE
					news_bytes.group_id=groups.group_id
					AND groups.status=$1
				ORDER BY
					post_date
				DESC',
		      array('A'),
		      $limit * 3);

// ## one time output
print " <channel>\n";
print "  <copyright>Copyright ".date("Y")." ".forge_get_config ('forge_name')."</copyright>\n";
print "  <pubDate>".rss_date(time())."</pubDate>\n";
print "  <description>".forge_get_config ('forge_name')." New Releases</description>\n";
print "  <link>http://".forge_get_config('web_host')."</link>\n";
print "  <title>".forge_get_config ('forge_name')." New Releases</title>\n";
print "  <webMaster>".forge_get_config('admin_email')."</webMaster>\n";
print "  <language>en-us</language>\n";
// ## item outputs
$outputtotal = 0;
$seen = array() ;
while ($row = db_fetch_array($res)) {
	if (!forge_check_perm('project_read', $row['group_id'])) {
		continue;
	}

	if (!isset ($seen[$row['group_id']])) {
		print "  <item>\n";
		print "   <title>".htmlspecialchars($row['group_name'])."</title>\n";
		print "   <link>http://".forge_get_config('web_host')."/project/showfiles.php?group_id=$row[group_id]</link>\n";
		print "   <description>".rss_description($row['summary'])."</description>\n";
		print "  </item>\n";
		$outputtotal++;
	}
	// eliminate dupes, only do $limit of these
	$seen[$row['group_id']] = 1;
	if ($outputtotal >= $limit) {
		break;
	}
}
// ## end output
print " </channel>\n";
?>
</rss>
