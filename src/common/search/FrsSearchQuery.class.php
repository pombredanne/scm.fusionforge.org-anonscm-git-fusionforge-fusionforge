<?php
/**
 * FusionForge search engine
 *
 * Copyright 2004, Dominik Haas
 * Copyright 2009, Roland Mas
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
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

require_once $gfcommon.'search/SearchQuery.class.php';

class FrsSearchQuery extends SearchQuery {

	/**
	 * group id
	 *
	 * @var int $groupId
	 */
	var $groupId;

	/**
	 * flag if non public items are returned
	 *
	 * @var bool $showNonPublic
	 */
	var $showNonPublic;

	/**
	 * @param	string	$words		words we are searching for
	 * @param	int	$offset		offset
	 * @param	bool	$isExact	if we want to search for all the words or if only one matching the query is sufficient
	 * @param	int	$groupId	group id
	 * @param	string	$sections	sections to search in
	 * @param	bool	$showNonPublic
	 */
	function __construct($words, $offset, $isExact, $groupId, $sections = SEARCH__ALL_SECTIONS, $showNonPublic = false) {
		$this->groupId = $groupId;
		$this->showNonPublic = $showNonPublic;

		parent::__construct($words, $offset, $isExact);

		$this->setSections($sections);
	}

	/**
	 * getQuery - get the query built to get the search results
	 *
	 * @return	array	query+params array
	 */
	function getQuery() {
		$qpa = db_construct_qpa(false, 'SELECT ts_headline(frs_package.name, q) AS package_name, ts_headline(frs_release.name, q) as release_name, frs_release.release_date, frs_release.release_id, frs_status.name as status_name, users.realname, frs_release.package_id FROM frs_file, frs_release LEFT OUTER JOIN frs_status USING(status_id), users, frs_package, to_tsquery($1) AS q, frs_release_idx r, frs_file_idx f WHERE frs_release.released_by = users.user_id AND r.release_id = frs_release.release_id AND f.file_id = frs_file.file_id AND frs_package.package_id = frs_release.package_id AND frs_file.release_id=frs_release.release_id AND frs_package.group_id=$2 ',
						 array($this->getFTIwords(), $this->groupId));
		if ($this->sections != SEARCH__ALL_SECTIONS) {
			$qpa = db_construct_qpa($qpa, 'AND frs_package.package_id = ANY ($1) ',
							array(db_int_array_to_any_clause ($this->sections)));
		}
		if (!$this->showNonPublic) {
			$qpa = db_construct_qpa($qpa, 'AND is_public = 1 ');
		}
		$qpa = db_construct_qpa($qpa, 'AND (f.vectors @@ q OR r.vectors @@ q) ');
		if(count($this->phrases)) {
			$qpa = db_construct_qpa($qpa, 'AND ((');
			$qpa = $this->addMatchCondition($qpa, 'frs_release.changes');
			$qpa = db_construct_qpa($qpa, ') OR (');
			$qpa = $this->addMatchCondition($qpa, 'frs_release.notes');
			$qpa = db_construct_qpa($qpa, ') OR (');
			$qpa = $this->addMatchCondition($qpa, 'frs_release.name');
			$qpa = db_construct_qpa($qpa, ') OR (');
			$qpa = $this->addMatchCondition($qpa, 'frs_file.filename');
			$qpa = db_construct_qpa($qpa, ')) ');
		}

		$qpa = db_construct_qpa($qpa, ' ORDER BY frs_package.name, frs_release.name');

		return $qpa ;
	}

	/**
	 * getSections - returns the list of available forums
	 *
	 * @param	int	$groupId	group id
	 * @param	bool	$showNonPublic	if we should consider non public sections
	 * @return	array
	 */
	static function getSections($groupId, $showNonPublic) {
		$sql = 'SELECT package_id, name FROM frs_package WHERE group_id=$1';
		if(!$showNonPublic) {
			$sql .= ' AND is_public=1';
		}
		$sql .= ' ORDER BY name';

		$sections = array();
		$res = db_query_params ($sql,
					array ($groupId));
		while($data = db_fetch_array($res)) {
			$sections[$data['package_id']] = $data['name'];
		}
		return $sections;
	}

	function isRowVisible($row) {
		return forge_check_perm('frs', $row['package_id'], 'read');
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
