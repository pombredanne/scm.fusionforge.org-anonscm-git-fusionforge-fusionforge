<?php
/**
 * Copyright 2019, Franck Villaume - TrivialDev
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
 * along with Fusionforge. If not, see <http://www.gnu.org/licenses/>.
 */

require_once $gfcommon.'include/FFObject.class.php';
require_once $gfcommon.'diary/DiaryNote.class.php';

class DiaryFactory extends FFObject {

	var $diarynoteids = false;

	function __construct() {
		parent::__construct();
		if (!forge_get_config('use_diary')) {
			$this->setError(_('Diary feature is off.'));
			return;
		}
	}

	function getDiaryNoteIDs($public) {
		if (is_array($this->diarynoteids)) {
			return $this->diarynoteids;
		}
		$qpa = false;
		$qpa = db_construct_qpa($qpa, 'SELECT id FROM user_diary', array());
		if ($public) {
			$qpa = db_construct_qpa($qpa, ' WHERE is_public = $1', array($public));
		}
		$qpa = db_construct_qpa($qpa, ' ORDER BY date_posted DESC', array());
		$res = db_query_qpa($qpa);
		$this->diarynoteids = util_result_column_to_array($res);
		return $this->diarynoteids;
	}

	function hasNotes($public = 0) {
		$this->getDiaryNoteIDs($public);
		if (is_array($this->diarynoteids) && count($this->diarynoteids)) {
			return true;
		}
		return false;
	}

	function getDiaryNote($diarynote_id) {
		return diarynote_get_object($diarynote_id);
	}
}
