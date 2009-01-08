<?php
/**
 * GForge Search Engine
 *
 * Copyright 2004 (c) Dominik Haas, GForge Team
 *
 * http://gforge.org
 *
 * @version $Id: ForumsSearchQuery.class.php 6506 2008-05-27 20:56:57Z aljeux $
 */
 
require_once $gfcommon.'search/SearchQuery.class.php';

class ForumsSearchQuery extends SearchQuery {
	
	/**
	* group id
	*
	* @var int $groupId
	*/
	var $groupId;
	
	/**
	* flag if non public items are returned
	*
	* @var boolean $showNonPublic
	*/	
	var $showNonPublic;
	
	/**
	 * Constructor
	 *
	 * @param string $words words we are searching for
	 * @param int $offset offset
	 * @param boolean $isExact if we want to search for all the words or if only one matching the query is sufficient
	 * @param int $groupId group id
	 * @param array $sections sections to search in
	 * @param boolean $showNonPublic flag if private sections are searched too
	 */
	function ForumsSearchQuery($words, $offset, $isExact, $groupId, $sections=SEARCH__ALL_SECTIONS, $showNonPublic=false) {
		$this->groupId = $groupId;
		$this->showNonPublic = $showNonPublic;
		
		$this->SearchQuery($words, $offset, $isExact);
		
		$this->setSections($sections);
	}

	/**
	 * getQuery - get the sql query built to get the search results
	 *
	 * @return string sql query to execute
	 */
	function getQuery() {
		global $sys_use_fti;
		if ($sys_use_fti) {
			$nonPublic = 'false';
			$sections = '';
			if ($this->showNonPublic) {
				$nonPublic = 'true';
			}
			if ($this->sections != SEARCH__ALL_SECTIONS) {
				$sections = $this->sections;
			}
			$sql = 'SELECT forum.msg_id, headline(forum.subject, q) AS subject, forum.post_date, users.realname, forum_group_list.forum_name '
				. 'FROM forum, users, forum_group_list, forum_idx, to_tsquery(\''.
				  $this->getFormattedWords().'\') as q '
				. 'WHERE users.user_id = forum.posted_by '
				. 'AND vectors @@ q AND forum.msg_id = forum_idx.msg_id '
				. 'AND forum_group_list.group_forum_id = forum.group_forum_id '
				. 'AND forum_group_list.is_public <> 9 '			
				. 'AND forum.group_forum_id IN (SELECT group_forum_id FROM forum_group_list WHERE group_id = '.$this->groupId.') ';
			if ($this->sections != SEARCH__ALL_SECTIONS) {
				$sql .= 'AND forum_group_list.group_forum_id IN ('.$this->sections.') ';
			}
			if (!$this->showNonPublic) {
				$sql .= 'AND forum_group_list.is_public = 1 ';
			}
			$sql .= 'ORDER BY forum_group_list.forum_name ASC, forum.msg_id ASC, rank(vectors, q) DESC';
		} else {
			$sql = 'SELECT forum.msg_id, forum.subject, forum.post_date, users.realname, forum_group_list.forum_name '
				. 'FROM forum, users, forum_group_list '
				. 'WHERE users.user_id = forum.posted_by '
				. 'AND forum_group_list.group_forum_id = forum.group_forum_id '
				. 'AND forum_group_list.is_public <> 9 '			
				. 'AND forum.group_forum_id IN (SELECT group_forum_id FROM forum_group_list WHERE group_id = '.$this->groupId.') ';
			if ($this->sections != SEARCH__ALL_SECTIONS) {
				$sql .= 'AND forum_group_list.group_forum_id IN ('.$this->sections.') ';
			}
			if (!$this->showNonPublic) {
				$sql .= 'AND forum_group_list.is_public = 1 ';
			}
			$sql .= 'AND (('.$this->getIlikeCondition('forum.body', $this->words).') '
				. 'OR ('.$this->getIlikeCondition('forum.subject', $this->words).')) '
				. 'ORDER BY forum_group_list.forum_name, forum.msg_id';
		}
		return $sql;
	}

	/**
	 * getSearchByIdQuery - get the sql query built to get the search results when we are looking for an int
	 *
	 * @return string sql query to execute
	 */	
	function getSearchByIdQuery() {
		$sql = 'SELECT msg_id '
			. 'FROM forum, forum_group_list '
			. 'WHERE msg_id=\''.$this->searchId.'\' '
			. 'AND forum_group_list.group_forum_id = forum.group_forum_id '
			. 'AND group_forum_id=\''.$this->forumId.'\'';
		if (!$this->showNonPublic) {
			$sql .= ' AND forum_group_list.is_public = 1';
		}

		return $sql;
	}
	
	/**
	 * getSections - returns the list of available forums
	 *
	 * @param $groupId int group id
	 * @param $showNonPublic boolean if we should consider non public sections
	 */
	function getSections($groupId, $showNonPublic=false) {
		$sql = 'SELECT group_forum_id, forum_name FROM forum_group_list WHERE group_id = '.$groupId.' AND is_public <> 9';
		if (!$showNonPublic) {
			$sql .= ' AND is_public = 1';
		}
		$sql .= ' ORDER BY forum_name';
		
		$sections = array();
		$res = db_query($sql);
		while($data = db_fetch_array($res)) {
			$sections[$data['group_forum_id']] = $data['forum_name'];
		}
		return $sections;
	}
}

?>
