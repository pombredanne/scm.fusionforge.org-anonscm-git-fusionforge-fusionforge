<?php
/**
 * Search Engine
 *
 * Copyright 2004 (c) Dominik Haas, GForge Team
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

require_once $gfwww.'search/include/renderers/HtmlGroupSearchRenderer.class.php';
require_once $gfcommon.'search/ForumsSearchQuery.class.php';

class ForumsHtmlSearchRenderer extends HtmlGroupSearchRenderer {

	/**
	 * Constructor
	 *
	 * @param string $words words we are searching for
	 * @param int $offset offset
	 * @param boolean $isExact if we want to search for all the words or if only one matching the query is sufficient
	 * @param int $groupId group id
	 * @param array $sections array of all sections to search in (array of strings)
	 *
	 */
	function ForumsHtmlSearchRenderer($words, $offset, $isExact, $groupId, $sections=SEARCH__ALL_SECTIONS) {
		$userIsGroupMember = $this->isGroupMember($groupId);

		$searchQuery = new ForumsSearchQuery($words, $offset, $isExact, $groupId, $sections, $userIsGroupMember);

		$this->HtmlGroupSearchRenderer(SEARCH__TYPE_IS_FORUMS, $words, $isExact, $searchQuery, $groupId, 'forums');

		$this->tableHeaders = array(
			'',
			_('Thread'),
			_('Author'),
			_('Date')
		);
	}

	function getFilteredRows() {
		$rowsCount = $this->searchQuery->getRowsCount();
		$result =& $this->searchQuery->getResult();

		$fields = array ('group_forum_id',
				 'msg_id',
				 'forum_name',
				 'subject',
				 'realname',
				 'post_date');

		$fd = array();
		for($i = 0; $i < $rowsCount; $i++) {
			if (forge_check_perm('forum',
					     db_result($result, $i, 'group_forum_id'),
					     'read')) {
				$r = array();
				foreach ($fields as $f) {
					$r[$f] = db_result($result, $i, $f);
				}
				$fd[] = $r;
			}
		}
		return $fd;
	}

	/**
	 * getRows - get the html output for result rows
	 *
	 * @return string html output
	 */
	function getRows() {
		$fd = $this->getFilteredRows();

		$dateFormat = _('Y-m-d H:i');

		$return = '';
		$rowColor = 0;
		$lastForumName = null;
		
		foreach ($fd as $row) {
			//section changed
			$currentForumName = $row['forum_name'];
			if ($lastForumName != $currentForumName) {
				$return .= '<tr><td colspan="4">'.$currentForumName.'</td></tr>';
				$lastForumName = $currentForumName;
				$rowColor = 0;
			}
			$return .= '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($rowColor) .'>'
						. '<td width="5%"></td>'
						. '<td><a href="'.util_make_url ('/forum/message.php?msg_id='. $row['msg_id']).'">'
							. html_image('ic/msg.png', '10', '12').' '.$row['subject']
							.'</a></td>'			
						. '<td width="15%">'.$row['realname'].'</td>'
						. '<td width="15%">'.date($dateFormat, $row['post_date']).'</td></tr>';
			$rowColor ++;
		}
		return $return;
	}

	/**
	 * getSections - get the array of possible sections to search in
	 *
  	 * @return array sections
	 */
	static function getSections($groupId) {
		$userIsGroupMember = ForumsHtmlSearchRenderer::isGroupMember($groupId);

		return ForumsSearchQuery::getSections($groupId, $userIsGroupMember);
	}
}
