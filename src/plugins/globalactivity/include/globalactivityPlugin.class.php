<?php
/**
 * globalactivityPlugin Class
 *
 * Copyright 2017, Franck Villaume - TrivialDev
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class globalactivityPlugin extends Plugin {
	public function __construct($id = 0) {
		parent::__construct($id);
		$this->name = "globalactivity";
		$this->text = "Global Activity";

		$this->_addHook('register_soap');
	}

	public function register_soap(&$params) {
		$server = &$params['server'];
		$uri = util_make_url();

		$server->wsdl->addComplexType(
			'GlobalActivityEntry',
			'complexType',
			'struct',
			'sequence',
			'',
			array(
				'group_id' => array('name'=>'group_id', 'type' => 'xsd:int'),
				'section' => array('name'=>'section', 'type' => 'xsd:string'),
				'ref_id' => array('name'=>'ref_id', 'type' => 'xsd:string'),
				'subref_id' => array('name'=>'subref_id', 'type' => 'xsd:string'),
				'description' => array('name'=>'description', 'type' => 'xsd:string'),
				'activity_date' => array('name'=>'activity_date', 'type' => 'xsd:int')
				)
			);

		$server->wsdl->addComplexType(
			'ArrayOfGlobalActivityEntry',
			'complexType',
			'array',
			'',
			'SOAP-ENC:Array',
			array(),
			array(array('ref'=>'SOAP-ENC:arrayType','wsdl:arrayType'=>'tns:GlobalActivityEntry[]')),
			'tns:GlobalActivityEntry');

		$server->register(
			'globalactivity_getActivity',
			array('session_ser'=>'xsd:string',
				  'begin'=>'xsd:int',
				  'end'=>'xsd:int',
				  'show'=>'tns:ArrayOfstring',),
			array('return'=>'tns:ArrayOfGlobalActivityEntry'),
			$uri,
			$uri.'#globalactivity_getActivity','rpc','encoded');

		$server->register(
			'globalactivity_getActivityForProject',
			array('session_ser'=>'xsd:string',
				  'begin'=>'xsd:int',
				  'end'=>'xsd:int',
				  'group_id'=>'xsd:int',
				  'show'=>'tns:ArrayOfstring',),
			array('return'=>'tns:ArrayOfGlobalActivityEntry'),
			$uri,
			$uri.'#globalactivity_getActivityForProject','rpc','encoded');
	}

	public function getData($begin, $end, &$show, &$ids, &$texts, $gid = NULL) {
		if ($begin > $end) {
			$tmp = $end;
			$end = $begin;
			$begin = $tmp;
		}

		if (forge_get_config('use_forum')) {
			$ids[]		= 'forumpost';
			$texts[]	= _('Forum Post');
		}

		if (forge_get_config('use_tracker')) {
			$ids[]		= 'trackeropen';
			$texts[]	= _('Tracker Opened');
			$ids[]		= 'trackerclose';
			$texts[]	= _('Tracker Closed');
		}

		if (forge_get_config('use_news')) {
			$ids[]		= 'news';
			$texts[]	= _('News');
		}

		if (forge_get_config('use_pm')) {
			$ids[]		= 'taskopen';
			$texts[]	= _('Tasks Opened');
			$ids[]		= 'taskclose';
			$texts[]	= _('Tasks Closed');
			$ids[]		= 'taskdelete';
			$texts[]	= _('Tasks Deleted');
		}

		if (forge_get_config('use_frs')) {
			$ids[]		= 'frsrelease';
			$texts[]	= _('FRS Release');
		}

		if (forge_get_config('use_docman')) {
			$ids[]		= 'docmannew';
			$texts[]	= _('New Documents');
			$ids[]		= 'docmanupdate';
			$texts[]	= _('Updated Documents');
			$ids[]		= 'docgroupnew';
			$texts[]	= _('New Directories');
		}

		if (count($show) < 1) {
			$section = $ids;
		} else {
			$section = $show;
		}

		$ffactivity = new Activity();
		$cached_perms = array();

		if ($gid) {
			$res = db_query_params('SELECT * FROM activity_vw WHERE activity_date BETWEEN $1 AND $2 AND section = ANY ($3) AND group_id = $4 ORDER BY activity_date DESC',
							   array($begin, $end, db_string_array_to_any_clause($section), $gid));
		} else {
			$res = db_query_params('SELECT * FROM activity_vw WHERE activity_date BETWEEN $1 AND $2 AND section = ANY ($3) ORDER BY activity_date DESC',
							   array($begin, $end, db_string_array_to_any_clause($section)));
		}

		if (db_error()) {
			exit_error(db_error(), 'home');
		}

		$results = array();
		while ($arr = db_fetch_array($res)) {
			$group_id = $arr['group_id'];
			if (!forge_check_perm('project_read', $group_id)) {
				continue;
			}
			if (!$ffactivity->check_perm_for_activity($arr, $cached_perms)) {
				continue;
			}
			$results[] = $arr;
		}

		if ($gid) {
			$res = db_query_params('SELECT group_id FROM groups WHERE status=$1 AND group_id=$2',
								   array('A', $gid));
		} else {
			$res = db_query_params('SELECT group_id FROM groups WHERE status=$1',
								   array('A'));
		}

		if (db_error()) {
			exit_error(db_error(), 'home');
		}

		// If plugins wants to add activities.
		while ($arr = db_fetch_array($res)) {
			if (!forge_check_perm('project_read', $arr['group_id'])) {
				continue;
			}
			$hookParams['group_id'] = $arr['group_id'];
			$hookParams['results'] = &$results;
			$hookParams['show'] = &$show;
			$hookParams['begin'] = $begin;
			$hookParams['end'] = $end;
			$hookParams['ids'] = &$ids;
			$hookParams['texts'] = &$texts;
			plugin_hook("activity", $hookParams);
		}

		if (count($show) < 1) {
			$show = $ids;
		}

		foreach ($show as $showthis) {
			if (array_search($showthis, $ids) === false) {
				throw new Exception(_('Invalid Data Passed to query'));
			}
		}

		$res2 = array();
		foreach ($results as $arr) {
			$group_id = $arr['group_id'];
			if (!forge_check_perm('project_read', $group_id)) {
				continue;
			}
			if (!$ffactivity->check_perm_for_activity($arr, $cached_perms)) {
				continue;
			}
			$res2[] = $arr;
		}

		usort($res2, 'Activity::date_compare');

		return $res2;
	}
}

function &globalactivity_getActivity($session_ser,$begin,$end,$show=array()) {
	continue_session($session_ser);

	$plugin = plugin_get_object('globalactivity');
	if (!forge_get_config('use_activity')
		|| !$plugin) {
		$fault = new soap_fault ('','globalactivity_getActivity','Global activity not available','Global activity not available');
		return $fault;
	}

	$ids = array();
	$texts = array();

	try {
		$results = $plugin->getData($begin,$end,$show,$ids,$texts);
	} catch (Exception $e) {
		$msg = "Error in global activity: ".$e->getMessage();
		return new soap_fault ('','globalactivity_getActivity',$msg,$msg);
	}

	$keys = array(
		'group_id',
		'section',
		'ref_id',
		'subref_id',
		'description',
		'activity_date',
		);


	$res2 = array();
	foreach ($results as $res) {
		$r = array();

		foreach ($keys as $k) {
			$r[$k] = $res[$k];
		}
		$res2[] = $r;
	}

	return $res2;
}

function &globalactivity_getActivityForProject($session_ser,$begin,$end,$group_id,$show=array()) {
	continue_session($session_ser);

	$plugin = plugin_get_object('globalactivity');
	if (!forge_get_config('use_activity')
		|| !$plugin) {
		return new soap_fault ('','globalactivity_getActivity','Global activity not available','Global activity not available');
	}

	$ids = array();
	$texts = array();

	try {
		$results = $plugin->getData($begin,$end,$show,$ids,$texts,$group_id);
	} catch (Exception $e) {
		$msg = "Error in global activity: ".$e->getMessage();
		return new soap_fault ('','globalactivity_getActivity',$msg,$msg);
	}

	$keys = array(
		'group_id',
		'section',
		'ref_id',
		'subref_id',
		'description',
		'activity_date',
		);


	$res2 = array();
	foreach ($results as $res) {
		$r = array();

		foreach ($keys as $k) {
			$r[$k] = $res[$k];
		}
		$res2[] = $r;
	}

	return $res2;
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
