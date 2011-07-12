<?php
#
# Copyright (c) STMicroelectronics, 2005. All Rights Reserved.

 # Originally written by Jean-Philippe Giola, 2005
 #
 # This file is a part of codendi.
 #
 # codendi is free software; you can redistribute it and/or modify
 # it under the terms of the GNU General Public License as published by
 # the Free Software Foundation; either version 2 of the License, or
 # (at your option) any later version.
 #
 # codendi is distributed in the hope that it will be useful,
 # but WITHOUT ANY WARRANTY; without even the implied warranty of
 # MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 # GNU General Public License for more details.
 #
 # You should have received a copy of the GNU General Public License along
 # with this program; if not, write to the Free Software Foundation, Inc.,
 # 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 #
 # $Id$
 #
require_once 'ForumML_MessageDao.class.php';

// ForumML Database Query Class
class ForumMLInsert {
    var $id_message;
    var $mail;
    var $id_list;
    var $dao;

    // Class Constructor
	function __construct($list_id) {
		// set id_list
		$this->id_list = $list_id;
		$this->dao = new ForumML_MessageDao(CodendiDataAccess::instance());
	}

    // Insert values into forumml_messageheader table
    function insertMessageHeader($id_header,$value) {
	$this->dao->insertMessageHeader($this->id_message,$id_header,$value);
    }

    // Insert values into forumml_attachment table
    function insertAttachment($id_message, $filename,$filetype,$filepath,$content_id="") {
        if (is_file($filepath)) {
            $filesize = filesize($filepath);
        } else {
            $filesize = 0;
        }
	$this->dao->insertAttachment($id_message, $filename, $filetype, $filesize, $filepath,$content_id);
    }

    // Insert values into forumml_header table
    function insertHeader($header) {

    	// Search if the header is already in the table
       $result = $this->dao->searchHeader($header);
        // If not, insert it
       if ($result->rowCount()<1) {
	       return $this->dao->insertHeader($header);
       } else {
	       $row=$result->getRow();
	       return $row['id_header'];
       }
    }

    function getParentMessageFromHeader($messageIdHeader) {
	    $result = $this->dao->getParentMessageFromHeader($messageIdHeader) ;
	    if ($result && $result->rowCount() >= 1 ) {
		    $row = $result->getRow();
		    return $row['id_message'];
	    }
	    return false;

    }

    function updateParentDate($messageId, $date) {
	    if ($messageId != 0) {
		    $dar = $this->dao->getParents($messageId);
		    if ($dar) {
			    $row = $dar->getRow();
			    if ($date > $row['last_thread_update']) {
				    $this->dao->updateParentDate($messageId, $date);

				    $this->updateParentDate($row['id_parent'], $date);
			    }
		    }
	    }
    }

    // Insert values into forumml_message table
    function insertMessage($structure,$body,$ctype="") {

	    $this->mail = $structure;

	    if (isset($structure["in-reply-to"])) {
		    // special case: 'in-reply-to' header may contain "Message from ... "
		    if (preg_match('/^Message from.*$/',$structure["in-reply-to"])) {
			    $arr = explode(" ",$structure["in-reply-to"]);
			    $reply_to = $arr[count($structure["in-reply-to"]) - 1];
		    } else {
			    $reply_to = $structure["in-reply-to"];
		    }
	    } else {
		    if (isset($structure["references"])) {
			    // special case: 'in-reply-to' header is not set, but 'references' - which contain list of parent messages ids - is set
			    $ref_arr = explode(" ",$structure["references"]);
			    $reply_to = $ref_arr[count($structure["references"]) - 1];
		    } else {
			    $reply_to = "";
		    }
	    }

	    // Message date
	    // Cannot rely on server's date because it might be different
	    // and it doesn't work when it comes to load mail archives!
	    $messageDate = strtotime($structure['date']);

	    $id_parent = 0;
	    // If the current message is an answer
	    if ($reply_to != "") {
		    $id_parent = $this->getParentMessageFromHeader($reply_to);
	    }

	    if ($id_parent != 0) {
		    $this->updateParentDate($id_parent, $messageDate);
	    }
$this->id_message = $this->dao->insertMessage($this->id_list,  $id_parent , $body , $messageDate , $ctype);

	    // All headers of the current mail are stored in the forumml_messageheader table
	    $k=0;
	    foreach ($structure as $header => $value_header) {
		    $k++;
		    if ($k != 1) {
			    if ($header != "received") {
				    $id_header = $this->insertHeader($header);
				    if (is_array($value_header)) {
					    $value_header = implode(",",$value_header);
				    }
				    $this->insertMessageHeader($id_header,$value_header);
			    }
		    }
	    }

	    return $this->id_message;
    }

    /**
     * Encode string in UTF8 if source charset given or if detected
     */
    function getUtf8String($string,$charset=null) {
	    if ($charset == null) {
		    $charset = mb_detect_encoding($string);
	    }
	    if ($charset) {
		    return mb_convert_encoding($string, 'UTF-8', $charset);
	    } else {
		    return $string;
	    }
    }

    /**
     * Convert structure body to utf8 if charset defined in structure headers
     */
    function getUtf8Body($structure) {
	    $charset = null;
	    if (isset($structure->headers["content-type"]) && isset($structure->ctype_parameters['charset'])) {
		    $charset = $structure->ctype_parameters['charset'];
	    }
	    if (isset($structure->body)) {
		    return $this->getUtf8String($structure->body, $charset);
	    } else {
		    return '';
	    }
    }

    /**
     * Extract from given structure the content and store it as an attachment of the given message
     *
     * @param Integer             $messageId   Message id
     * @param Object              $struct      Subpart of a Mime message to treat
     * @param Object              $mailHeaders Headers of the message (not the subpart)
     * @param ForumML_FileStorage $storage     Object that manage the file storage on FS
     */
    function storePart($messageId, $struct, $mailHeaders, $storage) {
	    if (isset($struct->body) && trim($struct->body) != "") {
		    $body = $struct->body;
		    $filetype = $struct->headers["content-type"];
		    if ($struct->ctype_primary == 'text' && $struct->ctype_secondary == 'html') {
			    $filename = "message_".substr($mailHeaders["message-id"], 1, strpos($mailHeaders["message-id"], '@') - 1).".html";
		    } else {
			    if (! isset($struct->d_parameters["filename"])) {
				    // special case where a content is attached, without filename
				    $pos = strpos($filetype,"name=");
				    if ($pos === false) {
					    // set filename to 'attachment_<k>'
					    $filename = "attachment";
				    } else {
					    // get filename from 'name' section
					    $filename = substr(substr($filetype,$pos),6,-1);
				    }
			    } else {
				    $filename = $struct->d_parameters["filename"];
			    }
		    }
		    $basename = basename($filename);

		    // For multipart/related emails
		    $content_id = '';
		    if (isset($struct->headers['content-id'])) {
			    $content_id = $struct->headers['content-id'];
		    }

		    // store attachment in /var/lib/codendi/forumml/<listname>/<Y_M_D>
		    $date  = date("Y_m_d",strtotime($mailHeaders["date"]));
		    $fpath = $storage->store($basename, $struct->body, $this->id_list, $date);

		    // insert attachment in the DB
		    $this->insertAttachment($messageId, $basename, $filetype, $fpath, $content_id);
	    }
    }

    /**
     * Parse recursively Mime message to create the message and it's attachments in DB
     *
     * A MIME message is a hierarchical organization that maybe very
     * simple for a text message (just one structure with headers and
     * a text body) to a very complex HTML mail with inline images,
     * attachments sent in Text+HTML.
     *
     * The main challenge of this method is to find the "root" of the
     * MIME message to store it as a message in the DB, all the other
     * stuff will be attached to this message as an attachment.
     *
     * The root message can be either:
     * - The text version of the message. This applies for
     *   -> mail in plain text (with or without attachments)
     *   -> mail in HTML sent in Text+HTML
     * - If no text version available:
     *   -> if their is an HTML version of the mail, we store it
     *      (happens with mail sent in HTML only).
     *   -> if their is no HTML, we store an empty body.
     *
     * How do we detect the root message:
     * -> We crawl the hierarchy and we take the first text/plain or
     *    text/html part.
     * -> Otherwise, if we are about to store an attachment (an
     *    attachment is everything but first text/plain or first
     *    text/html) we create a empty message.
     *
     * @see http://en.wikipedia.org/wiki/MIME
     *
     * @param Object              $struct      Subpart of a Mime message to treat
     * @param Object              $mailHeaders Headers of the message (not the subpart)
     * @param ForumML_FileStorage $storage     Object that manage the file storage on FS
     * @param Integer             $messageId   Message id
     */
    function storeMime($struct, $mailHeaders, $storage, $messageId=0) {
	    if ($struct->ctype_primary == 'multipart') {
		    foreach ($struct->parts as $part) {
			    $messageId = $this->storeMime($part, $mailHeaders, $storage, $messageId);
		    }
	    } else {
		    $inserted = false;
		    if ($struct->ctype_primary == 'text') {
			    switch ($struct->ctype_secondary) {
				    case 'html':
				    case 'plain':
					    if ($messageId == null) {
						    $body      = $this->getUtf8Body($struct);
						    if (isset($struct->headers["content-type"])) {
							    $ctype = $struct->headers["content-type"];
						    } else {
							    $ctype = "";
						    }
						    $messageId = $this->insertMessage($mailHeaders, $body, $ctype);
						    $inserted  = true;
					    }
					    break;
			    }
		    }

		    if ($messageId == 0) {
			    if (isset($struct->headers["content-type"])) {
				    $ctype = $struct->headers["content-type"];
			    } else {
				    $ctype = "";
			    }
			    $messageId = $this->insertMessage($mailHeaders, "", $ctype);
		    }

		    if (!$inserted) {
			    $this->storePart($messageId, $struct, $mailHeaders, $storage);
		    }
	    }
	    return $messageId;
    }

    /**
     * Abandon all hope you who enter here! Mail & MIME is at best a nightmare, take a couple of
     * bottles before diving into this code...
     * http://en.wikipedia.org/wiki/MIME
     *
     * List (not comprehensive) of email possibilities
     * Text                                                         text/plain
     * -> pure_text.mbox
     * Text + attached files                                        multipat/mixed (text/plain, other/mime)
     * -> text_plus_attachment.mbox
     * HTML (sent in Text + HTML)                                   multipart/alternative (text/plain, text/html)
     * -> pure_html_text_plus_html.mbox
     * HTML (sent in HTML)                                          text/html
     * -> pure_html_in_html_only.mbox
     * HTML + inline image (sent in Text + HTML)                    multipart/alternative(text/plain, multipart/related(text/html, image/png))
     * -> html_with_inline_content_in_text_plus_html.mbox
     * HTML + inline image (sent in HTML)                           multipart/related(text/html, image/png)
     * -> html_with_inline_content_in_html_only.mbox
     * HTML + attached file (sent in Text + HTML)                   multipart/mixed(multipart/alternative(text/plain, text/html), other/mime))
     * HTML + attached file (sent in HTML)                          multipart/mixed(text/html, other/mime)
     * HTML + inline image + attached file (sent in Text + HTML)    multipart/mixed(multipart/alternative(text/plain, multipart/related(text/html, image/png)), other/mime)
     * -> html_with_inline_content_and_attch_in_text_plus_html.mbox
     * HTML + inline image + attached file (sent in HTML)           multipart/mixed(multipart/related(text/html, image/png), other/mime)
     * -> html_with_inline_content_and_attch_in_html_only.mbox
     */
    public function storeEmail($email, $storage) {
	    return $this->storeMime($email, $email->headers, $storage);
    }
}

?>
