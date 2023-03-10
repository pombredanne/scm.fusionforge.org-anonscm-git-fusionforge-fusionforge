<?php
/**
 * FusionForge forums
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2017, Franck Villaume - TrivialDev
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

require_once $gfcommon.'include/FFError.class.php';
require_once $gfcommon.'forum/Forum.class.php';

class ForumFactory extends FFError {

	/**
	 * The Group object.
	 *
	 * @var	object	$Group.
	 */
	var $Group;

	/**
	 * The forums array.
	 *
	 * @var	array	forums.
	 */
	var $forums;

	/**
	 * @param	object	$Group		The Group object to which this forum is associated.
	 * @param	bool	$skip_check
	 */
	function __construct(&$Group, $skip_check = false) {
		parent::__construct();
		if (!$Group || !is_object($Group)) {
			$this->setError(_('Invalid Project'));
			return;
		}
		if ($Group->isError()) {
			$this->setError(_('Forums')._(': ').$Group->getErrorMessage());
			return;
		}
		if (!$skip_check && !$Group->usesForum()) {
			$this->setError(sprintf(_('%s does not use the Forum tool.'),
				$Group->getPublicName()));
			return;
		}
		$this->Group =& $Group;
	}

	/**
	 * getGroup - get the Group object this ForumFactory is associated with.
	 *
	 * @return	object	The Group object.
	 */
	function &getGroup() {
		return $this->Group;
	}

	function &getAllForumIds() {
		$result = array();
		$res = db_query_params('SELECT group_forum_id FROM forum_group_list
					WHERE group_forum_id NOT IN (
						SELECT group_forum_id FROM forum_group_list WHERE group_forum_id IN (
							SELECT forum_id FROM news_bytes))
					AND group_id=$1
					ORDER BY group_forum_id',
					array($this->Group->getID()));
		if (!$res) {
			return $result;
		}
		while ($arr = db_fetch_array($res)) {
			$result[] = $arr['group_forum_id'];
		}
		return $result;
	}

	function &getAllForumIdsWithNews() {
		$result = array();
		$res = db_query_params('SELECT group_forum_id FROM forum_group_list WHERE group_id=$1 ORDER BY group_forum_id',
					array($this->Group->getID()));
		if (!$res) {
			return $result;
		}
		while ($arr = db_fetch_array($res)) {
			$result[] = $arr['group_forum_id'];
		}
		return $result;
	}

	/**
	 * getForums - get an array of Forum objects for this Group.
	 *
	 * @return	array	The array of Forum objects.
	 */
	function &getForums() {
		if ($this->forums) {
			return $this->forums;
		}

		$this->forums = array();
		$ids = $this->getAllForumIds();

		if (!empty($ids) ) {
			foreach ($ids as $id) {
				if (forge_check_perm ('forum', $id, 'read')) {
					$this->forums[] = new Forum($this->Group, $id);
				}
			}
		}
		return $this->forums;
	}

	/**
	 * getForumsAdmin - get an array of all (public, private and suspended) Forum objects for this Group.
	 *
	 * @return	array	The array of Forum objects.
	 */
	function &getForumsAdmin() {
		if (session_loggedin()) {
			if (!forge_check_perm ('forum_admin', $this->Group->getID())) {
				$this->setError(_('You are not allowed to access this page'));
				$this->forums = false;
			} else {
				if ($this->forums) {
					return $this->forums;
				}
				$result = db_query_params('SELECT * FROM forum_group_list_vw
							WHERE group_id=$1
							ORDER BY group_forum_id',
							array($this->Group->getID()));
			}
		} else {
			$this->setError(_('You are not allowed to access this page'));
			$this->forums = false;
		}

		if (isset($result)) {
			if (!$result) {
				$this->setError(db_error());
				$this->forums = false;
			} else {
				$rows = db_numrows($result);
				if ($rows <= 0) {
					$this->setError(_('No forums found.'));
					$this->forums = false;
				} else {
					while ($arr = db_fetch_array($result)) {
						$this->forums[] = new Forum($this->Group, $arr['group_forum_id'], $arr);
					}
				}
			}
		}
		return $this->forums;
	}

	/**
	 * moveThread - move thread in another forum
	 *
	 * @param	$group_forum_id
	 * @param	$thread_id
	 * @param	bool $old_forum_id
	 *
	 * Note: old forum ID is useless if forum_agg_msg_count table is no longer used
	 *
	 * @return bool success.
	 */
	function moveThread($group_forum_id,$thread_id,$old_forum_id = false) {
		$res = db_query_params('UPDATE forum SET group_forum_id=$1 WHERE thread_id=$2',
					array($group_forum_id, $thread_id));
		if (!$res) {
			$this->setError(db_error());
			return false;
		} else {
			$msg_count = db_affected_rows($res);
			if ($msg_count < 1) {
				$this->setError(_("Thread not found"));
				return false;
			}
		}

		if ($old_forum_id !== false) {
			// Update forum_agg_msg_count table
			// Note: if error(s) are raised it's certainly because forum_agg_msg_count
			//		is no longer used and updated. So, error(s) are not catched
			// Update row of old forum id
			$res = db_query_params('SELECT count FROM forum_agg_msg_count WHERE group_forum_id=$1',
						array($old_forum_id));
			if ($res && db_numrows($res)) {
				// Update row
				$count = db_result($res, 0, 'count');
				$count -= $msg_count;
				if ($count < 0) {
					$count = 0;
				}
				db_query_params('UPDATE forum_agg_msg_count SET count=$1 WHERE group_forum_id=$2',
							array($count, $old_forum_id));
			} else {
				// Error because row doesn't exist... insert it
				$res = db_query_params('SELECT COUNT(*) AS count FROM forum WHERE group_forum_id=$1',
							array($old_forum_id));
				if ($res && db_numrows($res)) {
					$count = db_result($res, 0, 'count');
					db_query_params('INSERT INTO forum_agg_msg_count (group_forum_id, count) VALUES ($1,$2)',
								array($old_forum_id, $count));
				}
			}

			// Update row of new forum id
			$res = db_query_params('SELECT count FROM forum_agg_msg_count WHERE group_forum_id=$1',
						array($group_forum_id));
			if ($res && db_numrows($res)) {
				// Update row
				$count = db_result($res, 0, 'count');
				$count += $msg_count;
				db_query_params('UPDATE forum_agg_msg_count SET count=$1 WHERE group_forum_id=$2',
							array($count, $group_forum_id));
			} else {
				// Insert row
				db_query_params('INSERT INTO forum_agg_msg_count (group_forum_id, count) VALUES ($1,$2)',
							array($group_forum_id, $msg_count));
			}
		}
		return true;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
