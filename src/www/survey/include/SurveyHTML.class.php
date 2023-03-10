<?php
/**
 * FusionForge Survey HTML Facility
 *
 * Portions Copyright 1999-2001 (c) VA Linux Systems
 * The rest Copyright 2002-2004 (c) GForge Team - Sung Kim
 * Copyright 2008-2010 (c) FusionForge Team
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013-2014,2016, Franck Villaume - TrivialDev
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

require_once $gfcommon.'include/pre.php';
require_once $gfwww.'include/note.php';

/**
 * Survey HTML related functions
 */
class SurveyHTML extends FFError {

	/**
	 * header() - Show survey header
	 *
	 * @param	array	$params
	 */
	function header($params) {
		global $group_id,$is_admin_page,$HTML;

		if (!forge_get_config('use_survey')) {
			exit_disabled();
		}

		$params['toptab'] = 'surveys';
		$params['group'] = $group_id;

		if ($project = group_get_object($group_id)) {
			if (!$project->usesSurvey()) {
				exit_disabled();
			}

			if ($is_admin_page && $group_id) {
				$params['submenu'] = $HTML->subMenu(
					array(
						_('Add Survey'),
						_('Add Question'),
						_('Show Results'),
						_('Administration')
					),
					array(
						'/survey/admin/survey.php?group_id='.$group_id,
						'/survey/admin/question.php?group_id='.$group_id,
						'/survey/admin/show_results.php?group_id='.$group_id,
						'/survey/admin/?group_id='.$group_id
					),
					array(
						null,
						null,
						null,
						null
					)
				);
			} else {
				$labels[] = _('Views Surveys');
				$links[]  = '/survey/?group_id='.$group_id;
				$arr[] = null;
				if (forge_check_perm ('project_admin', $group_id)) {
						$labels[] = _('Administration');
						$links[]  = '/survey/admin/?group_id='.$group_id;
						$arr[] = null;
				}
				$params['submenu'] = $HTML->subMenu($labels, $links, $arr);
			}
			site_project_header($params);
		}// end if (valid group id)
	}

	/**
	 * footer() - Show Survey footer
	 *
	 * @param	array	$params
	 */
	function footer($params = array()) {
		site_project_footer($params);
	}

	/**
	 * showAddQuestionForm() - Show Add/Modify Question Forums
	 *
	 * @param	Survey	$q	Question Question Object
	 * @return	string
	 */
	function showAddQuestionForm(&$q) {
		global $group_id;
		global $HTML;

		/* Default is add */
		$question_button = _('Add this Question');

		/* If we have a question object, it is a Modify */
		if ($q && is_object($q) && !$q->isError() && $q->getID()) {
			$warning_msg = $HTML->warning_msg(_('WARNING! It is a bad idea to change a question after responses to it have been submitted'));
			$question_id = $q->getID();
			$question = $q->getQuestion();
			$question_type = $q->getQuestionType();
			$question_button = _('Submit Changes');
		} else {
			$warning_msg = '';
			$question = '';
			$question_id = '';
			$question_type = '';
		}

		$ret = $warning_msg;
		$ret.= $HTML->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'post'));
		$ret.='<p><input type="hidden" name="post" value="Y" />';
		$ret.='<input type="hidden" name="group_id" value="'.$group_id.'" />';
		$ret.='<input type="hidden" name="question_id" value="'.$question_id.'" />';
		$ret.='<input type="hidden" name="form_key" value="' . form_generate_key() . '" />';
		$ret.='<label for="question">'._('Question')._(':').'</label>'.'<br />';
		$ret.='<input id="question" required="required" type="text" name="question" value="'.$question.'" size="60" maxlength="150" /></p>';
		$ret.='<p><label for="question_type">'. _('Question Type')._(':').'</label><br />';

		$result = db_query_params('SELECT * FROM survey_question_types', array());
		$ret.= html_build_select_box($result,'question_type',$question_type,false);

		$ret.='</p><p><input type="submit" name="submit" value="'.$question_button.'" /></p>' . "\n";
		$ret.= $HTML->closeForm();

		return $ret;
	}

	/**
	 * showAddSurveyForm() - Show Add/Modify Question Forums
	 *
	 * @param	Survey	$s	Question Question Object
	 * @return	string
	 */
	function showAddSurveyForm(&$s) {
		global $group_id;
		global $survey_id;
		global $HTML;

		/* If no question is available */
		if (!$survey_id && !count($s->getAddableQuestionInstances())) {
			$ret = '<p>' . sprintf(_('Please %1$s create a question %2$s before creating a survey'),
								  '<a href="'.util_make_url('/survey/admin/question.php?group_id='.$group_id).'">',
								  '</a>') .
				   '</p>';
			return $ret;
		}

		/* Default is add */
		$survey_button = _('Add this Survey');
		$active = ' checked="checked" ';
		$inactive = '';

		/* If we have a survey object, it is a Modify */
		if ($s && is_object($s) && !$s->isError() && $s->getID()) {
			$warning_msg = $HTML->warning_msg(_('WARNING! It is a bad idea to edit a survey after responses have been posted'));
			$survey_id = $s->getID();
			$survey_title = $s->getTitle();
			$survey_questions = $s->getQuestionString();
			$survey_button = _('Submit Changes');
			if (!$s->isActive()) {
				$inactive = 'checked ="checked" ';
				$active ='';
			}
		} else {
			$warning_msg = '';
			$survey_questions = '';
			$survey_title = '';
		}

		$ret = $warning_msg;
		$ret.= $HTML->openForm(array('action' => getStringFromServer('PHP_SELF'), 'method' => 'post'));
		$ret.='<p><input type="hidden" name="post" value="Y" />';
		$ret.='<input type="hidden" name="group_id" value="'.$group_id.'" />';
		$ret.='<input type="hidden" name="survey_id" value="'.$survey_id.'" />';
		$ret.='<input type="hidden" name="survey_questions" value="'.$survey_questions.'" />';
		$ret.='<input type="hidden" name="form_key" value="' . form_generate_key() . '" />';
		$ret.='<label for="survey_title">';
		$ret.='<strong>'._('Survey Title').utils_requiredField()._(':').'</strong>';
		$ret.='</label>';
		$ret.= '<input required="required" type="text" name="survey_title" id="survey_title" value="'.$survey_title.'" size="60" maxlength="150" /></p>';

		$ret.='<p><strong>'. _('Is Active?').'</strong>';
		$ret.='<br /><input type="radio" name="is_active" id="is_active_yes" value="1"' .$active. '/> <label for="is_active_yes">'._('Yes').'</label>';
		$ret.='<br /><input type="radio" name="is_active" id="is_active_no" value="0"' .$inactive. '/> <label for="is_active_no">'._('No').'</label>';
		$ret.='</p>';

		$arr_to_add = & $s->getAddableQuestionInstances();
		$arr_to_del = & $s->getQuestionInstances();

		if (!empty($arr_to_add)) {
			$ret.='<h2>'. _('Addable Questions').'</h2>';
			$title_arr[] = "&nbsp;";
			$title_arr[] = _('Questions');
			$title_arr[] = "&nbsp;";
			$ret.= $HTML->listTableTop($title_arr);
		}

		for($i = 0;  $i < count($arr_to_add); $i++) {

			if ($arr_to_add[$i]->isError()) {
				echo $arr_to_add[$i]->getErrorMessage();
				continue;
			}

			if ($i%3==0) {
				$ret.= "<tr>\n";
			}

			$ret.= '<td><input type="checkbox" id="to_add_'.$i.'" name="to_add[]" value="'.$arr_to_add[$i]->getID().'" />'.
				'<label for="to_add_'.$i.'">'.
				$arr_to_add[$i]->getQuestion().' ('.
				$arr_to_add[$i]->getQuestionStringType().")</label></td>\n";

			if ($i%3==2) {
				$ret.= "</tr>";
			}
		}

		if (!empty($arr_to_add)) {
			/* Fill the remain cells */
			if ($i%3==1) {
				$ret.='<td>&nbsp;</td><td>&nbsp;</td></tr>';
			} elseif ($i%3==2) {
				$ret.='<td>&nbsp;</td></tr>';
			}

			$ret.= $HTML->listTableBottom();
		}

		/* Deletable questions */
		if (!empty($arr_to_del)) {
			$ret.='<h2>'. _('Questions in this Survey').'</h2>';
			$title_arr = array('', _('Question'), _('Type'), _('Order'), _('Delete from this Survey'));
			$ret.= $HTML->listTableTop($title_arr);
		}

		for($i = 0; $i < count($arr_to_del); $i++) {
			if ($arr_to_del[$i]->isError()) {
				echo $arr_to_del[$i]->getErrorMessage();
				continue;
			}

			$ret.= "<tr>\n";

			$ret.= '<td>'.$arr_to_del[$i]->getID().'</td>';
			$ret.= '<td>'.$arr_to_del[$i]->getQuestion().'</td>';
			$ret.= '<td>'.$arr_to_del[$i]->getQuestionStringType().'</td>';
			$ret.= '<td><center>['.util_make_link('/survey/admin/survey.php?group_id='.$group_id.'&survey_id='. $survey_id.'&is_up=1&updown=Y&question_id='.$arr_to_del[$i]->getID(),_('Up')).'] ';
			$ret.= '['.util_make_link('/survey/admin/survey.php?group_id='.$group_id.'&survey_id='. $survey_id.'&is_up=0&updown=Y&question_id='.$arr_to_del[$i]->getID(),_('Down')).']</center></td>';

			$ret.= '<td><center><input type="checkbox" name="to_del[]" value="'.$arr_to_del[$i]->getID().'" /></center></td>';
			$ret.= '</tr>';

		}

		if (!empty($arr_to_del)) {
			$ret.= $HTML->listTableBottom();
		}

		$ret.='<p><input type="submit" name="submit" value="'.$survey_button.'" /></p>';
		$ret.= $HTML->closeForm();
		return $ret;
	}

	/**
	 * showQuestions() - Show list of questions
	 *
	 * @param	$questions
	 * @return	string
	 */
	function showQuestions(&$questions) {
		global $group_id;
		global $HTML;

		$n = count($questions);
		$ret = "<h2>" . sprintf(ngettext("%d question found", "%d questions found", $n), $n)."</h2>";

		/* Head information */
		$title_arr = array(_('Question ID'), _('Question'), _('Type'), _('Edit/Delete'));
		$ret.= $HTML->listTableTop($title_arr);

		for ($i = 0; $i < count($questions); $i++) {
			if ($questions[$i]->isError()) {
				echo $questions[$i]->getErrorMessage();
				continue;
			}

			$ret.= "<tr>\n";
			$ret.= '<td>'.util_make_link('/survey/admin/question.php?group_id='.$group_id.'&question_id='.$questions[$i]->getID(), $questions[$i]->getID()).'</td>'."\n";
			$ret.= '<td>'.$questions[$i]->getQuestion().'</td>';
			$ret.= '<td>'.$questions[$i]->getQuestionStringType().'</td>';
			/* Edit/Delete Link */
			$ret.= '<td>['.util_make_link('/survey/admin/question.php?group_id='.$group_id.'&question_id='.$questions[$i]->getID(), _('Edit')).'] ';
			$ret.= '['.util_make_link('/survey/admin/question.php?delete=Y&group_id='.$group_id.'&question_id='.$questions[$i]->getID(), _('Delete')).']</td>';
			$ret.= '</tr>';
		}
		$ret.= $HTML->listTableBottom();
		return $ret;
	}

	/**
	 * showSurveys() - Show list of surveys with many options
	 *
	 * have to set $user_id to get the right show_vote option
	 * @param $surveys
	 * @param int $show_id
	 * @param int $show_questions
	 * @param int $show_number_questions
	 * @param int $show_number_votes
	 * @param int $show_vote
	 * @param int $show_edit
	 * @param int $show_result
	 * @param int $show_result_graph
	 * @param int $show_result_comment
	 * @param int $show_result_csv
	 * @param int $show_inactive
	 * @return string
	 */
	function showSurveys(&$surveys, $show_id=0, $show_questions=0,
			      $show_number_questions=0, $show_number_votes=0,
			      $show_vote=0, $show_edit=0, $show_result=0,
			      $show_result_graph=0, $show_result_comment=0,
			      $show_result_csv=0,
			      $show_inactive=0) {
		global $user_id;
		global $group_id;
		global $HTML;

		$ret = '';
		$displaycount = 0;

		/* Head information */
		if ($show_id) {
			$title_arr[] = _('Survey ID');
		}

		$title_arr[] = _('Survey Title');

		if ($show_questions) {
			$title_arr[] = _('Questions');
		}
		if ($show_number_questions) {
			$title_arr[] = _('Number of Questions');
		}
		if ($show_number_votes) {
			$title_arr[] = _('Number of Votes');
		}
		if ($show_vote && $user_id) {
			$title_arr[] = _('Did I Vote?');
		}
		if ($show_edit) {
			$title_arr[] = _('Edit');
		}
		if ($show_result) {
			$title_arr[] = _('Result');
		}
		if ($show_result_graph) {
			$title_arr[] = _('Result with Graph');
		}
		if ($show_result_comment) {
			$title_arr[] = _('Result with Graph and Comments');
		}
		if ($show_result_csv) {
			$title_arr[] = _("CSV");
		}

		$ret.= $HTML->listTableTop($title_arr);

		for($i = 0;  $i < count($surveys); $i++) {
			if ($surveys[$i]->isError()) {
				echo $surveys[$i]->getErrorMessage();
				continue;
			}

			$displaycount++;

			$ret.= "<tr>\n";
			if ($show_id) {
				$ret.= '<td>'.$surveys[$i]->getID().'</td>';
			}

			$ret.= '<td>';
			if ($surveys[$i]->isActive()) {
				$ret.= util_make_link('/survey/survey.php?group_id='.$group_id.'&survey_id='. $surveys[$i]->getID(), $surveys[$i]->getTitle());
			} else {
				$ret.= '<s>'.$surveys[$i]->getTitle().'</s>';
			}
			$ret.= '</td>';

			if ($show_questions) {
				// add a space after comma
				$ret.= '<td>'.str_replace(",", ", ", $surveys[$i]->getQuestionString()).'</td>';
			}
			if ($show_number_questions) {
				$ret.= '<td>'.$surveys[$i]->getNumberOfQuestions().'</td>';
			}
			if ($show_number_votes) {
				$ret.= '<td>'.$surveys[$i]->getNumberOfVotes().'</td>';
			}
			if ($show_vote && $user_id) {
				if ($surveys[$i]->isUserVote($user_id)) {
					$ret.='<td>'. _('Yes') . '</td>';
				} else {
					$ret.='<td>'. _('No') . '</td>';
				}
			}
			if ($show_edit) {
				/* Edit/Delete Link */
				$ret.= '<td>['.util_make_link('/survey/admin/survey.php?group_id='.$group_id.'&survey_id='. $surveys[$i]->getID(),_('Edit')).'] ';

				/* We don't support delete yet. Need to delete all results as well */
				/* $ret.= '['.util_make_link('/survey/admin/survey.php?delete=Y&group_id='.$group_id.'&survey_id='. $surveys[$i]->getID(),_('Delete')).']'; */
				$ret.='</td>';
			}
			if ($show_result) {
				/* Edit/Delete Link */
				$ret.= '<td>['.util_make_link('/survey/admin/show_results.php?group_id='.$group_id.'&survey_id='. $surveys[$i]->getID(),_('Result')).']</td>';
			}
			if ($show_result_graph) {
				/* Edit/Delete Link */
				$ret.= '<td>['.util_make_link('/survey/admin/show_results.php?graph=yes&group_id='.$group_id.'&survey_id='. $surveys[$i]->getID(),_('Result with Graph')).']</td>';
			}
			if ($show_result_comment) {
				/* Edit/Delete Link */
				$ret.= '<td>['.util_make_link('/survey/admin/show_results.php?graph=yes&show_comment=yes&group_id='.$group_id.'&survey_id='.$surveys[$i]->getID(),_('Result with Graph and Comments')).']</td>';
			}
			if ($show_result_csv) {
				/* Csv Link */
				$ret.= '<td>['.util_make_link('/survey/admin/show_csv.php?group_id='.$group_id.'&survey_id='.$surveys[$i]->getID(), _('CSV')).']</td>';
			}
			$ret.= "</tr>\n";
		}

		$ret.= $HTML->listTableBottom();
		if ($displaycount == 0) {
			return $HTML->information(_('No Survey is found'));
		}
		return $ret;
	}

	/**
	 * showSurveyForm - Show all forums of Survey
	 *
	 * @param	$s
	 * @return	string
	 */
	function showSurveyForm(&$s) {
		global $group_id;
		global $survey_id;
		global $HTML;

		if (!$s->isActive()) {
			return $HTML->error_msg(_('Error: you cannot vote for inactive survey'));
		}
		/* Get questions of this survey */
		$questions = & $s->getQuestionInstances();

		$ret="";
		if ($s->isUserVote(user_getid())) {
			$ret.= $HTML->warning_msg(_('Warning - you are about to vote a second time on this survey.'));
		}
		$ret.= $HTML->openForm(array('action' => '/survey/survey_resp.php', 'method' =>'post')).
			'<input type="hidden" name="group_id" value="'.$group_id.'" />'.
			'<input type="hidden" name="survey_id" value="'.$survey_id. '" />';

		$ret.= '<table>';

		/* Keep question numbers */
		$index = 1;
		$last_question_type = "";
		for($i = 0; $i < count($questions); $i++) {
			if ($questions[$i]->isError()) {
				echo $questions[$i]->getErrorMessage();
				continue;
			}
			$question_type = $questions[$i]->getQuestionType();
			$question_id = $questions[$i]->getID();
			$question_title = stripslashes($questions[$i]->getQuestion());

			if ($question_type == '4') {
				/* Don't show question number if it's just a comment */
				$ret.='<tr><td class="top">&nbsp;</td><td>';
			} else {
				$ret.= '<tr><td class="top"><strong>';
				/* If it's a 1-5 question box and first in series, move Quest number down a bit	*/
				if (($question_type != $last_question_type) && (($question_type == '1') || ($question_type == '3'))) {
					$ret.= '&nbsp;<br />';
				}

				$ret.= $index++.'&nbsp;&nbsp;&nbsp;&nbsp;<br /></strong></td><td>';
			}

			switch($question_type) {
			case 1: /* This is a radio-button question. Values 1-5.
			  Show the 1-5 markers only if this is the first in a series */
				if ($question_type != $last_question_type) {
					$ret.='	<strong>1</strong>'._('Low').
						'  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>5</strong>' .
						_('High').'<br />';
				}

				for ($j=1; $j<=5; $j++) {
					$ret.= '<input type="radio" name="_'.$question_id.'" value="'.$j.'" />';
				}

				$ret.= '&nbsp; '.$question_title;
				break;

			case 2:	/* This is a text-area question. */
				$ret.= $question_title.'<br />';
				$ret.='<textarea name="_'.$question_id.'" rows="5" cols="60"></textarea>';
				break;
			case 3:	/* This is a Yes/No question. Show the Yes/No only if this is the first in a series */
				if ($question_type != $last_question_type) {
					$ret.= '<strong>Yes / No</strong><br />';
				}

				$ret.='<input type="radio" name="_'.$question_id.'" value="1" />';
				$ret.='<input type="radio" name="_'.$question_id.'" value="5" />';
				$ret.='&nbsp; '.$question_title;
				break;
			case 4:	/* This is a comment only. */
				$ret.= '&nbsp;<br /><strong>'.util_make_links($question_title).'</strong>';
				$ret.= '<input type="hidden" name="_'.$question_id.'" value="-666" />';
				break;
			case 5:	/* This is a text-field question. */
				$ret.= $question_title. '<br />';
				$ret.= '<input type="text" name="_'.$question_id.'" size="20" maxlength="70" />';
				break;
			default:
				$ret.= $question_title. '<br />';
			}

			$ret.= '</td></tr>';
			$last_question_type=$question_type;
		}

		$ret.='<tr><td class="align-center" colspan="2">'.
			'<input type="submit" name="submit" value="'._('Submit').'" />'.
			'<br />'.util_make_link('/survey/privacy.php?group_id='.$group_id, _('Survey Privacy')).
			'</td></tr></table>';
		echo $HTML->closeForm();
		return $ret;
	}

	/**
	 * showResult() - Show survey Result
	 *
	 * @param	object	$sr a Survey Response Factory
	 * @param	int		$show_comment
	 * @param	string	$q_num
	 * @param	int		$show_graph
	 * @return	string
	 */
	function showResult(&$sr, $show_comment=0, $q_num="", $show_graph=0) {
		global $group_id;

		$Survey = $sr->getSurvey();
		$Question = $sr->getQuestion();

		$ret='<strong>';
		if ($q_num) {
			$ret.= $q_num . '. ';
		}

		$ret.=$Question->getQuestion().'</strong><br />';
		$results = $sr->getResults();
		if ($sr->isError()){
			echo $sr->getErrorMessage();
		}

		$totalCount = $sr->getNumberOfSurveyResponses();
		$votes = $Survey->getNumberOfVotes();

		/* No votes, no result to show */
		if ($votes==0) {
			$ret.= '<ul><li>'._('No Votes').'</li></ul>';
			return $ret;
		}

		switch($Question->getQuestionType()) {
		case 1: /* This is a radio-button question. Values 1-5.
			  Show the 1-5 markers only if this is the first in a series */
			$arr_name=array('No Answer', 'Low 1', '2', '3', '4', 'High 5', 'No Answer');
			$arr_color=array('black', 'red', 'blue', 'yellow', 'green', 'brown', 'black');
			$results[0] = $votes - $results[1] - $results[2] - $results[3] - $results[4] - $results[5];

			if ($show_graph) {
				for ($j=5; $j>=0; $j--) {
					$percent = sprintf("%02.1f%%", (float)$results[$j]*100/$votes);
					$legendArr[] = $arr_name[$j].' ('. $percent.')';
					$valuesArr[] = $results[$j];
				}
				$ret.= $this->drawGraph($Question->getID(), 'hbar', $legendArr, $valuesArr);
			} else {
				$ret.= '<table style="padding-left: 3em; width: 100%">';

				for ($j=5; $j>=0; $j--) {
					$percent = (float)$results[$j]*100/$votes;
					$ret.= $this->makeBar($arr_name[$j].' ('.$results[$j].')', $percent, $arr_color[$j]);
				}
				$ret.= '</table>';
			}
			$ret.='<br />';
			break;

		case 3:	/* This is a Yes/No question. */

			$arr_name=array('', 'YES', 'NO', 'No Answer');
			$arr_color=array('', 'red', 'blue', 'black');

			$res[1] = $results[1]; /* Yes */
			$res[2] = $results[5]; /* No */
			$res[3] = $votes - $res[1] -$res[2];

			if ($show_graph) {
				for ($j=1; $j<=3; $j++) {
					$legendArr[] = $arr_name[$j].'('.$res[$j].')';
					$valuesArr[] = $res[$j];
				}
				$ret.= $this->drawGraph($Question->getID(), 'pie', $legendArr, $valuesArr);
			} else {
				$ret.= '<table style="padding-left: 3em; width: 100%">';
				for ($j=1; $j<=3; $j++) {
					$result_per[$j] = (float)$res[$j]*100/$votes;
					$ret.= $this->makeBar($arr_name[$j].' ('.$res[$j].')', $result_per[$j], $arr_color[$j]);
				}
				$ret.= '</table>';
			}
			$ret.='<br />';
			break;

		case 4:	/* This is a comment only. */
			break;

		case 2:	/* This is a text-area question. */
		case 5:	/* This is a text-field question. */
			if ($show_comment) {
				for($j=0; $j<$totalCount; $j++) {
					$ret.='<hr /><p><strong>'._('Comments').
						' # '.($j+1).'/'.$totalCount. '</strong></p>';
					$ret.='<pre>';
					$words = explode(" ",$results[$j]);
					$linelength = 0;
					//print 100 chars in words per line
					foreach ($words as $word) {
						// if we have a stupidly strange word with lots of letters, we'll make a new line for it and split it
						if ((strlen($word)>100) && ((strlen($word)+$linelength)>100)) {
							$chunks = str_split($word,50);
							foreach ($chunks as $chunk) {
								$ret .= $chunk;
								$ret .= "<br />";
							}
							$linelength = 0;
						} else {
							$linelength += strlen($word);
							if ($linelength>100) {
								$ret .= "<br />";
								$linelength = 0;
							} else {
								$ret .= $word . " ";
							}
						}
					}
					$ret.='</pre>';
				}
			} else {
				$ret.='<ul><li>'.util_make_link('/survey/admin/show_results.php?survey_id='.$Survey->getID().
					'&question_id='.$Question->getID().
					'&group_id='.$group_id,
					sprintf(ngettext('View All %s Comment', 'View All %s Comments', $totalCount), $totalCount)).
					'</li></ul>';
			}
			break;
		default:
			break;
		}
		return $ret;
	}

	/**
	 * makeBar - make Percentage bar as a cell in a table. Starts with <tr> and ends with </tr>
	 *
	 * @param	string	$name		Name
	 * @param	int		$percent	Percentage of the name
	 * @param	string	$color		Color
	 * @return	string
	 */
	private function makeBar($name, $percent, $color) {
		$ret = '<tr><td style="width: 30%">'.$name.'</td><td>';
		$ret.= '<table style="width: '.$percent.'%"><tr>';
		if ($percent) {
			$ret.='<td style="width: 90%" bgcolor="'.$color.'">&nbsp;</td>';
		}

		$ret.= '<td>'.sprintf("%.2f", $percent).'%</td></tr></table></td></tr>'."\n";

		return $ret;
	}

	private function drawGraph($id, $graphType, $legend, $values) {
		switch($graphType) {
			case 'pie': {
				$ret = '<script type="text/javascript">//<![CDATA['."\n";
				$ret .= 'var data'.$id.' = new Array();';
				$ret .= 'var plot'.$id.';';
				for ($i = 0; $i < count($values); $i++) {
					$ret .= 'data'.$id.'.push([\''.htmlentities($legend[$i]).'\','.$values[$i].']);';
				}
				$ret .= 'jQuery(document).ready(function(){
						plot'.$id.' = jQuery.jqplot (\'chart'.$id.'\', [data'.$id.'],
						{
							seriesDefaults: {
								renderer: jQuery.jqplot.PieRenderer,
								rendererOptions: {
									showDataLabels: true,
									dataLabels: \'percent\',
								}
							},
							legend: {
								show:true, location: \'e\',
							},
						});
					});';
				$ret .= 'jQuery(window).resize(function() {
						plot'.$id.'.replot( { resetAxes: true } );
					});'."\n";
				$ret .= '//]]></script>';
				$ret .= '<div id="chart'.$id.'"></div>';
				break;
			}
			default: {
				$ret = '<script type="text/javascript">//<![CDATA['."\n";
				$ret .= 'var data'.$id.' = new Array();';
				$ret .= 'var ticks'.$id.' = new Array();';
				$ret .= 'var plot'.$id.';';
				$yMax = 0;
				for ($i = 0; $i < count($values); $i++) {
					$ret .= 'data'.$id.'.push('.$values[$i].');';
					if ($yMax < $values[$i]) {
						$yMax = $values[$i];
					}
					$ret .= 'ticks'.$id.'.push([\''.htmlentities($legend[$i]).'\']);';
				}
				$ret .= 'jQuery(document).ready(function(){
						plot'.$id.' = jQuery.jqplot (\'chart'.$id.'\', [data'.$id.'],
						{
							axesDefaults: {
								tickRenderer: jQuery.jqplot.CanvasAxisTickRenderer,
								tickOptions: {
									angle: 90,
									fontSize: \'8px\',
									showGridline: false,
									showMark: false,
								},
								pad: 0,
							},
							seriesDefaults: {
								showMarker: false,
								lineWidth: 1,
								fill: true,
								renderer:jQuery.jqplot.BarRenderer,
								rendererOptions: {
									fillToZero: true,
								},
							},
							axes: {
								xaxis: {
									renderer: jQuery.jqplot.CategoryAxisRenderer,
									ticks: ticks'.$id.',
								},
								yaxis: {
									max: '.++$yMax.',
									min: 0,
									tickOptions: {
										angle: 0,
										showMark: true,
										formatString: \'%d\'
									}
								},
							},
							highlighter: {
								show: true,
								sizeAdjust: 2.5,
								showTooltip: true,
								tooltipAxes: \'y\',
							},
						});
					});';
				$ret .= 'jQuery(window).resize(function() {
						plot'.$id.'.replot( { resetAxes: true } );
					});'."\n";
				$ret .= '//]]></script>';
				$ret .= '<div id="chart'.$id.'"></div>';
			}
		}
		return $ret;
	}
}
