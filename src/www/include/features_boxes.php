<?php
/**
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012,2016,2021, Franck Villaume - TrivialDev
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

// Attempt to set up the include path, to fix problems with relative includes

require_once $gfcommon.'include/FusionForge.class.php';
require_once $gfcommon.'include/tag_cloud.php';
require_once $gfcommon.'include/Stats.class.php';

function show_features_boxes() {
	global $HTML;

	$return = '';
	$params['returned_text'] = &$return;
	plugin_hook_by_reference('features_boxes_top', $params);
	$return .= '<h2 class="skip">' . _('Features Boxes') . '</h2>';

	if (forge_get_config('use_project_tags')) {
		$contentTag = tag_cloud();
		if (!empty($contentTag)) {
			$return .= $HTML->boxTop(_('Tag Cloud'), 'Tag_Cloud');
			$return .= $contentTag;
			$return .= $HTML->boxBottom();
		}
	}
	$return .= $HTML->boxTop(sprintf(_('%s Statistics'), forge_get_config ('forge_name')), 'Forge_Statistics');
	$return .= show_sitestats();
	if (forge_get_config('use_frs')) {
		$return .= $HTML->boxMiddle(_('Top Project Downloads'), 'Top_Projects_Downloads');
		$return .= show_top_downloads();
	}
	if (forge_get_config('use_ratings')) {
		$return .= $HTML->boxMiddle(_('Highest Ranked Users'), 'Highest_Ranked_Users');
		$return .= show_highest_ranked_users();
	}
	$return .= $HTML->boxMiddle(_('Most Active This Week'), 'Most_Active_This_Week');
	$return .= show_highest_ranked_projects();
	$return .= $HTML->boxMiddle(_('Recently Registered Projects'), 'Recently_Registered_Projects');
	$return .= show_newest_projects();
	$return .= $HTML->boxMiddle(_('System Information'), 'System_Information');
	$ff = FusionForge::getInstance();
	$return .= sprintf(_('%s is running %s version %s'),
			   forge_get_config ('forge_name'),
			   $ff->software_name,
			   $ff->software_version);
	$return .= $HTML->boxBottom();
	plugin_hook_by_reference('features_boxes_bottom', $params);
	return $return;
}

function show_top_downloads() {
	global $HTML;
	// TODO yesterday is now defined as two days ago.  Quick fix
	//      to allow download list to be cached before nightly
	//      aggregation is done. jbyers 2001.03.19
	//
	$return = '' ;

	$res_topdown = db_query_params ('
		SELECT groups.group_id,
		groups.group_name,
		groups.unix_group_name,
		frs_dlstats_grouptotal_vw.downloads
		FROM frs_dlstats_grouptotal_vw,groups
		WHERE
		frs_dlstats_grouptotal_vw.group_id=groups.group_id AND groups.status=$1
		ORDER BY downloads DESC
	',
					array ('A'));

	// print each one
	$count = 0 ;
	while (($row_topdown=db_fetch_array($res_topdown)) && ($count < 10)) {
		if (!forge_check_perm ('project_read', $row_topdown['group_id'])) {
			continue ;
		}
		if ($row_topdown['downloads'] > 0) {
			$t_downloads = number_format($row_topdown['downloads']);
			$t_prj_link = util_make_link_g ($row_topdown['unix_group_name'], $row_topdown['group_id'], $row_topdown['group_name']);

			$return .= '<tr>';
			$return .= '<td class="width-stat-col1">' . $t_downloads . '</td>';
			$return .= '<td>' . $t_prj_link . '</td>';
			$return .= '</tr>';
			$count++ ;
		}
	}
	if ( $return == "" ) {
		return $HTML->warning_msg(_('No stats available.'));
	} else {
		$return = $HTML->listTableTop(). $return . $HTML->listTableBottom();
	}
	$return .= '<div class="align-center">' . util_make_link ('/top/', _('All the ranking'), array('class' => 'dot-link')) . '</div>';

	return $return;
}

function stats_getpageviews_total() {
	$res_count = db_query_params ('SELECT SUM(site_views) AS site, SUM(subdomain_views) AS subdomain FROM stats_site',
			array());
	if (db_numrows($res_count) > 0) {
		$row_count = db_fetch_array($res_count);
		return ($row_count['site'] + $row_count['subdomain']);
	} else {
		return "error";
	}
}

function stats_downloads_total() {
	$res_count = db_query_params ('SELECT SUM(downloads) AS downloads FROM stats_site',
			array());
	if (db_numrows($res_count) > 0) {
		$row_count = db_fetch_array($res_count);
		return $row_count['downloads'];
	} else {
		return "error";
	}
}

function show_sitestats() {
	$fforge = FusionForge::getInstance();
	$return = '<p>';

	$count_type = forge_get_config('sitestats_projects_count');
	if ($count_type == 'visible') {
		$arr = group_get_public_active_projects_asc();  // cf. softwaremap/full_list.php
		$count = count($arr);
	} else { //if ($count_type == 'all') {
		$count = $fforge->getNumberOfActiveProjects();
	}
	$return .= _('Hosted Projects')._(': ');
	$content = '<strong>'.number_format($count).'</strong>';

	if (forge_get_config('use_project_full_list')) {
		$return .= util_make_link('/softwaremap/full_list.php', $content);
	} else {
		$return .= $content;
	}
	$return .= "</p><p>";

	$return .= _('Registered Users')._(': ').'<strong>'.
		number_format($fforge->getNumberOfActiveUsers()).'</strong>';
	$return .= "</p>\n";
	return $return;
}

function show_newest_projects() {
	global $HTML;
	$res_newproj = db_query_params ('SELECT group_id,unix_group_name,group_name,register_time FROM groups WHERE status=$1 AND register_time > 0 ORDER BY register_time DESC', array ('A'));

	$return = '';

	$count = 0 ;
	while (($row_newproj=db_fetch_array($res_newproj)) && ($count < 10)) {
		if (!forge_check_perm ('project_read', $row_newproj['group_id'])) {
			continue ;
		}

		$count++ ;
		$t_prj_date = date(_('m/d'),$row_newproj['register_time']);
		$t_prj_link = util_make_link_g ($row_newproj['unix_group_name'],$row_newproj['group_id'],$row_newproj['group_name']);

		$return .= "<tr>";
		$return .= '<td class="width-stat-col1">' . $t_prj_date . "</td>";
		$return .= '<td>' . $t_prj_link . '</td>';
		$return .= "</tr>\n";
	}

	if ( $return == "" ) {
		return $HTML->warning_msg(_('No stats available.'));
	} else {
		$return = $HTML->listTableTop().$return.$HTML->listTableBottom();
	}

	if (forge_get_config('use_project_full_list')) {
		$return .= '<p class="align-center">'.util_make_link ('/softwaremap/full_list.php', _('All newest projects'), array('class' => 'dot-link')).'</p>';
	}
	return $return;
}

function show_highest_ranked_users() {
	global $HTML;
	//select out the users information to show the top users on the site
	$res = db_query_params('SELECT users.user_name,users.user_id,users.realname,user_metric.metric	FROM user_metric,users WHERE users.user_id=user_metric.user_id AND user_metric.ranking < 11 AND users.status != $1 ORDER BY ranking ASC',
				array ('D'));
	if (!$res) {
		return $HTML->error_msg(db_error());
	} else {
		$rows = db_numrows($res);
		if ($rows < 1) {
			return  $HTML->warning_msg(_('No stats available.'));
		} else {
			$return = '';
			for ($i=0; $i < $rows; $i++) {
				$return .= ($i+1).' - ('. number_format(db_result($res,$i,'metric'),4) .') '
				. util_make_link_u(db_result($res, $i, 'user_name'), db_result($res,$i,'realname'))
				.'<br />';
			}
		}
		$return .= '<div class="align-center">'.util_make_link('/top/topusers.php', _('All Users'), array('class' => 'dot-link')).'</div>';
		return $return;
	}
}

function show_highest_ranked_projects() {
	global $HTML;
	$stats = new Stats();
	$result = $stats->getMostActiveStats ('week', 0) ;
	$return = '' ;

	$count = 1 ;
	while (($row=db_fetch_array($result)) && ($count <= 20)) {
		if (!forge_check_perm ('project_read', $row['group_id'])) {
			continue ;
		}

		$t_prj_link = util_make_link_g ($row['unix_group_name'],$row['group_id'],$row['group_name']);

		$return .= "<tr>";
		$return .= '<td class="width-stat-col1">'. $count . "</td>";
		$return .= '<td>' . $t_prj_link . '</td>';
		$return .= "</tr>\n";

		$count++ ;
	}
	if ( $return == "" ) {
		return $HTML->warning_msg(_('No stats available.'));
	} else {
		$return = $HTML->listTableTop().$return.$HTML->listTableBottom();
	}

	$return .= '<div class="align-center">' . util_make_link ('/top/mostactive.php?type=week', _('All project activities'), array('class' => 'dot-link')) . '</div>';

	return $return;
}

function show_top_voted_project() {
	global $HTML;
	$stats = new Stats();
	$result = $stats->getTopVotedProjects();
	$return = '' ;
	$count = 1 ;
	while(($row = db_fetch_array($result)) && ($count <= 20)) {
		if (!forge_check_perm('project_read', $row['group_id'])) {
			continue ;
		}

		$t_prj_link = util_make_link_g($row['unix_group_name'], $row['group_id'], $row['group_name']);

		$return .= "<tr>";
		$return .= '<td class="width-stat-col1">'. $row['counter'] . "</td>";
		$return .= '<td>' . $t_prj_link . '</td>';
		$return .= "</tr>\n";

		$count++ ;
	}
	if ( $return == "" ) {
		return $HTML->warning_msg(_('No stats available.'));
	} else {
		return $HTML->listTableTop().$return.$HTML->listTableBottom();
	}
}
