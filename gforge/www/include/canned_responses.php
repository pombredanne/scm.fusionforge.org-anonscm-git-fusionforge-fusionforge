<?php
/**
 * Canned Responses functions library.
 *
 * SourceForge: Breaking Down the Barriers to Open Source Development
 * Copyright 1999-2001 (c) VA Linux Systems
 * http://sourceforge.net
 *
 */

/**
 * add_canned_response() - Add a new canned response
 *
 * @param		string	Canned response title
 * @param		string	Canned response text
 */
function add_canned_response($title, $text)
{
		global $feedback;
		if( !db_query_params ('INSERT INTO canned_responses (response_title, response_text) VALUES($1,$2)',
			array($title,
				$text)) ) {
			$feedback .= db_error();
		}
}

/**
 * get_canned_responses() - Get an HTML select-box of canned responses
 */
function get_canned_responses()
{
	global $canned_response_res;
	if (!$canned_response_res) {
		$canned_response_res = db_query_params ('SELECT response_id, response_title FROM canned_responses',
			array());
	}
	return html_build_select_box($canned_response_res, 'response_id');
}

?>
