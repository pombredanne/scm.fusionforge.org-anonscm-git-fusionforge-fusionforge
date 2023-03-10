<?php
/**
 * Copyright (C) 2008-2009 Alcatel-Lucent
 * Copyright 2016, Franck Villaume - TrivialDev
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

/**
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The Tag Cloud ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

$NB_MAX = 20;
$NB_SIZE = 5;
$CLASS_PREFIX = 'tag';
$SELECTED_STYLE = 'text-decoration:overline underline';

/**
 * tag_cloud() - This function displays a tag cloug with the tags defined by projects.
 * 				 The size of each tag depends of its frequency.
 * 				 Each tag is a link to display a list of projects where the tag is defined.
 * 				 Delta between two font sizes is constant :
 * 				 function is F(count) = size = A * count + B
 * 				 If:
 * 				 Smin = size min
 *               Smax = size max
 *				 Cmin = count min
 * 				 Cmax = count max
 * 				 So:
 * 				 Smin = A * Cmin + B
 * 				 Smax = A * Cmax + B
 * 				 A = (Smax - Smin) / (Cmax - Cmin)
 * 				 B = Smin - A * Cmin
 * 				 If Smin = 1 then Smax = number of size = N
 * 				 So:
 * 				 A = (N - 1) / (Cmax - Cmin)
 * 				 B = 1 - A * Cmin
 * 				 F(count) = size = A * count + 1 - A * Cmin
 * 				 => size = 1 + (count  - Cmin) * A
 *
 * @param	array	$params	selected tag, max tag displayed, number of sizes available,
 * 							class prefix for css class, style for selected tag
 * @return	string
 */
function tag_cloud($params = array()) {
	global $NB_SIZE;
	global $NB_MAX;
	global $CLASS_PREFIX;
	global $SELECTED_STYLE;
	global $HTML;

	if (!is_array($params)) {
		$params = array();
	}
	if (!isset($params['selected'])) {
		$params['selected'] = '';
	}
	if (!isset($params['nb_max'])) {
		$params['nb_max'] = $NB_MAX;
	}
	if (!isset($params['nb_size'])) {
		$params['nb_size'] = $NB_SIZE;
	}
	if (!isset($params['class_prefix'])) {
		$params['class_prefix'] = $CLASS_PREFIX;
	}
	if (!isset($params['selected_style'])) {
		$params['selected_style'] = $SELECTED_STYLE;
	}

	$return = '';

	// count tag occurrence
	$res = db_query_params('SELECT project_tags.name,project_tags.group_id
					 FROM project_tags, groups
					 WHERE project_tags.group_id = groups.group_id
					 AND groups.status = $1 AND groups.register_time > 0',
				array('A'));
	$tag_count = array();
	while ($row = db_fetch_array($res)) {
		if (forge_check_perm ('project_read', $row['group_id'])) {
			$tagname = $row['name'];
			if (!isset ($tag_count[$tagname])) {
				$tag_count[$tagname] = 0;
			}
			$tag_count[$tagname]++;
		}
	}

	// order tags by popularity
	$count_to_tags = array();
	foreach ($tag_count as $name => $count) {
		if (!isset($count_to_tags[$count])) {
			$count_to_tags[$count] = array();
		}
		$count_to_tags[$count][] = $name;
	}

	$available_counts = array_keys($count_to_tags);
	rsort($available_counts, SORT_NUMERIC);

	if (!empty($tag_count)) {
		$count_min = 0;
		$count_max = 0;
		$nb = 0;
		foreach ($available_counts as $count) {
			if ($count_min == 0 || $count < $count_min) {
				$count_min = $count;
			}
			if ($count > $count_max) {
				$count_max = $count;
			}
			$nb = $nb + count($count_to_tags[$count]);
			if ($params['nb_max'] && $nb >= $params['nb_max']) {
				break; // no limit if nb_max == 0
			}
		}

		// Compute 'A' parameter of the function
		if ($count_max != $count_min) {// else we have a division by zero
			$a = ($params['nb_size'] - 1) / ($count_max - $count_min);
		} else {
			// Set value 0 for 'A' parameter just for initialised variable
			// but it's not realy necessary because if $count_max == $count_min
			// then $count - $count_min = 0 (see below)
			$a = 0;
		}

		ksort($tag_count, SORT_STRING);
		foreach ($tag_count as $name => $count) {
			if ($count < $count_min) {
				continue;
			}
			$size = intval(1 + ($count - $count_min) * $a);
			$linkAttr = array();
			$linkAttr['class'] = $params['class_prefix'] . $size;
			if ($name == $params['selected']) {
				$linkAttr['style'] = $params['selected_style'];
			}
			$return .= util_make_link('/softwaremap/tag_cloud.php?tag='.urlencode($name), htmlspecialchars($name), $linkAttr);
		}
	}

	if (!strlen($return)) {
		$return .= $HTML->warning_msg(_('No used tags.'));
	}

	return $return;
}

/**
 * list_project_tag() - Returns the list of the tags defined by the project.
 * 						Each tag is a link to display a list of projects
 * 						where the tag is defined.
 *
 * @param	int	Group ID
 * @return	string
 */
function list_project_tag($group_id) {
	$req = 'SELECT name FROM project_tags WHERE group_id = $1';
	$res = db_query_params($req, array($group_id));
	$nb_tag = db_numrows($res);
	$return = '';
	$idx = 1;
	if ($nb_tag) {
		while ($row = db_fetch_array($res)) {
			$return .= util_make_link('/softwaremap/tag_cloud.php?tag='.urlencode($row['name']), htmlspecialchars($row['name']))
				. (($idx < $nb_tag) ? ', ' : '');
			$idx++;
		}
	}

	return $return;
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
