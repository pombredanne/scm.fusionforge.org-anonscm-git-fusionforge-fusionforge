<?php
/**
 * FusionForge statistics
 *
 * Copyright 1999-2001, VA Linux Systems, Inc.
 * Copyright 2002, GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2012, Thorsten Glaser <t.glaser@tarent.de>
 * Copyright 2021, Franck Villaume - TrivialDev
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

class Stats extends FFError {

	/**
	 * Returns a resultset consisting of the month, day, total_users, pageviews, and sessions
	 * from the stats_site tables
	 *
	 * @return resource A resultset of month, day, total_users, pageviews, sessions
	 */
	function getSiteStats() {
		$res = db_query_params ("select byday.month,byday.day,byday.site_page_views as pageviews, ss.total_users, ss.sessions from stats_site_pages_by_day byday, stats_site ss where byday.month=ss.month and byday.day = ss.day order by byday.month asc, byday.day asc",
					array ());
		if (!$res) {
			$this->setError('Unable to get stats: '.db_error());
			return false;
		}
		return $res;
	}

	/**
	* Returns a result set containing the group_name, unix_group_name, group_id, ranking, and percentile
	* for either the last week or for all time
	*
	* @param string	$type	week or null (for all time)
	* @param int	$offset	used to page thru the result
	* @return resource A resultset of group_name, unix_group_name, group_id, ranking, percentile
	*/
	function getMostActiveStats($type, $offset) {
		if ($type == 'week') 	{
			return db_query_params('SELECT groups.group_name,groups.unix_group_name,groups.group_id,project_weekly_metric.ranking,project_weekly_metric.percentile FROM groups,project_weekly_metric WHERE groups.group_id=project_weekly_metric.group_id AND groups.status = $1 AND groups.use_stats=1 ORDER BY ranking ASC',
				array('A'),
				0,
				$offset) ;
		} else {
			return db_query_params('SELECT g.group_name,g.unix_group_name,g.group_id,s.group_ranking as ranking,s.group_metric as percentile
				FROM groups g,stats_project_all_vw s
				WHERE g.group_id=s.group_id
					AND g.status = $1
					AND g.use_stats=1
					AND s.group_ranking > 0
					ORDER BY ranking ASC',
				array('A'),
				0,
				$offset);
		}
	}

	/**
	* Returns a resultset containing unix_group_name, group_name, and items - the count of
	* the messages posted on that group's forums
	*
	* @return resource a resultset of unix_group_name, group_name, items
	*/
	function getTopMessagesPosted() {
		return db_query_params ('SELECT g.unix_group_name, g.group_name, SUM(s.msg_posted) AS items, g.group_id FROM stats_project s, groups g WHERE s.group_id=g.group_id AND g.status=$1 GROUP BY g.unix_group_name, g.group_name, g.group_id ORDER BY items DESC',
					array ('A'),
					100) ;
	}

	/**
	* Returns a resultset containing group_name, unix_group_name, and items - the count of
	* the page views for that group
	*
	* @return resource a resultset of group_name, unix_group_name, items
	*/
	function getTopPageViews() {
		return db_query_params ('SELECT g.group_name, g.unix_group_name, SUM(s.page_views) AS items, g.group_id FROM stats_project_months s, groups g WHERE s.group_id=g.group_id AND g.status=$1 GROUP BY g.group_name, g.unix_group_name, g.group_id ORDER BY items DESC',
					array ('A'),
					100) ;
	}

	/**
	* Returns a resultset containing group_name, unix_group_name, and items - the count of
	* the downloads for that group
	*
	* @return resource	a resultset of group_name, unix_group_name, items
	*/
	function getTopDownloads() {
		return db_query_params ('SELECT g.group_name, g.unix_group_name, SUM(frs.downloads) AS items, g.group_id FROM frs_dlstats_grouptotal_vw frs, groups g WHERE g.group_id = frs.group_id AND g.status=$1 GROUP BY g.group_name, g.unix_group_name, g.group_id ORDER BY items DESC',
					array ('A'),
					100) ;
	}

	function getTopVotedProjects() {
		return db_query_params('SELECT g.group_name, g.unix_group_name, gv.group_id, COUNT(*) AS counter FROM group_votes AS gv, groups AS g WHERE gv.group_id = g.group_id AND g.status = $1 GROUP BY gv.group_id, g.group_name, g.unix_group_name ORDER BY counter DESC',
					array('A'),
					100);
	}
}
