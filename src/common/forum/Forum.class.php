<?php
/**
 * FusionForge forums
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013-2014, Franck Villaume - TrivialDev
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
require_once $gfcommon.'forum/ForumMessage.class.php';
require_once $gfcommon.'include/MonitorElement.class.php';

// This string is used when sending the notification mail for identifying the
// user response
define('FORUM_MAIL_MARKER', '#+#+#+#+#+#+#+#+#+#+#+#+#+#+#+#+#+');

/**
 * Gets a Forum object from its id
 *
 * @param	int	$forum_id	the Forum id
 * @return	object	the Forum object
 */
function &forum_get_object($forum_id) {
	$res = db_query_params('SELECT group_id FROM forum_group_list WHERE group_forum_id=$1',
				array($forum_id));
	if (!$res || db_numrows($res) < 1) {
		return NULL;
	}

	$data = db_fetch_array($res);
	$Group = group_get_object($data['group_id']);
	$f = new Forum($Group, $forum_id);

	$f->fetchData($forum_id);

	return $f;
}

function forum_get_groupid ($forum_id) {
	$res = db_query_params('SELECT group_id FROM forum_group_list WHERE group_forum_id=$1',
				array($forum_id));
	if (!$res || db_numrows($res) < 1) {
		return false;
	}
	$arr = db_fetch_array($res);
	return $arr['group_id'];
}

class Forum extends FFError {

	/**
	 * Associative array of data from db.
	 *
	 * @var	array	$data_array.
	 */
	var $data_array;

	/**
	 * The Group object.
	 *
	 * @var	object	$Group.
	 */
	var $Group;

	/**
	 * An array of 'types' for this forum - nested, flat, ultimate, etc.
	 *
	 * @var	array	view_types.
	 */
	var $view_types;

	/**
	 * flag : forum or news entry
	 * @var int	is_news
	 */
	var $is_news;
	
	var $save_date;

	/**
	 * @param	$Group
	 * @param	bool	$group_forum_id
	 * @param	bool	$arr
	 * @param	bool	$is_news
	 */
	function __construct(&$Group, $group_forum_id = false, $arr = false, $is_news = false) {
		parent::__construct();
		if (!$Group || !is_object($Group)) {
			$this->setError(_('Invalid Project'));
			return;
		}
		if ($Group->isError()) {
			$this->setError('Forums: '.$Group->getErrorMessage());
			return;
		}

		if ($group_forum_id) {
			//
			//	Is this a news posting (or a real forum)?
			//
			$res = db_query_params('SELECT forum_id FROM news_bytes
				WHERE forum_id=$1',
				array($group_forum_id));
			$is_news = $res && db_numrows($res) >= 1;
		}

		$this->Group =& $Group;

		if ($group_forum_id) {
			if (!$arr || !is_array($arr)) {
				if (!$this->fetchData($group_forum_id)) {
					return;
				}
			} else {
				$this->data_array =& $arr;
				if ($this->data_array['group_id'] != $this->Group->getID()) {
					$this->setError(_('group_id in db result does not match Group Object'));
					$this->data_array = null;
					return;
				}
			}
			//
			//	Make sure they can even access this object
			//
			if (!$is_news &&
				!forge_check_perm ('forum', $this->getID(), 'read')) {
				$this->setPermissionDeniedError();
				$this->data_array = null;
				return;
			}
		}
		$this->view_types[] = 'ultimate';
		$this->view_types[] = 'flat';
		$this->view_types[] = 'nested';
		$this->view_types[] = 'threaded';
		$this->is_news = $is_news;
	}

	/**
	 * create - use this function to create a new entry in the database.
	 *
	 * @param	string	$forum_name		The name of the forum.
	 * @param	string	$description		The description of the forum.
	 * @param	string	$send_all_posts_to	The email address to send all new posts to.
	 * @param	int	$create_default_message	Pass (1) if a welcome message should be created (0) for no welcome message.
	 * @return	bool	success.
	 */
	function create($forum_name, $description, $send_all_posts_to = '', $create_default_message = 1) {
		if (!$this->is_news && strlen(trim($forum_name)) < 3) {
			$this->setError(_('Forum name must be at least 3 characters.'));
			return false;
		}
		if (!$this->is_news && strlen(trim($description)) < 10) {
			$this->setError(_('Forum description must be at least 10 characters.'));
			return false;
		}
		if (!preg_match('/^([_\.0-9a-z-])*$/i',$forum_name)) {
			if (preg_match('/ /',$forum_name)){
				$this->setError(_('Illegal characters in Forum name.').' - '._('No space allowed.'));
			} else {
				$this->setError(_('Illegal characters in Forum name.'));
			}
			return false;
		}
		if ($send_all_posts_to) {
			$send_all_posts_to = str_replace(';', ',', $send_all_posts_to);
			$invalid_mails = validate_emails($send_all_posts_to);
			if (count($invalid_mails) > 0) {
				$this->setInvalidEmailError($send_all_posts_to);
				return false;
			}
		}

		$project_name = $this->Group->getUnixName();
		$result_list_samename = db_query_params('SELECT 1 FROM mail_group_list WHERE list_name=$1 AND group_id=$2',

							array($project_name.'-'.strtolower($forum_name),
								$this->Group->getID()));

		if (db_numrows($result_list_samename) > 0){
			$this->setError(_('Mailing List exists with same name.'));
			return false;
		}

		// This is a hack to allow non-site-wide-admins to post
		// news.  The news/submit.php checks for proper permissions.
		// This needs to be revisited.

		if ($this->Group->getID() == GROUP_IS_NEWS) {
			// Future check will be added.

		} else {
			// Current permissions check.
			if (!forge_check_perm ('forum_admin', $this->Group->getID())) {
				$this->setPermissionDeniedError();
				return false;
			}
		}

		db_begin();
		$result = db_query_params('INSERT INTO forum_group_list (group_id,forum_name,description,send_all_posts_to) VALUES ($1,$2,$3,$4)',
					  array($this->Group->getID(),
						strtolower($forum_name),
						htmlspecialchars($description),
						$send_all_posts_to));
		if (!$result) {
			$this->setError(_('Error Adding Forum')._(': ').db_error());
			db_rollback();
			return false;
		}
		$group_forum_id = db_insertid($result,'forum_group_list','group_forum_id');
		$this->fetchData($group_forum_id);

		if ($create_default_message) {
			$fm=new ForumMessage($this);
			// Use the system side default language
			setup_gettext_from_sys_lang();
			$string = sprintf(_('Welcome to %s'), $forum_name);
			// and switch back to the user preference
			setup_gettext_from_context();
			if (!$fm->create($string, $string)) {
				$this->setError($fm->getErrorMessage());
				db_rollback();
				return false;
			}
		}
		$this->Group->normalizeAllRoles () ;
		db_commit();
		return true;
	}

	/**
	 * fetchData - re-fetch the data for this forum from the database.
	 *
	 * @param	int	$group_forum_id	The forum_id.
	 * @return	bool	success.
	 */
	function fetchData($group_forum_id) {
		$res=db_query_params('SELECT * FROM forum_group_list_vw	WHERE group_forum_id=$1 AND group_id=$2',
			array($group_forum_id, $this->Group->getID()));
		if (!$res || db_numrows($res) < 1) {
			$this->setError(_('Invalid forum group identifier.'));
			return false;
		}
		$this->data_array = db_fetch_array($res);
		db_free_result($res);
		return true;
	}

	/**
	 * getGroup - get the Group object this ArtifactType is associated with.
	 *
	 * @return	object	The Group object.
	 */
	function &getGroup() {
		return $this->Group;
	}

	/**
	 * getID - The id of this forum.
	 *
	 * @return	int	The group_forum_id #.
	 */
	function getID() {
		return $this->data_array['group_forum_id'];
	}

	/**
	 * getNextThreadID - The next thread_id for a new top in this forum.
	 *
	 * @return	int	The next thread_id #.
	 */
	function getNextThreadID() {
		$result = db_query_params('SELECT nextval($1)',
					  array('forum_thread_seq'));
		if (!$result || db_numrows($result) < 1) {
			echo db_error();
			return false;
		}
		return db_result($result, 0, 0);
	}

	/**
	 * getUnixName - returns the name used by email gateway
	 *
	 * @return	string	unix name
	 */
	function getUnixName() {
		return $this->Group->getUnixName().'-'.$this->getName();
	}

	/**
	 * getSavedDate - The unix time when the person last hit "save my place".
	 *
	 * @return	int	The unix time.
	 */
	function getSavedDate() {
		if (isset($this->save_date)) {
			return $this->save_date;
		} else {
			if (session_loggedin()) {
				$result = db_query_params('SELECT save_date FROM forum_saved_place WHERE user_id=$1 AND forum_id=$2',
							  array(user_getid(),
								$this->getID()));
				if ($result && db_numrows($result) > 0) {
					$this->save_date=db_result($result, 0, 'save_date');
					return $this->save_date;
				} else {
					//highlight new messages from the past week only
					$this->save_date=(time()-604800);
					return $this->save_date;
				}
			} else {
				//highlight new messages from the past week only
				$this->save_date=(time()-604800);
				return $this->save_date;
			}
		}
	}

	/**
	 * getName - get the name of this forum.
	 *
	 * @return	string	The name of this forum.
	 */
	function getName() {
		return $this->data_array['forum_name'];
	}

	/**
	 * getSendAllPostsTo - an optional email address to send all forum posts to.
	 *
	 * @return	string	The email address.
	 */
	function getSendAllPostsTo() {
		return $this->data_array['send_all_posts_to'];
	}

	/**
	 * getDescription - the description of this forum.
	 *
	 * @return	string	The description.
	 */
	function getDescription() {
		return $this->data_array['description'];
	}

	/**
	 * getMessageCount - the total number of messages in this forum.
	 *
	 * @return	int	The count.
	 */
	function getMessageCount() {
		return $this->data_array['total'];
	}

	/**
	 * getThreadCount - the total number of threads in this forum.
	 *
	 * @return	int	The count.
	 */
	function getThreadCount() {
		return $this->data_array['threads'];
	}

	/**
	 * getMostRecentDate - the most recent date of a post to this board.
	 *
	 * @return	int	The most recent date.
	 */
	function getMostRecentDate() {
		return $this->data_array['recent'];
	}

	/**
	 * getMonitoringIDs - return an array of user_id's for those monitoring this forum.
	 *
	 * @return	array	The array of user_id's.
	 */
	function getMonitoringIDs() {
		$MonitorElementObject = new MonitorElement('forum');
		return $MonitorElementObject->getMonitorUsersIdsInArray($this->getID());
	}

	/**
	 * getReturnEmailAddress() - return the return email address for notification emails
	 *
	 * @return	string	return email address
	 */
	function getReturnEmailAddress() {

		if(forge_get_config('use_gateways')) {
			$address = $this->getUnixName();
		} else {
			$address = 'noreply';
		}
		$address .= '@';
		if(forge_get_config('use_gateways') && forge_get_config('forum_return_domain')) {
			$address .= forge_get_config('forum_return_domain');
		} else {
			$address .= forge_get_config('web_host');
		}
		return $address;
	}

	/**
	 * setMonitor - Add the current user to the list of people monitoring the forum.
	 *
	 * @param	int	$u user id of the user which will be set to monitor this forum. Defaults to 0, meaning the current logged in user will be used.
	 * @return	bool	success.
	 */
	function setMonitor($u = -1) {
		if ($u == -1) {
			if (!session_loggedin()) {
				$this->setError(_('You can only monitor if you are logged in.'));
				return false;
			}
			$u = user_getid();
		}
		$MonitorElementObject = new MonitorElement('forum');
		if (!$MonitorElementObject->enableMonitoringByUserId($this->getID(), $u)) {
			$this->setError($MonitorElementObject->getErrorMessage());
			return false;
		}
		return true;
	}

	/**
	 * stopMonitor - Remove the current user from the list of people monitoring the forum.
	 *
	 * @param	$u
	 * @return	bool	success.
	 */
	function stopMonitor($u = -1) {
		if ($u == -1) {
			if (!session_loggedin()) {
				$this->setError(_('You can only monitor if you are logged in.'));
				return false;
			}
			$u = user_getid();
		}
		$MonitorElementObject = new MonitorElement('forum');
		if (!$MonitorElementObject->disableMonitoringByUserId($this->getID(), $u)) {
			$this->setError($MonitorElementObject->getErrorMessage());
			return false;
		}
		return true;
	}

	/**
	 * isMonitoring - See if the current user is in the list of people monitoring the forum.
	 *
	 * @return	bool	is_monitoring.
	 */
	function isMonitoring() {
		if (!session_loggedin()) {
			return false;
		}
		$MonitorElementObject = new MonitorElement('forum');
		return $MonitorElementObject->isMonitoredByUserId($this->getID(), user_getid());
	}

	/**
	 * savePlace - set a unix time into the database for this user, so future messages can be highlighted.
	 *
	 * @return	bool	success.
	 */
	function savePlace() {
		if (!session_loggedin()) {
			$this->setError(_('You can only save your place if you are logged in.'));
			return false;
		}
		$result = db_query_params('SELECT * FROM forum_saved_place WHERE user_id=$1 AND forum_id=$2',
					  array(user_getid(),
						$this->getID()));

		if (!$result || db_numrows($result) < 1) {
			/*
				User is not already monitoring thread, so
				insert a row so monitoring can begin
			*/
			$result = db_query_params('INSERT INTO forum_saved_place (forum_id,user_id,save_date) VALUES ($1,$2,$3)',
						  array($this->getID(),
							user_getid(),
							time()));

			if (!$result) {
				$this->setError('Forum::savePlace()'._(': ').db_error());
				return false;
			}

		} else {
			$result = db_query_params('UPDATE forum_saved_place SET save_date=$1 WHERE user_id=$2 AND forum_id=$3',
						  array(time(),
							user_getid(),
							$this->getID()));

			if (!$result) {
				$this->setError('Forum::savePlace() '.db_error());
				return false;
			}
		}
		return true;
	}

	/**
	 * update - use this function to update an entry in the database.
	 *
	 * @param	string	$forum_name The name of the forum.
	 * @param	string	$description The description of the forum.
	 * @param	string	$send_all_posts_to The email address to send all new posts to.
	 * @return	bool	success.
	 */
	function update($forum_name, $description, $send_all_posts_to = '') {
		if (strlen($forum_name) < 3) {
			$this->setError(_('Forum name must be at least 3 characters.'));
			return false;
		}
		if (strlen($description) < 10) {
			$this->setError(_('Forum description must be at least 10 characters.'));
			return false;
		}
		if (!preg_match('/^([_\.0-9a-z-])*$/i',$forum_name)) {
			if (preg_match('/ /',$forum_name)){
				$this->setError(_('Illegal characters in Forum name.').' - '._('No space allowed.'));
			} else {
				$this->setError(_('Illegal characters in Forum name.'));
			}
			return false;
		}
		if ($send_all_posts_to) {
			$send_all_posts_to = str_replace(';', ',', $send_all_posts_to);
			$invalid_mails = validate_emails($send_all_posts_to);
			if (count($invalid_mails) > 0) {
				$this->setInvalidEmailError($send_all_posts_to);
				return false;
			}
		}

		if (!forge_check_perm('forum_admin', $this->Group->getID())) {
			$this->setPermissionDeniedError();
			return false;
		}

		$project_name = $this->Group->getUnixName();
		$result_list_samename = db_query_params('SELECT 1 FROM mail_group_list WHERE list_name=$1 AND group_id=$2',

							array($project_name.'-'.strtolower($forum_name),
								$this->Group->getID()));

		if (db_numrows($result_list_samename) > 0){
			$this->setError(_('Mailing List exists with same name.'));
			return false;
		}

		$res = db_query_params('UPDATE forum_group_list SET
			forum_name=$1,
			description=$2,
			send_all_posts_to=$3
			WHERE group_id=$4
			AND group_forum_id=$5',
					array(strtolower($forum_name),
						  htmlspecialchars($description),
						  $send_all_posts_to,
						  $this->Group->getID(),
						  $this->getID()));

		if (!$res || db_affected_rows($res) < 1) {
			$this->setError(_('Update failed')._(': ').db_error());
			return false;
		}
		return true;
	}

	/**
	 * delete - delete this forum and all its related data.
	 *
	 * @param	bool	$sure		I'm Sure.
	 * @param	bool	$really_sure	I'm REALLY sure.
	 * @return	bool	true/false;
	 */
	function delete($sure, $really_sure) {
		if (!$sure || !$really_sure) {
			$this->setMissingParamsError(_('Please tick all checkboxes.'));
			return false;
		}
		if (!forge_check_perm('forum_admin', $this->Group->getID())) {
			$this->setPermissionDeniedError();
			return false;
		}
		db_begin();
		$result = db_query_params('DELETE FROM forum_agg_msg_count WHERE group_forum_id=$1',
				array($this->getID()));
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}

		$MonitorElementObject = new MonitorElement('forum');
		$result = $MonitorElementObject->clearMonitor($this->getID());
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}

		$result = db_query_params('DELETE FROM forum_saved_place WHERE forum_id=$1',
				array($this->getID()));
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}

		$result = db_query_params('DELETE FROM forum_attachment WHERE msg_id IN (SELECT msg_id from forum where group_forum_id=$1)',
					array($this->getID()));
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}
		# TODO: remove attachments from the filesystem

		$result = db_query_params('DELETE FROM forum WHERE group_forum_id=$1',
				array($this->getID()));
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}

		$result = db_query_params('DELETE FROM forum_group_list WHERE group_forum_id=$1',
				 array($this->getID()));
		if (!$result) {
			$this->setError(_('Error Deleting Forum')._(': ').db_error());
			db_rollback();
			return false;
		}

		db_commit();

		$this->Group->normalizeAllRoles();

		return true;
	}

}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
