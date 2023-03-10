<?php
/**
 * FusionForge reporting system
 *
 * Copyright 2003-2004, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
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

define('REPORT_DAY_SPAN',24*60*60);
define('REPORT_WEEK_SPAN',7*24*60*60);
define('REPORT_MONTH_SPAN',30*24*60*60);

define('REPORT_TYPE_DAILY',1);
define('REPORT_TYPE_WEEKLY',2);
define('REPORT_TYPE_MONTHLY',3);
define('REPORT_TYPE_OA',4);

class Report extends FFError {

	//var $adjust_days=array('Sun'=>0, 'Sat'=>6, 'Fri'=>5, 'Thu'=>4, 'Wed'=>3, 'Tue'=>2, 'Mon'=>1);
	var $adjust_days = array('Sun'=>'0.0', 'Sat'=>1, 'Fri'=>2, 'Thu'=>3, 'Wed'=>4, 'Tue'=>5, 'Mon'=>6);
	var $month_start_arr = array();
	var $month_start_arr_format = array();
	var $week_start_arr = array();
	var $week_start_arr_format = array();
	var $site_start_date;
	var $data;
	var $labels;
	var $span;
	var $start_date;
	var $end_date;
	var $span_name = array(1=>'Daily',2=>'Weekly',3=>'Monthly',4=>'OverAll');
	var $graph_interval = array(1=>7,2=>1,3=>1,4=>1);
	var $max_weeks = 104;
	var $max_month = 24;
	var $rawdates = array();

	function __construct() {
		parent::__construct();
		//
		//	All reporting action will be done in GMT timezone
		//
		putenv('TZ=GMT');
		date_default_timezone_set('GMT');
	}

	/**
	* getMinDate - get the unix time that this install was setup.
	*
	* @return	int	the startdate
	*/
	function getMinDate() {
		if (!$this->site_start_date) {
			$res = db_query_params ('SELECT MIN(add_date) AS start_date FROM users WHERE add_date > 0',
						array ());
			$this->site_start_date=db_result($res,0,'start_date');
		}
		return $this->site_start_date;
	}

	/**
	* setStartDate - force the start date for this report
	*
	* @param	int	$startdate	the epoch start date
	*/
	function setStartDate($startdate) {
		$_startdate = $this->getMinDate();
		if ($_startdate > $startdate) {
			$startdate = $_startdate;
		}
		$this->site_start_date = $startdate;
	}

	function &getMonthStartArr() {
		if (count($this->month_start_arr) < 1) {
			$min_date=$this->getMinDate();
			for ($i=0; $i<$this->max_month; $i++) {
				$this->month_start_arr[] = mktime(0,0,0,date('m')+1-$i,1,date('Y'));
				$this->month_start_arr_format[] = date('Ym', $this->month_start_arr[$i]);
				if ($this->month_start_arr[$i] < $min_date) {
					break;
				}
			}
			sort($this->month_start_arr);
			sort($this->month_start_arr_format);
		}
		return $this->month_start_arr;
	}

	function &getWeekStartArr() {
		if (count($this->week_start_arr) < 1) {
			$min_date=$this->getMinDate();
			$start=mktime(0,0,0,date('m'),(date('d')+$this->adjust_days[date('D')]),date('Y'));
			for ($i=0; $i<$this->max_weeks; $i++) {
				$this->week_start_arr[]=($start-REPORT_WEEK_SPAN*$i);
				$this->week_start_arr_format[] = date('Y/W', $this->week_start_arr[$i]);
				if ($this->week_start_arr[$i] < $min_date) {
					break;
				}
			}
			sort($this->week_start_arr);
			sort($this->week_start_arr_format);
		}
		return $this->week_start_arr;
	}

	function setSpan($span) {
		$this->span=$span;
	}

	function getSpanName() {
		return $this->span_name[$this->span];
	}

	function setData($result,$column) {
		$this->data =& util_result_column_to_array($result,$column);
	}

	function setDates($result,$column) {
		$arr = NULL;
		if (!is_array($result)) {
			$arr =& util_result_column_to_array($result,$column);
		} elseif (is_array($result)) {
			$arr = $result;
		}
		if(isset($this->span) && $this->span == REPORT_TYPE_MONTHLY) {
			$format = 'M Y';
		} else {
			$format = 'M d';
		}

		for ($i=0; $i<count($arr); $i++) {
			$this->labels[$i] = date($format,$arr[$i]);
			$this->rawdates[$i] = $arr[$i];
		}
	}

	function getGraphInterval() {
		return $this->graph_interval[$this->span];
	}

	function &getData() {
		return $this->data;
	}

	function &getDates() {
		return $this->labels;
	}

	function &getRawDates() {
		return $this->rawdates;
	}

	function getStartDate() {
		return $this->start_date;
	}

	function getEndDate() {
		return $this->end_date;
	}

	function getMonthStartArrFormat() {
		return $this->month_start_arr_format;
	}

	function getWeekStartArrFormat() {
		return $this->week_start_arr_format;
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
