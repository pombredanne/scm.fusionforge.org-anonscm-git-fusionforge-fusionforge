<?php
/**
 * Role Editing Page
 *
 * Copyright 2004 (c) GForge LLC
 *
 * @version   $Id$
 * @author Tim Perdue tim@gforge.org
 * @date 2004-03-16
 *
 * This file is part of GForge.
 *
 * GForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once('pre.php');
require_once('www/project/admin/project_admin_utils.php');
require_once('www/include/role_utils.php');

$group_id = getIntFromRequest('group_id');
session_require(array('group'=>$group_id,'admin_flags'=>'A'));

$group =& group_get_object($group_id);
if (!$group || !is_object($group)) {
	exit_error('Error','Could Not Get Group');
} elseif ($group->isError()) {
	exit_error('Error',$group->getErrorMessage());
}

$sw = getStringFromRequest('sw');
if (!$sw) {
	$sw='A';
}

$sql="SELECT user_id,user_name,lastname,firstname FROM users ";
if ($sys_database_type == "mysql") {
	$sql.="WHERE status='A' and type_id='1' and lastname LIKE '$sw%' ";
} else {
	$sql.="WHERE status='A' and type_id='1' and lastname ILIKE '$sw%' ";
}
$res=db_query($sql);

$accumulated_ids = getStringFromRequest('accumulated_ids');
if (!$accumulated_ids) {
	$accumulated_ids=array();
} else {
	$accumulated_ids =& explode(',',$accumulated_ids);
}

$newids = getArrayFromRequest('newids');
if (count($newids) > 0) {
	if (count($accumulated_ids) > 0) {
		$accumulated_ids = array_merge($accumulated_ids,$newids);
	} else {
		$accumulated_ids=$newids;
	}
}
$accumulated_ids = array_unique($accumulated_ids);

if (getStringFromRequest('finished')) {
	header("Location: massfinish.php?group_id=$group_id&accumulated_ids=".implode(',',$accumulated_ids));
}

project_admin_header(array('title'=>$Language->getText('rbac_edit','pgtitle'),'group'=>$group_id));


echo '
<h2>'.$Language->getText('project_admin','addfromlist').'</h2>
<p>
'.$Language->getText('project_admin','addfromlist1').'
<p>
<form action="'.getStringFromServer('PHP_SELF').'?group_id='.$group_id.'" method="post">
<input type="hidden" name="accumulated_ids" value="'. implode(',',$accumulated_ids) .'">';

$abc_array = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
echo $Language->getText('project_admin', 'choose_first_letter');
for ($i=0; $i<count($abc_array); $i++) {
    if ($sw == $abc_array[$i]) {
        echo '<strong>'.$abc_array[$i].'</strong>&nbsp;';
    } else {
        echo '<input type="submit" name="sw" value="'.$abc_array[$i].'">&nbsp;';
    }
}

if (!$res || db_numrows($res) < 1) {
	echo $Language->getText('project_admin', 'no_matching_user');
} else {

	$titles[]=$Language->getText('project_admin','userrealname');
	$titles[]=$Language->getText('project_admin','unix_name');
	$titles[]=$Language->getText('project_admin','add_user');

	echo $HTML->listTableTop($titles);

	//
	//	Everything is built on the multi-dimensial arrays in the Role object
	//
	for ($i=0; $i<db_numrows($res); $i++) {
		$uid = db_result($res,$i,'user_id');
		echo '<tr '. $HTML->boxGetAltRowStyle($i) . '>
			<td>'.db_result($res,$i,'lastname').', '.db_result($res,$i,'firstname').'</td>
			<td>'.db_result($res,$i,'user_name').'</td>
			<td><input type="checkbox" name="newids[]" value="'. $uid .'"';
		if (in_array($uid, $accumulated_ids)) {
			echo ' checked="checked"';
		}
		echo '></td></tr>';

	}

	echo $HTML->listTableBottom();

}

echo '<input type="submit" name="finished" value="'.$Language->getText('project_admin', 'finish').'">
</form>';

project_admin_footer(array());

?>
