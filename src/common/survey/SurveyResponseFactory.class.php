<?php
/**
 * FusionForge surveys
 *
 * Copyright 2004, Sung Kim/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2013, Franck Villaume - TrivialDev
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

require_once $gfcommon.'include/FFError.class.php';
require_once $gfcommon.'survey/SurveyResponse.class.php';

class SurveyResponseFactory extends FFError {

	/**
	* The Survey object.
	*
	* @var	 object  $Survey.
	*/
	var $Survey;

	/**
	* The Question object.
	*
	* @var	 object  $Question.
	*/
	var $Question;

	/**
	 * The Response array.
	 *
	 * @var	 array	Response
	 */
	var $Responses;

	/**
	 * The Aggregated Result array for question.
	 *
	 * @var	 array	Response
	 */
	var $Result;

	/**
	 * The Aggregated Results array for all questions.
	 *
	 * @var	 array	Response
	 */
	var $Results;

	/**
	 * @param	object	$survey		The Survey object
	 * @param	object	$question	The Question object to which this survey Response is associated.
	 */
	function __construct(&$survey, &$question ) {
		parent::__construct();

		if (!$survey || !is_object($survey)) {
			$this->setError(_('No valid Survey Object'));
			return;
		}
		if ($survey->isError()) {
			$this->setError(_('Survey')._(': ').$survey->getErrorMessage());
			return;
		}
		if (!$question || !is_object($question)) {
			$this->setError(_('No valid Question Object'));
			return;
		}
		if ($question->isError()) {
			$this->setError(_('Survey')._(': ').$question->getErrorMessage());
			return;
		}

		$this->Survey = &$survey;
		$this->Question = &$question;
	}

	/**
	 * getGroup - get the Group object this SurveyResponse is associated with.
	 *
	 * @return	object	The Group object.
	 */
	function &getGroup() {
		$lsurvey = $this->getSurvey();
		return $lsurvey->Group;
	}

	/**
	 * getSurvey - get the Survey object this SurveyResponse is associated with.
	 *
	 * @return	object	The Survey object.
	 */
	function &getSurvey() {
		return $this->Survey;
	}

	/**
	 * getQuestion - get the Question object this SurveyResponse is associated with.
	 *
	 * @return	object	The Question object.
	 */
	function &getQuestion() {
		return $this->Question;
	}

	/**
	 * getSurveyResponses - get an array of Survey Response objects
	 *                      for the Survey and Question
	 *
 	 * @return	array	The array of Survey Response objects.
	 */
	function getSurveyResponses() {
		/* We already have it */
		if ($this->Responses) {
			return $this->Responses;
		}

		$group = $this->getGroup();
		$group_id = $group->GetID();
		$survey = $this->getSurvey();
		$survey_id = $survey->GetID();
		$question = $this->getQuestion();
		$question_id = $question->GetID();

		$result = db_query_params ('SELECT * FROM survey_responses WHERE survey_id=$1 AND question_id=$2 AND group_id=$3 ORDER BY post_date DESC',
					   array ($survey_id,
						  $question_id,
						  $group_id)) ;
		if (!$result) {
			$this->setError(_('No Survey Response is found').db_error());
			return false;
		} else {
			while ($arr = db_fetch_array($result)) {
				$this->Responses[] = new SurveyResponse($this->getGroup(), $arr);
			}
			db_free_result($result);
		}
		return $this->Responses;
	}

	/**
	 * getSurveyAllResponses - get an array of Survey Response objects
	 *			for the Survey and Question
	 *
	 * @return	array	The array of Survey Response objects.
	 */
	function getSurveyAllResponses() {
		/* We already have it */
		if ($this->Responses) {
			return $this->Responses;
		}

		$group = $this->getGroup();
		$group_id = $group->GetID();
		$survey = $this->getSurvey();
		$survey_id = $survey->GetID();

		$result = db_query_params('SELECT * FROM survey_responses WHERE survey_id=$1 AND group_id=$2 ORDER BY post_date DESC',
			array($survey_id, $group_id));
		if (!$result) {
			$this->setError(_('No Survey Response is found').db_error());
			return false;
		} else {
			while ($arr = db_fetch_array($result)) {
				$this->Responses[] = new SurveyResponse($this->getGroup(), $arr);
			}
			db_free_result($result);
		}
		return $this->Responses;
	}

	/**
	 * getNumberOfSurveyResponses - get the number of Survey Responses
	 *
 	 * @return	int      the number of survey responses
	 */
	function getNumberOfSurveyResponses() {
		$arr = $this->getSurveyResponses();
		if (!$arr || !is_array($arr)) {
			return 0;
		}
		return count($arr);
	}

	/**
	 * getResults - get the array of result for yes/no and 1-5 question
	 *
	 * @return	int      the array of result
	 *              for the yes/no question, it returns counts in arr[1] and arr[5];
	 *              for the 1-5 question, it returns counts in arr[1], arr[1], ..., arr[5];
	 *              for comments, we return arr[1], ...arr[n] with comments
	 */
	function getResults() {
		if ($this->Result) {
			return $this->Result;
		}

		$arr = $this->getSurveyResponses();
		if (!$arr || !is_array($arr)) {
			return false;
		}
		$count = count($arr);

		$question = $this->getQuestion();
		if ($question->getQuestionType()=='1' ||
		    $question->getQuestionType()=='3') {
			/* This is a radio-button question. Values 1-5 or yes(1) no (5)question  */
			$is_radio = true;
			$this->Result = array(0, 0, 0, 0, 0, 0);
		} else {
			$is_radio=false;
		}

		for($i=0; $i<$count; $i++) {
			if ($arr[$i]->isError()) {
				echo $arr[$i]->getErrorMessage();
				continue;
			}

			$response = $arr[$i]->getResponse();

			if($is_radio) {
				/* We only counts */
				// ugly hack to avoid php warning, and count correctly 'no answer' response.
				if (is_string($response) && !strlen($response)) {
					$response = 0;
				}
				$this->Result[$response]++;
			} else {
				/* Save response */
				$this->Result[] = $response;
			}
		}

		return $this->Result;
	}

	/**
	 * getDetailResults - get the array of result
	 *
	 * @return	int      the array of result
	 *		return all responses for one survey
	 */
	function getDetailResults() {
		if ($this->Results) {
			return $this->Results;
		}
		$arr = $this->getSurveyAllResponses();
		if (!$arr || !is_array($arr)) {
			return false;
		}
		$count = count($arr);

		for($i=0; $i<$count; $i++) {
			$user_id = $arr[$i]->getUserId();
			$qid = $arr[$i]->GetQuestionID();
			if ($arr[$i]->isError()) {
				echo $arr[$i]->getErrorMessage();
				continue;
			}
			$response = $arr[$i]->getResponse();
			$this->Result[$user_id][$qid] = $response;
		}
		return $this->Result;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
