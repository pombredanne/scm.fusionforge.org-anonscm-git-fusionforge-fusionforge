<?php
/**
 * News Facility
 *
 * Copyright 1999-2001, VA Linux Systems
 * Copyright 2002-2004, GForge Team
 * Copyright 2010, Alain Peyrat - Alcatel-Lucent
 * Copyright 2011, Roland Mas
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
 * Coprygith 2019, Franck Villaume - TrivialDev
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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'include/note.php';
require_once $gfwww.'news/admin/news_admin_utils.php';
require_once $gfwww.'news/news_utils.php';
//common forum tools which are used during the creation/editing of news items
require_once $gfcommon.'forum/Forum.class.php';
require_once $gfcommon.'include/TextSanitizer.class.php'; // to make the HTML input by the user safe to store

global $HTML;

$post_changes = getStringFromRequest('post_changes');
$approve = getStringFromRequest('approve');
$status = getIntFromRequest('status');
$summary = getStringFromRequest('summary');
$details = getHtmlTextFromRequest('details');
$id = getIntFromRequest('id');
$for_group = getIntFromRequest('for_group');

/*
  News uber-user admin pages
  Show all waiting news items except those already rejected.
  Admin members of GROUP_IS_NEWS (news project) can edit/change/approve news items
*/
session_require_global_perm ('approve_news') ;

if ($post_changes) {
	if ($approve) {

		$result=db_query_params("SELECT * FROM news_bytes WHERE id=$1 AND group_id=$2", array($id, $for_group));
		if (db_numrows($result) < 1) {
			exit_error(_('Newsbyte not found'),'news');
		}

		$forum_id = db_result($result,0,'forum_id');

		if ($status==1) {
			/*
			  Update the db so the item shows on the home page
			*/
			$result=db_query_params('UPDATE news_bytes SET is_approved=1, post_date=$1, summary=$2, details=$3 WHERE id=$4',
						array(time(),
						      htmlspecialchars($summary),
						      $details,
						      $id));
			if (!$result || db_affected_rows($result) < 1) {
				$error_msg .= _('Error On Update')._(': ').db_error();
			} else {
				$feedback .= _('Newsbyte Updated.');
			}
		} elseif ($status==2) {
			/*
			  Move msg to deleted status
			*/
			$result=db_query_params("UPDATE news_bytes SET is_approved='2' WHERE id=$1", array($id));
			if (!$result || db_affected_rows($result) < 1) {
				$error_msg .= _('Error On Update')._(': ').db_error();
			} else {
				$feedback .= _('Newsbyte Deleted.');
			}
		}

		/*
		  Show the list_queue
		*/
		$approve='';
		$list_queue='y';
	} elseif (getStringFromRequest('mass_reject')) {
		/*
		  Move msg to rejected status
		*/
		$news_id = getArrayFromRequest('news_id');
		$result = db_query_params("UPDATE news_bytes
					SET is_approved='2'
					WHERE id = ANY($1)",array(db_int_array_to_any_clause($news_id)));
		if (!$result || db_affected_rows($result) < 1) {
			$error_msg .= _('Error On Update')._(': ').db_error();
		} else {
			$feedback .= _('Newsbytes Rejected.');
		}
	}
}

news_header(array('title'=>_('News Admin')));

if ($approve) {
	/*
	  Show the submit form
	*/

	$result=db_query_params("SELECT groups.unix_group_name,groups.group_id,news_bytes.*
				FROM news_bytes,groups WHERE id=$1
				AND news_bytes.group_id=groups.group_id ", array($id));
	if (db_numrows($result) < 1) {
		exit_error(_('Newsbyte not found'), 'news');
	}
	if (db_result($result,0,'is_approved') == 4) {
		exit_error(_('Newsbyte Deleted.'), 'news');
	}

	$group = group_get_object(db_result($result,0,'group_id'));
	$user = user_get_object(db_result($result,0,'submitted_by'));

	echo $HTML->openForm(array('action' => '/admin/pending-news.php?approve=1&id='.$id, 'method' => 'post'));
	echo '
		<input type="hidden" name="for_group" value="'.db_result($result,0,'group_id').'" />
		<input type="hidden" name="id" value="'.db_result($result,0,'id').'" />
		<strong>'._('Submitted for project')._(':').'</strong> '.
		util_make_link_g (strtolower(db_result($result,0,'unix_group_name')),db_result($result,0,'group_id'),$group->getPublicName()).'<br />
		<strong>'._('Submitted by')._(':').'</strong> '.$user->getRealName().'<br />
		<input type="hidden" name="approve" value="y" />
		<input type="hidden" name="post_changes" value="y" />
		<input type="radio" name="status" value="1" /> '._('Approve For Front Page').'<br />
		<input type="radio" name="status" value="0" /> '._('Do Nothing').'<br />
		<input type="radio" name="status" value="2" checked="checked" /> '._('Reject').'<br />
		<strong>'._('Subject')._(':').'</strong><br />
		<input type="text" name="summary" value="'.db_result($result,0,'summary').'" size="60" maxlength="60" /><br />
		<strong>'._('Details')._(':').'</strong><br />';

	$params = array () ;
	$params['name'] = 'details';
	$params['width'] = "600";
	$params['height'] = "300";
	$params['group'] = db_result($result,0,'group_id');
	$params['body'] = db_result($result,0,'details');
	$params['content'] = '<textarea name="details" rows="5" cols="50">'.$params['body'].'</textarea>';
	plugin_hook_by_reference("text_editor",$params);

	echo $params['content'].'<br/>';
	echo '<br />
		<input type="submit" name="submit" value="'._('Submit').'" />';
	echo $HTML->closeForm();

} else {

	/*
	  Show list of waiting news items
	*/

	$old_date = time()-60*60*24*30;
	$qpa_pending = db_construct_qpa(array(), 'SELECT groups.group_id,id,post_date,summary,
				group_name,unix_group_name
			FROM news_bytes,groups
			WHERE is_approved=0
			AND news_bytes.group_id=groups.group_id
			AND post_date > $1
			AND groups.status=$2
			ORDER BY post_date', array ($old_date, 'A')) ;

	$old_date = time()-(60*60*24*7);
	$qpa_rejected = db_construct_qpa(array(), 'SELECT groups.group_id,id,post_date,summary,
				group_name,unix_group_name
			FROM news_bytes,groups
			WHERE is_approved=2
			AND news_bytes.group_id=groups.group_id
			AND post_date > $1
			ORDER BY post_date', array($old_date)) ;

	$qpa_approved = db_construct_qpa(array(), 'SELECT groups.group_id,id,post_date,summary,
				group_name,unix_group_name
			FROM news_bytes,groups
			WHERE is_approved=1
			AND news_bytes.group_id=groups.group_id
			AND post_date > $1
			ORDER BY post_date', array ($old_date)) ;
	show_news_approve_form(
		$qpa_pending,
		$qpa_rejected,
		$qpa_approved,
		'/admin/pending-news.php'
		);

}
$HTML->footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
