<?php
/**
 * FusionForge trackers
 *
 * Copyright 2005, Anthony J. Pugliese
 * Copyright 2005, GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright 2009, Alcatel-Lucent
 * Copyright 2016, Stéphane-Eymeric Bredthauer - TrivialDev
 * Copyright 2021, Franck Villaume - TrivialDev
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

/**
 * Standard Alcatel-Lucent disclaimer for contributing to open source
 *
 * "The Artifact ("Contribution") has not been tested and/or
 * validated for release as or in products, combinations with products or
 * other commercial use. Any use of the Contribution is entirely made at
 * the user's own responsibility and the user can not rely on any features,
 * functionalities or performances Alcatel-Lucent has attributed to the
 * Contribution.
 *
 * THE CONTRIBUTION BY ALCATEL-LUCENT IS PROVIDED AS IS, WITHOUT WARRANTY
 * OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, COMPLIANCE,
 * NON-INTERFERENCE AND/OR INTERWORKING WITH THE SOFTWARE TO WHICH THE
 * CONTRIBUTION HAS BEEN MADE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL
 * ALCATEL-LUCENT BE LIABLE FOR ANY DAMAGES OR OTHER LIABLITY, WHETHER IN
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * CONTRIBUTION OR THE USE OR OTHER DEALINGS IN THE CONTRIBUTION, WHETHER
 * TOGETHER WITH THE SOFTWARE TO WHICH THE CONTRIBUTION RELATES OR ON A STAND
 * ALONE BASIS."
 */

require_once $gfcommon.'include/FFError.class.php';

define('ARTIFACT_QUERY_ASSIGNEE',1);
define('ARTIFACT_QUERY_STATE',2);
define('ARTIFACT_QUERY_MODDATE',3);
define('ARTIFACT_QUERY_EXTRAFIELD',4);
define('ARTIFACT_QUERY_SORTCOL',5);
define('ARTIFACT_QUERY_SORTORD',6);
define('ARTIFACT_QUERY_OPENDATE',7);
define('ARTIFACT_QUERY_CLOSEDATE',8);
define('ARTIFACT_QUERY_SUMMARY',9);
define('ARTIFACT_QUERY_DESCRIPTION',10);
define('ARTIFACT_QUERY_FOLLOWUPS',11);
define('ARTIFACT_QUERY_SUBMITTER',12);
define('ARTIFACT_QUERY_LAST_MODIFIER',13);

require_once $gfcommon.'tracker/ArtifactType.class.php';

class ArtifactQuery extends FFError {
	/**
	 * The artifact type object.
	 *
	 * @var	object	$ArtifactType.
	 */
	var $ArtifactType; //object

	/**
	 * Array of artifact data.
	 *
	 * @var	array	$data_array.
	 */
	var $data_array;

	/**
	 * Array of query conditions
	 *
	 * @var	array	$element_array.
	 */
	var $element_array;

	/**
	 * @param	$ArtifactType	$ArtifactType	c object.
	 * @param	array|bool	$data
	 */
	function __construct(&$ArtifactType, $data = false) {
		parent::__construct();

		// Was ArtifactType legit?
		if (!$ArtifactType || !is_object($ArtifactType)) {
			$this->setError('ArtifactQuery: No Valid ArtifactType');
			return;
		}
		// Did ArtifactType have an error?
		if ($ArtifactType->isError()) {
			$this->setError('ArtifactQuery: '.$ArtifactType->getErrorMessage());
			return;
		}
		$this->ArtifactType =& $ArtifactType;

		if ($data) {
			if (is_array($data)) {
				$this->data_array =& $data;
			} else {
				$this->fetchData($data);
			}
		}
	}

	/**
	 * create - create a row in the table that stores a saved query for
	 * a tracker.
	 *
	 * @param	string		$name
	 * @param	$status
	 * @param	$assignee
	 * @param	$moddaterange
	 * @param	$sort_col
	 * @param	$sort_ord
	 * @param	$extra_fields
	 * @param	int		$opendaterange
	 * @param	int		$closedaterange
	 * @param	string		$summary
	 * @param	string		$description
	 * @param	string		$followups
	 * @param	int		$query_type
	 * @param	array		$query_options
	 * @param	string		$submitter	Name of the saved query.
	 * @param	string		$last_modifier
	 * @return	bool		true on success / false on failure.
	 */
	function create($name,$status,$assignee,$moddaterange,$sort_col,$sort_ord,$extra_fields,$opendaterange=0,$closedaterange=0,
		$summary='',$description='',$followups='',$query_type=0,$query_options=array(),$submitter='',$last_modifier='') {
		//
		//	data validation
		//
		if (!$name) {
			$this->setMissingParamsError();
			return false;
		}
		if (!session_loggedin()) {
			$this->setError(_('Must Be Logged In'));
			return false;
		}

		if ($this->Exist(htmlspecialchars($name))) {
			$this->setError(_('Query already exists'));
			return false;
		}

		if ($query_type>0 && !forge_check_perm ('tracker', $this->ArtifactType->getID(), 'manager')) {
			$this->setError( _('You must have tracker admin rights to set or update a project level query.'));
			return false;
		}

		// Reset the project default query.
		if ($query_type==2) {
			$res = db_query_params ('UPDATE artifact_query SET query_type=1 WHERE query_type=2 AND group_artifact_id=$1',
						array($this->ArtifactType->getID()));
			if (!$res) {
				$this->setError('Error Updating: '.db_error());
				return false;
			}
		}

		db_begin();
		$result = db_query_params ('INSERT INTO artifact_query (group_artifact_id,query_name,user_id,query_type) VALUES ($1,$2,$3,$4)',
					   array ($this->ArtifactType->getID(),
						  htmlspecialchars($name),
						  user_getid(),
						  $query_type)) ;
		if ($result && db_affected_rows($result) > 0) {
			$this->clearError();
			$id=db_insertid($result,'artifact_query','artifact_query_id');
			if (!$id) {
				$this->setError('Error getting id '.db_error());
				db_rollback();
				return false;
			} else {
				if (!$this->insertElements($id,$status,$submitter,$assignee,$moddaterange,$sort_col,$sort_ord,$extra_fields,$opendaterange,$closedaterange,$summary,$description,$followups,$last_modifier)) {
					db_rollback();
					return false;
				}
			}
		} else {
			$this->setError(db_error());
			db_rollback();
			return false;
		}
		//
		//	Now set up our internal data structures
		//
		if ($this->fetchData($id)) {
			db_commit();
			return true;
		} else {
			db_rollback();
			return false;
		}
	}

	/**
	 * fetchData - re-fetch the data for this ArtifactQuery from the database.
	 *
	 * @param	int	$id	ID of saved query.
	 * @return	bool	success.
	 */
	function fetchData($id) {
			$res = db_query_params ('SELECT * FROM artifact_query WHERE artifact_query_id=$1',
						array ($id)) ;

		if (!$res || db_numrows($res) < 1) {
			$this->setError('ArtifactQuery: Invalid ArtifactQuery ID'.db_error());
			return false;
		}
		$this->data_array = db_fetch_array($res);
		db_free_result($res);
			$res = db_query_params ('SELECT * FROM artifact_query_fields WHERE artifact_query_id=$1',
						array ($id)) ;
		unset($this->element_array);
		while ($arr = db_fetch_array($res)) {
			//
			//	Some things may have been saved as comma-separated items
			//
			if (strstr($arr['query_field_values'],',')) {
				$arr['query_field_values']=explode(',',$arr['query_field_values']);
			}
			$this->element_array[$arr['query_field_type']][$arr['query_field_id']]=$arr['query_field_values'];
		}
		return true;
	}

	/**
	 * getArtifactType - get the ArtifactType Object this ArtifactExtraField is associated with.
	 *
	 * @return	object	ArtifactType.
	 */
	function &getArtifactType() {
		return $this->ArtifactType;
	}

	/**
	 * insertElements - ???
	 *
	 * @param	int		$id
	 * @param	$status
	 * @param	$submitter
	 * @param	$assignee
	 * @param	$moddaterange
	 * @param	$sort_col
	 * @param	$sort_ord
	 * @param	$extra_fields
	 * @param	$opendaterange
	 * @param	$closedaterange
	 * @param	string		$summary
	 * @param	string		$description
	 * @param	$last_modifier
	 * @param	$followups
	 * @return	bool		True/false on success or not.
	 */
	function insertElements($id,$status,$submitter,$assignee,$moddaterange,$sort_col,$sort_ord,$extra_fields,$opendaterange,$closedaterange,$summary,$description,$followups,$last_modifier) {
		$res = db_query_params ('DELETE FROM artifact_query_fields WHERE artifact_query_id=$1',
					array ($id)) ;
		if (!$res) {
			$this->setError('Deleting Old Elements: '.db_error());
			return false;
		}
		$id = intval($id);
		$status = intval($status);
		$res = db_query_params ('INSERT INTO artifact_query_fields (artifact_query_id,query_field_type,query_field_id,query_field_values) VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_STATE,
					       $status)) ;
		if (!$res) {
			$this->setError('Setting Status: '.db_error());
			return false;
		}

		if (is_array($submitter)) {
				for($e=0; $e<count($submitter); $e++) {
					$submitter[$e]=intval($submitter[$e]);
				}
				$submitter=implode(',',$submitter);
		} else {
			$submitter = intval($submitter);
		}

		if (is_array($assignee)) {
				for($e=0; $e<count($assignee); $e++) {
					$assignee[$e]=intval($assignee[$e]);
				}
				$assignee=implode(',',$assignee);
		} else {
			$assignee = intval($assignee);
		}

		if (is_array($last_modifier)) {
			for($e=0; $e<count($last_modifier); $e++) {
				$last_modifier[$e]=intval($last_modifier[$e]);
			}
			$last_modifier=implode(',',$last_modifier);
		} else {
			$last_modifier = intval($last_modifier);
		}

		if (preg_match("/[^[:alnum:]_]/", $sort_col)) {
			$this->setError('ArtifactQuery: not valid sort_col');
			return false;
		}

		if (preg_match("/[^[:alnum:]_]/", $sort_ord)) {
			$this->setError('ArtifactQuery: not valid sort_ord');
			return false;
		}

		//CSV LIST OF SUBMITTERS
		if ($submitter) {
			$res = db_query_params ('INSERT INTO artifact_query_fields
									(artifact_query_id,query_field_type,query_field_id,query_field_values)
									VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_SUBMITTER,
					       $submitter)) ;
			if (!$res) {
				$this->setError('Setting Submitter: '.db_error());
				return false;
			}
		}

		//CSV LIST OF SUBMITTERS
		if ($last_modifier) {
			$res = db_query_params ('INSERT INTO artifact_query_fields
									(artifact_query_id,query_field_type,query_field_id,query_field_values)
									VALUES ($1,$2,0,$3)',
					array ($id,
							ARTIFACT_QUERY_LAST_MODIFIER,
							$last_modifier)) ;
			if (!$res) {
				$this->setError('Setting Last Modifier: '.db_error());
				return false;
			}
		}

		//CSV LIST OF ASSIGNEES
		if ($assignee) {
			$res = db_query_params ('INSERT INTO artifact_query_fields
				(artifact_query_id,query_field_type,query_field_id,query_field_values)
				VALUES ($1,$2,0,$3)',
						array ($id,
							   ARTIFACT_QUERY_ASSIGNEE,
							   $assignee)) ;
			if (!$res) {
				$this->setError('Setting Assignee: '.db_error());
				return false;
			}
		}

		//MOD DATE RANGE  YYYY-MM-DD YYYY-MM-DD format
		if ($moddaterange && !$this->validateDateRange($moddaterange)) {
			$this->setError(_('Invalid Last Modified Date Range'));
			return false;
		}
		$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_MODDATE,
					       $moddaterange)) ;
		if (!$res) {
			$this->setError('Setting Last Modified Date Range: '.db_error());
			return false;
		}

		//OPEN DATE RANGE YYYY-MM-DD YYYY-MM-DD format
		if ($opendaterange && !$this->validateDateRange($opendaterange)) {
			$this->setError(_('Invalid Open Date Range'));
			return false;
		}
		$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_OPENDATE,
					       $opendaterange)) ;
		if (!$res) {
			$this->setError('Setting Open Date Range: '.db_error());
			return false;
		}

		//CLOSE DATE RANGE YYYY-MM-DD YYYY-MM-DD format
		if ($closedaterange && !$this->validateDateRange($closedaterange)) {
			$this->setError(_('Invalid Close Date Range'));
			return false;
		}
		$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_CLOSEDATE,
					       $closedaterange)) ;
		if (!$res) {
			$this->setError('Setting Close Date Range: '.db_error());
			return false;
		}

		// SORT COLUMN
		$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_SORTCOL,
					       $sort_col)) ;
		if (!$res) {
			$this->setError('Setting Sort Col: '.db_error());
			return false;
		}
		$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,0,$3)',
					array ($id,
					       ARTIFACT_QUERY_SORTORD,
					       $sort_ord)) ;
		if (!$res) {
			$this->setError('Setting Sort Order: '.db_error());
			return false;
		}

		// Saving the summary value.
		$res=db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,$3,$4)',
			array($id,
				ARTIFACT_QUERY_SUMMARY,
				0,
				$summary));
		if (!$res) {
			$this->setError('Setting Summary: '.db_error());
			return false;
		}

		// Saving the description value.
		$res=db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,$3,$4)',
			array($id,
				ARTIFACT_QUERY_DESCRIPTION,
				0,
				$description));
		if (!$res) {
			$this->setError('Setting Description: '.db_error());
			return false;
		}

		// Saving the followups value.
		$res=db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,$3,$4)',
			array($id,
				ARTIFACT_QUERY_FOLLOWUPS,
				0,
				$followups));
		if (!$res) {
			$this->setError('Setting Followups: '.db_error());
			return false;
		}

		if (!$extra_fields) {
			$extra_fields=array();
		}

		$keys=array_keys($extra_fields);
		$vals=array_values($extra_fields);
		for ($i=0; $i<count($keys); $i++) {
			if (!$vals[$i]) {
				continue;
			}
			//
			//	Checkboxes and multi-select may be arrays so store it comma-separated
			//
			if (is_array($vals[$i])) {
				for($e=0; $e<count($vals[$i]); $e++) {
					$vals[$i][$e]=intval($vals[$i][$e]);
				}
				$vals[$i]=implode(',',$vals[$i]);
			}

			$aef = new ArtifactExtraField($this->ArtifactType, $keys[$i]);
			$type = $aef->getType();
			if ($type == ARTIFACT_EXTRAFIELDTYPE_INTEGER) {
				if (!preg_match('/^[><= \-\+0-9%]+$/', $vals[$i])) {
					$this->setError('Invalid Value for Integer type: '. $vals[$i]);
					return false;
				}
			}

			$res = db_query_params ('INSERT INTO artifact_query_fields
			(artifact_query_id,query_field_type,query_field_id,query_field_values)
			VALUES ($1,$2,$3,$4)',
						array ($id,
						       ARTIFACT_QUERY_EXTRAFIELD,
						       intval ($keys[$i]),
						       $vals[$i])) ;
			if (!$res) {
				$this->setError('Setting values: '.db_error());
				return false;
			}
		}
		return true;
	}

	/**
	 * getID - get this ArtifactQuery ID.
	 *
	 * @return	int	The id #.
	 */
	function getID() {
		if (isset($this->data_array['artifact_query_id'])) {
			return $this->data_array['artifact_query_id'];
		}
		return null;
	}

	/**
	 * getName - get the name.
	 *
	 * @return	string	The name.
	 */
	function getName() {
		if (isset($this->data_array['query_name'])) {
			return $this->data_array['query_name'];
		}
		return '';
	}

	/**
	 * getUserId - get the user_id.
	 *
	 * @return	string	The user_id.
	 */
	function getUserId() {
		return $this->data_array['user_id'];
	}

	/**
	 * getQueryType - get the type of the query
	 *
	 * @return	string	type of query (0: private, 1: project, 2: project&default)
	 */
	function getQueryType() {
		if (isset($this->data_array['query_type'])) {
			return $this->data_array['query_type'];
		}
		return null;
	}

	/**
	 * getQueryOptions - get the options of the query
	 *
	 * @return	array	array of all activated options
	 */
	function getQueryOptions() {
		if (isset($this->data_array['query_options'])) {
			return explode('|', $this->data_array['query_options']);
		} else {
			return array();
		}
	}

	/**
	 * getSortCol - the column that you're sorting on
	 *
	 * @return	string	The column name.
	 */
	function getSortCol() {
		if (!isset($this->element_array)) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_SORTCOL][0];
	}

	/**
	 * getSortOrd - ASC or DESC
	 *
	 * @return	string	ASC or DESC
	 */
	function getSortOrd() {
		if (!isset($this->element_array)) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_SORTORD][0];
	}

	/**
	 * getModDateRange - get the range of dates to include in a query
	 *
	 * @return	string	mod date range.
	 */
	function getModDateRange() {
		if (!isset($this->element_array)) {
			return false;
		}
		if ($this->element_array[ARTIFACT_QUERY_MODDATE][0]) {
			return $this->element_array[ARTIFACT_QUERY_MODDATE][0];
		} else {
			return false;
		}
	}

	/**
	 * getOpenDateRange - get the range of dates to include in a query
	 *
	 * @return	string	Open date range.
	 */
	function getOpenDateRange() {
		if (!isset($this->element_array)) {
			return false;
		}
		if (isset($this->element_array[ARTIFACT_QUERY_OPENDATE][0])) {
			return $this->element_array[ARTIFACT_QUERY_OPENDATE][0];
		} else {
			return false;
		}
	}

	/**
	 * getCloseDateRange - get the range of dates to include in a query
	 *
	 * @return	string	Close date range.
	 */
	function getCloseDateRange() {
		if (!isset($this->element_array)) {
			return false;
		}
		if (isset($this->element_array[ARTIFACT_QUERY_CLOSEDATE][0])) {
			return $this->element_array[ARTIFACT_QUERY_CLOSEDATE][0];
		} else {
			return false;
		}
	}

	/**
	 * getSummary - get the summary string to include in a query
	 *
	 * @return	string	Summary string.
	 */
	function getSummary() {
		if (!isset($this->element_array[ARTIFACT_QUERY_SUMMARY][0])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_SUMMARY][0];
	}

	/**
	 * getDescription - get the description string to include in a query
	 *
	 * @return	string	Description string.
	 */
	function getDescription() {
		if (!isset($this->element_array[ARTIFACT_QUERY_DESCRIPTION][0])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_DESCRIPTION][0];
	}

	/**
	 * getFollowups - get the followups string to include in a query
	 *
	 * @return	string	Folowups string.
	 */
	function getFollowups() {
		if (!isset($this->element_array[ARTIFACT_QUERY_FOLLOWUPS][0])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_FOLLOWUPS][0];
	}

	/**
	 * getAssignee
	 *
	 * @return	string	Assignee ID
	 */
	function getAssignee() {
		if (!isset($this->element_array[ARTIFACT_QUERY_ASSIGNEE])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_ASSIGNEE][0];
	}

	/**
	 * getSubmitter
	 *
	 * @return	string	Submitter ID
	 */
	function getSubmitter() {
		if (!isset($this->element_array[ARTIFACT_QUERY_SUBMITTER])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_SUBMITTER][0];
	}

	/**
	 * getLastModifier
	 *
	 * @return	string	Last Modifier ID
	 */
	function getLastModifier() {
		if (!isset($this->element_array[ARTIFACT_QUERY_LAST_MODIFIER])) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_LAST_MODIFIER][0];
	}

	/**
	 * getStatus
	 *
	 * @return	string	Status ID
	 */
	function getStatus() {
		if (!isset($this->element_array)) {
			return false;
		}
		return $this->element_array[ARTIFACT_QUERY_STATE][0];
	}

	/**
	 * getExtraFields - complex multi-dimensional array of extra field IDs/Vals
	 *
	 * @return	array	Complex Array
	 */
	function getExtraFields() {
		if (!isset($this->element_array)) {
			return false;
		}
		if (! isset ($this->element_array[ARTIFACT_QUERY_EXTRAFIELD])) {
			$this->element_array[ARTIFACT_QUERY_EXTRAFIELD] = array () ;
		}
		return $this->element_array[ARTIFACT_QUERY_EXTRAFIELD];
	}

	/**
	 * validateDateRange - validate a date range in this format '1999-05-01 1999-06-01'.
	 *
	 * @param	string	$daterange A range of two dates (1999-05-01 1999-06-01)
	 * @return	bool	true/false.
	 */
	function validateDateRange(&$daterange) {
		if(! preg_match('/([0-9]{4})-[0-9]{2}-[0-9]{2} ([0-9]{4})-[0-9]{2}-[0-9]{2}/', $daterange, $matches)) {
			return false;
		} else {
			# Hack to avoid exceeding the maximum value for an integer in the database
			if ($matches[1] > 2037) {
				$daterange = preg_replace('/[\d]{4}(-[\d]{2}-[\d]{2} [\d]{4}-[\d]{2}-[\d]{2})/', '2037$1', $daterange);
			}
			if ($matches[2] > 2037) {
				$daterange = preg_replace('/([\d]{4}-[\d]{2}-[\d]{2} )[\d]{4}(-[\d]{2}-[\d]{2})/', '${1}2037$2', $daterange);
			}
		}
		return true;
	}

	/**
	 * update - update a row in the table used to query names
	 * for a tracker.
	 *
	 * @param	$name
	 * @param	$status
	 * @param	$assignee
	 * @param	$moddaterange
	 * @param	$sort_col
	 * @param	$sort_ord
	 * @param	$extra_fields
	 * @param	string		$opendaterange
	 * @param	string		$closedaterange
	 * @param	$summary
	 * @param	$description
	 * @param	$followups
	 * @param	int		$query_type	Id of the saved query
	 * @param	array		$query_options
	 * @param	string		$submitter
	 * @param	string		$last_modifier
	 * @return	bool		success.
	 */
	function update($name, $status, $assignee, $moddaterange, $sort_col, $sort_ord, $extra_fields, $opendaterange = '', $closedaterange = '',
		$summary = '', $description = '', $followups = '', $query_type = 0, $query_options = array(), $submitter = '', $last_modifier = '') {
		if (!$name) {
			$this->setMissingParamsError();
			return false;
		}
		if (!session_loggedin()) {
			$this->setError(_('Must Be Logged In'));
			return false;
		}
		if ($query_type>0 && !forge_check_perm ('tracker', $this->ArtifactType->getID(), 'manager')) {
			$this->setError(_('You must have tracker admin rights to set or update a project level query.'));
			return false;
		}

		// Reset the project default query.
		if ($query_type==2) {
			$res = db_query_params ('UPDATE artifact_query SET query_type=1 WHERE query_type=2 AND group_artifact_id=$1',
						array($this->ArtifactType->getID()));
			if (!$res) {
				$this->setError('Error Updating: '.db_error());
				return false;
			}
		}
		db_begin();
		$result = db_query_params ('UPDATE artifact_query
			SET query_name=$1,
				query_type=$2,
				query_options=$3
			WHERE artifact_query_id=$4',
					   array (htmlspecialchars($name),
						  $query_type,
						  join('|', $query_options),
						  $this->getID())) ;
		if ($result && db_affected_rows($result) > 0) {
			if (!$this->insertElements($this->getID(),$status,$submitter,$assignee,$moddaterange,$sort_col,$sort_ord,$extra_fields,$opendaterange,$closedaterange,$summary,$description,$followups,$last_modifier)) {
				db_rollback();
				return false;
			} else {
				db_commit();
				$this->fetchData($this->getID());
				return true;
			}
		} else {
			$this->setError('Error Updating: '.db_error());
			db_rollback();
			return false;
		}
	}

	/**
	 * makeDefault - set this as the default query
	 *
	 * @return	bool	success.
	 */
	function makeDefault() {
		if (!session_loggedin()) {
			$this->setError(_('Must Be Logged In'));
			return false;
		}
		$usr =& session_get_user();
		return $usr->setPreference('art_query'.$this->ArtifactType->getID(),$this->getID());
	}

	/**
	 * delete - delete query
	 *
	 * @return	bool	success.
	 */
	function delete() {
		if (forge_check_perm ('tracker', $this->ArtifactType->getID(), 'manager')) {
			$res = db_query_params ('DELETE FROM artifact_query WHERE artifact_query_id=$1 AND (user_id=$2 OR query_type>0)',
					array ($this->getID(),
					       user_getid())) ;
			if (!$res) {
				return false;
			}
		} else {
			$res = db_query_params ('DELETE FROM artifact_query WHERE artifact_query_id=$1 AND user_id=$2',
					array ($this->getID(),
					       user_getid())) ;
			if (!$res) {
				return false;
			}
		}
		db_query_params ('DELETE FROM user_preferences WHERE preference_value=$1 AND preference_name =$2',
					array ($this->getID(),
					       'art_query'.$this->ArtifactType->getID())) ;
		unset($this->data_array);
		unset($this->element_array);
		return true;
	}

	/**
	 * Exist - check if already exist a query with the same name , user_id and artifact_id
	 *
	 * @param	string	$name	Name of query
	 * @return	bool	true if query already exists
	 */
	function Exist($name) {
		$user_id = user_getid();
		$art_id = $this->ArtifactType->getID();
		$res = db_query_params ('SELECT * FROM artifact_query WHERE group_artifact_id = $1 AND query_name = $2 AND (user_id = $3 OR query_type>0)',
					array ($art_id,
					       $name,
					       $user_id)) ;
		if (db_numrows($res)>0) {
			return true;
		}
		return false;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
