<?php
/**
 * Copyright (c) STMicroelectronics, 2005. All Rights Reserved.
 *
 * Originally written by Jean-Philippe Giola, 2005
 *
 * This file is a part of Fusionforge.
 *
 * Fusionforge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fusionforge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

define('FORUMML_MESSAGE_ID', 1);
define('FORUMML_DATE', 2);
define('FORUMML_FROM', 3);
define('FORUMML_SUBJECT', 4);
define('FORUMML_CONTENT_TYPE', 12);
define('FORUMML_CC', 34);

require_once(dirname(__FILE__).'/../include/ForumML_Attachment.class.php');
require_once(dirname(__FILE__).'/../include/ForumML_MessageDao.class.php');
//require_once('common/include/Toggler.class.php');
require_once 'Mail/RFC822.php';
require_once 'common/mail/Mail.class.php';
require_once 'PEAR.php';
global $feedback;

function getForumMLDao() {
	return new ForumML_MessageDao(CodendiDataAccess::instance());
}

// Get message headers
function plugin_forumml_get_message_headers($id_message) {
	return getForumMLDao()->getMessageHeaders($id_message)->getRow();
}

// Display search results
function plugin_forumml_show_search_results($p,$result,$group_id,$list_id) {

	echo "<table width='100%'>
			<tr>
				<th class=forumml>".
					_('Thread')."
				</th>
				<th class=forumml>".
					_('Submitted on')."
				</th>
				<th class=forumml>".
					_('Author')."
				</th>
			</tr>";

	$idx = 0;
	// Build a table full of search results
	while ($rows = $result->getRow()) {
		$idx++;
		if ($idx % 2 == 0) {
			$class="boxitemalt bgcolor-white";
		} else {
			$class="boxitem bgcolor-grey";
		}

		$res1 = getForumMLDao()->getSpecificMessage($rows['id_message'],$list_id)->getRow();
		$subject = mb_decode_mimeheader($res1['value']);
		$res2 = getForumMLDao()->getHeaderValue($rows['id_message'],array(2,3));
		$k = 1;
		while ($rows2 =$res2->getRow()) {
			$header[$k] = $rows2['value'];
			$k++;
		}
		$from = mb_decode_mimeheader($header[1]);

		// Replace '<' by '&lt;' and '>' by '&gt;'. Otherwise the email adress won't be displayed
		// because it will be considered as an xhtml tag.
		$from = preg_replace('/\</', '&lt;', $from);
		$from = preg_replace('/\>/', '&gt;', $from);

		$date = date("Y-m-d H:i",strtotime($header[2]));
		// purify message subject
		$hp = new ForumML_HTMLPurifier();
		$subject = $hp->purifyml($subject);

		// display the resulting threads in rows
		printf ("<tr class='".$class."'>
					<td class='subject'>
						&nbsp;<img src='".$p->getThemePath()."/images/ic/comment.png'/>
    					<a href='message.php?group_id=".$group_id."&topic=".$rows['id_message']."&list=".$list_id."'><b>".$subject."</b></a>
					</td>
					<td>
         				<font class='info'>".$date."</font>
					</td>
					<td>
						<font class='info'>".$from."</font>
					</td>
				</tr>");
	}
	echo "</table>";

}

// List all threads
function plugin_forumml_show_all_threads($p,$list_id,$list_name,$offset) {

	$chunks = 30;
	$request =& HTTPRequest::instance();

	// all threads
	$result = getForumMLDao()->getAllThreadsFromList($list_id,$offset,$chunks);
	$nbRowFound = $result->rowCount();

	// Total number of threads
	$nbThreads = 0;
	$res = getForumMLDao()->countAllThreadsFromList($list_id);
	if ($res && !db_error()) {
		$row = $res->getRow();
		$nbThreads = $row['nb'];
	}

	$start = $offset;
	$end   = min($start + $chunks - 1, $nbRowFound - 1);

	// all threads to be displayed
	$colspan = "";
	$item = _('Thread');

	if (isset($offset) && $offset != 0) {
		$begin = "<a href=\"/plugins/forumml/message.php?group_id=".$request->get('group_id')."&list=".$list_id.
			"\"><img src='".$p->getThemePath()."/images/ic/resultset_first.png' title='begin')'/></a>";
		$previous = "<a href=\"/plugins/forumml/message.php?group_id=".$request->get('group_id')."&list=".$list_id."&offset=".($offset - $chunks).
			"\"><img src='".$p->getThemePath()."/images/ic/resultset_previous.png' title='".
			_('Previous ').$chunks.(' messages')."'/></a>";
	} else {
		$begin = "<img src='".$p->getThemePath()."/images/ic/resultset_first_disabled.png' alt='".$p->getThemePath()."/images/ic/resultset_first_disabled.png'/>";
		$previous = "<img src='".$p->getThemePath()."/images/ic/resultset_previous_disabled.png'
			title='"._('Previous ').$chunks.(' messages')."'/>";
	}

	if (($offset + $chunks ) < $nbThreads) {
		$next = "<a href=\"/plugins/forumml/message.php?group_id=".$request->get('group_id')."&list=".$list_id."&offset=".($offset + $chunks)."\"><img src='".$p->getThemePath()."/images/ic/resultset_next.png' title='"._('Next ').$chunks.(' messages')."'/></a>";
		$finish = "<a href=\"/plugins/forumml/message.php?group_id=".$request->get('group_id')."&list=".$list_id."&offset=".($chunks * (int) (($nbThreads - 1) / $chunks))."\"><img src='".$p->getThemePath()."/images/ic/resultset_last.png' title='".$_('Last messages')."'/></a>";
	} else {
		$next = "<img src='".$p->getThemePath()."/images/ic/resultset_next_disabled.png' title='".$chunks."'/>";
		$finish = "<img src='".$p->getThemePath()."/images/ic/resultset_last_disabled.png'/>";
	}

	// display page-splitting information, at the top of threads table
	echo "<table width='100%'>
		<tr>
		<td align='left' width='10%'>".
		$begin
		."</td>
		<td align='left' width='15%'>".
		$previous
		."</td>
		<td align='center' width='55%'>".
		_('Threads')." ".($start + 1)." - ".($end + 1)." <b>(".$nbThreads.")</b>
		</td>
		<td align='right' width='10%'>
		$next
		</td>
		<td align='right' width='10%'>
		$finish
		</td>
		</tr>
		</table>";

	if ($nbRowFound > 0) {

		echo "<table class='border' width='100%' border='0'>
			<tr class='boxtable'>
			<th class='forumml' ".$colspan." width='40%'>".$item."</th>
			<th class='forumml' width='15%'>"._('Last updated')."</th>
			<th class='forumml' width='15%'>"._('Submitted on')."</th>
			<th class='forumml' width='25%'>"._('Author')."</th>
			</tr>";

		$i = 0;
		while ($msg = $result->getRow()) {
			$i++;
			if ($i % 2 == 0) {
				$class="boxitemalt bgcolor-white";
				$headerclass="headerlabelalt";
			} else {
				$class="boxitem bgcolor-grey";
				$headerclass="headerlabel";
			}

			// Get the number of messages in thread
			// nb of children + message
			$count = 1 + plugin_forumml_nb_children(array($msg['id_message']));

			// all threads
			print "<tr class='".$class."'><a name='".$msg['id_message']."'></a>
				<td class='subject'>";
			if ($count > 1) {
				print "<img src='".$p->getThemePath()."/images/ic/comments.png'/>";
			}
			else {
				print "<img src='".$p->getThemePath()."/images/ic/comment.png'/>";
			}

			// Remove listname from suject
			$subject = preg_replace('/^[ ]*\['.$list_name.'\]/i', '', $msg['subject']);

			print "<a href='message.php?group_id=".$request->get('group_id')."&topic=".$msg['id_message']."&list=".$request->get('list')."'>
				".htmlentities($subject, ENT_QUOTES, 'UTF-8')."
				</a> <b>".html_e('em', array(), '('.$count.')')."</b>
				</td>".
				"<td class='info'>".strftime("%a, %e %h %G  %R",$msg['lastup'])."</td>".
				"<td class='info'>".strftime("%a, %e %h %G  %R",strtotime($msg['date']))."</td>
				<td class='info'>".htmlentities($msg['sender'], ENT_QUOTES, 'UTF-8')."</td>
				</tr>";
		}

		echo '</table>';
		// display page-splitting information, at the bottom of threads table
		echo "<table width='100%'>
			<tr>
			<td align='left' width='10%'>".
			$begin
			."</td>
			<td align='left' width='15%'>".
			$previous
			."</td>
			<td align='center' width='55%'>".
			_('Threads')." ".($start + 1)." - ".($end + 1)." <b>(".$nbThreads.")</b>
			</td>
			<td align='right' width='10%'>
			$next
			</td>
			<td align='right' width='10%'>
			$finish
			</td>
			</tr>
			</table>";
	}

}

function plugin_forumml_nb_children($parents) {
	if (count($parents) == 0) {
		return 0;
	} else {
		$result = getForumMLDao()->countChildrenFromParents(implode(',',$parents));
		if ($result && !$result->isError()) {
			$p = array();
			while ($row = $result->getRow()) {
				$p[] = $row['id_message'];
			}
			$num = $result->rowCount();
			return $num + plugin_forumml_nb_children($p);
		}
	}
}

/**
 * Extract attachment info from a database result
 *
 * @see plugin_forumml_build_flattened_thread
 */
function plugin_forumml_new_attach($row) {
	if (isset($row['id_attachment']) && $row['id_attachment']) {
		return array('id_attachment' => $row['id_attachment'],
				'file_name' => $row['file_name'],
				'file_type' => $row['file_type'],
				'file_size' =>$row['file_size'],
				'file_path' =>$row['file_path'],
				'content_id' =>$row['content_id']);
	} else {
		return null;
	}
}

/**
 * Insert a message in the thread list with a unique date
 *
 * @see plugin_forumml_build_flattened_thread
 */
function plugin_forumml_insert_in_thread(&$thread, $row) {
	$date = strtotime($row['date']);
	while (isset($thread[$date])) {
		$date++;
	}
	$thread[$date] = $row;
	return $date;
}

/**
 * Insert all messages returned by a SQL query in the thread list with
 * the attachments
 *
 * @see plugin_forumml_build_flattened_thread
 */
function plugin_forumml_insert_msg_attach(&$thread, $result) {
	$parents = array();
	$prev    = -1;
	while ($row = $result->getRow()) {
		if ($row['id_message'] != $prev) {
			// new message
			$parents[] = $row['id_message'];
			$curMsg = plugin_forumml_insert_in_thread($thread, $row);
			$thread[$curMsg]['attachments'] = array();
		}

		$attch = plugin_forumml_new_attach($row);
		if ($attch) {
			$thread[$curMsg]['attachments'][] = $attch;
		}
		$prev = $row['id_message'];
	}
	return $parents;
}

/**
 * Search all chilrens at a given level of depth
 *
 * @see plugin_forumml_build_flattened_thread
 */
function plugin_forumml_build_flattened_thread_children(&$thread, $parents) {
	if (count($parents) > 0) {
		$result = getForumMLDao()->getChildrenFromDepthLevel(implode(',',$parents));
		if ($result && !$result->isError()){
			$p = plugin_forumml_insert_msg_attach($thread, $result);
			plugin_forumml_build_flattened_thread_children($thread, $p);
		}
	}
}

/**
 * Entry point to create a flattened view of a message thread.
 *
 * In order to display the messages in the right order, we fetch the
 * all the messages with the needed hearders and attachments.
 * To lower the number of SQL queries, there is 1 query per message
 * tree depth level.
 * All the messages are stored in an array indexed by the message
 * date. If dates conflict we add +1s to the message date.
 * Once all the messages are fetched, we just sort the array based on
 * the keys values.
 * The thread array looks like:
 * array (
 *   123342334 => array(
 *                  'message_id'  => '1234',
 *                  'subject'     => 'toto',
 *                  ...
 *                  'attachments' => array(
 *                                     'id_attachment' => '5678',
 *                                     ...
 *                                   )
 *                ),
 *   ...
 * );
 *
 */
function plugin_forumml_build_flattened_thread($topic) {
	$thread = array();
	$result = getForumMLDao()->getFlattenedThread($topic);
	if ($result && !$result->isError()) {
		$p = plugin_forumml_insert_msg_attach($thread, $result);
		plugin_forumml_build_flattened_thread_children($thread, $p);
	}
	ksort($thread, SORT_NUMERIC);
	return $thread;
}

// List all messages inside a thread
function plugin_forumml_show_thread($p, $list_id, $parentId, $purgeCache) {
	$thread = plugin_forumml_build_flattened_thread($parentId);
	foreach ($thread as $message) {
		plugin_forumml_show_message($p, $message, $parentId, $purgeCache);
	}
}

// Display a message
function plugin_forumml_show_message($p, $msg, $id_parent, $purgeCache) {
	$body    = $msg['body'];
	$request = HTTPRequest::instance();
	$hp = new TextSanitizer();

	// Is "ready to display" body already in cache or not
	$bodyIsCached = false;
	if (isset($msg['cached_html']) && !$purgeCache) {
		$bodyIsCached = true;
	}

	if (PEAR::isError($from_info = Mail_RFC822::parseAddressList($msg['sender'], forge_get_config('web_host'))) || !isset($from_info[0]) || !$from_info[0]->personal) {
		$from_info = htmlentities($msg['sender'], ENT_QUOTES, 'UTF-8');
	} else {
		$from_info = '<abbr title="'.  htmlentities($from_info[0]->mailbox .'@'. $from_info[0]->host, ENT_QUOTES, 'UTF-8')  .'">'.  htmlentities($from_info[0]->personal, ENT_QUOTES, 'UTF-8')  .'</abbr>';
	}

	echo '<div class="plugin_forumml_message">';
	// specific thread
	echo '<div class="plugin_forumml_message_header boxitemalt" id="plugin_forumml_message_'. $msg['id_message'] .'">';
	echo '<div class="plugin_forumml_message_header_subject">'. htmlentities($msg['subject'], ENT_QUOTES, 'UTF-8') .'</div>';

	echo '<a href="#'. $msg['id_message'] .'" title="message #'. $msg['id_message'] .'">';
	echo '<img src="'. $p->getThemePath() .'/images/ic/comment.png" id="'. $msg['id_message'] .'" style="vertical-align:middle" alt="#'. $msg['id_message'] .'" />';
	echo '</a>';

	echo ' <span class="plugin_forumml_message_header_from">'.  $from_info  .'</span>';
	echo ' <span class="plugin_forumml_message_header_date">'. _('On ').$msg['date'] .'</span>';

	echo '&nbsp;<a href="#" id="plugin_forumml_toogle_msg_'.$msg['id_message'].'" class="plugin_forumml_toggle_font">'._('Toggle font family (typewriter/normal)').'</a>';

	// get CC
	$cc = trim($msg['cc']);
	if ($cc) {
		if (PEAR::isError($cc_info = Mail_RFC822::parseAddressList($cc, forge_get_config('web_host')))) {
			$ccs = htmlentities($cc, ENT_QUOTES, 'UTF-8');
		} else {
			$ccs = array();
			foreach($cc_info as $c) {
				if (!$c->personal) {
					$ccs[] = htmlentities($c->mailbox .'@'. $c->host, ENT_QUOTES, 'UTF-8');
				} else {
					$ccs[] = '<abbr title="'. htmlentities($c->mailbox .'@'. $c->host, ENT_QUOTES, 'UTF-8') .'">'. htmlentities($c->personal, ENT_QUOTES, 'UTF-8') .'</abbr>';
				}
			}
			$ccs = implode(', ', $ccs);
		}
		print '<div class="plugin_forumml_message_header_cc">'. _('Cc:') .' '. $ccs .'</div>';
	}

	// Message content
	if (strpos($msg['content_type'], 'multipart/') !== false) {
		$content_type = $msg['msg_type'];
	} else {
		$content_type = $msg['content_type'];
	}
	$is_html = strpos($content_type, "text/html") !== false;

	// get attached files
	if (count($msg['attachments'])) {
		print '<div class="plugin_forumml_message_header_attachments">';
		$first = true;
		foreach($msg['attachments'] as $attachment) {
			// Special case, this is an HTML email
			if (preg_match('/.html$/i',$attachment['file_name'])) {
				// By default, the first html attachment replaces the default body (text)
				if ($first) {
					if (!$bodyIsCached && is_file($attachment['file_path'])) {
						$body = file_get_contents($attachment['file_path']);
						$is_html = true;
					}
					continue;
				} else {
					$flink = $attachment['file_name'];
				}
			} else {
				$flink = $attachment['file_name'];
			}
			if (!$first) {
				echo ',&nbsp;&nbsp;';
			}

			echo "<img src='".$p->getThemePath()."/images/ic/attach.png'/>  <a href='upload.php?group_id=".$request->get('group_id')."&list=".$request->get('list')."&id=".$attachment['id_attachment']."&topic=".$id_parent."'>".$flink."</a>";
			$first = false;
		}
		echo '</div>';
	}
	echo '</div>';

	print '<div id="plugin_forumml_message_content_'.$msg['id_message'].'" class="plugin_forumml_message_content_std">';
	$body = str_replace("\r\n","\n", $body);

	// If there is no cached html of if user requested to regenerate the cache, do it, otherwise use cached HTML.
	if (!$bodyIsCached) {
		// Purify message body, according to the content-type
		if ($is_html) {
			// Update attachment links
			$body = plugin_forumml_replace_attachment($msg['id_message'], $request->get('group_id'), $request->get('list'), $id_parent, $body);

			// Use TextSanitizer for html mails
			$msg['cached_html'] = $hp->purify($body);
		} else {
			// Allowed: url + automagic links + <blockquote>
			$purified_body = htmlentities($body, ENT_QUOTES, 'UTF-8');
			$purified_body = str_replace('&gt;', '>', $purified_body);
			$tab_body = '';
			$level = 0;
			$current_level = 0;
			$search_for_quotes = false;
			$maxi = strlen($purified_body);
			for($i = 0 ; $i < $maxi ; ++$i) {
				if ($search_for_quotes) {
					if($purified_body{$i} == ">") {
						++$current_level;
						if($level < $current_level) {
							$tab_body .= '<blockquote class="grep">';
							++$level;
						}
					} else {
						$search_for_quotes = false;
						if($level > $current_level) {
							$tab_body .= '</blockquote>';
							--$level;
						}
						if($purified_body{$i} == "\n" && $i < $maxi - 1) {
							$search_for_quotes = true;
							$current_level = 0;
						}
						$tab_body .= $purified_body{$i};
					}
				} else {
					if($purified_body{$i} == "\n" && $i < $maxi - 1) {
						$search_for_quotes = true;
						$current_level = 0;
					}
					$tab_body .= $purified_body{$i};
				}
			}
			$purified_body = str_replace('>', '&gt;', $purified_body);
			$msg['cached_html'] = nl2br($tab_body);
		}
		getForumMLDao()->updateCacheHTML($msg['cached_html'] , $msg['id_message']);
	}
	echo $msg['cached_html'];
	echo '</div>';

	// Reply
	echo '<div class="plugin_forumml_message_footer">';

	// If you click on 'Reply', load reply form
	$vMess = new Valid_UInt('id_mess');
	$vMess->required();
	if ($request->valid($vMess) && $request->get('id_mess') == $msg['id_message']) {
		$vReply = new Valid_WhiteList('reply',array(0,1));
		$vReply->required();
		if ($request->valid($vReply) && $request->get('reply') == 1) {
			if ($is_html) {
				$body = $hp->purify($body);
			} else {
				$body = htmlentities($body, ENT_QUOTES, 'UTF-8');
			}
			plugin_forumml_reply($msg['subject'],$msg['id_message'],$id_parent,$body,$msg['sender']);
		}
	} else {

		print "<a href='message.php?group_id=".$request->get('group_id')."&topic=".$id_parent."&id_mess=".$msg['id_message']."&reply=1&list=".$request->get('list')."#reply-".$msg['id_message']."'>
			<img src='".$p->getThemePath()."/images/ic/comment_add.png'/>
			"._('Reply')."
			</a>";
	}

	echo '</div>';
	echo '</div>';
}

// Display the post form under the current post
function plugin_forumml_reply($subject,$in_reply_to,$id_parent,$body,$author) {

	$request =& HTTPRequest::instance();
	$tab_tmp = explode("\n",$body);
	$tab_tmp = array_pad($tab_tmp,-count($tab_tmp)-1,"$author wrote :");

	echo '<script type="text/javascript" src="scripts/cc_attach_js.php"></script>';
	echo ' <div id="reply-'. $in_reply_to .'" class="plugin_forumml_message_reply">'."
		<form id='".$in_reply_to."' action='?group_id=".$request->get('group_id')."&list=".$request->get('list')."&topic=".$id_parent."' name='replyform' method='post' enctype='multipart/form-data'>
		<input type='hidden' name='reply_to' value='".$in_reply_to."'/>
		<input type='hidden' name='subject' value='".$subject."'/>
		<input type='hidden' name='list' value='".$request->get('list')."'/>
		<input type='hidden' name='group_id' value='".$request->get('group_id')."'/>";
	echo   '<a href="javascript:;" onclick="addHeader(\'\',\'\',1);">['._('Add cc:').']</a>
		- <a href="javascript:;" onclick="addHeader(\'\',\'\',2);">['._('Attach:').']</a>
		<input type="hidden" value="0" id="header_val" />
		<div id="mail_header"></div>';
	echo "<p><textarea name='message' rows='15' cols='100'>";

	foreach($tab_tmp as $k => $line) {
		$line = trim($line);
		if ($k == 0) {
			print($line."\n");
		} else {
			$indent = substr($line, 0, 4) == '&gt;' ? '>' : '> ';
			print($indent . $line."\n");
		}
	}

	echo        "</textarea></p>
		<p>
		<input type='submit' name='send_reply' value='"._('Submit')."'/>
		<input type='reset' value='"._('Erase')."'/>
		</p>
		</form>
		</div>";
}

// Search & replace reference to attached content
// This happens for images attached to html messages (multipart/related)
function plugin_forumml_replace_attachment($id_message, $group_id, $list, $id_parent, $body) {
	if (preg_match_all('/"cid:([^"]*)"/m', $body, $matches)) {
		$search_parts  = array();
	$replace_parts = array();
	foreach ($matches[1] as $match) {
		$result = getForumMLDao()->getAttachment($id_mesage , $match) ;
		if ($res && $res->rowCount() == 1) {
			$row = $res->getRow();
			$url = "upload.php?group_id=".$group_id."&list=".$list."&id=".$row['id_attachment']."&topic=".$id_parent;
			$search_parts[] = 'cid:'.$match;
			$replace_parts[] = $url;
		}
	}
	if (!empty($replace_parts)) {
		$body = str_replace($search_parts, $replace_parts, $body);
	}
}
return $body;
}

// Build Mail headers, and send the mail
function plugin_forumml_process_mail($plug,$reply=false) {
	global $feedback;
	$request = HTTPRequest::instance();
	$hp = new TextSanitizer();

	// Instantiate a new Mail class
	$mail = new Mail();

	// Build mail headers
	$list = new MailmanList($request->get('group_id') , $request->get('list'));
	$to = $list->getName()."@".forge_get_config('lists_host');
	$mail->setTo($to);

	$mail->setFrom(session_get_user()->getEmail());

	$vMsg = new Valid_Text('message');
	if ($request->valid($vMsg)) {
		$message = $request->get('message');
	}

	$subject = $request->get('subject');
	$mail->setSubject($subject);

	if ($reply) {
		// set In-Reply-To header
		$hres = plugin_forumml_get_message_headers($request->get('reply_to'));
		$reply_to = $hres['value'];
		$mail->addAdditionalHeader("In-Reply-To",$reply_to);
	}
	$continue = true;

	if ($request->validArray(new Valid_Email('ccs')) && $request->exist('ccs')) {
		$cc_array = array();
		$idx = 0;
		foreach ($request->get('ccs') as $cc) {
			if (trim($cc) != "") {
				$cc_array[$idx] = $hp->purify($cc);
				$idx++;
			}
		}
		// Checks sanity of CC List
		$err = '';
		$valid = true;
		foreach ($cc_array as $cc) {
			$user = user_get_object_by_email($cc);
			if (!$user) {
				$valid = false;
				$err .= $cc.'<br>';
			}
		}
		if (!$valid) {
			$continue=false;
			$feedback .=_('Invalid Email Address')._(': ').$err;
		} else {
			// add list of cc users to mail mime
			if (!empty($cc_array)) {
				$cc_list = implode(',',$cc_array);
				$mail->setCc($cc_list,true);
			}
		}
	}

	if ($continue) {
		// Process attachments

		// Define boundaries as specified in RFC:
		// http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html
		$boundary      = '----=_NextPart';
		$boundaryStart = '--'.$boundary;
		$boundaryEnd   = '--'.$boundary.'--';

		// Attachments headers
		if (isset($_FILES["files"]) && count($_FILES["files"]['name']) > 0) {
			$attachment = "";
			$text = "This is a multi-part message in MIME format.\n";
			$text = "$boundaryStart\n";
			$text .= "Content-Type: text/plain; charset=\"UTF-8\"\n";
			$text .= "Content-Transfer-Encoding: 8bit\n\n";
			$text .= $message;
			$text .= "\n\n";
			foreach($_FILES["files"]['name'] as $i => $fileName) {
				$attachment .= "$boundaryStart\n";
				$attachment .= "Content-Type:".$_FILES["files"]["type"][$i]."; name=".$fileName."\n";
				$attachment .= "Content-Transfer-Encoding: base64\n";
				$attachment .= "Content-Disposition: attachment; filename=".$fileName."\n\n";
				$attachment .= chunk_split(base64_encode(file_get_contents($_FILES["files"]["tmp_name"][$i])));
			}
			$attachment .= "\n$boundaryEnd\n";
			$body = $text.$attachment;
			// force MimeType to multipart/mixed as default (when instantiating new Mail object) is text/plain
			$mail->setMimeType('multipart/mixed; boundary="'.$boundary.'"');
			$mail->addAdditionalHeader("MIME-Version","1.0");
		} else {
			$body = $message;
		}

		$mail->setBody($body);

		if ($mail->send()) {
			$feedback .= _('Mail successfully sent ');
		} else {
			$feedback .= _('Sending mail failed');
			$continue = false;
		}
	}
	return $continue;
}
