<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright 2021, Franck Villaume - TrivialDev
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
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'Widget.class.php';

/**
 * Widget_ProjectLatestNews
 */
class Widget_ProjectLatestNews extends Widget {
	var $content;

	function __construct() {
		global $project;
		parent::__construct('projectlatestnews');
		if ($project && $this->canBeUsedByProject($project)) {
			require_once 'www/news/news_utils.php';
			$this->content = news_show_latest($project->getID(), 10, false);
		}
	}

	function getTitle() {
		return _('Latest News');
	}

	function getContent() {
		return $this->content;
	}

	function isAvailable() {
		return $this->content ? true : false;
	}

	function hasRss() {
		return true;
	}

	function getRssUrl($owner_id, $owner_type) {
		if ($owner_type != 'g') {
			return false;
		}
		return '/export/rss20_news.php?group_id=' . $owner_id;
	}

	function canBeUsedByProject(&$project) {
		return $project->usesNews();
	}

	function getDescription() {
		return _('List the last 10 pieces of news posted by the project members.');
	}
}
