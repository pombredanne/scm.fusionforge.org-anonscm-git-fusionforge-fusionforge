<?php
/**
 * Survey Facility: Question handle program
 *
 * Copyright 2004 (c) GForge Team
 * Copyright 2010 (c) FusionForge Team
 * Copyright (C) 2010-2011 Alain Peyrat - Alcatel-Lucent
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

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfcommon.'survey/Survey.class.php';
require_once $gfcommon.'survey/SurveyFactory.class.php';
require_once $gfcommon.'survey/SurveyQuestion.class.php';
require_once $gfcommon.'survey/SurveyQuestionFactory.class.php';
require_once $gfwww.'survey/include/SurveyHTML.class.php';

global $HTML;

$group_id = getIntFromRequest('group_id');
$survey_id = getIntFromRequest('survey_id');

/* We need a group_id */
if (!$group_id) {
	exit_no_group();
}

$g = group_get_object($group_id);
if (!$g || !is_object($g) || $g->isError()) {
	exit_no_group();
}

$is_admin_page='y';
$sh = new  SurveyHTML();
$s = new Survey($g, $survey_id);

if (!session_loggedin() || !forge_check_perm('project_admin', $group_id)) {
	$sh->header(array());
	echo $HTML->error_msg(_('Permission denied.'));
	$sh->footer();
	exit;
}

if (getStringFromRequest('post')=="Y") {
	if (!form_key_is_valid(getStringFromRequest('form_key'))) {
		exit_form_double_submit('surveys');
	}
	$survey_title = getStringFromRequest('survey_title');
	$to_add = getStringFromRequest('to_add');
	$to_del = getStringFromRequest('to_del');
	$is_active = getStringFromRequest('is_active');

	if ($survey_id) { /* Modify */
		$s->update($survey_title, $to_add, $to_del, $is_active);
		$feedback = _('Successfully Updated');
	}  else {  /* Add */
		$s->create($survey_title, $to_add, $is_active);
		$feedback = _('Survey Added');
	}
}

/* Order changes */
if (getStringFromRequest('updown')=="Y") {
	$question_id = getIntFromRequest('question_id');
	$is_up = getStringFromRequest('is_up');
	$s->updateOrder($question_id, $is_up);
	$feedback = _('Successfully Updated');
}

/* Error on previous transactions? */
if ($s->isError()) {
	$error_msg = $s->getErrorMessage();
	form_release_key(getStringFromRequest("form_key"));
}

$title = $survey_id ? _('Edit a Survey') : _('Add a Survey');
$sh->header(array('title'=>$title, 'modal'=>1));

echo $sh->showAddSurveyForm($s);

/* Show list of Survey */
$sf = new SurveyFactory($g);
$ss = & $sf->getSurveys();
if (!$ss) {
	echo $HTML->information(_('No Survey is found'));
} else {
	echo $sh->showSurveys($ss, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1);
}

$sh->footer();
