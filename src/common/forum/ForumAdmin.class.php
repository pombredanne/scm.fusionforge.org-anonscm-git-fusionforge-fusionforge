<?php
/**
 * Forum Admin Class
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2002-2004 (c) GForge Team
 * Copyright 2005 (c) Daniel Perez
 * Copyright 2010 (c) Franck Villaume - Capgemini
 * Copyright (C) 2010-2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2014, Franck Villaume - TrivialDev
 * http://fusionforge.org/
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

require_once $gfcommon.'forum/Forum.class.php';
require_once $gfcommon.'forum/ForumMessage.class.php';
require_once $gfcommon.'forum/AttachManager.class.php';

class ForumAdmin extends FFError {
	var $group_id;
	var $p,$g;

	function __construct($group_id) {
		parent::__construct();
		if ($group_id) {
			$this->g = group_get_object($group_id);
			if (!$this->g->usesForum()) {
				$this->setError(sprintf(_('%s does not use the Forum tool.'), $this->g->getPublicName()));
			}
			$this->group_id = $group_id;
			$this->p =& $this->g->getPermission();
		}
	}

	/**
	 * PrintAdminMessageOptions - prints the different administrator options for a message
	 *
	 * @param	int	$msg_id		The Message ID
	 * @param	int	$group_id	The Project ID
	 * @param	int	$thread_id	The Thread ID : to return to the message if the user cancels (forumhtml only, not message.php)
	 * @param	int	$forum_id	The Forum ID : to return to the message if the user cancels (forumhtml only, not message.php)
	 * @param	int	$return_to_message
	 * @return	string	The HTML output
	 */
	function PrintAdminMessageOptions($msg_id,$group_id,$thread_id=0,$forum_id=0,$return_to_message=0) {

		$return = util_make_link('/forum/admin/?movethread='.$thread_id.'&msg_id='.$msg_id.'&group_id='.$group_id.'&forum_id='.$forum_id.'&return_to_message='.$return_to_message, html_image('ic/forum_move.png', 16, 18, array('alt' => _('Move Thread'))));

		// Following code (if ...) is to keep old implementation but need to be cleaned
		if ($return_to_message) {
			$thread_id = 0;
		}

		$return .= util_make_link('/forum/admin/?editmsg='.$msg_id.'&group_id='.$group_id.'&thread_id='.$thread_id.'&forum_id='.$forum_id, html_image('ic/forum_edit.png', 16, 18, array('alt' => _("Edit"))));
		$return .= util_make_link('/forum/admin/?deletemsg='.$msg_id.'&group_id='.$group_id.'&thread_id='.$thread_id.'&forum_id='.$forum_id, html_image('ic/forum_delete.png', 16, 18, array('alt'=>_("Delete"))));
		return $return;
	}

	/**
	 * PrintAdminOptions - prints the different administrator option for the forums (heading).
	 *
	 */
	function PrintAdminOptions() {
		global $group_id;
		echo html_e('p', array(), util_make_link('/forum/admin/?group_id='.$group_id.'&add_forum=1', _('Add Forum')).
			' | '.util_make_link('/forum/admin/pending.php?action=view_pending&group_id='.$group_id, _('Manage Pending Messages')).'<br />');
	}

	/**
	 * PrintAdminOptions - prints the administrator option for an individual forum, to link to the pending messages management
	 *
	 * @param	int	$forum_id	The Forum ID.
	 */
	function PrintAdminPendingOption($forum_id) {
		echo html_e('p', array(), util_make_link('/forum/admin/pending.php?action=view_pending&group_id='.$this->group_id.'&forum_id='.$forum_id, _('Manage Pending Messages')).'<br />');
	}

	/**
	 * GetPermission - Gets the permission for the user
	 *
	 * @return	object	 The permission
	 */
	function &GetPermission() {
		return $this->p;
	}

	/**
	 * GetGroupObject - Gets the group object of the forum
	 *
	 * @return	object	 The group obj
	 */
	function &GetGroupObject() {
		return $this->g;
	}

	/**
	 * isGroupAdmin - checks whether the authorized user is a group admin for the forums. The user must be authenticated
	 *
	 */
	function isGroupAdmin() {
		return forge_check_perm ('forum_admin', $this->group_id) ;
	}

	/**
	 * Authorized - authorizes and returns true if the user is authorized for the group, or false.
	 *
	 * @param	string	 $group_id	The group id.
	 * @return	bool
	 */
	function Authorized($group_id) {
		if (!$group_id) {
			$this->setGroupIdError();
			return false;
		}
		if (!session_loggedin()) {
			$this->setPermissionDeniedError();
			return false;
		}
		$this->group_id = $group_id;
		$this->g = group_get_object($group_id);
		if (!$this->g || !is_object($this->g) || $this->g->isError()) {
			$this->setGroupIdError();
			return false;
		}
		$this->p =& $this->g->getPermission();
		if (!$this->p || !is_object($this->p) || $this->p->isError()) {
			$this->setPermissionDeniedError();
			return false;
		}
		return true;
	}

	/**
	 * ExecuteAction - Executes the action passed as parameter
	 *
	 * @param	string	 $action	action to execute.
	 * @return	string
	 */
	function ExecuteAction($action) {
		global $HTML;

		$feedback = '';
		if ($action == "change_status") { //change a forum
			$forum_name = getStringFromRequest('forum_name');
			$description = getStringFromRequest('description');
			$send_all_posts_to = getStringFromRequest('send_all_posts_to');
			$group_forum_id = getIntFromRequest('group_forum_id');
			/*
				Change a forum
			*/
			$f = new Forum($this->g, $group_forum_id);
			if (!$f || !is_object($f)) {
				exit_error(_('Error getting Forum'), 'forums');
			} elseif ($f->isError()) {
				exit_error($f->getErrorMessage(), 'forums');
			}

			session_require_perm('forum_admin', $f->Group->getID());

			if (!$f->update($forum_name,$description,$send_all_posts_to)) {
				exit_error($f->getErrorMessage(),'forums');
			} else {
				$feedback = _('Forum Info Updated Successfully');
			}
			return $feedback;
		}
		if ($action == "add_forum") { //add forum
			$forum_name = getStringFromRequest('forum_name');
			$description = getStringFromRequest('description');
			$send_all_posts_to = getStringFromRequest('send_all_posts_to');
			/*
				Adding forums to this group
			*/
			if (!forge_check_perm ('forum_admin', $this->g->getID())) {
				form_release_key(getStringFromRequest("form_key"));
				exit_permission_denied('forums');
			}
			$f = new Forum($this->g);
			if (!$f || !is_object($f)) {
				form_release_key(getStringFromRequest("form_key"));
				exit_error(_('Error getting Forum'),'forums');
			} elseif ($f->isError()) {
				form_release_key(getStringFromRequest("form_key"));
				exit_error($f->getErrorMessage(),'forums');
			}
			if (!$f->create($forum_name,$description,$send_all_posts_to,1)) {
				form_release_key(getStringFromRequest("form_key"));
				exit_error($f->getErrorMessage(),'forums');
			} else {
				$feedback = _('Forum added successfully');
			}
			return $feedback;
		}
		if ($action == "delete") { //Deleting messages or threads
			$msg_id = getIntFromRequest('deletemsg');
			$forum_id = getIntFromRequest('forum_id');
			$f = new Forum($this->g,$forum_id);
			if (!$f || !is_object($f)) {
				exit_error(_('Error getting Forum'),'forums');
			} elseif ($f->isError()) {
				exit_error($f->getErrorMessage(),'forums');
			}

			session_require_perm ('forum_admin', $f->Group->getID()) ;

			$fm = new ForumMessage($f, $msg_id);
			if (!$fm || !is_object($fm)) {
				exit_error(_('Error Getting ForumMessage'),'forums');
			} elseif ($fm->isError()) {
				exit_error($fm->getErrorMessage(),'forums');
			}
			$count=$fm->delete();
			if (!$count || $fm->isError()) {
				exit_error($fm->getErrorMessage(),'forums');
			} else {
				$feedback = sprintf(ngettext('%s message deleted', '%s messages deleted', $count), $count);
			}
			return $feedback;
		}
		if ($action == "delete_forum") { //delete the forum
			/*
				Deleting entire forum
			*/
			$group_forum_id = getIntFromRequest('group_forum_id');
			$f = new Forum($this->g, $group_forum_id);
			if (!$f || !is_object($f)) {
				exit_error(_('Error getting Forum'),'forums');
			} elseif ($f->isError()) {
				exit_error($f->getErrorMessage(),'forums');
			}

			session_require_perm('forum_admin', $f->Group->getID()) ;

			if (!$f->delete(getStringFromRequest('sure'),getStringFromRequest('really_sure'))) {
				exit_error($f->getErrorMessage(),'forums');
			} else {
				$feedback = _('Successfully Deleted.');
			}
			return $feedback;
		}
		if ($action=="view_pending") {
			//show the pending messages, awaiting moderation
			$project_id = $this->group_id;
			$forum_id = getStringFromRequest("forum_id");
			if ($this->isGroupAdmin()) {
				$this->PrintAdminOptions();
			}
			$res = db_query_params('SELECT fgl.forum_name, fgl.group_forum_id FROM forum_group_list fgl, forum_pending_messages fpm WHERE fgl.group_id=$1 AND fpm.group_forum_id = fgl.group_forum_id GROUP BY fgl.forum_name, fgl.group_forum_id',
						array ($project_id));
			if (!$res) {
				echo db_error();
				return '';
			}

			$moderated_forums = array();
			for ($i=0;$i<db_numrows($res);$i++) {
				$aux = db_fetch_array($res);
				$moderated_forums[$aux[1]] = $aux[0];
			}

			if (empty($moderated_forums)) {
				echo $HTML->feedback(_('No forums are moderated for this group'));
				forum_footer();
				exit();
			}
			if (!$forum_id) {
				//get the first one
				$keys = array_keys($moderated_forums);
				$forum_id = $keys[0];
			}

			echo '
			<script type="text/javascript">/* <![CDATA[ */

			function confirmDel() {
				var agree=confirm("' . _('Proceed? Actions are permanent!') . '");
				if (agree) {
					return true;
				} else {
					return false;
				}
			}
			/* ]]> */</script>';
			echo $HTML->openForm(array('name' => 'pending', 'action' => '/forum/admin/pending.php', 'method' => 'post'));
			echo '
			<input type="hidden" name="action" value="update_pending" />
			<input type="hidden" name="form_key" value="' . form_generate_key() . '" />
			<input type="hidden" name="group_id" value="' . getIntFromRequest("group_id") . '" />
			<input type="hidden" name="forum_id" value="' . $forum_id . '" />
			';

			echo html_build_select_box_from_assoc($moderated_forums,'forum_id',$forum_id);
			echo '    <input name="Go" type="submit" value="Go" />';

			$title = array();
			$title[] = _('Forum Name');
			$title[] = _('Message');
			$title[] = _('Action');

			$res = db_query_params('SELECT msg_id,subject,pm.group_forum_id,gl.forum_name FROM forum_pending_messages pm, forum_group_list gl WHERE pm.group_forum_id=$1 AND pm.group_forum_id=gl.group_forum_id AND gl.group_forum_id=$2',
			array ($forum_id,
				$forum_id));
			if (!$res) {
				echo db_error();
				return '';
			}

			//array with the supported actions
			$options = array("1" => _("No action"),
                             "2" => _("Delete"),
                             "3" => _("Release"));
			//i'll make a hidden variable, helps to determine when the user updates the info, which action corresponds to which msgID
			$ids='';
			for($i=0;$i<db_numrows($res);$i++) {
				$ids .= db_result($res,$i,'msg_id') . ",";
			}

			echo $HTML->listTableTop($title);
			while ($onemsg = db_fetch_array($res)) {
				echo "
				<tr>
					<td>$onemsg[forum_name]</td>
					<td><a href=\"#\" onclick=\"window.open('pendingmsgdetail.php?msg_id=$onemsg[msg_id]&amp;forum_id=$onemsg[group_forum_id]&amp;group_id=$project_id','PendingMessageDetail','width=800,height=600,status=no,resizable=yes');\">$onemsg[subject]</a></td>
					<td><div class=\"align-right\">" . html_build_select_box_from_assoc($options,"doaction[]",1) . "</div></td>
				</tr>";
			}

			echo $HTML->listTableBottom();
			echo '
			<input type="hidden" name="msgids" value="' . $ids . '" />
			<p class="align-right"><input type="submit" onclick="return confirmDel();" name="update" value="' . _('Update') . '" /></p>
			';
			echo $HTML->closeForm();
		}
		if ($action == "update_pending") {
			$forum_id = getIntFromRequest("forum_id");
			$msgids = getStringFromRequest("msgids");//the message ids to update
			$doaction = getArrayFromRequest("doaction"); //the actions for the messages

			$msgids = explode(",", $msgids);
			array_pop($msgids);//this last one is empty

			for($i=0;$i<count($msgids);$i++) {
				switch ($doaction[$i]) {
					case 1 : {
						//no action
						break;
					}
					case 2 : {
						//delete
						db_begin();
						$res_pa = db_query_params('SELECT attachmentid FROM forum_pending_attachment WHERE msg_id=$1',
												  array($msgids[$i]));
						while ($pa = db_fetch_array($res_pa)) {
							ForumPendingStorage::instance()->delete($pa['attachmentid']);
							db_query_params('DELETE FROM forum_pending_attachment WHERE attachmentid=$1', array($pa['attachmentid']));
						}
						if (!db_query_params('DELETE FROM forum_pending_messages WHERE msg_id=$1',
									array ($msgids[$i]))) {
							$error_msg = "DB Error: ". db_error();
							db_rollback();
							ForumPendingStorage::instance()->rollback();
							break;
						}
						db_commit();
						ForumPendingStorage::instance()->commit();
						$feedback .= _('Forum deleted');
						break;
					}
					case 3 : {
						//release
						$res1 = db_query_params ('SELECT * FROM forum_pending_messages WHERE msg_id=$1',
									array ($msgids[$i]));
						if (!$res1) {
							$error_msg = "DB Error " . db_error() . "<br />";
							break;
						}
						$res2 = db_query_params ('SELECT * FROM forum_pending_attachment WHERE msg_id=$1',
									array ($msgids[$i]));
						if (!$res2) {
							$error_msg = "DB Error " . db_error() . "<br />";
							break;
						}
						$f = new Forum($this->g,$forum_id);
						if (!$f || !is_object($f)) {
							exit_error(_('Error getting new Forum'),'forums');
						} elseif ($f->isError()) {
							exit_error($f->getErrorMessage(),'forums');
						}
						$fm = new ForumMessage($f); // pending = false
						if (!$fm || !is_object($fm)) {
							exit_error(_('Error getting new forum message'),'forums');
						} elseif ($fm->isError()) {
							exit_error(_('Error getting new forum message')._(': ').$fm->getErrorMessage(),'forums');
						}
						$group_forum_id = db_result($res1,0,"group_forum_id");
						$subject = db_result($res1,0,"subject");
						$body = db_result($res1,0,"body");
						$post_date = db_result($res1,0,"post_date");
						$thread_id = db_result($res1,0,"thread_id");
						$is_followup_to = db_result($res1,0,"is_followup_to");
						$posted_by = db_result($res1,0,"posted_by");
						if ($fm->insertreleasedmsg($group_forum_id,$subject, $body,$post_date, $thread_id, $is_followup_to,$posted_by,time())) {
							$feedback .= "($subject) " . _('Pending message released') . "<br />";
							if (db_numrows($res2)>0) {
								//if there's an attachment
								$am = new AttachManager();//object that will handle and insert the attachment into the db
								$am->SetForumMsg($fm);
								$userid = db_result($res2,0,"userid");
								$dateline = db_result($res2,0,"dateline");
								$filename = db_result($res2,0,"filename");
								$filedata = ForumPendingStorage::instance()->get_storage(db_result($res2,0,"attachmentid"));
								$filesize = db_result($res2,0,"filesize");
								$visible = db_result($res2,0,"visible");
								$msg_id = db_result($res2,0,"msg_id");
								$filehash = db_result($res2,0,"filehash");
								$mimetype = db_result($res2,0,"mimetype");
								$am->AddToDBOnly($userid, $dateline, $filename, $filedata, $filesize, $visible, $filehash, $mimetype);
								foreach ($am->Getmessages() as $item) {
									$feedback .= "$msg_id - " . $item . "<br />";
								}
							}
							$deleteok = true;
						} else {
							if ($fm->isError()) {
							    if ( $fm->getErrorMessage() == (_('Could not Update Master Thread parent with current time')) ) {
							    	//the thread which the message was replying to doesn't exist any more
							    	$feedback .= "( " . $subject . " ) " . _('The thread which the message was posted to doesn\'t exist anymore, please delete the message.') . "<br />";
							    } else {
									$error_msg .= "$msg_id - " . $fm->getErrorMessage() . "<br />";
							    }
								$deleteok = false;
							}
						}

						if ( isset($am) && (is_object($am)) ) {
							//if there was an attach, check if it was uploaded ok
							 if (!$am->isError()) {
								$deleteok = true;
							 } else {
							 	//undo the changes to the forum table
								db_begin();
								if (!db_query_params ('DELETE FROM forum WHERE msg_id=$1',
										      array ($fm->getID()))) {
									$error_msg .= "DB Error: ". db_error();
									db_rollback();
									break;
								}
								db_commit();
								$deleteok = false;
							 }
						}

						if ($deleteok) {
							// delete the message
							// delete attachments (in the DB only, files already moved by FileStorage::store)
							db_begin();
							if (!db_query_params ('DELETE FROM forum_pending_attachment WHERE msg_id=$1',
										array ($msgids[$i]))) {
								$error_msg = "DB Error: ". db_error();
								db_rollback();
								break;
							}
							if (!db_query_params ('DELETE FROM forum_pending_messages WHERE msg_id=$1',
										array ($msgids[$i]))) {
								$error_msg = "DB Error: ". db_error();
								db_rollback();
								break;
							}
							db_commit();
						}
					}
				}
			}
			html_feedback_top($feedback);
			$this->ExecuteAction("view_pending");
		}
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
