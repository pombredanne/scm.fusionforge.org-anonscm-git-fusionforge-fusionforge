<?php
/**
 * Sitewide Statistics
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2010 (c) FusionForge Team
 * Copyright (C) 2010 Alain Peyrat - Alcatel-Lucent
 * http://fusionforge.org
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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'stats/site_stats_utils.php';

session_require_global_perm ('forge_stats', 'read') ;

$HTML->header(array('title' => sprintf(_('%s I18n Statistics: Languages Distributions'), forge_get_config ('forge_name'))));

echo $GLOBALS['HTML']->listTableTop(array(_('Language'), _('Users'), '%'));

$total=db_result(db_query_params('SELECT count(user_name) AS total FROM users', array()),0,'total');

$res = db_query_params ('SELECT supported_languages.name AS lang,count(user_name) AS cnt
FROM supported_languages LEFT JOIN users ON language_id=users.language
GROUP BY lang,language_id,name
ORDER BY cnt DESC',
			array ());
$non_english=0;
while ($lang_stat = db_fetch_array($res)) {
	if ($lang_stat['cnt'] > 0) {
		echo '<tr><th>'.$lang_stat['lang'].'</th>'.
		'<td class="align-right">'.$lang_stat['cnt'].' </td>'.
		'<td class="align-right">'.sprintf("%.2f",$lang_stat['cnt']*100/$total)." </td></tr>\n";
		if ($lang_stat['lang']!='English') {
			$non_english+=$lang_stat['cnt'];
		}
	}
}

echo '<tr><td><strong>'._('Total Non-English').'</strong></td>'.
'<td class="align-right"><strong>'.$non_english.' </strong></td>'.
'<td class="align-right"><strong>'.sprintf("%.2f",$non_english*100/$total).' </strong></td></tr>';

echo $GLOBALS['HTML']->listTableBottom();
echo "<p>"._('This is a list of the preferences that users have chosen in their user preferences; it does not include languages which are selected via cookies or browser preferences')."</p>";

$HTML->footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
