<?php
/**
 * GForge Search Engine
 *
 * Copyright 2004 (c) Dominik Haas, GForge Team
 *
 * http://gforge.org
 *
 * @version $Id$
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

	/**
	 * getRows - get the html output for result rows
	 *
	 * @return string html output
	 */
	function getRows() {
		$rowsCount = $this->searchQuery->getRowsCount();
		$result =& $this->searchQuery->getResult();
		$dateFormat = _('Y-m-d H:i');

		$return = '';
		$rowColor = 0;
		$lastForumName = null;
		
		for($i = 0; $i < $rowsCount; $i++) {
			//section changed
			$currentForumName = db_result($result, $i, 'forum_name');
			if ($lastForumName != $currentForumName) {
				$return .= '<tr><td colspan="4">'.$currentForumName.'</td></tr>';
				$lastForumName = $currentForumName;
				$rowColor = 0;
			}
			$return .= '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($rowColor) .'>'
						. '<td width="5%">&nbsp;</td>'
						. '<td><a href="'.util_make_url ('/forum/message.php?msg_id='. db_result($result, $i, 'msg_id')).'">'
							. html_image('ic/msg.png', '10', '12', array('border' => '0')).' '.db_result($result, $i, 'subject')
							.'</a></td>'			
						. '<td width="15%">'.db_result($result, $i, 'realname').'</td>'
						. '<td width="15%">'.date($dateFormat, db_result($result, $i, 'post_date')).'</td></tr>';
			$rowColor ++;
		}
		return $return;
	}
	
	/**
	 * getSections - get the array of possible sections to search in
	 * 
  	 * @return array sections
	 */				
	function getSections($groupId) {
		$userIsGroupMember = $this->isGroupMember($groupId);
		
		return ForumsSearchQuery::getSections($groupId, $userIsGroupMember);
	}
}

?>
