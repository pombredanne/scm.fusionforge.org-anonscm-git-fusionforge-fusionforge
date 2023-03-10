<?php
/**
 * Taskboard Front Page
 *
 * Copyright 2016, Stéphane-Eymeric Bredthauer - TrivialDev
 * Copyright 2016, Franck Villaume - TrivialDev
 *
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

global $group_id, $group, $HTML, $pluginTaskboard;

require_once $gfplugins.'taskboard/common/include/TaskBoardFactoryHtml.class.php';

$taskboardFactory = new TaskBoardFactoryHtml($group);
if (!$taskboardFactory || !is_object($taskboardFactory) || $taskboardFactory->isError()) {
	exit_error(_('Could Not Get TaskBoardFactory'), 'taskboard');
}
//$group_id = $group->getID();
$tb_arr = $taskboardFactory->getTaskboards();
if ($tb_arr === false) {
	exit_permission_denied('taskboard');
}

html_use_tablesorter();

$taskboardFactory->header();
if (!$tb_arr || empty($tb_arr)) {
	echo $HTML->information(_('No taskboards have been set up, or you cannot view them.'));
	echo html_e('p', array(), sprintf(_('The Admin for this project will have to set up data types using the %1$s admin page %2$s'), '<a href="'.util_make_uri('/plugins/'.$pluginTaskboard->name.'/?group_id='.$group_id).'">', '</a>'));
} else {
	echo html_e('p', array(), _('Choose a Task Board.'));
	$tablearr = array(_('Task Board'),_('Description'));
	echo $HTML->listTableTop($tablearr, array(), 'full sortable sortable_table_taskboard', 'sortable_table_taskboard');
	for ($j = 0; $j < count($tb_arr); $j++) {
		if (is_object($tb_arr[$j])) {
			if ($tb_arr[$j]->isError()) {
				echo $tb_arr[$j]->getErrorMessage();
			} else {
				$cells = array();
				$cells[][] = util_make_link('/plugins/'.$pluginTaskboard->name.'/?group_id='.$group_id.'&taskboard_id='.$tb_arr[$j]->getID(),
								$HTML->getFollowPic().' '.$tb_arr[$j]->getName());
				$cells[][] = $tb_arr[$j]->getDescription();
				echo $HTML->multiTableRow(array(), $cells);
			}
		}
	}
	echo $HTML->listTableBottom();
}
$taskboardFactory->footer();
